<?php

use App\Domains\DataManagement\Actions\PreviewDataManagement;
use Illuminate\Support\Facades\Route;

Route::post('/apps/{app}/data-management/preview', PreviewDataManagement::class);
