<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AdminCmsDashboardController extends Controller
{
    public function index(): View
    {
        $tables = [
            'pages' => 'website_pages',
            'posts' => 'blog_posts',
            'media' => 'media_assets',
            'menus' => 'website_menu_items',
            'plugins' => 'plugin_registry',
            'themes' => 'themes',
        ];
        $counts = [];
        foreach ($tables as $key => $table) {
            $counts[$key] = Schema::hasTable($table) ? DB::table($table)->count() : 0;
        }
        $counts['published_pages'] = Schema::hasTable('website_pages') ? DB::table('website_pages')->where('status', 'published')->count() : 0;
        $counts['published_posts'] = Schema::hasTable('blog_posts') ? DB::table('blog_posts')->where('status', 'published')->count() : 0;
        $counts['active_plugins'] = Schema::hasTable('plugin_registry') ? DB::table('plugin_registry')->where('status', 'active')->count() : 0;
        $counts['active_themes'] = Schema::hasTable('themes') ? DB::table('themes')->where('is_active', true)->count() : 0;
        $recentPages = Schema::hasTable('website_pages')
            ? DB::table('website_pages')->select('id', 'title', 'slug', 'status', 'updated_at')->latest('updated_at')->limit(5)->get()
            : collect();
        $recentPosts = Schema::hasTable('blog_posts')
            ? DB::table('blog_posts')->select('id', 'title', 'slug', 'status', 'updated_at')->latest('updated_at')->limit(5)->get()
            : collect();

        return view('admin.cms.index', compact('counts', 'recentPages', 'recentPosts'));
    }
}
