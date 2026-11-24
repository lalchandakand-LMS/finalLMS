<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    protected $fillable = ['name', 'slug', 'address', 'phone', 'is_active', 'status', 'registration_open'];

    protected $casts = [
        'is_active' => 'boolean',
        'registration_open' => 'boolean',
    ];

    public function isOperational(): bool
    {
        return $this->status === 'active' && $this->is_active;
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }
}
