<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    protected $guarded = ['id'];

    public function org(): BelongsTo
    {
        return $this->belongsTo(Org::class);
    }

    public function apps(): HasMany
    {
        return $this->hasMany(App::class);
    }
}
