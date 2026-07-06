<?php

use App\Domains\Auth\Actions\ShowAuthenticatedUser;
use Illuminate\Support\Facades\Route;

Route::get('/user', ShowAuthenticatedUser::class);
