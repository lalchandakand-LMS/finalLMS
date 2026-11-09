<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class QuestionSet extends Model
{
    protected $fillable = ['title','description','status','created_by'];

    public function creator(): BelongsTo { return $this->belongsTo(User::class,'created_by'); }
    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(QuestionBankQuestion::class,'question_set_question')
            ->withPivot(['position','marks_override'])->withTimestamps()->orderByPivot('position');
    }
}
