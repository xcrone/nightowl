<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QueryRecord extends Model
{
    protected $connection = 'nightowl';

    protected $table = 'nightowl_queries';

    public $timestamps = false;

    protected $guarded = ['*'];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
