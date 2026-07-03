<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Issue extends Model
{
    protected $connection = 'nightowl';

    protected $table = 'nightowl_issues';

    public $timestamps = true;

    protected $guarded = ['*'];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'occurrences_count' => 'integer',
        'users_count' => 'integer',
    ];
}
