import asyncio
from playwright.async_api import async_playwright, Response, TimeoutError
from playwright_stealth import stealth_async
import json
import sys
import logging
import re
import os
from datetime import datetime
import random

# --- Configurazione del Logging ---
logging.basicConfig(
    level=logging.INFO, # Cambia a DEBUG per più verbosità
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.StreamHandler(sys.stdout) # Invia log a stdout (visibili con docker-compose logs)
    ]
)

# --- Variabili Globali/Configurazioni ---
USER_AGENT = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36"
SCREENSHOT_DIR = os.getenv('SCREENSHOT_DIR', '/var/src/screenshots') # Prende dalla variabile d'ambiente Docker
if not os.path.exists(SCREENSHOT_DIR):
    os.makedirs(SCREENSHOT_DIR)
    logging.info(f"Directory screenshots creata: {SCREENSHOT_DIR}")

# --- Funzione per Screenshot ---
async def take_screenshot(page, name_suffix):
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    screenshot_path = os.path.join(SCREENSHOT_DIR, f"screenshot_{timestamp}_{name_suffix}.png")
    try:
        await page.screenshot(path=screenshot_path, full_page=True)
        logging.info(f"Screenshot salvato: {screenshot_path}")
    except Exception as e:
        logging.error(f"Errore durante lo screenshot '{name_suffix}': {e}")

# --- Gestione delle Risposte di Rete ---
async def handle_network_response(response: Response, potential_video_urls: set):
    url = response.url
    status = response.status
    headers = response.headers

    if response.request.resource_type == "media" or re.search(r'\.(mp4|m3u8|mpd|webm|ts|mkv)(\?.*)?$', url, re.IGNORECASE):
        logging.info(f"Video/Media Response: {url} - Status: {status}")
        potential_video_urls.add(url)
    elif response.request.resource_type == "xhr" and 'json' in response.headers.get('content-type', ''):
        # Alcuni video stream o token vengono rivelati via XHR con risposta JSON
        try:
            json_response = await response.json()
            if isinstance(json_response, dict):
                # Cerca URL video ricorsivamente nel JSON
                found_urls = re.findall(r'https?://[^\s"<>\'\\]+\.(mp4|m3u8|mpd|webm|ts|mkv)', json.dumps(json_response))
                for fu in found_urls:
                    potential_video_urls.add(fu)
                    logging.info(f"Trovata URL video in XHR/JSON: {fu}")
        except Exception:
            pass # Non è JSON valido o errore di parsing

# --- Gestione dei Messaggi della Console del Browser ---
def handle_console_message(msg):
    logging.info(f"Browser Console: [{msg.type}] {msg.text}")

