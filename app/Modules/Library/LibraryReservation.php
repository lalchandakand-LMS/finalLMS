<?php

declare(strict_types=1);

namespace App\Modules\Library;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LibraryReservation extends Model
{
    protected $table = 'library_reservations';
    protected $fillable = ['library_book_id', 'branch_id', 'user_id', 'status', 'queue_position', 'expires_at', 'fulfilled_at'];

    protected function casts(): array
    {
        return ['queue_position' => 'integer', 'expires_at' => 'datetime', 'fulfilled_at' => 'datetime'];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(LibraryBook::class, 'library_book_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
