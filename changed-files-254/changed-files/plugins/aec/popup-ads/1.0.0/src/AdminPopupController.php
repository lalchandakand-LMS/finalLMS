<?php

namespace Aloms\PopupAds;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Course;
use App\Models\MediaAsset;
use Carbon\Carbon;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class AdminPopupController extends Controller
{
    public function index(Request $request): View
    {
        $statusFilter = (string) $request->query('status', 'all');
        $search = trim((string) $request->query('q', ''));

        $query = PopupCampaign::query()
            ->with(['image', 'mobileImage', 'branch', 'course', 'batch'])
            ->withSum('events as impressions_count', 'impressions')
            ->withSum('events as clicks_count', 'clicks')
            ->withSum('events as dismissals_count', 'dismissals');

        if ($statusFilter !== 'all' && in_array($statusFilter, PopupCampaign::STATUSES, true)) {
            $query->where('status', $statusFilter);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', '%'.$search.'%')
                    ->orWhere('body', 'like', '%'.$search.'%');
            });
        }

        $popups = $query->orderByDesc('priority')->latest()->paginate(20)->withQueryString();

        $summary = [
            'total' => PopupCampaign::query()->count(),
            'active' => PopupCampaign::query()->where('is_active', true)->whereIn('status', ['active', 'scheduled'])->count(),
            'impressions' => Schema::hasTable('popup_ad_events') ? (int) PopupAdEvent::query()->sum('impressions') : 0,
            'clicks' => Schema::hasTable('popup_ad_events') ? (int) PopupAdEvent::query()->sum('clicks') : 0,
        ];

        return view('popup-ads::admin.index', compact('popups', 'statusFilter', 'search', 'summary'));
    }

    public function create(): View
    {
        $popup = new PopupCampaign([
            'popup_type' => 'offer',
            'content_mode' => 'mixed',
            'layout' => 'modal',
            'placement' => 'center',
            'max_width_px' => 620,
            'background_color' => '#ffffff',
            'text_color' => '#14213d',
            'accent_color' => '#1546a8',
            'font_scale' => 'normal',
            'overlay_opacity' => 70,
            'show_close_button' => true,
            'dismissible' => true,
            'animation_style' => 'fade',
            'respect_reduced_motion' => true,
            'audience' => 'all',
            'frequency' => 'once_per_session',
            'delay_seconds' => 1,
            'scroll_trigger_percent' => 0,
            'cooldown_hours' => 0,
            'timezone' => config('app.timezone', 'UTC'),
            'status' => 'draft',
            'analytics_enabled' => true,
            'require_consent_for_analytics' => true,
        ]);

        return view('popup-ads::admin.form', array_merge(
            ['popup' => $popup],
            $this->formReferenceData()
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;

        $popup = PopupCampaign::create($data);
        $this->audit($request, 'admin.popup-ads.created', 'Created popup campaign #'.$popup->id.': '.$popup->title);

        return redirect()->route('admin.visitor-popups.index')
            ->with('success', __('Popup campaign created.'));
    }

    public function edit(int $visitorPopup): View
    {
        $popup = PopupCampaign::query()->findOrFail($visitorPopup);

        return view('popup-ads::admin.form', array_merge(
            ['popup' => $popup],
            $this->formReferenceData()
        ));
    }

    public function update(Request $request, int $visitorPopup): RedirectResponse
    {
        $popup = PopupCampaign::query()->findOrFail($visitorPopup);
        $data = $this->validated($request, $popup);
        $popup->update($data);

        $this->audit($request, 'admin.popup-ads.updated', 'Updated popup campaign #'.$popup->id.': '.$popup->title);

        return redirect()->route('admin.visitor-popups.index')
            ->with('success', __('Popup campaign updated.'));
    }

    public function preview(int $visitorPopup): View
    {
        $popup = PopupCampaign::query()
            ->with(['image', 'mobileImage', 'branch', 'course', 'batch'])
            ->findOrFail($visitorPopup);

        return view('popup-ads::admin.preview', [
            'popup' => $popup,
            'payload' => $popup->toPublicPayload(null, false),
        ]);
    }

    public function duplicate(Request $request, int $visitorPopup): RedirectResponse
    {
        $source = PopupCampaign::query()->findOrFail($visitorPopup);

        $copy = $source->replicate(['created_at', 'updated_at']);
        $copy->title = mb_substr($source->title.' (Copy)', 0, 180);
        $copy->is_active = false;
        $copy->status = 'draft';
        $copy->created_by = $request->user()->id;
        $copy->save();

        $this->audit($request, 'admin.popup-ads.duplicated', 'Duplicated popup campaign #'.$source->id.' to #'.$copy->id);

        return redirect()->route('admin.visitor-popups.edit', $copy->id)
            ->with('success', __('Popup campaign duplicated as draft.'));
    }

    public function toggle(Request $request, int $visitorPopup): RedirectResponse
    {
        $popup = PopupCampaign::query()->findOrFail($visitorPopup);
        $activate = ! $popup->is_active;
        $newStatus = $activate
            ? ($popup->starts_at !== null && $popup->starts_at->isFuture() ? 'scheduled' : 'active')
            : 'paused';

        $popup->update([
            'is_active' => $activate,
            'status' => $newStatus,
        ]);

        $this->audit($request, 'admin.popup-ads.toggled', 'Set popup campaign #'.$popup->id.' status to '.$newStatus);

        return back()->with('success', __('Popup status updated.'));
    }

    public function pause(Request $request, int $visitorPopup): RedirectResponse
    {
        $popup = PopupCampaign::query()->findOrFail($visitorPopup);
        $popup->update(['is_active' => false, 'status' => 'paused']);
        $this->audit($request, 'admin.popup-ads.paused', 'Paused popup campaign #'.$popup->id);

        return back()->with('success', __('Popup campaign paused.'));
    }

    public function resume(Request $request, int $visitorPopup): RedirectResponse
    {
        $popup = PopupCampaign::query()->findOrFail($visitorPopup);
        $status = $popup->starts_at !== null && $popup->starts_at->isFuture() ? 'scheduled' : 'active';
        $popup->update(['is_active' => true, 'status' => $status]);
        $this->audit($request, 'admin.popup-ads.resumed', 'Resumed popup campaign #'.$popup->id.' ('.$status.')');

        return back()->with('success', __('Popup campaign resumed.'));
    }

    public function archive(Request $request, int $visitorPopup): RedirectResponse
    {
        $popup = PopupCampaign::query()->findOrFail($visitorPopup);
        $popup->update(['is_active' => false, 'status' => 'archived']);
        $this->audit($request, 'admin.popup-ads.archived', 'Archived popup campaign #'.$popup->id);

        return back()->with('success', __('Popup campaign archived.'));
    }

    public function destroy(Request $request, int $visitorPopup): RedirectResponse
    {
        $popup = PopupCampaign::query()->findOrFail($visitorPopup);
        $title = $popup->title;
        $id = $popup->id;
        $popup->delete();

        $this->audit($request, 'admin.popup-ads.deleted', 'Deleted popup campaign #'.$id.': '.$title);

        return back()->with('success', __('Popup campaign deleted.'));
    }

    public function export(Request $request): Response
    {
        $campaigns = PopupCampaign::query()
            ->with('events')
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get()
            ->map(function (PopupCampaign $campaign): array {
                return [
                    'id' => $campaign->id,
                    'title' => $campaign->title,
                    'popup_type' => $campaign->popup_type,
                    'content_mode' => $campaign->content_mode,
                    'status' => $campaign->status,
                    'is_active' => $campaign->is_active,
                    'layout' => $campaign->layout,
                    'placement' => $campaign->placement,
                    'audience' => $campaign->audience,
                    'target_paths' => $campaign->target_paths,
                    'target_roles' => $campaign->target_roles,
                    'target_branch_id' => $campaign->target_branch_id,
                    'target_course_id' => $campaign->target_course_id,
                    'target_batch_id' => $campaign->target_batch_id,
                    'frequency' => $campaign->frequency,
                    'delay_seconds' => $campaign->delay_seconds,
                    'scroll_trigger_percent' => $campaign->scroll_trigger_percent,
                    'cooldown_hours' => $campaign->cooldown_hours,
                    'max_impressions' => $campaign->max_impressions,
                    'timezone' => $campaign->timezone,
                    'starts_at' => $campaign->starts_at?->toIso8601String(),
                    'ends_at' => $campaign->ends_at?->toIso8601String(),
                    'priority' => $campaign->priority,
                    'daily_analytics' => $campaign->events->map(fn (PopupAdEvent $event): array => [
                        'date' => $event->event_date?->toDateString(),
                        'impressions' => $event->impressions,
                        'clicks' => $event->clicks,
                        'dismissals' => $event->dismissals,
                    ])->values()->all(),
                ];
            })->values()->all();

        $payload = json_encode([
            'plugin' => 'aec.popup-ads',
            'exported_at' => now()->toIso8601String(),
            'campaigns_count' => count($campaigns),
            'campaigns' => $campaigns,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $this->audit($request, 'admin.popup-ads.exported', 'Exported '.count($campaigns).' popup campaign records');

        return response($payload, 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="popup-ads-export-'.now()->format('Y-m-d').'.json"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function purge(Request $request): RedirectResponse
    {
        $request->validate([
            'confirm_purge' => ['accepted'],
            'confirmation_phrase' => ['required', 'string', Rule::in(['ERASE POPUP DATA'])],
        ], [
            'confirmation_phrase.in' => __('Type ERASE POPUP DATA exactly to confirm purging retained popup campaigns and analytics.'),
        ]);

        $deletedCampaigns = 0;
        DB::transaction(function () use (&$deletedCampaigns): void {
            if (Schema::hasTable('popup_ad_events')) {
                PopupAdEvent::query()->delete();
            }
            $deletedCampaigns = PopupCampaign::query()->count();
            PopupCampaign::query()->delete();
        });

        $this->audit($request, 'admin.popup-ads.purged', 'Purged '.$deletedCampaigns.' popup campaigns and all aggregate analytics.');

        return redirect()->route('admin.visitor-popups.index')
            ->with('success', __('All retained popup campaigns and analytics were erased.'));
    }

    private function validated(Request $request, ?PopupCampaign $existing = null): array
    {
        $imageExistsRule = Rule::exists('media_assets', 'id')
            ->where(fn ($query) => $query->where('mime_type', 'like', 'image/%'));

        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'body' => ['nullable', 'string', 'max:3000'],
            'rich_html' => ['nullable', 'string', 'max:8000'],
            'popup_type' => ['required', Rule::in(PopupCampaign::TYPES)],
            'content_mode' => ['nullable', Rule::in(PopupCampaign::CONTENT_MODES)],
            'layout' => ['nullable', Rule::in(PopupCampaign::LAYOUTS)],
            'placement' => ['nullable', Rule::in(PopupCampaign::PLACEMENTS)],
            'max_width_px' => ['nullable', 'integer', 'min:280', 'max:1200'],
            'background_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'text_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'font_scale' => ['nullable', Rule::in(PopupCampaign::FONT_SCALES)],
            'overlay_opacity' => ['nullable', 'integer', 'min:0', 'max:95'],
            'show_close_button' => ['nullable', 'boolean'],
            'dismissible' => ['nullable', 'boolean'],
            'animation_style' => ['nullable', Rule::in(PopupCampaign::ANIMATIONS)],
            'respect_reduced_motion' => ['nullable', 'boolean'],
            'image_media_id' => ['nullable', 'integer', $imageExistsRule],
            'mobile_image_media_id' => ['nullable', 'integer', $imageExistsRule],
            'desktop_image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'dimensions:min_width=1,min_height=1,max_width=4096,max_height=4096', 'max:5120'],
            'mobile_image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'dimensions:min_width=1,min_height=1,max_width=4096,max_height=4096', 'max:5120'],
            'hide_on_mobile' => ['nullable', 'boolean'],
            'hide_on_desktop' => ['nullable', 'boolean'],
            'button_label' => ['nullable', 'string', 'max:60'],
            'button_url' => ['nullable', 'string', 'max:500'],
            'audience' => ['required', Rule::in(['all', 'guests', 'members'])],
            'target_paths' => ['nullable', 'string', 'max:2000'],
            'target_roles' => ['nullable', 'array'],
            'target_roles.*' => ['string', Rule::in(PopupCampaign::TARGET_ROLES)],
            'target_branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')],
            'target_course_id' => ['nullable', 'integer', Rule::exists('courses', 'id')],
            'target_batch_id' => ['nullable', 'integer', Rule::exists('batches', 'id')],
            'frequency' => ['required', Rule::in(PopupCampaign::FREQUENCIES)],
            'delay_seconds' => ['required', 'integer', 'min:0', 'max:60'],
            'scroll_trigger_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'cooldown_hours' => ['nullable', 'integer', 'min:0', 'max:8760'],
            'max_impressions' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'analytics_enabled' => ['nullable', 'boolean'],
            'require_consent_for_analytics' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::in(PopupCampaign::STATUSES)],
            'is_active' => ['nullable', 'boolean'],
            'timezone' => ['nullable', 'string', Rule::in(DateTimeZone::listIdentifiers())],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ]);

        $rawUrl = trim((string) ($data['button_url'] ?? ''));
        if ($rawUrl !== '') {
            if (str_starts_with($rawUrl, '//')) {
                throw ValidationException::withMessages([
                    'button_url' => __('Protocol-relative URLs are not allowed.'),
                ]);
            }
            $normalizedUrl = PopupContentSanitizer::normalizeSafeUrl($rawUrl);
            if ($normalizedUrl === null) {
                throw ValidationException::withMessages([
                    'button_url' => __('Use a site-relative path or a secure HTTPS URL.'),
                ]);
            }
            $data['button_url'] = $normalizedUrl;
        } else {
            $data['button_url'] = null;
        }

        $timezone = (string) ($data['timezone'] ?? $existing?->timezone ?? config('app.timezone', 'UTC'));
        $startsAt = ! empty($data['starts_at']) ? Carbon::parse((string) $data['starts_at'], $timezone)->utc() : null;
        $endsAt = ! empty($data['ends_at']) ? Carbon::parse((string) $data['ends_at'], $timezone)->utc() : null;

        if ($startsAt !== null && $endsAt !== null && $endsAt->lt($startsAt)) {
            throw ValidationException::withMessages([
                'ends_at' => __('The end date/time must be after or equal to the start date/time.'),
            ]);
        }

        if ($request->hasFile('desktop_image')) {
            $data['image_media_id'] = $this->storeUploadedImage($request, 'desktop_image', $data['title']);
        }
        if ($request->hasFile('mobile_image')) {
            $data['mobile_image_media_id'] = $this->storeUploadedImage($request, 'mobile_image', $data['title'].' (Mobile)');
        }
        unset($data['desktop_image'], $data['mobile_image']);

        $contentMode = (string) ($data['content_mode'] ?? $existing?->content_mode ?? 'mixed');
        $sanitizedHtml = PopupContentSanitizer::sanitizeHtml($data['rich_html'] ?? null);
        if ($contentMode === 'sanitized_html' && ($sanitizedHtml === null || trim(strip_tags($sanitizedHtml)) === '')) {
            throw ValidationException::withMessages([
                'rich_html' => __('Sanitized HTML content cannot be empty when using sanitized HTML mode.'),
            ]);
        }

        $requestedStatus = $data['status'] ?? null;
        $isActiveChecked = (bool) ($data['is_active'] ?? false);

        if ($requestedStatus === null) {
            $status = $isActiveChecked
                ? ($startsAt !== null && $startsAt->isFuture() ? 'scheduled' : 'active')
                : 'draft';
        } else {
            $status = $requestedStatus;
            if ($isActiveChecked && $status === 'draft') {
                $status = $startsAt !== null && $startsAt->isFuture() ? 'scheduled' : 'active';
            }
        }

        $isActive = in_array($status, ['active', 'scheduled'], true);

        return [
            'title' => $data['title'],
            'body' => $data['body'] ?? null,
            'rich_html' => $sanitizedHtml,
            'popup_type' => $data['popup_type'],
            'content_mode' => $contentMode,
            'layout' => (string) ($data['layout'] ?? $existing?->layout ?? 'modal'),
            'placement' => (string) ($data['placement'] ?? $existing?->placement ?? 'center'),
            'max_width_px' => (int) ($data['max_width_px'] ?? $existing?->max_width_px ?? 620),
            'background_color' => PopupContentSanitizer::sanitizeHexColor($data['background_color'] ?? null, $existing?->background_color ?? '#ffffff'),
            'text_color' => PopupContentSanitizer::sanitizeHexColor($data['text_color'] ?? null, $existing?->text_color ?? '#14213d'),
            'accent_color' => PopupContentSanitizer::sanitizeHexColor($data['accent_color'] ?? null, $existing?->accent_color ?? '#1546a8'),
            'font_scale' => (string) ($data['font_scale'] ?? $existing?->font_scale ?? 'normal'),
            'overlay_opacity' => (int) ($data['overlay_opacity'] ?? $existing?->overlay_opacity ?? 70),
            'show_close_button' => $request->has('show_close_button') ? $request->boolean('show_close_button') : ($existing?->show_close_button ?? true),
            'dismissible' => $request->has('dismissible') ? $request->boolean('dismissible') : ($existing?->dismissible ?? true),
            'animation_style' => (string) ($data['animation_style'] ?? $existing?->animation_style ?? 'fade'),
            'respect_reduced_motion' => $request->has('respect_reduced_motion') ? $request->boolean('respect_reduced_motion') : ($existing?->respect_reduced_motion ?? true),
            'image_media_id' => $data['image_media_id'] ?? null,
            'mobile_image_media_id' => $data['mobile_image_media_id'] ?? null,
            'hide_on_mobile' => $request->boolean('hide_on_mobile'),
            'hide_on_desktop' => $request->boolean('hide_on_desktop'),
            'button_label' => $data['button_label'] ?? null,
            'button_url' => $data['button_url'],
            'audience' => $data['audience'],
            'target_paths' => PopupContentSanitizer::normalizePathPatterns($data['target_paths'] ?? null),
            'target_roles' => array_values(array_unique($data['target_roles'] ?? [])),
            'target_branch_id' => $data['target_branch_id'] ?? null,
            'target_course_id' => $data['target_course_id'] ?? null,
            'target_batch_id' => $data['target_batch_id'] ?? null,
            'frequency' => $data['frequency'],
            'delay_seconds' => (int) $data['delay_seconds'],
            'scroll_trigger_percent' => (int) ($data['scroll_trigger_percent'] ?? 0),
            'cooldown_hours' => (int) ($data['cooldown_hours'] ?? 0),
            'max_impressions' => isset($data['max_impressions']) ? (int) $data['max_impressions'] : null,
            'analytics_enabled' => $request->has('analytics_enabled') ? $request->boolean('analytics_enabled') : ($existing?->analytics_enabled ?? true),
            'require_consent_for_analytics' => $request->has('require_consent_for_analytics') ? $request->boolean('require_consent_for_analytics') : ($existing?->require_consent_for_analytics ?? true),
            'status' => $status,
            'is_active' => $isActive,
            'timezone' => $timezone,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'priority' => (int) ($data['priority'] ?? 0),
        ];
    }

    private function storeUploadedImage(Request $request, string $field, string $altText): int
    {
        $file = $request->file($field);
        $path = $file->store('media/'.now()->format('Y/m'), 'public');

        $asset = MediaAsset::create([
            'uploaded_by' => $request->user()->id,
            'disk' => 'public',
            'path' => $path,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime_type' => $file->getMimeType() ?: 'image/png',
            'size' => $file->getSize(),
            'alt_text' => mb_substr($altText, 0, 255),
        ]);

        return (int) $asset->id;
    }

    private function formReferenceData(): array
    {
        return [
            'media' => MediaAsset::query()->where('mime_type', 'like', 'image/%')->latest()->limit(200)->get(),
            'branches' => Branch::query()->orderBy('name')->get(['id', 'name']),
            'courses' => Course::query()->orderBy('title')->limit(200)->get(['id', 'title']),
            'batches' => Batch::query()->orderBy('title')->limit(200)->get(['id', 'title']),
            'timezones' => ['UTC', 'Asia/Dhaka', 'Asia/Kolkata', 'Europe/London', 'America/New_York'],
        ];
    }

    private function audit(Request $request, string $event, string $description): void
    {
        try {
            ActivityLog::create([
                'user_id' => $request->user()?->id,
                'event' => $event,
                'description' => mb_substr($description, 0, 255),
                'method' => $request->method(),
                'route' => $request->route()?->getName(),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
