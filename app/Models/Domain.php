<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property mixed pages
 */
class Domain extends Model
{
    protected $table = 'domains';
    protected $fillable = [
        'domain',
        'cookies',
        'cookie_jar',
        'parse_time',
    ];

    public function pages()
    {
        return $this->hasMany('App\Models\Page', 'domain_id', 'id');
    }
}
