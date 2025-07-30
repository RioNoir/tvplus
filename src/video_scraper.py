#!/usr/bin/env python3
import argparse
import json
import re
import asyncio
from yt_dlp import YoutubeDL


def extract_video_url_with_ytdlp(url):
    """Estrai link video diretto da URL usando yt-dlp."""
    ydl_opts = {
        'quiet': True,
        'skip_download': True,
        'forcejson': True
    }
    try:
        with YoutubeDL(ydl_opts) as ydl:
            info = ydl.extract_info(url, download=False)
            return {
                "status": "success",
                "method": "yt-dlp",
                "title": info.get("title"),
                "url": info.get("url"),
                "ext": info.get("ext"),
                "source": url
            }
    except Exception as e:
        return {
            "status": "error",
            "method": "yt-dlp",
            "message": f"Errore yt-dlp: {str(e)}",
            "source": url
        }


async def fallback_extract_with_playwright(url):
    from playwright.async_api import async_playwright
    extracted_links = []

    async def handle_request(request):
        if re.search(r'\.(mp4|m3u8|webm|mov|avi|mkv)$', request.url, re.IGNORECASE):
            extracted_links.append(request.url)

    async def handle_response(response):
        try:
            url = response.url
            if re.search(r'\.(mp4|m3u8|webm|mov|avi|mkv)$', url, re.IGNORECASE):
                extracted_links.append(url)
            elif 'application/json' in response.headers.get("content-type", ""):
                text = await response.text()
                found = re.findall(r'https?://[^\s"\'\\]+?\.(mp4|m3u8|webm|mov|avi|mkv)', text)
                extracted_links.extend(found)
        except:
            pass

    async with async_playwright() as pw:
        browser = await pw.chromium.launch(headless=True)
        context = await browser.new_context()
        page = await context.new_page()

        # Chiudi eventuali pop-up pubblicitari
        context.on("page", lambda new_page: asyncio.create_task(new_page.close()))

        page.on("request", handle_request)
        page.on("response", handle_response)

        try:
            await page.goto(url, wait_until='networkidle', timeout=30000)
            await page.wait_for_timeout(3000)

            # Click multipli per superare ads / attivare player
            for i in range(3):
                try:
                    await page.mouse.click(400, 300)
                    await page.wait_for_timeout(1000)
                except:
                    continue

            # Click mirati su elementi noti del player video
            for sel in ["#player", ".play-button", ".vjs-play-control", "video"]:
                try:
                    await page.click(sel, timeout=1000)
                    await page.wait_for_timeout(1000)
                except:
                    continue

            # Estendi sniffing anche ai frame
            for frame in page.frames:
                frame.on("request", handle_request)
                frame.on("response", handle_response)
                try:
                    await frame.wait_for_load_state("networkidle", timeout=5000)
                except:
                    continue

            await page.wait_for_timeout(5000)

            # Estrai src diretti da tag video e source
            try:
                video_srcs = await page.eval_on_selector_all(
                    "video, source",
                    "els => els.map(e => e.src).filter(Boolean)"
                )
                extracted_links.extend(video_srcs)
            except:
                pass

            # Parsing del DOM finale (come fallback)
            html = await page.content()
            matches = re.findall(r'https?://[^\s"\'\\]+?\.(mp4|m3u8|webm|mov|avi|mkv)', html)
            extracted_links.extend(matches)

        except Exception as e:
            return {
                "status": "error",
                "method": "playwright",
                "message": f"Errore durante il caricamento della pagina: {str(e)}",
                "source": url
            }
        finally:
            await browser.close()

    unique_links = list(set(extracted_links))

    if unique_links:
        return {
            "status": "success",
            "method": "playwright",
            "links": unique_links,
            "source": url
        }
    else:
        return {
            "status": "error",
            "method": "playwright",
            "message": "Nessun link video trovato con Playwright",
            "source": url
        }

async def main():
    parser = argparse.ArgumentParser(description="Estrai link diretto al video da un URL (Mixdrop, Dropload, ecc.)")
    parser.add_argument("url", help="L'URL della pagina contenente il video")
    args = parser.parse_args()

    # Primo tentativo: yt-dlp
    result = extract_video_url_with_ytdlp(args.url)
    if result["status"] == "success":
        print(json.dumps(result, indent=4))
        return

    # Fallback: Playwright
    print("yt-dlp ha fallito, provo con Playwright...\n", file=sys.stderr)
    result = await fallback_extract_with_playwright(args.url)
    print(json.dumps(result, indent=4))


if __name__ == "__main__":
    import sys
    asyncio.run(main())
