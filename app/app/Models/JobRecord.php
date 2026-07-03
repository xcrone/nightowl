<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobRecord extends Model
{
    protected $connection = 'nightowl';

    protected $table = 'nightowl_jobs';

    public $timestamps = false;

    protected $guarded = ['*'];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
