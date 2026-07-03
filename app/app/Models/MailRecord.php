<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailRecord extends Model
{
    protected $connection = 'nightowl';

    protected $table = 'nightowl_mail';

    public $timestamps = false;

    protected $guarded = ['*'];

    protected $casts = [
        'created_at' => 'datetime',
        'failed' => 'boolean',
        'queued' => 'boolean',
    ];
}
