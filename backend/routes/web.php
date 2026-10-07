<?php

use Illuminate\Support\Facades\Route;

// API-only application: the storefront is the Next.js app (ARCHITECTURE §3).
Route::get('/', fn () => response()->json([
    'name' => config('app.name'),
    'api' => url('/api/v1'),
]));
