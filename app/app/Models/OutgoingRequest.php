<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutgoingRequest extends Model
{
    protected $connection = 'nightowl';

    protected $table = 'nightowl_outgoing_requests';

    public $timestamps = false;

    protected $guarded = ['*'];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
