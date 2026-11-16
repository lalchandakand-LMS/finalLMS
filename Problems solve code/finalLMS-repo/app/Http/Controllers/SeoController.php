<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\Course;
use App\Models\WebsitePage;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;

class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $urls = collect([
            ['loc' => route('landing'), 'lastmod' => null],
            ['loc' => route('courses.index'), 'lastmod' => null],
            ['loc' => route('course-combos.index'), 'lastmod' => null],
            ['loc' => route('ebooks.index'), 'lastmod' => null],
            ['loc' => route('news.index'), 'lastmod' => null],
            ['loc' => route('pages.about'), 'lastmod' => null],
            ['loc' => route('pages.contact'), 'lastmod' => null],
            ['loc' => route('pages.faq'), 'lastmod' => null],
        ]);

        if (Schema::hasTable('courses')) {
            Course::query()->where('status', 'published')->select(['id', 'slug', 'updated_at'])->orderBy('id')
                ->chunk(200, function ($courses) use ($urls) {
                    foreach ($courses as $course) {
                        $urls->push(['loc' => route('courses.show', $course), 'lastmod' => $course->updated_at]);
                    }
                });
        }

        if (Schema::hasTable('blog_posts')) {
            BlogPost::query()->where('status', 'published')->whereNotNull('published_at')
                ->where('published_at', '<=', now())->select(['id', 'slug', 'updated_at'])->orderBy('id')
                ->chunk(200, function ($posts) use ($urls) {
                    foreach ($posts as $post) {
                        $urls->push(['loc' => route('news.show', $post), 'lastmod' => $post->updated_at]);
                    }
                });
        }

        if (Schema::hasTable('website_pages')) {
            WebsitePage::published()->select(['id', 'slug', 'updated_at'])->orderBy('id')
                ->chunk(200, function ($pages) use ($urls) {
                    foreach ($pages as $page) {
                        $urls->push(['loc' => route('website-pages.show', $page->slug), 'lastmod' => $page->updated_at]);
                    }
                });
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($urls->unique('loc') as $item) {
            $xml .= '<url><loc>' . e($item['loc']) . '</loc>';
            if ($item['lastmod']) {
                $xml .= '<lastmod>' . e($item['lastmod']->toAtomString()) . '</lastmod>';
            }
            $xml .= '</url>';
        }
        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        $sitemapUrl = url('/sitemap.xml');
        $body = "User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /dashboard\n\nSitemap: {$sitemapUrl}\n";

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
