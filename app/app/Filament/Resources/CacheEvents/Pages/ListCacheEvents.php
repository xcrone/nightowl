<?php

namespace App\Filament\Resources\CacheEvents\Pages;

use App\Filament\Resources\CacheEvents\CacheEventResource;
use Filament\Resources\Pages\ListRecords;

class ListCacheEvents extends ListRecords
{
    protected static string $resource = CacheEventResource::class;
}
