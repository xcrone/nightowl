<?php

namespace App\Filament\Resources\OutgoingRequests\Pages;

use App\Filament\Resources\OutgoingRequests\OutgoingRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListOutgoingRequests extends ListRecords
{
    protected static string $resource = OutgoingRequestResource::class;
}
