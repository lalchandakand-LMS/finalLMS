<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    protected $fillable = ['name', 'slug', 'address', 'phone', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }
}