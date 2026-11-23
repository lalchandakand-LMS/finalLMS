<?php

declare(strict_types=1);

namespace App\Modules\Hr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StaffApplication extends Model
{
    protected $table = 'staff_applications';
    protected $fillable = ['staff_job_opening_id', 'applicant_name', 'email', 'phone', 'cover_letter', 'resume_path', 'status', 'review_notes', 'reviewed_by', 'reviewed_at'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function opening(): BelongsTo
    {
        return $this->belongsTo(StaffJobOpening::class, 'staff_job_opening_id');
    }
}
