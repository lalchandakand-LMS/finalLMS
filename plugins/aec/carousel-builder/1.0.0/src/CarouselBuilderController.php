<?php

namespace Aloms\CarouselBuilder;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CarouselBuilderController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);

        $carousels = Carousel::query()->withCount('items')->orderBy('sort_order')->orderByDesc('id')->paginate(20);

        return view('carousel-builder::admin.index', compact('carousels'));
    }

    public function create(Request $request): View
    {
        $this->authorizeAdmin($request);

        return view('carousel-builder::admin.form', ['carousel' => new Carousel([
            'source_type' => 'manual', 'item_limit' => 8, 'desktop_cards' => 3,
            'mobile_cards' => 1, 'interval_ms' => 5000, 'status' => 'draft',
            'show_arrows' => true, 'show_dots' => true,
        ])]);
    }

    public function edit(Request $request, int $carousel): View
    {
        $this->authorizeAdmin($request);

        return view('carousel-builder::admin.form', ['carousel' => Carousel::with('items')->findOrFail($carousel)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;

        DB::transaction(function () use ($data): void {
            $carousel = Carousel::create(collect($data)->except('items')->all());
            $this->syncItems($carousel, $data['items'] ?? []);
        });

        return redirect()->route('admin.carousels.index')->with('success', 'Carousel created.');
    }

    public function update(Request $request, int $carousel): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $record = Carousel::findOrFail($carousel);
        $data = $this->validated($request, $record->id);

        DB::transaction(function () use ($record, $data): void {
            $record->update(collect($data)->except('items')->all());
            $this->syncItems($record, $data['items'] ?? []);
        });

        return redirect()->route('admin.carousels.index')->with('success', 'Carousel updated.');
    }

    public function destroy(Request $request, int $carousel): RedirectResponse
    {
        $this->authorizeAdmin($request);
        Carousel::findOrFail($carousel)->delete();

        return redirect()->route('admin.carousels.index')->with('success', 'Carousel deleted.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $slugRule = Rule::unique('website_carousels', 'slug');
        if ($ignoreId !== null) {
            $slugRule->ignore($ignoreId);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'alpha_dash', 'max:180', $slugRule],
            'source_type' => ['required', Rule::in(['manual', 'courses', 'teachers', 'testimonials'])],
            'item_limit' => ['required', 'integer', 'between:1,30'],
            'desktop_cards' => ['required', 'integer', 'between:1,6'],
            'mobile_cards' => ['required', 'integer', 'between:1,2'],
            'autoplay' => ['nullable', 'boolean'],
            'interval_ms' => ['required', 'integer', 'between:2500,15000'],
            'show_arrows' => ['nullable', 'boolean'],
            'show_dots' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'sort_order' => ['required', 'integer', 'between:0,100000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'items' => ['nullable', 'array', 'max:30'],
            'items.*' => ['array'],
            'items.*.title' => ['required_if:source_type,manual', 'nullable', 'string', 'max:180'],
            'items.*.description' => ['nullable', 'string', 'max:2000'],
            'items.*.image_url' => ['nullable', 'string', 'max:2048', function ($attribute, $value, $fail): void {
                if ($value !== null && $value !== '' && ! str_starts_with($value, '/') && filter_var($value, FILTER_VALIDATE_URL) === false) {
                    $fail('Image must be an HTTPS URL or a site-relative path.');
                }
                if (is_string($value) && str_starts_with($value, '//')) {
                    $fail('Protocol-relative image URLs are not allowed.');
                }
                if (is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false
                    && strtolower((string) parse_url($value, PHP_URL_SCHEME)) !== 'https') {
                    $fail('External images must use HTTPS.');
                }
            }],
            'items.*.link_url' => ['nullable', 'string', 'max:2048', function ($attribute, $value, $fail): void {
                if ($value !== null && $value !== '' && ! str_starts_with($value, '/') && filter_var($value, FILTER_VALIDATE_URL) === false) {
                    $fail('Link must be an HTTPS URL or a site-relative path.');
                }
                if (is_string($value) && str_starts_with($value, '//')) {
                    $fail('Protocol-relative links are not allowed.');
                }
                if (is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false
                    && strtolower((string) parse_url($value, PHP_URL_SCHEME)) !== 'https') {
                    $fail('External links must use HTTPS.');
                }
            }],
            'items.*.sort_order' => ['nullable', 'integer', 'between:0,100000'],
        ]);

        if ($data['source_type'] === 'manual' && $data['status'] === 'published'
            && collect($data['items'] ?? [])->filter(fn ($item): bool => is_array($item) && trim((string) ($item['title'] ?? '')) !== '')->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Add at least one titled item before publishing a manual carousel.']);
        }

        $data['autoplay'] = $request->boolean('autoplay');
        $data['show_arrows'] = $request->boolean('show_arrows');
        $data['show_dots'] = $request->boolean('show_dots');
        $data['starts_at'] = $data['starts_at'] ?? null;
        $data['ends_at'] = $data['ends_at'] ?? null;

        return $data;
    }

    private function syncItems(Carousel $carousel, array $items): void
    {
        $carousel->items()->delete();

        if ($carousel->source_type !== 'manual') {
            return;
        }

        foreach (array_values($items) as $index => $item) {
            if (! is_array($item) || trim((string) ($item['title'] ?? '')) === '') {
                continue;
            }

            $carousel->items()->create([
                'title' => trim($item['title']),
                'description' => $item['description'] ?? null,
                'image_url' => $item['image_url'] ?? null,
                'link_url' => $item['link_url'] ?? null,
                'sort_order' => (int) ($item['sort_order'] ?? $index),
            ]);
        }
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403);
    }
}