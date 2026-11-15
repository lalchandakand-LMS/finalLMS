<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MetaBoostCampaign extends Model
{
    protected $fillable = [
        'title', 'post_url', 'destination_url', 'objective', 'audience_description',
        'target_location', 'daily_budget_bdt', 'total_budget_bdt', 'starts_at',
        'ends_at', 'status', 'meta_campaign_id', 'actual_spend_bdt',
        'impressions', 'clicks', 'leads', 'conversions', 'notes', 'created_by',
    ];

    protected $casts = [
        'daily_budget_bdt' => 'decimal:2',
        'total_budget_bdt' => 'decimal:2',
        'actual_spend_bdt' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'impressions' => 'integer',
        'clicks' => 'integer',
        'leads' => 'integer',
        'conversions' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTrackingUrlAttribute(): string
    {
        $parts = parse_url($this->destination_url);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return $this->destination_url;
        }

        parse_str($parts['query'] ?? '', $query);
        $query['utm_source'] = 'facebook';
        $query['utm_medium'] = 'paid_social';
        $query['utm_campaign'] = Str::slug($this->title);

        $authority = $parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
        $path = $parts['path'] ?? '/';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return $parts['scheme'].'://'.$authority.$path.'?'.http_build_query($query).$fragment;
    }
}
