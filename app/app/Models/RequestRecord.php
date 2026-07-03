<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestRecord extends Model
{
    protected $connection = 'nightowl';

    protected $table = 'nightowl_requests';

    public $timestamps = false;

    protected $guarded = ['*'];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
