<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TrackingController;

Route::get('/', function () {
    return view('welcome');
});


Route::get('/tracking/open/{batchId}/{encodedRecipientIdentifier}', [TrackingController::class, 'trackOpen'])->name('tracking.open');
Route::get('/tracking/click/{batchId}/{encodedRecipientIdentifier}/{encodedUrl}', [TrackingController::class, 'trackClick'])->name('tracking.click');
