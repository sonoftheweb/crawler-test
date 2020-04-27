<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $table = 'pages';
    protected $fillable = [
        'domain_id',
        'path',
        'title',
        'content',
        'http_code',
        'parse_time'
    ];

    public function domain()
    {
        return $this->belongsTo('App\Models\Domain', 'domain_id', 'id');
    }
}
