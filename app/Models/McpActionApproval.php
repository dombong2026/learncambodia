<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class McpActionApproval extends Model
{
    protected $fillable = ['id','user_id','token_id','operation','resource','resource_id','payload_hash','status','expires_at','approved_at','consumed_at'];
    public $incrementing = false;
    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['expires_at'=>'datetime','approved_at'=>'datetime','consumed_at'=>'datetime'];
    }
}
