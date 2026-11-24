<?php

declare(strict_types=1);

namespace App\Modules\Admissions;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AdmissionApplicationEvent extends Model
{
    protected $table = 'admission_application_events';

    protected $fillable = [
        'admission_application_id', 'actor_id', 'event_key',
        'from_status', 'to_status', 'note', 'metadata',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(AdmissionApplication::class, 'admission_application_id');
    }
}
