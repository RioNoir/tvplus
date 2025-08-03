<?php

namespace App\Http\Controllers;

use App\Services\ScraperService;
use Facebook\WebDriver\Chrome\ChromeDriver;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverSelect;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Request;
use Illuminate\View\View;
use Nesk\Puphpeteer\Puppeteer;
use PlaywrightPhp\Playwright;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class MainController extends Controller
{


    public static function driver(){
        ini_set('memory_limit', '-1');
        set_time_limit(3800);
        ini_set('default_socket_timeout', 1200);
        //Indirizzo server Selenium o Chromedriver
        //$host = 'http://localhost:4444';
        // Create an instance of ChromeOptions:
        $headless=1;
        $port="9515";
        $chromeOptions = new ChromeOptions();
        //Avvio il driver senza schermata
        if($headless == 1){
            $chromeOptions->addArguments(["--headless"]); //Senza schermata grafica
        }
        $chromeOptions->addArguments([
            "--port=".$port,
            "--window-size=1024,768",
            "--no-sandbox",
            "--disable-dev-shm-usage",
            "--disable-infobars",
            "--disable-extensions",
            "--disable-gpu",
            "--disable-dev-shm-usage",
        ]);
        // Create $capabilities and add configuration from ChromeOptions
        $capabilities = DesiredCapabilities::chrome();
        $capabilities->setCapability(ChromeOptions::CAPABILITY_W3C, $chromeOptions);
        putenv('WEBDRIVER_CHROME_DRIVER=/usr/local/bin/chromedriver');
        //putenv('WEBDRIVER_CHROME_DRIVER=/usr/local/bin/chromedriver');
        $driver = ChromeDriver::start($capabilities);
        //$driver = RemoteWebDriver::create($host, $capabilities);
        //Schermata full screen
        $driver->manage()->window()->maximize();
        return $driver;
    }

    public function getIndex(){
        $url = "http://localhost:8098/iframe";
        $driver = self::driver();

        $driver->get($url);

        // Opzionale: Screenshot per debug (utile per vedere cosa "vede" il browser)
        // Assicurati che la cartella 'storage/app' sia scrivibile dal processo del container.
        // $this->driver->takeScreenshot(storage_path('app/pre_iframe_screenshot.png'));
        // $this->info("Screenshot della pagina prima dell'iframe salvato in storage/app/pre_iframe_screenshot.png.");

        // 5. Attendi che l'iframe sia presente e visibile nel DOM
        // Sostituisci 'iframe' con un selettore più specifico (ID, name, classe CSS) se ne hai più di uno
        $driver->wait(30, 1000)->until( // Attendi fino a 30 secondi, controllando ogni 1 secondo
            WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('iframe'))
        );

        // 6. Passa al contesto dell'iframe
        // Puoi usare l'ID dell'iframe, il nome, o l'elemento stesso
        // Esempio con un selettore CSS:
        $iframeElement = $driver->findElement(WebDriverBy::cssSelector('iframe'));
        $driver->switchTo()->frame($iframeElement); // Passa al contesto dell'iframe

        // 7. Attendi che gli elementi specifici all'interno dell'iframe siano caricati e visibili
        // Sostituisci '#myElementInIframe' e '.itemClassInIframe' con i selettori reali
        $driver->wait(30, 1000)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('.matches-container'))
        );

        // 8. Estrai i dati dall'iframe
        $extractedText = $driver->findElement(WebDriverBy::cssSelector('.matches-container'))->getText();

        // Esempio: Estrai tutti gli elementi con una certa classe e itera su di essi
        $listItems = $driver->findElements(WebDriverBy::cssSelector('.match-category'));

        foreach ($listItems as $index => $item) {
            echo "Elemento lista #" . ($index + 1) . ": " . $item->getText()."<br>";
        }
    }

    public function getIndex4(){
        $url = "http://localhost:8098/iframe";
        $host = 'http://localhost:9515';

        // 1. Configura le capacità per Chrome
        $capabilities = DesiredCapabilities::chrome();

        // 2. Imposta le opzioni di Chrome, cruciale per l'esecuzione in Docker/headless
        $options = new ChromeOptions();
//        $options->addArguments([
//            '--headless',        // Esegui il browser senza interfaccia grafica
//            '--disable-gpu',     // Disabilita l'accelerazione hardware GPU (utile in ambienti headless)
//            '--no-sandbox',      // Necessario in molti ambienti Docker per motivi di sicurezza
//            '--window-size=1920,1080', // Imposta una dimensione della finestra, utile per layout responsivi
//            '--disable-dev-shm-usage', // Evita problemi di memoria condivisa in Docker
//            '--ignore-certificate-errors', // Ignora errori di certificato SSL/TLS
//        ]);
        $options->addArguments([
            '--headless',
            '--disable-gpu',
            '--no-sandbox',
            '--window-size=1920,1080',
            '--disable-dev-shm-usage',
            '--ignore-certificate-errors',
            '--disable-software-rasterizer', // Forzatura disabilita il rasterizzatore software
            '--disable-setuid-sandbox',      // Duplica --no-sandbox, a volte aiuta
            '--disable-features=NetworkService', // Evita il crash del network service se è problematico
            '--disable-web-security',         // Se hai problemi CORS (ma con cautela)
        ]);
        // Aggiungi gli argomenti al Capabilities
        $capabilities->setCapability(ChromeOptions::CAPABILITY, $options);

        //$this->info("Inizializzazione WebDriver per l'URL: " . $url);

        //try {
            // 3. Crea una nuova sessione WebDriver
            $driver = RemoteWebDriver::create($host, $capabilities);

            // 4. Naviga alla pagina desiderata
            $driver->get($url);

            // Opzionale: Screenshot per debug (utile per vedere cosa "vede" il browser)
            // Assicurati che la cartella 'storage/app' sia scrivibile dal processo del container.
            // $this->driver->takeScreenshot(storage_path('app/pre_iframe_screenshot.png'));
            // $this->info("Screenshot della pagina prima dell'iframe salvato in storage/app/pre_iframe_screenshot.png.");

            // 5. Attendi che l'iframe sia presente e visibile nel DOM
            // Sostituisci 'iframe' con un selettore più specifico (ID, name, classe CSS) se ne hai più di uno
            $driver->wait(30, 1000)->until( // Attendi fino a 30 secondi, controllando ogni 1 secondo
                WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('iframe'))
            );

            // 6. Passa al contesto dell'iframe
            // Puoi usare l'ID dell'iframe, il nome, o l'elemento stesso
            // Esempio con un selettore CSS:
            $iframeElement = $driver->findElement(WebDriverBy::cssSelector('iframe'));
            dd($iframeElement);
            $driver->switchTo()->frame($iframeElement); // Passa al contesto dell'iframe

            // 7. Attendi che gli elementi specifici all'interno dell'iframe siano caricati e visibili
            // Sostituisci '#myElementInIframe' e '.itemClassInIframe' con i selettori reali
            $driver->wait(30, 1000)->until(
                WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('.matches-container'))
            );

            // 8. Estrai i dati dall'iframe
            $extractedText = $driver->findElement(WebDriverBy::cssSelector('.matches-container'))->getText();
            dd($extractedText);

            // Esempio: Estrai tutti gli elementi con una certa classe e itera su di essi
            $listItems = $driver->findElements(WebDriverBy::cssSelector('.match-category'));

            foreach ($listItems as $index => $item) {
                echo "Elemento lista #" . ($index + 1) . ": " . $item->getText();
            }

            // Puoi anche estrarre attributi (es. href, src), HTML, o simulare interazioni
            // Esempio: Estrai un attributo
            // $linkHref = $this->driver->findElement(WebDriverBy::cssSelector('a.some-link-in-iframe'))->getAttribute('href');
            // $this->info("Link estratto: " . $linkHref);

            // Esempio: Clicca su un pulsante nell'iframe
            // $this->driver->findElement(WebDriverBy::id('buttonInIframe'))->click();
            // $this->info("Cliccato su 'buttonInIframe'.");

            // 9. Torna al contesto della pagina principale (fondamentale se devi interagire con elementi esterni all'iframe)
            //$this->driver->switchTo()->defaultContent();
            //$this->info("Tornato al contesto della pagina principale.");

        // } catch (\Exception $e) {
        //     echo "Errore durante lo scraping: " . $e->getMessage();
        //     echo "Stack Trace: " . $e->getTraceAsString();
        //     //return Command::FAILURE;
        // }
