<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

// Login/logout live here (not routes/api.php) so they run through the 'web'
// middleware group — session + CSRF — per Sanctum's SPA auth pattern. The
// frontend hits GET /sanctum/csrf-cookie first, then POSTs here with the
// X-XSRF-TOKEN header.
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);
