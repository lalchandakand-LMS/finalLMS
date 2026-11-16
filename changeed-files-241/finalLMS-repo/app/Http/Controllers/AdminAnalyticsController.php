<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\Comment;
use App\Models\Course;
use App\Models\PageView;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminAnalyticsController extends Controller
{
    public function index(): View
    {
        $today = now()->toDateString();
        $weekStart = now()->subDays(6)->toDateString();

        $stats = [
            'unique_visitors' => PageView::where('view_type', 'site')->distinct('visitor_hash')->count('visitor_hash'),
            'visitors_today' => PageView::where('view_type', 'site')->whereDate('viewed_on', $today)->distinct('visitor_hash')->count('visitor_hash'),
            'visitors_7_days' => PageView::where('view_type', 'site')->whereBetween('viewed_on', [$weekStart, $today])->distinct('visitor_hash')->count('visitor_hash'),
            'course_views' => PageView::where('view_type', 'course')->count(),
            'post_views' => PageView::where('view_type', 'post')->count(),
            'pending_comments' => Comment::where('status', 'pending')->count(),
            'approved_comments' => Comment::where('status', 'approved')->count(),
        ];

        $dailyVisitors = PageView::select('viewed_on', DB::raw('COUNT(DISTINCT visitor_hash) as visitors'))
            ->where('view_type', 'site')->whereBetween('viewed_on', [$weekStart, $today])
            ->groupBy('viewed_on')->orderBy('viewed_on')->get();

        $topCourses = Course::query()->select('courses.*')
            ->selectSub(fn ($query) => $query->from('page_views')->selectRaw('COUNT(*)')
                ->whereColumn('page_views.viewable_id', 'courses.id')->where('page_views.view_type', 'course'), 'views_count')
            ->orderByDesc('views_count')->limit(10)->get();

        $topPosts = BlogPost::query()->select('blog_posts.*')
            ->selectSub(fn ($query) => $query->from('page_views')->selectRaw('COUNT(*)')
                ->whereColumn('page_views.viewable_id', 'blog_posts.id')->where('page_views.view_type', 'post'), 'views_count')
            ->orderByDesc('views_count')->limit(10)->get();

        return view('admin.analytics.index', compact('stats', 'dailyVisitors', 'topCourses', 'topPosts'));
    }
}