//        finally {
//            // 10. Chiudi il browser alla fine, anche in caso di errori
//            if ($driver) {
//                $driver->quit();
//                echo "Browser chiuso.";
//            }
//        }
    }

    public function getIndex3(Request $request){

        return self::scrapeIframe($request);

        $scraper = new ScraperService();
        $response = $scraper->startSession('http://localhost:8098/iframe')
            //->clickElement('#overview > div > div > div.buttons > div.video-actions > a')
            //->waitForElement('#vplayer')
            ->getFullHTML()
            ->closeSession()
            ->execute();
        ;

        dd($response);

        $page = self::getPage('http://localhost:8098/iframe');

        dd($page);

    }

    public function getIframe(){

        return view('iframe');

    }

    public static function getPage($url, $post=0, $cache=1){
        //try{
        //Controllo se la pagina è rimasta nella cache
//        if($cache == 1){
//            try{
//                if(Cache::has('scraped-'.md5($url))) {
//                    $page = Cache::get('scraped-'.md5($url));
//                    if($page->start_url == $url){
//                        $page->cached = 1;
//                        return $page;
//                    }
//                }
//            }catch(Exception $e){}
//        }


        $default_agent = "Mozilla/5.0 (Windows; U; Windows NT 5.1; it-IT; rv:1.8.1.7) Gecko/20070914 Firefox/2.0.0.7";
        ini_set('default_socket_timeout', 240);
        ini_set('Accept_Language', 'it-it');
        ini_set('user_agent', $default_agent);

        //Prendo dati Proxy
        //$proxy = ProxyController::getProxy();

        $url = str_replace("\r", "", str_replace("\"", "", $url));
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL,$url);
        //curl_setopt($ch, CURLOPT_PROXY, $proxy->address); //PROXY
        //curl_setopt($ch, CURLOPT_PROXYPORT, $proxy->port); //PORT
        //curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy->auth); //AUTH
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 180); //Timeout di 3 Minuti
        //curl_setopt($ch, CURLOPT_USERAGENT, $proxy->agent); //AGENT
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept-Language: it; Content-Type: text/html; charset=UTF-8; Referer: https://daddylivehd1.my/']);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 18);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
        curl_setopt($ch, CURLOPT_ENCODING, 1);
        if($post == 1){
            curl_setopt($ch, CURLOPT_POST, 1);
        }

        $page = new \stdClass();
        $page->html = curl_exec($ch);
        $page->response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $page->start_url = $url;
        $page->final_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $page->request_ip = curl_getinfo($ch, CURLINFO_PRIMARY_IP);
        $page->request_time = round(curl_getinfo($ch, CURLINFO_TOTAL_TIME), 1);
        $page->cached = 0;

        curl_close($ch);

        //Salvo l'html in locale cosi se riserve non faccio una'altra richiesta
        /*$fh = fopen(public_path('/temp_html/scraped_page.html'), 'w') or die("no no");
        fwrite($fh, $page->html);
        fclose($fh);*/
        //$html = file_get_contents(public_path('/temp_html/scraped_page.html'));

