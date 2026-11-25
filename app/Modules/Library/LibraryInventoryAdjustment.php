<?php

declare(strict_types=1);

namespace App\Modules\Library;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LibraryInventoryAdjustment extends Model
{
    protected $table = 'library_inventory_adjustments';

    protected $fillable = [
        'library_book_id', 'library_loan_id', 'branch_id', 'adjusted_by',
        'condition', 'quantity', 'notes',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(LibraryBook::class, 'library_book_id');
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(LibraryLoan::class, 'library_loan_id');
    }

    public function adjustedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }
}
