<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class McpActionAudit extends Model
{
    protected $fillable = ['id','user_id','token_id','tool','operation','resource','resource_id','result','http_status','summary'];
    public $incrementing = false;
    protected $keyType = 'string';
}
