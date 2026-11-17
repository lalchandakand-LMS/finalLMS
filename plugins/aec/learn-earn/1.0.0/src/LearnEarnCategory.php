<?php

namespace Aloms\LearnEarn;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LearnEarnCategory extends Model
{
    protected $table = 'learn_earn_categories';

    protected $fillable = ['name', 'slug', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function questions(): HasMany
    {
        return $this->hasMany(LearnEarnQuestion::class, 'category_id');
    }
}