//        try{
//            if($page->response_code == 200){
//                if($page->html != false){
//                    Cache::put('scraped-'.md5($url), $page, 600); //Salvo la pagina 10 minuti in cache 600 sec = 10 min
//                }
//            }
//        }catch(\Exception $e){}


        return $page;
        /*}
        catch(Exception $e){
            return null;
        }*/
    }


    public function getIndex2(){
        #cd /var/src && /var/python/venv/bin/python video_iframe_scraper.py "https://vixsrc.to/movie/786892"

        $nodeScriptPath = resource_path('/js/extract_videos.js'); // Assicurati che il percorso sia corretto

        $process = new Process(['node', $nodeScriptPath, "https://vixsrc.to/movie/786892/"]);
        $process->setTimeout(120); // Imposta un timeout adeguato

        try {
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $output = $process->getOutput();
            $videoLinks = json_decode($output, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['error' => 'Errore nel parsing JSON dall\'output dello script Node: ' . json_last_error_msg()];
            }

            return $videoLinks;

        } catch (ProcessFailedException $exception) {
            return ['error' => 'Errore nell\'esecuzione dello script Node: ' . $exception->getMessage()];
        } catch (\Exception $e) {
            return ['error' => 'Si è verificato un errore: ' . $e->getMessage()];
        }




        $data = file_get_contents('https://vixsrc.to/movie/786892/');


        preg_match('/<video.*?src="(.*?)"/', $data, $matches);
        dd($matches);
        $video = $matches[1];

        dd($video);

        // /var/python/venv/bin/python
        // /var/src/scraper.py

        $scraper = new ScraperService();

        //https://mostraguarda.stream/set-movie-a/tt20969586
        //https://mostraguarda.stream/set-series-a/tt20969586

        //https://streaming-community.ovh/filmgratis/sezione-qualita/img/paramount.webm

        $response = $scraper->startSession('https://vixsrc.to/movie/786892/')
            //->clickElement('#overview > div > div > div.buttons > div.video-actions > a')
            //->waitForElement('#vplayer')
            ->getFullHTML()
            ->getVideoLinks()
            ->closeSession()
            ->execute();
            ;

        dd($response);


        $response = $scraper->startSession('https://it.wikipedia.org/wiki/Pagina_principale')
            //->clickElement('#n-portale a')
            //->clickElement('#n-currente a')
            //->getContent('#mw-content-text p', 'text')
            //->getFullHTML()
            ->closeSession()
            ->execute();

        dd($response);


    }
}
