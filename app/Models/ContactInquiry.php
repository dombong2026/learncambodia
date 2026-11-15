<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
final class ContactInquiry extends Model {
use SoftDeletes;
protected $fillable=['name','email','topic','subject','message','consented_at'];
protected function casts(): array {return ['consented_at'=>'datetime','reviewed_at'=>'datetime'];}
}