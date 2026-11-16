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

        $validated = $request->validate([
            'path' => ['nullable', 'string', 'max:255'],
            'course_id' => ['nullable', 'integer', 'min:1'],
            'batch_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $rawPath = isset($validated['path']) ? trim((string) $validated['path']) : null;
        $overridePath = null;
        if ($rawPath !== null && $rawPath !== '') {
            $overridePath = '/'.ltrim(str_replace(['..', '\\'], '', $rawPath), '/');
        }

        $consentGranted = $request->cookie('marketing_consent') === 'accepted';
        $courseId = isset($validated['course_id']) ? (int) $validated['course_id'] : null;
        $batchId = isset($validated['batch_id']) ? (int) $validated['batch_id'] : null;

        $candidates = PopupCampaign::query()
            ->currentlyEligible()
            ->with(['image', 'mobileImage', 'events'])
            ->orderByDesc('priority')
            ->latest('id')
            ->limit(25)
            ->get();

        $matched = $candidates->first(fn (PopupCampaign $campaign): bool =>
            $campaign->matchesRequestContext($request, $overridePath, $courseId, $batchId)
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

        $campaign = PopupCampaign::query()->currentlyEligible()->find($popup);
        if (! $campaign) {
            return response()->json(['recorded' => false], 404);
        }

        if (! $campaign->analytics_enabled) {
            return response()->json(['recorded' => false, 'reason' => 'analytics_disabled'], 202);
        }

        if ($campaign->require_consent_for_analytics && $request->cookie('marketing_consent') !== 'accepted') {
            return response()->json(['recorded' => false, 'reason' => 'consent_required'], 202);
        }

        if ($validated['event'] === 'impression' && $campaign->hasReachedImpressionCap()) {
            return response()->json(['recorded' => false, 'reason' => 'impression_cap_reached'], 202);
        }

        $column = match ($validated['event']) {
            'impression' => 'impressions',
            'click' => 'clicks',
            'dismissal' => 'dismissals',
        };

        $timezone = $campaign->timezone ?: config('app.timezone', 'UTC');
        $eventDate = now($timezone)->toDateString();

        DB::transaction(function () use ($campaign, $eventDate, $column): void {
            $existing = DB::table('popup_ad_events')
                ->where('popup_id', $campaign->id)
                ->where('event_date', $eventDate)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                DB::table('popup_ad_events')
                    ->where('id', $existing->id)
                    ->increment($column, 1, ['updated_at' => now()]);
            } else {
                DB::table('popup_ad_events')->insert([
                    'popup_id' => $campaign->id,
                    'event_date' => $eventDate,
                    'impressions' => $column === 'impressions' ? 1 : 0,
                    'clicks' => $column === 'clicks' ? 1 : 0,
                    'dismissals' => $column === 'dismissals' ? 1 : 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return response()->json(['recorded' => true]);
    }
}
