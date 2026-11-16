<?php

namespace Aloms\PopupAds;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PopupAdEvent extends Model
{
    protected $table = 'popup_ad_events';

    protected $fillable = [
        'popup_id',
        'event_date',
        'impressions',
        'clicks',
        'dismissals',
    ];

    protected $casts = [
        'event_date' => 'date',
        'impressions' => 'integer',
        'clicks' => 'integer',
        'dismissals' => 'integer',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(PopupCampaign::class, 'popup_id');
    }
}
