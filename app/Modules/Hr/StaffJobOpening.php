<?php

declare(strict_types=1);

namespace App\Modules\Hr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class StaffJobOpening extends Model
{
    protected $table = 'staff_job_openings';
    protected $fillable = ['title', 'department', 'designation', 'description', 'employment_type', 'location', 'closes_at', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['closes_at' => 'datetime'];
    }

    public function applications(): HasMany
    {
        return $this->hasMany(StaffApplication::class, 'staff_job_opening_id');
    }
}
