<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CefrLevel extends Model
{
    public $incrementing = false;
    public $timestamps = false;

    protected $primaryKey = 'code';
    protected $keyType = 'string';

    protected $fillable = ['code', 'category', 'name', 'description', 'sort_order'];
}
