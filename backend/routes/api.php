<?php

use Illuminate\Support\Facades\Route;

/*
| API entry point. Versioned groups live in their own files (API.md §1.1).
| Payment webhooks (Phase 5) will be registered from routes/webhooks.php.
*/

Route::prefix('v1')->name('v1.')->group(base_path('routes/api_v1.php'));

Route::group([], base_path('routes/webhooks.php'));
