<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CacheEvent extends Model
{
    protected $connection = 'nightowl';

    protected $table = 'nightowl_cache_events';

    public $timestamps = false;

    protected $guarded = ['*'];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
