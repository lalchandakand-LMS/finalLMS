<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class BlogCategory extends Model {
 protected $fillable=['name','slug','description'];
 public function posts(): BelongsToMany { return $this->belongsToMany(BlogPost::class,'blog_category_post'); }
}
