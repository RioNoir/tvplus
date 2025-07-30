<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ScraperController;

// ... existing code ...

Route::prefix('scraper')->group(function () {
    Route::post('/example', [ScraperController::class, 'scrapeExample']);
    Route::post('/custom', [ScraperController::class, 'scrapeWithCustomSelector']);
}); 