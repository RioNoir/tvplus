// scraper-js/scrape.js
const puppeteer = require('puppeteer'); // Oppure const playwright = require('playwright'); e usa browser.chromium

(async () => {
    const url = process.argv[2]; // Prendi l'URL come argomento dalla riga di comando
    if (!url) {
        console.error("Usage: node scrape.js <url>");
        process.exit(1);
    }

    const browser = await puppeteer.launch({
        executablePath: '/data/playwright/chromium-1181/chrome-linux/chrome',
        headless: true
    }); // headless: true per non mostrare l'interfaccia
    const page = await browser.newPage();

    try {
        await page.goto(url, { waitUntil: 'networkidle0' }); // Attendi il caricamento completo della pagina

        // Trova l'iframe. Puoi usare un selettore CSS, un nome, o un ID.
        // Selezioniamo il primo iframe, ma sii più specifico se possibile.
        const iframeElementHandle = await page.$('iframe'); // Selettore CSS per l'iframe

        if (iframeElementHandle) {
            const iframe = await iframeElementHandle.contentFrame(); // Ottieni il frame content dell'iframe

            if (iframe) {
                // Ora sei all'interno dell'iframe. Puoi eseguire query Selector e estrarre dati.
                // Attendi che gli elementi all'interno dell'iframe siano caricati
                await iframe.waitForSelector('#liveFrame', { timeout: 5000 });

                const data = await iframe.$eval('#liveFrame', el => el.textContent);
                const moreData = await iframe.evaluate(() => {
                    const items = Array.from(document.querySelectorAll('.match-category'));
                    return items.map(item => item.textContent);
                });

                console.log(JSON.stringify({
                    status: 'success',
                    extractedData: data,
                    listItems: moreData
                }));
            } else {
                console.error(JSON.stringify({ status: 'error', message: 'Could not get iframe content frame.' }));
            }
        } else {
            console.error(JSON.stringify({ status: 'error', message: 'Iframe not found on the page.' }));
        }

    } catch (error) {
        console.error(JSON.stringify({ status: 'error', message: error.message, stack: error.stack }));
    } finally {
        await browser.close();
    }
})();
