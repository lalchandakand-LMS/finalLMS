<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherApplication extends Model
{
    protected $fillable = [
        'name', 'email', 'phone_number', 'institution_name', 'designation',
        'nid_encrypted', 'password', 'nid_document_path', 'institution_document_path',
        'status', 'otp_hash', 'otp_expires_at', 'phone_verified_at',
        'reviewed_by', 'reviewed_at', 'review_note', 'user_id',
    ];

    protected $hidden = ['nid_encrypted', 'password', 'otp_hash'];

    protected function casts(): array
    {
        return ['otp_expires_at' => 'datetime', 'phone_verified_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}