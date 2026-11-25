<?php

declare(strict_types=1);

namespace App\Modules\Library;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class LibraryLoan extends Model
{
    protected $table = 'library_loans';
    protected $fillable = ['library_book_id', 'branch_id', 'borrower_user_id', 'borrower_name', 'borrower_code', 'borrowed_at', 'due_at', 'returned_at', 'fine_amount', 'status'];

    protected function casts(): array
    {
        return ['borrowed_at' => 'date', 'due_at' => 'date', 'returned_at' => 'date', 'fine_amount' => 'decimal:2'];
    }

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'borrower_user_id');
    }

    public function fineInvoice(): HasOne
    {
        return $this->hasOne(\App\Models\Invoice::class, 'library_loan_id');
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(LibraryBook::class, 'library_book_id');
    }
}
