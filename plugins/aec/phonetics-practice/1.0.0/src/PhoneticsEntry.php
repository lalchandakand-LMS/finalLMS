<?php
namespace Aloms\PhoneticsPractice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class PhoneticsEntry extends Model {
 protected $table='phonetics_entries';
 protected $fillable=['course_id','created_by','entry_type','term','translation','uk_ipa','us_ipa','uk_audio_path','us_audio_path','example_sentence','notes','status'];
 public function course(): BelongsTo { return $this->belongsTo(\App\Models\Course::class); }
 public function creator(): BelongsTo { return $this->belongsTo(\App\Models\User::class,'created_by'); }
}
