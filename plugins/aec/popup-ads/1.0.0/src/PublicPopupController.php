<?php

namespace Aloms\PopupAds;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class PublicPopupController extends Controller
{
    public function active(Request $request): JsonResponse
    {
        if (! Schema::hasTable('visitor_popups')) {
            return response()->json(['popup' => null]);
        }

        // This endpoint cannot verify which page initiated the request. Never accept
        // caller-supplied path/course/batch IDs as trusted targeting context.
        // Page-specific campaigns are rendered by the server-side layout hook, where
        // the actual route, route models and enrollment are authoritative.
        $consentGranted = $request->cookie('marketing_consent') === 'accepted';

        $candidates = PopupCampaign::query()
            ->currentlyEligible()
            ->whereNull('target_course_id')
            ->whereNull('target_batch_id')
            ->where(function ($query): void {
                $query->whereNull('target_paths')->orWhere('target_paths', '');
            })
            ->with(['image', 'mobileImage', 'events'])
            ->orderByDesc('priority')
            ->latest('id')
            ->limit(25)
            ->get();

        $matched = $candidates->first(fn (PopupCampaign $campaign): bool =>
            $campaign->matchesRequestContext($request)
        );

        return response()->json([
            'popup' => $matched?->toPublicPayload($request->user(), $consentGranted),
        ]);
    }

    public function track(Request $request, int $popup): JsonResponse
    {
        $validated = $request->validate([
            'event' => ['required', 'string', Rule::in(['impression', 'click', 'dismissal'])],
        ]);

        $event = $validated['event'];
        $consentGranted = $request->cookie('marketing_consent') === 'accepted';

        // Serialize analytics writes per campaign. The campaign row lock makes the
        // impression-cap check and counter update one critical section on MySQL/MariaDB.
        // insertOrIgnore + the unique (popup_id, event_date) index prevents two first
        // events on the same day from racing into duplicate aggregate rows.
        $result = DB::transaction(function () use ($popup, $event, $consentGranted): array {
            $campaign = PopupCampaign::query()->whereKey($popup)->lockForUpdate()->first();

            if (! $campaign || ! $campaign->isCurrentlyPublished()) {
                return ['status' => 404, 'body' => ['recorded' => false]];
            }

            if (! $campaign->analytics_enabled) {
                return ['status' => 202, 'body' => ['recorded' => false, 'reason' => 'analytics_disabled']];
            }

            if ($campaign->require_consent_for_analytics && ! $consentGranted) {
                return ['status' => 202, 'body' => ['recorded' => false, 'reason' => 'consent_required']];
            }

            if ($event === 'impression' && $campaign->hasReachedImpressionCap()) {
                return ['status' => 202, 'body' => ['recorded' => false, 'reason' => 'impression_cap_reached']];
            }

            $column = match ($event) {
                'impression' => 'impressions',
                'click' => 'clicks',
                'dismissal' => 'dismissals',
            };
            $eventDate = now($campaign->timezone ?: config('app.timezone', 'UTC'))->toDateString();

            DB::table('popup_ad_events')->insertOrIgnore([
                'popup_id' => $campaign->id,
                'event_date' => $eventDate,
                'impressions' => 0,
                'clicks' => 0,
                'dismissals' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('popup_ad_events')
                ->where('popup_id', $campaign->id)
                ->where('event_date', $eventDate)
                ->increment($column, 1, ['updated_at' => now()]);

            return ['status' => 200, 'body' => ['recorded' => true]];
        }, 3);

        return response()->json($result['body'], $result['status']);
    }
}
