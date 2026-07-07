<?php

use App\Domains\Auth\Actions\Login;
use App\Domains\Auth\Actions\Logout;
use App\Domains\Auth\Actions\Register;
use Illuminate\Support\Facades\Route;

// Login/logout live here (not routes/api.php) so they run through the 'web'
// middleware group — session + CSRF — per Sanctum's SPA auth pattern. The
// frontend hits GET /sanctum/csrf-cookie first, then POSTs here with the
// X-XSRF-TOKEN header. Actions from lorisleiva/laravel-actions are plain
// invokable classes, so they're referenced directly here exactly like the
// controller they replaced.
Route::post('/register', Register::class);
Route::post('/login', Login::class);
Route::post('/logout', Logout::class);
