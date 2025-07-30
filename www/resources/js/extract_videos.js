// extract_videos.js
const puppeteer = require('puppeteer');

(async () => {
    const mainUrl = process.argv[2]; // Prendi l'URL come argomento dalla riga di comando

    if (!mainUrl) {
        console.error('Usage: node extract_videos.js <url>');
        process.exit(1);
    }

    const browser = await puppeteer.launch({
        headless: true // 'new' for new headless mode
    });
    const page = await browser.newPage();

    try {
        await page.goto(mainUrl, { waitUntil: 'networkidle2', timeout: 60000 }); // Attendi il caricamento completo

        // Estrai l'URL dell'iframe
        const iframeSrc = await page.$eval('iframe', iframe => iframe.src);

        if (!iframeSrc) {
            console.error('Nessun iframe trovato.');
            await browser.close();
            return;
        }

        // Vai direttamente alla pagina dell'iframe o valuta il suo contenuto
        // In questo caso, valuteremo direttamente il contenuto dell'iframe nella pagina principale
        const iframeElement = await page.$('iframe');
        const frame = await iframeElement.contentFrame();

        // Attendi che il JavaScript all'interno dell'iframe carichi i video
        // Potrebbe essere necessario un delay specifico o attendere un selettore
        await frame.waitForSelector('video, a[href*=".mp4"], a[href*=".m3u8"]', { timeout: 30000 }).catch(() => {
            console.log('Nessun elemento video trovato entro il timeout. Potrebbe non essere stato caricato o il selettore è errato.');
        });
        await page.waitForTimeout(5000); // Un piccolo delay per essere sicuri che tutto sia renderizzato, potrebbe essere rimosso con una wait più specifica

        const videoLinks = await frame.evaluate(() => {
            const links = [];
            document.querySelectorAll('a[href]').forEach(a => {
                const href = a.href;
                if (href.includes('.mp4') || href.includes('.m3u8') || href.includes('video.php')) {
                    links.push(href);
                }
            });
            document.querySelectorAll('video source').forEach(source => {
                if (source.src) {
                    links.push(source.src);
                }
            });
            return links;
        });

        console.log(JSON.stringify(videoLinks));

    } catch (error) {
        console.error('Errore durante l\'estrazione:', error.message);
    } finally {
        await browser.close();
    }
})();
