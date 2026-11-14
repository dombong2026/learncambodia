<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
final class PostTerm extends Model {
    protected $fillable=['kind','name','slug','description'];
    public function entries(): BelongsToMany {
        return $this->belongsToMany(ContentEntry::class,'content_entry_post_term');
    }
}