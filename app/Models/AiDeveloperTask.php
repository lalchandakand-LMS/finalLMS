<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiDeveloperTask extends Model
{
    protected function casts(): array
    {
        return [
            'instruction' => 'encrypted',
            'response' => 'encrypted',
        ];
    }

    protected $fillable = [
        'user_id', 'title', 'instruction', 'response', 'status',
        'provider', 'model', 'error_message',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
