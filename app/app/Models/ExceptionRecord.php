<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExceptionRecord extends Model
{
    protected $connection = 'nightowl';

    protected $table = 'nightowl_exceptions';

    public $timestamps = false;

    protected $guarded = ['*'];

    protected $casts = [
        'created_at' => 'datetime',
        'handled' => 'boolean',
    ];
}
