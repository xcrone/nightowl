<?php

namespace App\Filament\Resources\CacheEvents\Pages;

use App\Filament\Resources\CacheEvents\CacheEventResource;
use Filament\Resources\Pages\ViewRecord;

class ViewCacheEvent extends ViewRecord
{
    protected static string $resource = CacheEventResource::class;
}
