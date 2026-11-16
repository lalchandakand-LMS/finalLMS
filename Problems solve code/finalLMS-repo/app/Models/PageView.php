<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class PageView extends Model
{
    protected $fillable = ['view_type', 'viewable_type', 'viewable_id', 'visitor_hash', 'viewed_on'];

    public static function record(Request $request, string $type, string $modelType = 'site', int $modelId = 0): void
    {
        $sessionId = $request->hasSession() ? $request->session()->getId() : null;
        $visitorSeed = $request->attributes->get('analytics_visitor_key')
            ?: $request->cookie('aec_visitor')
            ?: $sessionId
            ?: bin2hex(random_bytes(32));
        $visitorHash = hash_hmac('sha256', (string) $visitorSeed, (string) config('app.key'));

        static::query()->insertOrIgnore([
            'view_type' => $type,
            'viewable_type' => $modelType,
            'viewable_id' => $modelId,
            'visitor_hash' => $visitorHash,
            'viewed_on' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
