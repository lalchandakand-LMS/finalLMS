<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentDocument extends Model
{
    protected $fillable = ['student_id','guardian_id','document_type','original_name','mime_type','file_size','encrypted_path','content_hash'];
    public function student(): BelongsTo { return $this->belongsTo(User::class, 'student_id'); }
    public function guardian(): BelongsTo { return $this->belongsTo(StudentGuardian::class, 'guardian_id'); }
}
