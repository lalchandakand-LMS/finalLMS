<div class="public-navigation">
    <button type="button" id="public-nav-trigger" class="public-nav-trigger" aria-controls="public-navigation-drawer" aria-expanded="false" aria-label="{{ __('Open navigation menu') }}">
        <span class="public-nav-trigger-icon" aria-hidden="true"><i></i><i></i><i></i></span>
        <span>{{ __('Explore') }}</span>
    </button>
    <div id="public-nav-backdrop" class="public-nav-backdrop" aria-hidden="true"></div>
    <aside id="public-navigation-drawer" class="public-drawer" role="dialog" aria-modal="true" aria-label="{{ __('Main navigation') }}" aria-hidden="true">
        <div class="public-drawer-head">
            <a class="public-drawer-brand" href="{{ route('landing') }}">
                <span class="public-drawer-brand-mark" aria-hidden="true">📘</span>
                <span>Alom’s English Center<small>{{ __('Learn. Practice. Achieve.') }}</small></span>
            </a>
            <button type="button" class="public-nav-close" data-menu-close aria-label="{{ __('Close navigation menu') }}">×</button>
        </div>
        <p class="public-drawer-intro">{{ __('Find your next learning step, explore courses and access your classroom.') }}</p>
        <div class="public-drawer-links">
            <a href="{{ route('landing') }}"><span class="menu-icon">⌂</span>{{ __('Home') }}</a>
            <a href="{{ route('courses.index') }}"><span class="menu-icon">▤</span>{{ __('All courses') }}</a>
            @if(\Illuminate\Support\Facades\Schema::hasTable('flash_sales'))
                <a href="{{ route('landing') }}#flash-sale"><span class="menu-icon">🔥</span>{{ __('Flash Sale') }}</a>
            @endif
            <a href="{{ route('contests.index') }}"><span class="menu-icon">✦</span>{{ __('Quiz & prizes') }}</a>
            <a href="{{ route('live-classes.index') }}"><span class="menu-icon">◉</span>{{ __('Live classes') }}</a>
            <a href="{{ route('news.index') }}"><span class="menu-icon">▧</span>{{ __('Blog & news') }}</a>
            @if(\Illuminate\Support\Facades\Schema::hasTable('website_pages'))
                @foreach(\App\Models\WebsitePage::published()->where('show_in_menu', true)->orderBy('menu_order')->orderBy('title')->get() as $customPage)
                    <a href="{{ route('website-pages.show', $customPage->slug) }}"><span class="menu-icon">▤</span>{{ $customPage->menu_label ?: $customPage->title }}</a>
                @endforeach
            @endif
            @foreach(is_array($websiteHeaderLinks ?? null) ? $websiteHeaderLinks : [] as $websiteLink)
                @if(is_array($websiteLink) && !empty($websiteLink['label']) && !empty($websiteLink['url']))
                    <a href="{{ $websiteLink['url'] }}"><span class="menu-icon">↗</span>{{ $websiteLink['label'] }}</a>
                @endif
            @endforeach
            <div class="public-drawer-section">{{ __('About the center') }}</div>
            <a href="{{ route('pages.about') }}"><span class="menu-icon">ⓘ</span>{{ __('About us') }}</a>
            <a href="{{ route('pages.contact') }}"><span class="menu-icon">✉</span>{{ __('Contact') }}</a>
            <a href="{{ route('pages.faq') }}"><span class="menu-icon">?</span>{{ __('FAQ & help') }}</a>
            @auth
                <div class="public-drawer-section">{{ __('My learning') }}</div>
                <a href="{{ route('dashboard') }}"><span class="menu-icon">▦</span>{{ __('Dashboard') }}</a>
                <a href="{{ route('profile.edit') }}"><span class="menu-icon">◎</span>{{ __('My profile') }}</a>
                @if(auth()->user()->isStudent())
                    <a href="{{ route('student.courses.index') }}"><span class="menu-icon">▣</span>{{ __('My courses') }}</a>
                    <a href="{{ route('student.exams.index') }}"><span class="menu-icon">✓</span>{{ __('Exams & results') }}</a>
                    <a href="{{ route('student.invoices.index') }}"><span class="menu-icon">৳</span>{{ __('My fees') }}</a>
                    <a href="{{ route('student.attendance.index') }}"><span class="menu-icon">◷</span>{{ __('Attendance') }}</a>
                @endif
                @if(auth()->user()->isTeacher())
                    <a href="{{ route('teacher.batches.index') }}"><span class="menu-icon">▥</span>{{ __('My batches') }}</a>
                @endif
                @if(auth()->user()->isBranchManager())
                    <a href="{{ route('branch-manager.batches.index') }}"><span class="menu-icon">⌖</span>{{ __('My branch') }}</a>
                @endif
                @if(auth()->user()->isAccountant())
                    <a href="{{ route('accountant.invoices.index') }}"><span class="menu-icon">৳</span>{{ __('Finance') }}</a>
                    <a href="{{ route('accountant.finance-reports.index') }}"><span class="menu-icon">▥</span>{{ __('Reports') }}</a>
                @endif
                @if(auth()->user()->isAdmin())
                    <div class="public-drawer-section">{{ __('Administration') }}</div>
                    <a href="{{ route('admin.website-builder.index') }}"><span class="menu-icon">▧</span>{{ __('Website builder') }}</a>
                    <a href="{{ route('admin.website-pages.index') }}"><span class="menu-icon">📄</span>{{ __('Custom pages') }}</a>
                    <a href="{{ route('admin.settings.index') }}"><span class="menu-icon">⚙</span>{{ __('Settings') }}</a>
                @endif
            @endauth
        </div>
        <div class="public-drawer-actions">
            @guest
                <a class="primary" href="{{ route('register') }}">{{ __('Join now') }}</a>
                <a href="{{ route('login') }}">{{ __('Student login') }}</a>
            @else
                <a class="primary" href="{{ route('dashboard') }}">{{ __('Open dashboard') }}</a>
                <form method="POST" action="{{ route('logout') }}" class="inline-form">
                    @csrf
                    <button type="submit">{{ __('Logout') }}</button>
                </form>
            @endguest
        </div>
        <div class="public-drawer-locale">
            <form method="POST" action="{{ route('locale.update') }}">
                @csrf
                <label for="public-locale-switch">{{ __('Choose language') }}</label>
                <select id="public-locale-switch" name="locale" onchange="this.form.submit()">
                    <option value="en" @selected(app()->getLocale() === 'en')>{{ __('English') }}</option>
                    <option value="bn" @selected(app()->getLocale() === 'bn')>{{ __('Bangla') }}</option>
                </select>
            </form>
        </div>
    </aside>
</div>