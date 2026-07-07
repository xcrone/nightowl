<?php

namespace App\Domains\Apps\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Base App fields shared by ShowApp/StoreApp/UpdateApp. Team/org context
 * (where relevant) is merged in at the call site, same convention as
 * TeamResource's apps_count/apps in ListApps.
 */
class AppResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'app_id' => $this->app_id,
            'name' => $this->name,
            'description' => $this->description,
            'db_connection' => $this->db_connection,
            'environments' => $this->environments ?? [],
        ];
    }
}
