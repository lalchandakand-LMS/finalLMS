<?php

declare(strict_types=1);

namespace App\Modules\Library;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class LibraryBook extends Model
{
    protected $table = 'library_books';
    protected $fillable = ['branch_id', 'isbn', 'title', 'author', 'category', 'total_copies', 'available_copies', 'shelf_location'];

    protected function casts(): array
    {
        return ['total_copies' => 'integer', 'available_copies' => 'integer'];
    }

    public function loans(): HasMany
    {
        return $this->hasMany(LibraryLoan::class, 'library_book_id');
    }
}
