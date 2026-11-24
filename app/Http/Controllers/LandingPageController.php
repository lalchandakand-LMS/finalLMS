<?php

namespace App\Http\Controllers;

use App\Services\HomepageStatistics;
use App\Modules\Carousel\Carousel;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LandingPageController extends Controller
{
    public function __invoke(HomepageStatistics $statistics): View|RedirectResponse
    {
        if (auth()->check()) {
            return redirect('/dashboard');
        }

        return view('landing', [
            'homepageStatistics' => $statistics->get(),
            'websiteCarousels' => Schema::hasTable('website_carousels')
                ? Carousel::query()->visible()->with('items')->orderBy('sort_order')->orderBy('id')->limit(12)->get()
                    ->map(fn (Carousel $carousel): array => [
                        'id' => $carousel->id,
                        'title' => $carousel->title,
                        'slug' => $carousel->slug,
                        'desktop_cards' => $carousel->desktop_cards,
                        'mobile_cards' => $carousel->mobile_cards,
                        'autoplay' => $carousel->autoplay,
                        'interval_ms' => $carousel->interval_ms,
                        'show_arrows' => $carousel->show_arrows,
                        'show_dots' => $carousel->show_dots,
                        'items' => $carousel->displayItems(),
                    ])->filter(fn (array $carousel): bool => $carousel['items']->isNotEmpty())->values()
                : collect(),
        ]);
    }
}
