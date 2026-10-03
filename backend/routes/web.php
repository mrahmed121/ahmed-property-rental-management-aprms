<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => 'APRMS API',
        'tagline' => 'Ahmed — Own Every Square Foot.',
        'version' => '1.0.0',
        'docs' => '/api/v1/health',
    ]);
});
