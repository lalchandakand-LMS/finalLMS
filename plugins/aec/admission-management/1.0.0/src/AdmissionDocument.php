<?php

declare(strict_types=1);

namespace Aloms\AdmissionManagement;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AdmissionDocument extends Model
{
    protected $fillable = ['admission_application_id', 'uploaded_by', 'disk', 'path', 'original_name', 'mime_type', 'size_bytes', 'sha256', 'document_type', 'review_status', 'review_notes', 'reviewed_by', 'reviewed_at'];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer', 'reviewed_at' => 'datetime'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(AdmissionApplication::class, 'admission_application_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
