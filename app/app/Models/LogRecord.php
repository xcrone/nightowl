<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogRecord extends Model
{
    protected $connection = 'nightowl';

    protected $table = 'nightowl_logs';

    public $timestamps = false;

    protected $guarded = ['*'];
}
