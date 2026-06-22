<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\ApiController;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [ApiController::class, 'login']);
    Route::get('/devices/search', [ApiController::class, 'search']);
    Route::post('/installation/sync', [ApiController::class, 'syncInstallation']);
});
