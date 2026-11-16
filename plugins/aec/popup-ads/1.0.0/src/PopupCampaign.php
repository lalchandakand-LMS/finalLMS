<?php

namespace Aloms\PopupAds;

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Course;
use App\Models\MediaAsset;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class PopupCampaign extends Model
{
    public const STATUSES = ['draft', 'scheduled', 'active', 'paused', 'archived'];

    public const TYPES = ['offer', 'banner', 'post', 'ad', 'registration', 'announcement'];

    public const CONTENT_MODES = ['mixed', 'image_only', 'text_only', 'sanitized_html'];

    public const LAYOUTS = ['modal', 'corner_card', 'full_banner', 'announcement_strip'];

    public const PLACEMENTS = ['center', 'bottom_right', 'bottom_left', 'top', 'bottom'];

    public const FREQUENCIES = ['every_page', 'once_per_session', 'once_per_day', 'once_per_browser'];

    public const FONT_SCALES = ['compact', 'normal', 'large'];

    public const ANIMATIONS = ['none', 'fade', 'slide'];

    public const TARGET_ROLES = ['guest', 'student', 'teacher', 'guardian', 'branch_manager', 'accountant', 'admin'];

    protected $table = 'visitor_popups';

    protected $fillable = [
        'title',
        'body',
        'rich_html',
        'popup_type',
        'content_mode',
        'layout',
        'placement',
        'max_width_px',
        'background_color',
        'text_color',
        'accent_color',
        'font_scale',
        'overlay_opacity',
        'show_close_button',
        'dismissible',
        'animation_style',
        'respect_reduced_motion',
        'image_media_id',
        'mobile_image_media_id',
        'hide_on_mobile',
        'hide_on_desktop',
        'button_label',
        'button_url',
        'audience',
        'target_paths',
        'target_roles',
        'target_branch_id',
        'target_course_id',
        'target_batch_id',
        'frequency',
        'delay_seconds',
        'scroll_trigger_percent',
        'cooldown_hours',
        'max_impressions',
        'analytics_enabled',
        'require_consent_for_analytics',
        'is_active',
        'status',
        'starts_at',
        'ends_at',
        'timezone',
        'priority',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'show_close_button' => 'boolean',
        'dismissible' => 'boolean',
        'respect_reduced_motion' => 'boolean',
        'hide_on_mobile' => 'boolean',
        'hide_on_desktop' => 'boolean',
        'analytics_enabled' => 'boolean',
        'require_consent_for_analytics' => 'boolean',
        'target_roles' => 'array',
        'max_width_px' => 'integer',
        'overlay_opacity' => 'integer',
        'delay_seconds' => 'integer',
        'scroll_trigger_percent' => 'integer',
        'cooldown_hours' => 'integer',
        'max_impressions' => 'integer',
        'priority' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function image(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'image_media_id');
    }

    public function mobileImage(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'mobile_image_media_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'target_branch_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'target_course_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'target_batch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PopupAdEvent::class, 'popup_id');
    }

    public function scopeCurrentlyEligible(Builder $query, ?CarbonInterface $now = null): Builder
    {
        $moment = ($now ?? now())->copy()->utc();

        return $query->where('is_active', true)
            ->where(function (Builder $q): void {
                $q->whereIn('status', ['active', 'scheduled'])
                    ->orWhereNull('status');
            })
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $moment))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $moment));
    }

    public function isCurrentlyPublished(?CarbonInterface $now = null): bool
    {
        if (! $this->is_active || ! in_array($this->status ?? 'active', ['active', 'scheduled'], true)) {
            return false;
        }

        $moment = ($now ?? now())->copy()->utc();
        if ($this->starts_at !== null && $this->starts_at->copy()->utc()->gt($moment)) {
            return false;
        }

        if ($this->ends_at !== null && $this->ends_at->copy()->utc()->lt($moment)) {
            return false;
        }

        return true;
    }

    public function hasReachedImpressionCap(): bool
    {
        if ($this->max_impressions === null || $this->max_impressions <= 0) {
            return false;
        }

        $total = $this->relationLoaded('events')
            ? (int) $this->events->sum('impressions')
            : (int) $this->events()->sum('impressions');

        return $total >= $this->max_impressions;
    }

    public function matchesRequestContext(
        Request $request,
        ?string $overridePath = null,
        ?int $courseId = null,
        ?int $batchId = null,
    ): bool {
        if (! $this->isCurrentlyPublished()) {
            return false;
        }

        if ($this->hasReachedImpressionCap()) {
            return false;
        }

        $user = $request->user();

        if ($this->audience === 'guests' && $user !== null) {
            return false;
        }
        if ($this->audience === 'members' && $user === null) {
            return false;
        }

        $roles = is_array($this->target_roles) ? array_values(array_filter($this->target_roles, 'is_string')) : [];
        if ($roles !== []) {
            $currentRole = $user ? (string) $user->role : 'guest';
            if (! in_array($currentRole, $roles, true)) {
                return false;
            }
        }

        if ($this->target_branch_id !== null) {
            if (! $user || (int) $user->branch_id !== (int) $this->target_branch_id) {
                return false;
            }
        }

        if ($this->target_course_id !== null) {
            $routeCourse = $request->route('course');
            $currentCourseId = $courseId ?? (is_object($routeCourse) ? (int) $routeCourse->getKey() : (is_numeric($routeCourse) ? (int) $routeCourse : null));
            $matchesRouteCourse = $currentCourseId !== null && $currentCourseId === (int) $this->target_course_id;
            $matchesEnrollment = $user !== null && $user->enrollments()->where('course_id', $this->target_course_id)->exists();

            if (! $matchesRouteCourse && ! $matchesEnrollment) {
                return false;
            }
        }

        if ($this->target_batch_id !== null) {
            $routeBatch = $request->route('batch');
            $currentBatchId = $batchId ?? (is_object($routeBatch) ? (int) $routeBatch->getKey() : (is_numeric($routeBatch) ? (int) $routeBatch : null));
            $matchesRouteBatch = $currentBatchId !== null && $currentBatchId === (int) $this->target_batch_id;
            $matchesEnrollment = $user !== null && $user->enrollments()->where('batch_id', $this->target_batch_id)->exists();

            if (! $matchesRouteBatch && ! $matchesEnrollment) {
                return false;
            }
        }

        if (is_string($this->target_paths) && trim($this->target_paths) !== '') {
            $path = $overridePath ?? ('/'.ltrim($request->path(), '/'));
            if ($path === '//' || $path === '') {
                $path = '/';
            }

            if (! self::pathMatchesPatterns($path, $this->target_paths)) {
                return false;
            }
        }

        return true;
    }

    public static function pathMatchesPatterns(string $path, string $patternsRaw): bool
    {
        $normalizedPath = '/'.ltrim(trim($path), '/');
        if ($normalizedPath === '//') {
            $normalizedPath = '/';
        }

        $lines = preg_split('/[\r\n,]+/', $patternsRaw) ?: [];
        $includes = [];
        $excludes = [];

        foreach ($lines as $line) {
            $pattern = trim($line);
            if ($pattern === '') {
                continue;
            }
            if (str_starts_with($pattern, '!')) {
                $excludes[] = substr($pattern, 1);
            } else {
                $includes[] = $pattern;
            }
        }

        foreach ($excludes as $exclude) {
            if (self::wildcardMatch($exclude, $normalizedPath)) {
                return false;
            }
        }

        if ($includes === []) {
            return true;
        }

        foreach ($includes as $include) {
            if (self::wildcardMatch($include, $normalizedPath)) {
                return true;
            }
        }

        return false;
    }

    private static function wildcardMatch(string $pattern, string $subject): bool
    {
        $clean = '/'.ltrim(trim($pattern), '/');
        $quoted = preg_quote($clean, '#');
        $regex = '#^'.str_replace('\*', '.*', $quoted).'$#i';

        return preg_match($regex, $subject) === 1;
    }

    public function resolvedButtonUrl(?User $user = null): ?string
    {
        if ($this->popup_type === 'registration') {
            return $user !== null ? route('dashboard') : route('register');
        }

        $safe = PopupContentSanitizer::normalizeSafeUrl($this->button_url);
        if ($safe === null) {
            return null;
        }

        return str_starts_with($safe, '/') ? url($safe) : $safe;
    }

    public function sanitizedRichHtml(): ?string
    {
        if ($this->content_mode !== 'sanitized_html') {
            return null;
        }

        return PopupContentSanitizer::sanitizeHtml($this->rich_html);
    }

    public function toPublicPayload(?User $user, bool $consentGranted): array
    {
        $buttonUrl = $this->resolvedButtonUrl($user);
        $buttonLabel = $this->button_label ?: ($this->popup_type === 'registration' ? __('Register now') : __('Learn more'));
        $isExternal = $buttonUrl !== null && str_starts_with($buttonUrl, 'https://') && ! str_starts_with($buttonUrl, url('/'));
        $analyticsActive = (bool) $this->analytics_enabled && (! $this->require_consent_for_analytics || $consentGranted);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->content_mode === 'image_only' ? null : $this->body,
            'rich_html' => $this->sanitizedRichHtml(),
            'content_mode' => $this->content_mode ?: 'mixed',
            'popup_type' => $this->popup_type,
            'layout' => $this->layout ?: 'modal',
            'placement' => $this->placement ?: 'center',
            'max_width_px' => (int) ($this->max_width_px ?: 620),
            'background_color' => PopupContentSanitizer::sanitizeHexColor($this->background_color, '#ffffff'),
            'text_color' => PopupContentSanitizer::sanitizeHexColor($this->text_color, '#14213d'),
            'accent_color' => PopupContentSanitizer::sanitizeHexColor($this->accent_color, '#1546a8'),
            'font_scale' => $this->font_scale ?: 'normal',
            'overlay_opacity' => (int) ($this->overlay_opacity ?? 70),
            'show_close_button' => (bool) ($this->show_close_button ?? true),
            'dismissible' => (bool) ($this->dismissible ?? true),
            'animation_style' => $this->animation_style ?: 'fade',
            'respect_reduced_motion' => (bool) ($this->respect_reduced_motion ?? true),
            'hide_on_mobile' => (bool) $this->hide_on_mobile,
            'hide_on_desktop' => (bool) $this->hide_on_desktop,
            'desktop_image_url' => $this->content_mode === 'text_only' ? null : $this->image?->url,
            'mobile_image_url' => $this->content_mode === 'text_only' ? null : ($this->mobileImage?->url ?? $this->image?->url),
            'button_label' => $buttonUrl ? $buttonLabel : null,
            'button_url' => $buttonUrl,
            'button_external' => $isExternal,
            'frequency' => $this->frequency,
            'delay_seconds' => (int) $this->delay_seconds,
            'scroll_trigger_percent' => (int) ($this->scroll_trigger_percent ?? 0),
            'cooldown_hours' => (int) ($this->cooldown_hours ?? 0),
            'track_url' => $analyticsActive
                ? URL::temporarySignedRoute('popups.events', now()->addMinutes(30), ['popup' => $this->id])
                : null,
        ];
    }
}
