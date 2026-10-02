<?php

use App\Http\Controllers\Api\V1\OeeReadingController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::apiResource('oee-readings', OeeReadingController::class)->only(['index', 'store']);
});
