#!/usr/bin/env python3
import asyncio
import argparse
import json
import re

from parsel import Selector
from playwright.async_api import async_playwright


class WebScraper:
    def __init__(self):
        self.browser = None
        self.page = None

    async def start(self):
        pw = await async_playwright().start()
        self.browser = await pw.chromium.launch(headless=True)
        self.page = await self.browser.new_page()

    async def close(self):
        if self.browser:
            await self.browser.close()

    async def open_url(self, url):
        await self.page.goto(url)

    async def wait_for(self, selector, timeout):
        await self.page.wait_for_selector(selector, timeout=timeout * 1000)

    async def type_text(self, selector, text):
        await self.page.fill(selector, text)

    async def click(self, selector):
        await self.page.click(selector)

    async def get_content(self, selector=None, attribute='text'):
        html = await self.page.content()
        sel = Selector(text=html)
        if selector:
            elements = sel.css(selector)
            if attribute == 'text':
                return [el.get() for el in elements]
            else:
                return [el.attrib.get(attribute) for el in elements if attribute in el.attrib]
        return html

    async def get_video_links(self):
        html = await self.page.content()
        sel = Selector(text=html)
        links = sel.css("a::attr(href)").getall()
        video_links = [
            link for link in links if re.search(r'\.(mp4|webm|ogg|avi|mov|mkv)$', link, re.IGNORECASE) or
            re.search(r'youtube\.com/watch|vimeo\.com/|vixsrc\.to/playlist', link, re.IGNORECASE)
        ]
        return video_links


async def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("url", help="URL da visitare")
    parser.add_argument("--click", nargs='+', help="Selettori CSS da cliccare")
    parser.add_argument("--type", nargs=2, metavar=('SELECTOR', 'TEXT'), action='append', help="Digita testo")
    parser.add_argument("--wait", help="Attende il selettore")
    parser.add_argument("--timeout", type=int, default=10, help="Timeout in secondi (default: 10)")
    parser.add_argument("--content", help="Selettore CSS per estrarre contenuti")
    parser.add_argument("--attribute", default="text", help="Attributo da estrarre (default: text)")
    parser.add_argument("--video_links", action='store_true', help="Estrae link a video")

    args = parser.parse_args()
    scraper = WebScraper()

    try:
        await scraper.start()
        await scraper.open_url(args.url)

        if args.wait:
            await scraper.wait_for(args.wait, args.timeout)

        if args.type:
            for selector, text in args.type:
                await scraper.type_text(selector, text)

        if args.click:
            for selector in args.click:
                await scraper.click(selector)

        if args.video_links:
            result = await scraper.get_video_links()
        elif args.content:
            result = await scraper.get_content(args.content, args.attribute)
        else:
            result = await scraper.get_content()

        print(json.dumps({"status": "success", "result": result}, indent=4))

    except Exception as e:
        print(json.dumps({"status": "error", "error": str(e)}, indent=4))
    finally:
        await scraper.close()


if __name__ == "__main__":
    asyncio.run(main())