# --- Funzione Principale di Scraping ---
async def extract_dynamic_video_urls(main_url: str) -> dict:
    potential_video_urls = set()
    found_dom_video_src = None

    async with async_playwright() as p:
        browser = None
        try:
            # Creare o usare una directory persistente per i dati utente
            user_data_dir = "/data/browser/user_data"
            if not os.path.exists(user_data_dir):
                os.makedirs(user_data_dir)

            logging.info("Avvio del browser Chromium...")
            context = await p.chromium.launch_persistent_context(
                user_data_dir=user_data_dir,
                headless=False,           # False per debug visuale e Xvfb
                devtools=True,            # Abilita DevTools per remote debugging
                args=[
                    "--remote-debugging-port=9222", # Porta per il remote debugging
                    "--no-sandbox",                 # Essenziale in Docker
                    "--disable-setuid-sandbox",     # Essenziale in Docker
                    "--disable-blink-features=AutomationControlled", # Anti-fingerprinting
                    "--disable-dev-shm-usage",      # Usa il disco per la memoria condivisa (meno RAM, più lento)
                    "--start-maximized",            # Avvia la finestra massimizzata
                    "--enable-logging=stderr",      # Invia log di Chrome a stderr per supervisord
                    "--v=1",                        # Livello di verbosità del log di Chrome
                ],
                user_agent=USER_AGENT # L'user_agent va qui, non sulla page
            )
            logging.info("Browser Chromium avviato con contesto persistente.")

            # Il contesto persistente avvia sempre con almeno una pagina.
            # Possiamo ottenere la prima pagina o crearne una nuova se necessario.
            # Generalmente, è meglio usare la prima pagina disponibile (page 0)
            # o se ne crei una nuova, quella avrà il profilo persistente.
            pages = context.pages
            if len(pages) > 0:
                page = pages[0]
                logging.info("Utilizzando la pagina esistente dal contesto persistente.")
            else:
                page = await context.new_page()
                logging.info("Creata una nuova pagina nel contesto persistente.")

            # --- Stealth Playwright va applicato sulla page ---
            await stealth_async(page)

            # --- Inietta JavaScript per alterare fingerprint prima del caricamento della pagina ---
            await page.add_init_script("""
                Object.defineProperty(navigator, 'webdriver', { get: () => undefined });
                window.chrome = {
                    runtime: {}, loadTimes: function() {}, app: {
                        isInstalled: false, RunningState: {}, installState: {}, getDetails: function(){}, get  Details: function(){}
                    }
                };
                Object.defineProperty(navigator, 'plugins', {
                    get: () => [
                        { name: 'Chrome PDF Plugin', description: 'Portable Document Format', filename: 'internal-pdf-viewer', mimeTypes: ['application/pdf'] },
                        { name: 'Chrome PDF Viewer', description: '', filename: 'mhjfbmdgcfjbbgdeojhdminlcdnfhihm', mimeTypes: ['application/x-google-chrome-pdf'] }
                    ]
                });
                Object.defineProperty(navigator, 'mimeTypes', {
                    get: () => [
                        { type: 'application/pdf', suffixes: 'pdf', description: 'Portable Document Format' },
                        { type: 'application/x-google-chrome-pdf', suffixes: 'pdf', description: '' }
                    ]
                });
                // Aggiungi altre falsificazioni se necessario, es. navigator.hardwareConcurrency, navigator.deviceMemory, etc.
                Object.defineProperty(navigator, 'hardwareConcurrency', { get: () => 8 }); // Simula 8 core
                Object.defineProperty(navigator, 'deviceMemory', { get: () => 8 }); // Simula 8GB di RAM
                Object.defineProperty(navigator, 'languages', { get: () => ['en-US', 'en'] }); // Lingue preferite
                // Falsifica toString di alcune funzioni native per nascondere il rilevamento
                // Questa è una tecnica più avanzata e può essere rischiosa
                // window.navigator.brave = {} // Esempio per rilevamento Brave
            """)
            logging.info("JavaScript anti-fingerprinting iniettato.")

            # --- Imposta la dimensione del viewport ---
            await page.set_viewport_size({"width": 1920, "height": 934})
            logging.info("Viewport size set to 1920x934.")

            # --- Configura Listeners per Eventi della Pagina ---
            page.on("response", lambda response: asyncio.create_task(handle_network_response(response, potential_video_urls)))
            page.on("console", handle_console_message)
            page.on("pageerror", lambda err: logging.error(f"Page error (unhandled JS exception): {err}"))

            logging.info(f"Navigando a: {main_url}")
            await take_screenshot(page, "1_before_goto")

            # Attendi il caricamento completo della pagina e della rete
            await page.goto(main_url, wait_until="networkidle", timeout=120000)
            logging.info("Pagina caricata (network idle).")
            await asyncio.sleep(random.uniform(5, 10)) # Pausa più lunga dopo il caricamento iniziale
            await take_screenshot(page, "2_after_goto_networkidle")

            # --- Leggi e Salva localStorage e sessionStorage ---
            logging.info("Tentativo di leggere e salvare localStorage e sessionStorage...")
            try:
                # Leggi localStorage
                local_storage_data = await page.evaluate("""
                    () => {
                        const data = {};
                        for (let i = 0; i < localStorage.length; i++) {
                            const key = localStorage.key(i);
                            try {
                                data[key] = JSON.parse(localStorage.getItem(key));
                            } catch (e) {
                                data[key] = localStorage.getItem(key);
                            }
                        }
                        return data;
                    }
                """)
                logging.info(f"LocalStorage Content: {json.dumps(local_storage_data, indent=2)}")
                with open(os.path.join(SCREENSHOT_DIR, "local_storage.json"), "w") as f:
                    json.dump(local_storage_data, f, indent=2)
                logging.info(f"LocalStorage salvato in: {os.path.join(SCREENSHOT_DIR, 'local_storage.json')}")

                # Leggi sessionStorage
                session_storage_data = await page.evaluate("""
                    () => {
                        const data = {};
                        for (let i = 0; i < sessionStorage.length; i++) {
                            const key = sessionStorage.key(i);
                            try {
                                data[key] = JSON.parse(sessionStorage.getItem(key));
                            } catch (e) {
                                data[key] = sessionStorage.getItem(key);
                            }
                        }
                        return data;
                    }
                """)
                logging.info(f"SessionStorage Content: {json.dumps(session_storage_data, indent=2)}")
                with open(os.path.join(SCREENSHOT_DIR, "session_storage.json"), "w") as f:
                    json.dump(session_storage_data, f, indent=2)
                logging.info(f"SessionStorage salvato in: {os.path.join(SCREENSHOT_DIR, 'session_storage.json')}")

            except Exception as e:
                logging.error(f"Errore durante la lettura/salvataggio di Storage: {e}")
            await asyncio.sleep(random.uniform(2, 4)) # Breve pausa

            # --- Trova e Clicca il pulsante Play del JW Player ---
            logging.info("Tentativo di cliccare il pulsante Play del JW Player...")
            try:
                # Selettore per il pulsante Play di JW Player.
                # Potrebbe essere necessario adattarlo se il sito usa selettori diversi.
                # Usa un locator più robusto che può trovare sia l'icona play iniziale che quella di overlay
                play_button_selector = '[aria-label="Play"], .jw-icon-playback[aria-label="Play"]'
                play_button = page.locator(play_button_selector).first

                if await play_button.is_visible(timeout=10000): # Dai un timeout generoso
                    await play_button.click()
                    logging.info(f"Cliccato sul pulsante Play di JW Player con selettore: '{play_button_selector}'")
                    await asyncio.sleep(random.uniform(3, 6)) # Breve pausa dopo il click
                    await take_screenshot(page, "3_after_play_click")
                else:
                    logging.warning(f"Pulsante Play di JW Player non visibile con selettore: '{play_button_selector}'.")

            except TimeoutError:
                logging.warning("Timeout: Pulsante Play di JW Player non trovato entro il tempo limite.")
                await take_screenshot(page, "3_timeout_play_button")
            except Exception as e:
                logging.error(f"Errore durante il tentativo di cliccare sul pulsante Play di JW Player: {e}", exc_info=True)
                await take_screenshot(page, "3_error_play_button")

            # --- Attesa per l'inizio della riproduzione e il caricamento dei flussi ---
            logging.info("Attesa aggiuntiva dopo l'interazione del player (10-15 secondi)...")
            await asyncio.sleep(random.uniform(10, 15)) # Dai tempo al video di iniziare a caricarsi e alle richieste di rete di apparire
            await take_screenshot(page, "4_after_player_interaction_wait")

            # --- Tentativo di cliccare il pulsante Pausa (Opzionale) ---
            logging.info("Tentativo di cliccare il pulsante Pausa del JW Player (opzionale)...")
            try:
                # Selettore per il pulsante Pausa di JW Player.
                pause_button_selector = '[aria-label="Pausa"], .jw-icon-playback[aria-label="Pausa"]'
                pause_button = page.locator(pause_button_selector).first

                if await pause_button.is_visible(timeout=5000):
                    await pause_button.click()
                    logging.info(f"Cliccato sul pulsante Pausa di JW Player con selettore: '{pause_button_selector}'")
                    await asyncio.sleep(random.uniform(1, 3))
                    await take_screenshot(page, "5_after_pause_click")
                else:
                    logging.debug(f"Pulsante Pausa di JW Player non visibile con selettore: '{pause_button_selector}'.")

            except TimeoutError:
                logging.debug("Timeout: Pulsante Pausa di JW Player non trovato entro il tempo limite.")
            except Exception as e:
                logging.warning(f"Errore durante il tentativo di cliccare sul pulsante Pausa di JW Player: {e}")

            # --- Attesa finale per la raccolta delle URL ---
            logging.info("Attesa finale per la raccolta delle URL (5 secondi)...")
            await asyncio.sleep(5)

            # --- Estrai URL video da elementi DOM diretti (se non catturati dalla rete) ---
            logging.info("Tentativo di estrarre URL video da elementi DOM diretti...")
            video_elements = await page.locator('video[src], source[src]').all()
            for element in video_elements:
                src = await element.get_attribute('src')
                if src:
                    logging.info(f"Trovata URL video da DOM: {src}")
                    potential_video_urls.add(src)

            # --- Tenta di ottenere la playlist di JW Player (se ancora non hai URL) ---
            if not potential_video_urls:
                logging.info("Nessuna URL trovata dalla rete/DOM, tentando di recuperare da JW Player API...")
                try:
                    jw_player_sources = await page.evaluate("""
                        () => {
                            if (typeof jwplayer !== 'undefined' && jwplayer().getPlaylist) {
                                const player = jwplayer();
                                const playlist = player.getPlaylist();
                                if (playlist && playlist.length > 0) {
                                    return playlist[0].sources.map(source => source.file);
                                }
                            }
                            return [];
                        }
                    """)
                    for source_url in jw_player_sources:
                        logging.info(f"Trovata URL video da JW Player API: {source_url}")
                        potential_video_urls.add(source_url)
                except Exception as e:
                    logging.error(f"Errore durante il recupero da JW Player API: {e}", exc_info=True)


            logging.info(f"Raccolta URL video completata. Trovate {len(potential_video_urls)} URL.")
            return {
                "main_url": main_url,
                "video_urls": list(potential_video_urls)
            }

        except TimeoutError as e:
            logging.error(f"Timeout durante l'operazione: {e}", exc_info=True)
            await take_screenshot(page, "error_timeout")
            return {
                "main_url": main_url,
                "video_urls": [],
                "error": f"Timeout: {e}"
            }
        except Exception as e:
            logging.error(f"Errore non gestito nello script: {e}", exc_info=True)
            if browser: # Assicurati di fare uno screenshot anche se il browser si è aperto
                 await take_screenshot(page, "error_unhandled")
            return {
                "main_url": main_url,
                "video_urls": [],
                "error": f"Errore generico: {e}"
            }
        finally:
            if browser:
                logging.info("Chiusura del browser.")
                await browser.close()
                logging.info("Browser chiuso.")

# --- Punto di Ingresso Principale ---
if __name__ == "__main__":
    url_to_scrape = os.getenv('MAIN_URL') # Prende l'URL dalla variabile d'ambiente
    if len(sys.argv) > 1:
        url_to_scrape = sys.argv[1] # Prende l'URL se passata come argomento da riga di comando (sovrascrive ENV)

    if not url_to_scrape:
        logging.error("Nessun URL fornito. Usare: python extract_dynamic_video.py <URL> o impostare MAIN_URL in docker-compose.yml")
        sys.exit(1)

    logging.info(f"Avvio dello scraper per l'URL: {url_to_scrape}")

    # Esegui la funzione asincrona
    results = asyncio.run(extract_dynamic_video_urls(url_to_scrape))

    # Stampa i risultati finali per debug o per un altro script
    logging.info("\n--- Risultati Finali ---")
    logging.info(json.dumps(results, indent=2))
    logging.info("------------------------")

    if results.get("error"):
        sys.exit(1) # Esce con un errore se c'è stato un problema grave