<?php

use App\Http\Controllers\Api\KaspiEnrichmentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['kaspi.auth', 'throttle:300,1'])
    ->prefix('kaspi')
    ->group(function () {
        Route::get('/candidates',        [KaspiEnrichmentController::class, 'candidates']);
        Route::post('/content',          [KaspiEnrichmentController::class, 'storeContent']);
        Route::get('/widget-candidates', [KaspiEnrichmentController::class, 'widgetCandidates']);
        Route::post('/widget-result',    [KaspiEnrichmentController::class, 'storeWidgetResult']);
    });
