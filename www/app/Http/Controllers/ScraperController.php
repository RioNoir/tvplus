<?php

namespace App\Http\Controllers;

use App\Services\ScraperService;
use Illuminate\Http\Request;

class ScraperController extends Controller
{
    private ScraperService $scraper;
    
    public function __construct(ScraperService $scraper)
    {
        $this->scraper = $scraper;
    }
    
    public function scrapeExample(Request $request)
    {
        try {
            // Esempio: Scraping di una pagina con video
            $url = $request->input('url', 'https://example.com');
            
            // 1. Apriamo la pagina
            $result = $this->scraper->openPage($url);
            if (isset($result['error'])) {
                return response()->json(['error' => $result['error']], 400);
            }
            
            // 2. Clicchiamo su un pulsante di accettazione cookie (se presente)
            $cookieButton = $this->scraper->clickElement('#cookie-accept-button');
            
            // 3. Clicchiamo su un elemento per aprire la sezione video
            $openVideos = $this->scraper->clickElement('.videos-section-button');
            
            // 4. Otteniamo tutti i link video
            $videoLinks = $this->scraper->getVideoLinks();
            
            // 5. Otteniamo il titolo della pagina
            $pageTitle = $this->scraper->getText('h1');
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'title' => $pageTitle['text'] ?? null,
                    'videos' => $videoLinks['links'] ?? [],
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Errore durante lo scraping: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function scrapeWithCustomSelector(Request $request)
    {
        try {
            $url = $request->input('url');
            $selector = $request->input('selector');
            
            if (!$url || !$selector) {
                return response()->json([
                    'error' => 'URL e selector sono obbligatori'
                ], 400);
            }
            
            // Apriamo la pagina
            $this->scraper->openPage($url);
            
            // Otteniamo il testo dell'elemento specificato
            $result = $this->scraper->getText($selector);
            
            return response()->json([
                'status' => 'success',
                'data' => $result
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Errore durante lo scraping: ' . $e->getMessage()
            ], 500);
        }
    }
} 