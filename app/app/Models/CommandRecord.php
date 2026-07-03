<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommandRecord extends Model
{
    protected $connection = 'nightowl';

    protected $table = 'nightowl_commands';

    public $timestamps = false;

    protected $guarded = ['*'];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
