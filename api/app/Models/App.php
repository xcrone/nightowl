<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A monitored application. Its opaque `app_id` appears in every
 * /dashboard/<app-id>/… URL and is stamped on every telemetry row.
 */
class App extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'environments' => 'array',
        'template_synced_at' => 'datetime',
    ];

    /** Bind route {app} by the opaque app_id, not the numeric id. */
    public function getRouteKeyName(): string
    {
        return 'app_id';
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function org(): ?Org
    {
        return $this->team?->org;
    }

    public function templates(): HasMany
    {
        return $this->hasMany(Template::class);
    }
}
