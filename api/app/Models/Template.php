<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Template extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'payload' => 'array',
        'synced_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // uuid-public-ids: every new template gets a public uuid identifier
        // going forward; existing rows are backfilled by the retrofit
        // migration (2026_07_06_120002_add_uuid_to_templates_table).
        static::creating(function (Template $template) {
            $template->uuid ??= (string) Str::uuid();
        });
    }

    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class);
    }
}
