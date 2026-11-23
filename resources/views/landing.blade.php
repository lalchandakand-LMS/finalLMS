@extends('layouts.app')

@section('title', \Illuminate\Support\Facades\Schema::hasTable('settings') ? \App\Models\Setting::value('seo_title', config('app.name')) : config('app.name'))

@section('content')
    @php
        $hasSettingsTable = \Illuminate\Support\Facades\Schema::hasTable('settings');
        $hasCoursesTable = \Illuminate\Support\Facades\Schema::hasTable('courses');
        $hasUsersTable = \Illuminate\Support\Facades\Schema::hasTable('users');
        $hasCertificatesTable = \Illuminate\Support\Facades\Schema::hasTable('certificates');

        $siteName = $hasSettingsTable ? \App\Models\Setting::value('institution_name', \App\Models\Setting::value('site_name', config('app.name'))) : config('app.name');
        $homeTitle = $hasSettingsTable ? \App\Models\Setting::value('home_title', 'Grow your skills with a complete LMS experience.') : 'Grow your skills with a complete LMS experience.';
        $homeSubtitle = $hasSettingsTable ? \App\Models\Setting::value('home_subtitle', 'Learn from expert-led courses, track your progress, enroll in structured programs, and earn certificates that help you move forward with confidence.') : 'Learn from expert-led courses, track your progress, enroll in structured programs, and earn certificates that help you move forward with confidence.';
        $homePrimaryLabel = $hasSettingsTable ? \App\Models\Setting::value('home_cta_primary', 'Browse courses') : 'Browse courses';
        $homeSecondaryLabel = $hasSettingsTable ? \App\Models\Setting::value('home_cta_secondary', 'Login') : 'Login';
        $homeFeaturedTitle = $hasSettingsTable ? \App\Models\Setting::value('home_featured_title', \App\Models\Setting::value('home_paths_title', 'Popular learning tracks')) : 'Popular learning tracks';
        $courseCarouselEnabled = $hasSettingsTable ? \App\Models\Setting::value('home_course_carousel_enabled', '1') === '1' : true;
        $courseCarouselItems = $hasSettingsTable ? max(3, min(12, (int) \App\Models\Setting::value('home_course_carousel_items', 6))) : 6;
        $courseCarouselCards = $hasSettingsTable ? max(2, min(4, (int) \App\Models\Setting::value('home_course_carousel_cards', 3))) : 3;
        $homeReasonTitle = $hasSettingsTable ? \App\Models\Setting::value('home_reason_title', 'Built to keep you moving forward') : 'Built to keep you moving forward';

        $metricOneValue = $hasSettingsTable ? \App\Models\Setting::value('home_metric_1_value', '12+') : '12+';
        $metricOneLabel = $hasSettingsTable ? \App\Models\Setting::value('home_metric_1_label', 'Active courses') : 'Active courses';
        $metricTwoValue = $hasSettingsTable ? \App\Models\Setting::value('home_metric_2_value', '4.9/5') : '4.9/5';
        $metricTwoLabel = $hasSettingsTable ? \App\Models\Setting::value('home_metric_2_label', 'Learner score') : 'Learner score';
        $metricThreeValue = $hasSettingsTable ? \App\Models\Setting::value('home_metric_3_value', '24/7') : '24/7';
        $metricThreeLabel = $hasSettingsTable ? \App\Models\Setting::value('home_metric_3_label', 'Support') : 'Support';

        $courseCount = $hasCoursesTable ? \App\Models\Course::count() : 0;
        $studentCount = $hasUsersTable ? \App\Models\User::where('role', 'student')->count() : 0;
        $certificateCount = $hasCertificatesTable ? \App\Models\Certificate::count() : 0;
        $featuredCourses = $hasCoursesTable ? \App\Models\Course::with('teacher')->withCount('lessons')->latest()->take(3)->get() : collect();
        $storedSlides = $hasSettingsTable ? json_decode((string) \App\Models\Setting::value('home_slides', '[]'), true) : [];
        $sliderOptions = [
            'autoplay' => $hasSettingsTable ? \App\Models\Setting::value('home_slider_autoplay', '1') === '1' : true,
            'interval' => $hasSettingsTable ? max(3000, min(12000, (int) \App\Models\Setting::value('home_slider_interval', 5000))) : 5000,
            'arrows' => $hasSettingsTable ? \App\Models\Setting::value('home_slider_arrows', '1') === '1' : true,
            'dots' => $hasSettingsTable ? \App\Models\Setting::value('home_slider_dots', '1') === '1' : true,
            'pauseHover' => $hasSettingsTable ? \App\Models\Setting::value('home_slider_pause_hover', '1') === '1' : true,
            'height' => $hasSettingsTable ? max(320, min(720, (int) \App\Models\Setting::value('home_slider_height', 480))) : 480,
            'mobileHeight' => $hasSettingsTable ? max(220, min(520, (int) \App\Models\Setting::value('home_slider_mobile_height', 300))) : 300,
            'overlay' => $hasSettingsTable ? max(0, min(90, (int) \App\Models\Setting::value('home_slider_overlay', 60))) : 60,
            'transition' => $hasSettingsTable && in_array(\App\Models\Setting::value('home_slider_transition', 'slide'), ['slide','fade','zoom'], true) ? \App\Models\Setting::value('home_slider_transition', 'slide') : 'slide',
            'progress' => $hasSettingsTable ? \App\Models\Setting::value('home_slider_progress', '1') === '1' : true,
            'playPause' => $hasSettingsTable ? \App\Models\Setting::value('home_slider_play_pause', '1') === '1' : true,
        ];
        $flashSales = \Illuminate\Support\Facades\Route::has('flash-sales.public')
            && \App\Models\FlashSale::isPluginActive()
            && (bool) (int) \App\Models\Setting::value('flash_sale_page_enabled', '1')
            ? \App\Models\FlashSale::activeCampaigns()
            : collect();
        $homeSlides = collect(is_array($storedSlides) ? $storedSlides : [])
            ->filter(function ($slide) {
                if (!is_array($slide) || empty($slide['enabled']) || empty($slide['image_id'])) {
                    return false;
                }

                $now = now()->timestamp;
                $startsAt = !empty($slide['starts_at']) ? strtotime((string) $slide['starts_at']) : null;
                $endsAt = !empty($slide['ends_at']) ? strtotime((string) $slide['ends_at']) : null;

                return ($startsAt === null || ($startsAt !== false && $startsAt <= $now))
                    && ($endsAt === null || ($endsAt !== false && $endsAt >= $now));
            })
            ->sortBy(fn ($slide) => (int) ($slide['sort_order'] ?? 0))
            ->map(function ($slide) {
                $asset = \App\Models\MediaAsset::query()->whereKey($slide['image_id'])->where('mime_type', 'like', 'image/%')->first();
                $mobileAsset = !empty($slide['mobile_image_id'])
                    ? \App\Models\MediaAsset::query()->whereKey($slide['mobile_image_id'])->where('mime_type', 'like', 'image/%')->first()
                    : null;

                return $asset ? [
                    'asset' => $asset,
                    'mobile_asset' => $mobileAsset ?: $asset,
                    'title' => $slide['title'] ?? '',
                    'subtitle' => $slide['subtitle'] ?? '',
                    'button_label' => $slide['button_label'] ?? '',
                    'button_url' => $slide['button_url'] ?? '',
                    'eyebrow' => $slide['eyebrow'] ?? '',
                    'secondary_button_label' => $slide['secondary_button_label'] ?? '',
                    'secondary_button_url' => $slide['secondary_button_url'] ?? '',
                    'image_position_x' => max(0, min(100, (int) ($slide['image_position_x'] ?? 50))),
                    'image_position_y' => max(0, min(100, (int) ($slide['image_position_y'] ?? 50))),
                    'mobile_position_x' => max(0, min(100, (int) ($slide['mobile_position_x'] ?? 50))),
                    'mobile_position_y' => max(0, min(100, (int) ($slide['mobile_position_y'] ?? 50))),
                    'content_alignment' => in_array($slide['content_alignment'] ?? 'left', ['left','center','right'], true) ? ($slide['content_alignment'] ?? 'left') : 'left',
                    'overlay_color' => preg_match('/^#[0-9A-Fa-f]{6}$/', $slide['overlay_color'] ?? '') ? $slide['overlay_color'] : '#050f23',
                ] : null;
            })->filter()->values();
        $homepageTemplate = (string) ($hasSettingsTable ? \App\Models\Setting::value('homepage_template', 'redwans-method') : 'redwans-method');
        $homepageTemplate = array_key_exists($homepageTemplate, \App\Http\Controllers\WebsiteBuilderController::homepageTemplates()) ? $homepageTemplate : 'redwans-method';

        $homeBatchesTitle = $hasSettingsTable ? \App\Models\Setting::value('home_batches_title', 'Online Batches are ongoing!') : 'Online Batches are ongoing!';
        $homeStatsTitle = $hasSettingsTable ? \App\Models\Setting::value('home_stats_title', 'See the stats!') : 'See the stats!';
        $homePathsTitle = $hasSettingsTable ? \App\Models\Setting::value('home_paths_title', 'Choose Your Learning Path') : 'Choose Your Learning Path';
        $homeReviewsTitle = $hasSettingsTable ? \App\Models\Setting::value('home_reviews_title', 'What Our Students Say') : 'What Our Students Say';
        $openBatches = \Illuminate\Support\Facades\Schema::hasTable('batches')
            ? \App\Models\Batch::query()->with(['course','teacher'])->where('status','open')->orderBy('starts_at')->take(4)->get()
            : collect();
        $publishedCourses = $hasCoursesTable ? \App\Models\Course::where('status','published')->with('teacher')->withCount('lessons')->latest()->take($courseCarouselItems)->get() : collect();
        $publishedCourseCount = $hasCoursesTable ? \App\Models\Course::where('status','published')->count() : 0;
        $activeStudentCount = $hasUsersTable ? \App\Models\User::where('role','student')->count() : 0;
        $teacherCount = $hasUsersTable ? \App\Models\User::where('role','teacher')->count() : 0;
        $publishedLessonCount = $hasCoursesTable && \Illuminate\Support\Facades\Schema::hasTable('lessons') ? \App\Models\Lesson::whereHas('course',fn($q)=>$q->where('status','published'))->count() : 0;
        $learnerVoices = \Illuminate\Support\Facades\Schema::hasTable('comments') && $hasCoursesTable
            ? \App\Models\Comment::with('author')->where('commentable_type',\App\Models\Course::class)->where('status','approved')->whereIn('commentable_id',\App\Models\Course::where('status','published')->select('id'))->latest()->take(12)->get()
            : collect();
        $hasHomeSlides = $homeSlides->isNotEmpty();
        $showSliderArrows = $homeSlides->count() > 1 && $sliderOptions['arrows'];
        $showSliderDots = $homeSlides->count() > 1 && $sliderOptions['dots'];
        $isGuest = auth()->guest();
        $hasPublishedCourses = $publishedCourses->isNotEmpty();
        $hasLearnerVoices = $learnerVoices->isNotEmpty();
    @endphp

    <div class="homepage-template homepage-template--{{ $homepageTemplate }}">
    <style>
        .homepage-template { --template-accent: var(--primary); --template-surface: #fff; --template-radius: 20px; }
        .home-carousel { position:relative; }
        .home-carousel__viewport { position:relative; overflow:hidden; height:var(--carousel-height); border-radius:24px; background:#10213d; outline-offset:4px; }
        .home-carousel__track { display:flex; height:100%; transition:transform .65s cubic-bezier(.22,.61,.36,1); will-change:transform; }
        .home-carousel__slide { position:relative; isolation:isolate; flex:0 0 100%; min-width:0; height:100%; display:flex; align-items:center; overflow:hidden; color:#fff; }
        .home-carousel__slide img { position:absolute; inset:0; z-index:-2; width:100%; height:100%; object-fit:cover; }
        .home-carousel__shade { position:absolute; inset:0; z-index:-1; background:linear-gradient(90deg,color-mix(in srgb,var(--slide-overlay,#050f23) calc(var(--carousel-overlay) * 100%),transparent),color-mix(in srgb,var(--slide-overlay,#050f23) calc(var(--carousel-overlay) * 70%),transparent) 58%,transparent); }
        .home-carousel__content { width:min(760px,85%); padding:clamp(24px,6vw,72px); }
        .home-carousel__content--center { margin-inline:auto; text-align:center; }
        .home-carousel__content--right { margin-left:auto; text-align:right; }
        .home-carousel__secondary { display:inline-flex; margin-left:10px; padding:10px 18px; border:1px solid #ffffffa8; border-radius:10px; color:#fff; background:#ffffff18; text-decoration:none; font-weight:800; }
        .home-carousel__progress { height:3px; overflow:hidden; border-radius:5px; background:#0b173822; }
        .home-carousel__progress span { display:block; width:100%; height:100%; transform:scaleX(0); transform-origin:left; background:var(--primary); }
        .home-carousel__progress span.is-running { animation:home-carousel-progress var(--progress-duration,5s) linear forwards; }
        @keyframes home-carousel-progress { to { transform:scaleX(1); } }
        .home-carousel__controls { display:flex; justify-content:flex-end; margin-top:8px; }
        .home-carousel__toggle { border:1px solid var(--border); border-radius:999px; padding:6px 12px; background:var(--surface,#fff); color:var(--text); cursor:pointer; }
        .home-carousel--fade .home-carousel__track,.home-carousel--zoom .home-carousel__track { position:relative; display:block; transform:none !important; }
        .home-carousel--fade .home-carousel__slide,.home-carousel--zoom .home-carousel__slide { position:absolute; inset:0; opacity:0; pointer-events:none; transition:opacity .55s ease; }
        .home-carousel--fade .home-carousel__slide.is-active,.home-carousel--zoom .home-carousel__slide.is-active { opacity:1; pointer-events:auto; }
        .home-carousel--zoom .home-carousel__slide img { transform:scale(1.05); transition:transform .8s ease; }
        .home-carousel--zoom .home-carousel__slide.is-active img { transform:scale(1); }
        .home-carousel__content h2 { margin:0 0 14px; color:#fff; font-size:clamp(28px,4.4vw,54px); line-height:1.1; letter-spacing:-.03em; }
        .home-carousel__content p { margin:0 0 24px; color:rgba(255,255,255,.94); font-size:clamp(15px,1.6vw,19px); line-height:1.7; }
        .home-carousel__arrow { position:absolute; top:50%; z-index:2; transform:translateY(-50%); width:46px; height:46px; border:1px solid #ffffff88; border-radius:50%; background:#0b1738aa; color:#fff; font-size:32px; line-height:1; cursor:pointer; backdrop-filter:blur(8px); }
        .home-carousel__arrow:hover { background:#0b1738ee; }
        .home-carousel__arrow--prev { left:18px; }
        .home-carousel__arrow--next { right:18px; }
        .home-carousel__dots { display:flex; justify-content:center; align-items:center; gap:9px; margin-top:14px; }
        .home-carousel__dots button { width:9px; height:9px; padding:0; border:0; border-radius:50px; background:var(--primary); opacity:.35; cursor:pointer; transition:width .2s,opacity .2s; }
        .home-carousel__dots button[aria-current="true"] { width:26px; opacity:1; }
        @media(max-width:700px) { .home-carousel__viewport { height:var(--carousel-mobile-height); border-radius:16px; } .home-carousel__content { width:100%; padding:24px; } .home-carousel__arrow { width:36px;height:36px;font-size:25px; } .home-carousel__arrow--prev { left:10px; } .home-carousel__arrow--next { right:10px; } .home-carousel__slide img { object-position:var(--mobile-image-position,50% 50%) !important; } }
        @media(prefers-reduced-motion:reduce) { .home-carousel__track,.home-carousel__dots button { transition:none; } }

        .home-welcome{display:grid;grid-template-columns:1.1fr .9fr;align-items:center;min-height:330px;margin-bottom:26px;padding:clamp(30px,5vw,66px);overflow:hidden;border-radius:24px;color:#fff;background:radial-gradient(circle at 78% 20%,#1b8eaa,transparent 34%),linear-gradient(120deg,#062f43,#07566b 58%,#073d5a);box-shadow:0 20px 45px #082b4430}.home-welcome__copy{position:relative;z-index:2}.home-eyebrow,.home-section-kicker,.home-heading-kicker{color:#8be5d1;font-size:.76rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase}.home-welcome h1{margin:14px 0;color:#fff;font-size:clamp(2.2rem,4.8vw,4.4rem);line-height:1.04}.home-welcome p{color:#d7edf3;line-height:1.75}.home-actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:26px}.home-button{display:inline-flex;min-height:46px;align-items:center;justify-content:center;padding:12px 20px;border-radius:12px;font-weight:800;text-decoration:none}.home-button--light{color:#07384b;background:#fff}.home-button--outline{color:#fff;border:1px solid #ffffff70;background:#ffffff12}.home-button--primary{color:#fff;background:#087dbb}
        .home-welcome__art{position:relative;min-height:235px;display:grid;place-items:center}.home-orbit{position:absolute;width:230px;height:230px;border:1px solid #ffffff30;border-radius:50%}.home-orbit--two{width:300px;height:300px}.home-book{z-index:1;display:flex;flex-direction:column;align-items:center;justify-content:center;width:190px;height:180px;border:1px solid #ffffff80;border-radius:22px;color:#083b50;background:linear-gradient(145deg,#fff,#c7f5eb);box-shadow:0 24px 55px #001b2c55;transform:rotate(-7deg)}.home-book span{font-size:2.6rem;font-weight:900;color:#087bb4}.home-book strong{font-size:1.3rem}.home-book small{margin-top:7px;font-size:.65rem}.home-floating{position:absolute;z-index:2;display:grid;place-items:center;width:48px;height:48px;border-radius:15px;color:#fff;background:#087da9;font-weight:900}.home-floating--one{top:15%;right:10%}.home-floating--two{bottom:12%;left:10%;background:#0c9b70}
        .home-overview{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:28px;padding:clamp(18px,3vw,28px);border-radius:22px;background:#06384b}.home-overview__panel{padding:22px;border:1px solid #ffffff1c;border-radius:16px;color:#fff;background:linear-gradient(145deg,#0a4c58,#0c3d5c)}.home-overview__panel--stats{background:linear-gradient(145deg,#0b4057,#123b69)}.home-overview h2{color:#fff}.home-overview p{color:#b7dce4}.home-batch{display:flex;align-items:center;gap:12px;margin:9px 0;padding:12px;border:1px solid #ffffff12;border-radius:11px;color:#fff;background:#ffffff0c;text-decoration:none}.home-batch__details{display:grid;gap:4px;flex:1}.home-batch__details small{color:#b7dce4}.home-text-link{display:inline-block;margin-top:12px;color:#68e0ca;font-size:.8rem;font-weight:800;text-decoration:none}.home-stat-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}.home-stat{display:grid;grid-template-columns:34px 1fr;grid-template-rows:auto auto;align-items:center;gap:2px 10px;padding:12px;border:1px solid #ffffff12;border-radius:10px;background:#ffffff0c}.home-stat>span{grid-row:1/3;display:grid;place-items:center;width:34px;height:34px;border-radius:9px;background:#087ebd}.home-stat strong{color:#fff}.home-stat small{color:#b7dce4}.home-empty{display:grid;gap:7px;padding:17px;border:1px dashed #ffffff40;border-radius:12px;color:#fff}.home-empty span{color:#b7dce4;font-size:.83rem}.home-empty--light{max-width:700px;margin:auto;color:#18344a;text-align:center;border-color:#b7cde0;background:#fff}.home-empty--light span{color:#5b6e7e}
        .home-paths{margin:0 -24px;padding:clamp(42px,6vw,70px) 24px;background:linear-gradient(135deg,#f1edff,#f5f1ff 55%,#eaf5ff)}.home-section-heading{max-width:760px;margin:0 auto 30px;text-align:center}.home-heading-kicker{color:#087dbb}.home-section-heading h2{color:#12344e;font-size:clamp(1.8rem,3vw,2.6rem)}.home-section-heading p{color:#607487}.home-course-grid{display:flex;gap:16px;max-width:1080px;margin:auto;overflow-x:auto;scroll-snap-type:x mandatory;scroll-behavior:smooth;padding:4px 2px 14px;overscroll-behavior-inline:contain}.home-course-tile{flex:0 0 calc((100% - var(--course-gaps,32px))/var(--course-cards,3));scroll-snap-align:start}.home-course-carousel__controls{display:flex;justify-content:flex-end;gap:8px;max-width:1080px;margin:0 auto 12px}.home-course-carousel__controls button{width:40px;height:40px;border:1px solid #b8cce3;border-radius:50%;background:#fff;color:#15344b;font-size:1.25rem;cursor:pointer}.home-course-carousel__controls button:disabled{opacity:.35;cursor:not-allowed}.home-course-tile{display:flex;min-height:185px;flex-direction:column;padding:22px;border-radius:15px;color:#15344b;background:linear-gradient(145deg,#dcecff,#c8e2ff);text-decoration:none;transition:transform .2s}.home-course-tile:nth-child(3n+2){background:linear-gradient(145deg,#dcfce7,#c6f5d6)}.home-course-tile:nth-child(3n+3){background:linear-gradient(145deg,#f3e8ff,#e8d5ff)}.home-course-tile:hover{transform:translateY(-4px)}.home-course-tile__image{display:block;width:calc(100% + 44px);max-width:none;height:150px;object-fit:cover;margin:-22px -22px 16px;border-radius:15px 15px 0 0}.home-course-tile__icon{display:grid;place-items:center;width:42px;height:42px;margin-bottom:15px;border-radius:12px;color:#fff;background:#1677d2;font-size:1.25rem}.home-course-tile strong{margin-bottom:7px}.home-course-tile>span:not(.home-course-tile__icon){color:#536b7e;font-size:.78rem;line-height:1.6}.home-course-tile small{display:flex;justify-content:space-between;margin-top:auto;padding-top:15px;color:#1264a2;font-weight:800}.home-paths__footer{text-align:center;margin-top:26px}
        .home-testimonials{margin:0 -24px;padding:clamp(42px,6vw,64px) 0;overflow:hidden;color:#fff;background:linear-gradient(135deg,#111d31,#17263e 55%,#0c1b30)}.home-section-heading--dark h2{color:#fff}.home-section-heading--dark p{color:#b6c6d9}.home-section-heading--dark .home-heading-kicker{color:#58c5ef}.home-testimonial-viewport{overflow:hidden;margin:14px 0;mask-image:linear-gradient(90deg,transparent,#000 4%,#000 96%,transparent)}.home-testimonial-track{display:flex;width:max-content;gap:14px;padding:4px 0;animation:home-testimonial-left 48s linear infinite}.home-testimonial-track--reverse{animation-name:home-testimonial-right;animation-duration:54s}.home-testimonial-viewport:hover .home-testimonial-track{animation-play-state:paused}.home-quote-card{display:flex;width:300px;min-height:155px;flex-direction:column;padding:20px;border:1px solid #ffffff18;border-radius:13px;background:#ffffff0c}.home-quote-mark{color:#5ac8ee;font-size:2rem}.home-quote-card p{flex:1;color:#dbe6f3;font-size:.82rem;line-height:1.65}.home-quote-author{display:flex;align-items:center;gap:9px;color:#fff;font-size:.78rem}.home-quote-avatar{display:grid;place-items:center;width:30px;height:30px;border-radius:50%;color:#082d49;background:#b9e8f6;font-weight:900}@keyframes home-testimonial-left{to{transform:translateX(-50%)}}@keyframes home-testimonial-right{from{transform:translateX(-50%)}to{transform:translateX(0)}}
        @media(max-width:820px){.home-welcome{grid-template-columns:1fr}.home-overview{grid-template-columns:1fr}.home-course-tile{flex-basis:calc((100% - 16px)/2)}}@media(max-width:560px){.home-course-tile__image{width:calc(100% + 44px);height:170px}.home-welcome{padding:26px 20px}.home-welcome__art{min-height:150px}.home-book{width:150px;height:140px}.home-overview{padding:12px}.home-overview__panel{padding:17px}.home-course-tile{flex-basis:min(86%,320px)}.home-course-tile{min-height:155px}.home-paths,.home-testimonials{margin-left:-12px;margin-right:-12px}.home-paths{padding-left:16px;padding-right:16px}.home-quote-card{width:260px}}@media(prefers-reduced-motion:reduce){.home-testimonial-track,.home-course-tile{animation:none;transition:none}}
        .homepage-template .hero { gap: clamp(20px,4vw,48px); }
        .homepage-template .hero > div:first-child { padding: clamp(8px,2vw,22px); }
        .homepage-template .hero h1 { letter-spacing: -.035em; }
        .homepage-template .panel, .homepage-template .card { border-radius: var(--template-radius); }
        .homepage-template .panel { border-color: color-mix(in srgb, var(--template-accent) 16%, #dbe2ee); }
        .homepage-template .btn.primary { background: var(--template-accent); }
        .homepage-template .badge.info { background: color-mix(in srgb, var(--template-accent) 12%, white); color: var(--template-accent); }
        .homepage-template .stat { border-radius: calc(var(--template-radius) * .7); }
        .homepage-template--redwans-method { --template-accent:#08a9c7; --template-radius:16px; padding:18px 24px 0; color:#e7f6fb; background:#062f43; }
        .homepage-template--redwans-method .home-carousel { max-width:1120px; margin:0 auto 24px !important; }
        .homepage-template--redwans-method .home-carousel__viewport { border-radius:18px; box-shadow:0 18px 44px #001b2b66; }
        .homepage-template--redwans-method .home-overview { max-width:1120px; margin:0 auto 22px; padding:0 0 22px; background:transparent; }
        .homepage-template--redwans-method .home-overview { grid-template-columns:1fr; }
        .homepage-template--redwans-method .home-overview__panel--batches { display:none; }
        .homepage-template--redwans-method .home-overview__panel--stats { width:100%; }

        .homepage-template--redwans-method .home-overview__panel { border-color:#0b7180; background:linear-gradient(145deg,#084d55,#0a3c57); }
        .homepage-template--redwans-method .home-overview__panel--stats { background:linear-gradient(145deg,#08445a,#123b69); }
        .homepage-template--redwans-method .home-overview h2 { color:#fff; }
        .homepage-template--redwans-method .home-paths { background:linear-gradient(135deg,#f1edff,#f5f1ff 55%,#eaf5ff); }
        .homepage-template--redwans-method .home-testimonials { background:linear-gradient(135deg,#111d31,#17263e 55%,#0c1b30); }
        .homepage-template--redwans-method .home-testimonial-track { animation-duration:48s; }

        /* Redwan's Method-inspired batch-first hero slider */
        .homepage-template--redwans-method .home-carousel { max-width:1120px; }
        .homepage-template--redwans-method .home-carousel__viewport { height:clamp(390px,42vw,500px); background:#071f3d; }
        .homepage-template--redwans-method .home-carousel__slide { display:grid; grid-template-columns:minmax(0,1.05fr) minmax(320px,.95fr); align-items:stretch; gap:clamp(18px,3vw,38px); padding:clamp(28px,5vw,62px); color:#102b49; background:radial-gradient(circle at 82% 18%,#d8f4ff 0,transparent 35%),linear-gradient(115deg,#f7fbff 0%,#eaf5ff 58%,#dff4fb 100%); }
        .homepage-template--redwans-method .home-carousel__slide::before { content:""; position:absolute; inset:0 auto 0 0; width:7px; background:#08a9c7; }
        .homepage-template--redwans-method .home-carousel__slide picture { position:absolute; inset:0 0 0 auto; z-index:0; width:42%; opacity:.12; pointer-events:none; }
        .homepage-template--redwans-method .home-carousel__slide picture img { position:static; width:100%; height:100%; object-fit:cover; }
        .homepage-template--redwans-method .home-carousel__shade { display:none; }
        .homepage-template--redwans-method .home-carousel__content { position:relative; z-index:1; width:auto; max-width:620px; padding:0; align-self:center; }
        .homepage-template--redwans-method .home-carousel__eyebrow { display:inline-flex; align-items:center; gap:8px; margin-bottom:15px; padding:8px 13px; border-radius:999px; color:#087d9c; background:#d8f5fb; font-size:.76rem; font-weight:900; letter-spacing:.1em; text-transform:uppercase; }
        .homepage-template--redwans-method .home-carousel__eyebrow::before { content:""; width:7px; height:7px; border-radius:50%; background:#09a8c7; box-shadow:0 0 0 4px #09a8c722; }
        .homepage-template--redwans-method .home-carousel__content h2 { margin:0 0 13px; color:#102b49; font-size:clamp(30px,4vw,52px); font-weight:900; line-height:1.08; letter-spacing:-.04em; }
        .homepage-template--redwans-method .home-carousel__content p { margin:0 0 22px; color:#526b82; font-size:clamp(15px,1.5vw,18px); line-height:1.65; }
        .homepage-template--redwans-method .home-carousel__content .btn.primary { display:inline-flex; min-height:48px; align-items:center; padding:12px 22px; border:0; border-radius:10px; color:#fff; background:#087dbb; font-weight:850; box-shadow:0 9px 22px #087dbb30; }
        .homepage-template--redwans-method .home-carousel__batch-list { position:relative; z-index:1; display:grid; grid-template-columns:1fr 1fr; align-content:center; gap:14px; min-width:0; }
        .homepage-template--redwans-method .home-carousel__batch-card { display:flex; min-height:160px; flex-direction:column; justify-content:space-between; padding:clamp(16px,2vw,22px); border:1px solid #d8e7f4; border-radius:17px; color:#153653; background:#ffffffed; box-shadow:0 15px 35px #15365316; text-decoration:none; transition:transform .2s,box-shadow .2s; }
        .homepage-template--redwans-method .home-carousel__batch-card:hover { transform:translateY(-4px); box-shadow:0 20px 38px #15365324; }
        .homepage-template--redwans-method .home-carousel__batch-card small { color:#087da4; font-size:.72rem; font-weight:900; letter-spacing:.08em; text-transform:uppercase; }
        .homepage-template--redwans-method .home-carousel__batch-card strong { margin:9px 0 4px; color:#123451; font-size:clamp(17px,1.7vw,22px); line-height:1.2; }
        .homepage-template--redwans-method .home-carousel__batch-card span { color:#657d91; font-size:.83rem; line-height:1.45; }
        .homepage-template--redwans-method .home-carousel__batch-card b { align-self:flex-end; color:#0786b7; font-size:1.15rem; }
        .homepage-template--redwans-method .home-carousel__batch-empty { grid-column:1/-1; display:grid; gap:8px; padding:24px; border:1px dashed #9bbbd0; border-radius:16px; color:#153653; background:#ffffffd9; }
        .homepage-template--redwans-method .home-carousel__batch-empty span { color:#60788d; font-size:.86rem; }
        .homepage-template--redwans-method .home-carousel__arrow { border-color:#c5d9e8; color:#16415f; background:#ffffffed; box-shadow:0 5px 15px #15365318; }
        .homepage-template--redwans-method .home-carousel__arrow:hover { background:#fff; }
        .homepage-template--redwans-method .home-carousel__dots button { background:#0787b7; }
        .homepage-template--redwans-method .home-welcome { display:none; }
        @media(max-width:760px) {
            .homepage-template--redwans-method .home-carousel__viewport { height:auto; min-height:0; }
            .homepage-template--redwans-method .home-carousel__track { height:auto; }
            .homepage-template--redwans-method .home-carousel__slide { grid-template-columns:1fr; gap:18px; min-height:0; padding:26px 22px 30px; }
            .homepage-template--redwans-method .home-carousel__slide picture { width:100%; opacity:.08; }
            .homepage-template--redwans-method .home-carousel__content { padding:0 16px; }
            .homepage-template--redwans-method .home-carousel__content h2 { font-size:clamp(28px,8vw,38px); }
            .homepage-template--redwans-method .home-carousel__batch-list { grid-template-columns:1fr 1fr; gap:9px; padding:0 8px 8px; }
            .homepage-template--redwans-method .home-carousel__batch-card { min-height:128px; padding:13px; border-radius:13px; }
            .homepage-template--redwans-method .home-carousel__batch-card strong { font-size:16px; }
            .homepage-template--redwans-method .home-carousel__batch-card span { font-size:.74rem; }
            .homepage-template--redwans-method .home-carousel__arrow { top:18px; transform:none; width:32px; height:32px; font-size:22px; }
            .homepage-template--redwans-method .home-carousel__arrow--prev { left:auto; right:52px; }
            .homepage-template--redwans-method .home-carousel__arrow--next { right:12px; }
        }
        @media(max-width:380px) { .homepage-template--redwans-method .home-carousel__batch-list { grid-template-columns:1fr; } .homepage-template--redwans-method .home-carousel__batch-card { min-height:112px; } }

        @media(max-width:820px) { .homepage-template--redwans-method { padding:12px 12px 0; } .homepage-template--redwans-method .home-overview { padding-bottom:16px; } }
        .homepage-template--academy-modern { --template-accent:#2457c5; --template-radius:20px; }
        .homepage-template--academy-modern .hero { background:linear-gradient(135deg,#eff6ff,#fff 60%); border-radius:26px; padding:clamp(18px,3vw,32px); }
        .homepage-template--bold-academy { --template-accent:#172554; --template-radius:10px; }
        .homepage-template--bold-academy .hero { background:#172554; color:#fff; border-radius:10px; padding:clamp(24px,4vw,48px); }
        .homepage-template--bold-academy .hero h1,.homepage-template--bold-academy .hero p { color:#fff; }
        .homepage-template--minimal-learning { --template-accent:#0f766e; --template-radius:8px; }
        .homepage-template--minimal-learning .hero { background:#f8fafc; padding:clamp(16px,3vw,30px); border-left:5px solid var(--template-accent); }
        .homepage-template--minimal-learning .card,.homepage-template--minimal-learning .panel { box-shadow:none; }
        .homepage-template--editorial { --template-accent:#92400e; --template-radius:4px; }
        .homepage-template--editorial .hero { grid-template-columns:1.3fr .7fr; border-top:5px solid var(--template-accent); padding:28px 0; }
        .homepage-template--editorial .hero h1 { font-family:Georgia,serif; font-weight:600; }
        .homepage-template--gradient-studio { --template-accent:#7c3aed; --template-radius:26px; }
        .homepage-template--gradient-studio .hero { background:linear-gradient(125deg,#ede9fe,#dbeafe 55%,#fce7f3); padding:clamp(20px,4vw,40px); border-radius:30px; }
        .homepage-template--gradient-studio .card { background:linear-gradient(145deg,#fff,#f5f3ff); }
        .homepage-template--corporate-training { --template-accent:#075985; --template-radius:6px; }
        .homepage-template--corporate-training .hero { background:#eaf2f8; padding:28px; border-bottom:5px solid var(--template-accent); }
        .homepage-template--corporate-training .card { border-top:3px solid var(--template-accent); }
        .homepage-template--playful-classroom { --template-accent:#db2777; --template-radius:28px; }
        .homepage-template--playful-classroom .hero { background:#fff1f2; border-radius:36px 12px 36px 12px; padding:28px; }
        .homepage-template--playful-classroom .card { border:2px solid #fbcfe8; }
        .homepage-template--midnight-campus { --template-accent:#38bdf8; --template-radius:16px; color:#e2e8f0; background:#0f172a; padding:22px; border-radius:24px; }
        .homepage-template--midnight-campus .hero { background:#1e293b; color:#f8fafc; padding:28px; border-radius:18px; }
        .homepage-template--midnight-campus .hero h1,.homepage-template--midnight-campus .hero p,.homepage-template--midnight-campus h2,.homepage-template--midnight-campus h3 { color:#f8fafc; }
        .homepage-template--midnight-campus .panel,.homepage-template--midnight-campus .card,.homepage-template--midnight-campus .stat { background:#1e293b; color:#e2e8f0; border-color:#334155; }
        .homepage-template--midnight-campus .muted { color:#cbd5e1; }
        .homepage-template--split-screen { --template-accent:#4338ca; --template-radius:14px; }
        .homepage-template--split-screen .hero { grid-template-columns:1fr 1fr; gap:0; background:#eef2ff; border-radius:16px; overflow:hidden; }
        .homepage-template--split-screen .hero > div:first-child { padding:clamp(24px,4vw,46px); }
        .homepage-template--split-screen .hero > .panel { margin:0; border:0; border-radius:0; }
        .homepage-template--classic-institute { --template-accent:#166534; --template-radius:2px; }
        .homepage-template--classic-institute .hero { border-top:4px double var(--template-accent); border-bottom:4px double var(--template-accent); padding:28px 8px; }
        .homepage-template--classic-institute .hero h1 { font-family:Georgia,serif; }
        .homepage-template--glass-campus { --template-accent:#0e7490; --template-radius:22px; }
        .homepage-template--glass-campus .hero { background:linear-gradient(135deg,#cffafeaa,#e0f2feaa); padding:28px; border:1px solid #bae6fd; }
        .homepage-template--glass-campus .panel,.homepage-template--glass-campus .card { background:#ffffffd9; backdrop-filter:blur(12px); border:1px solid #cbd5e1; }
        .homepage-template--vibrant-skills { --template-accent:#ea580c; --template-radius:18px; }
        .homepage-template--vibrant-skills .hero { background:linear-gradient(120deg,#ffedd5,#fef3c7); padding:28px; }
        .homepage-template--vibrant-skills .stat { background:#fff7ed; border:1px solid #fed7aa; }
        .homepage-template--focus-learning { --template-accent:#4338ca; --template-radius:12px; }
        .homepage-template--focus-learning .hero { grid-template-columns:1fr; max-width:900px; margin-inline:auto; text-align:center; }
        .homepage-template--focus-learning .hero .cta-row { justify-content:center; }
        .homepage-template--focus-learning .hero .stats { max-width:650px; margin:24px auto 0; }
        .homepage-template--elegant-academy { --template-accent:#9f1239; --template-radius:12px; }
        .homepage-template--elegant-academy .hero { background:#fff1f2; padding:30px; border:1px solid #fecdd3; }
        .homepage-template--elegant-academy .hero h1 { font-family:Georgia,serif; font-weight:500; }
        .homepage-template--community-campus { --template-accent:#0f766e; --template-radius:24px; }
        .homepage-template--community-campus .hero { background:linear-gradient(135deg,#ccfbf1,#f0fdfa); padding:30px; border-radius:32px 32px 12px 32px; }
        .homepage-template--community-campus .card { border-bottom:4px solid #99f6e4; }
        @media(max-width:700px) {
            .homepage-template--editorial .hero,.homepage-template--split-screen .hero { grid-template-columns:1fr; }
            .homepage-template--midnight-campus { padding:10px; }
            .homepage-template--focus-learning .hero { text-align:left; }
            .homepage-template--focus-learning .hero .cta-row { justify-content:flex-start; }
        }
    </style>

    @include('partials.flash-sales', ['flashSales' => $flashSales])

    @if($hasHomeSlides)
        <section class="home-carousel home-carousel--{{ $sliderOptions['transition'] }}" aria-label="{{ __('Homepage highlights') }}" data-home-carousel data-transition="{{ $sliderOptions['transition'] }}" data-autoplay="{{ $sliderOptions['autoplay'] ? 'true' : 'false' }}" data-interval="{{ $sliderOptions['interval'] }}" data-pause-hover="{{ $sliderOptions['pauseHover'] ? 'true' : 'false' }}" style="--progress-duration:{{ $sliderOptions['interval'] }}ms;--carousel-height:{{ $sliderOptions['height'] }}px;--carousel-mobile-height:{{ $sliderOptions['mobileHeight'] }}px;--carousel-overlay:{{ $sliderOptions['overlay'] / 100 }};margin-bottom:28px;">
            <div class="home-carousel__viewport" data-carousel-viewport tabindex="0" aria-roledescription="carousel">
                <div class="home-carousel__track" data-carousel-track>
                    @foreach($homeSlides as $index => $slide)
                        <article class="home-carousel__slide{{ $index === 0 ? ' is-active' : '' }}" data-carousel-slide aria-label="{{ $index + 1 }} {{ __('of') }} {{ $homeSlides->count() }}" aria-roledescription="slide" aria-hidden="{{ $index === 0 ? 'false' : 'true' }}" style="--slide-overlay:{{ $slide['overlay_color'] }};--mobile-image-position:{{ $slide['mobile_position_x'] }}% {{ $slide['mobile_position_y'] }}%;">
                            <picture><source media="(max-width: 700px)" srcset="{{ $slide['mobile_asset']->url }}"><img src="{{ $slide['asset']->url }}" alt="{{ $slide['asset']->alt_text ?: $slide['title'] }}" loading="{{ $index === 0 ? 'eager' : 'lazy' }}" style="object-position:{{ $slide['image_position_x'] }}% {{ $slide['image_position_y'] }}%;"></picture>
                            <div class="home-carousel__content home-carousel__content--{{ $slide['content_alignment'] }}">
                                <span class="home-carousel__eyebrow">{{ $slide['eyebrow'] !== '' ? $slide['eyebrow'] : __('Online Course') }}</span>
                                <h2>{{ $slide['title'] !== '' ? $slide['title'] : $homeBatchesTitle }}</h2>
                                <p>{{ $slide['subtitle'] !== '' ? $slide['subtitle'] : __('Book your seat now! Choose an available batch and start learning with Alom’s English Center.') }}</p>
                                <a class="btn primary" href="{{ $slide['button_url'] !== '' ? $slide['button_url'] : route('courses.index') }}" rel="noopener">{{ $slide['button_label'] !== '' ? $slide['button_label'] : __('Book your seat now!') }}</a>
                                @if($slide['secondary_button_label'] !== '' && $slide['secondary_button_url'] !== '')
                                    <a class="home-carousel__secondary" href="{{ $slide['secondary_button_url'] }}" rel="noopener">{{ $slide['secondary_button_label'] }}</a>
                                @endif
                            </div>
                            <div class="home-carousel__batch-list" aria-label="{{ __('Available online batches') }}">
                                @forelse($openBatches->take(2) as $batch)
                                    @if($batch->course)
                                        <a class="home-carousel__batch-card" href="{{ route('courses.show', $batch->course) }}">
                                            <small>{{ __('Online Batch') }}</small>
                                            <strong>{{ $batch->title }}</strong>
                                            <span>{{ $batch->course->title }}{{ $batch->teacher ? ' · '.$batch->teacher->name : '' }}</span>
                                            <b aria-hidden="true">↗</b>
                                        </a>
                                    @endif
                                @empty
                                    @forelse($publishedCourses->take(2) as $course)
                                        <a class="home-carousel__batch-card" href="{{ route('courses.show', $course) }}">
                                            <small>{{ __('Featured Course') }}</small>
                                            <strong>{{ $course->title }}</strong>
                                            <span>{{ \Illuminate\Support\Str::limit(strip_tags($course->description), 76) }}</span>
                                            <b aria-hidden="true">↗</b>
                                        </a>
                                    @empty
                                        <div class="home-carousel__batch-empty"><strong>{{ __('New batches are coming soon!') }}</strong><span>{{ __('Visit all courses to explore current learning opportunities.') }}</span><a href="{{ route('courses.index') }}">{{ __('See All Courses') }} →</a></div>
                                    @endforelse
                                @endforelse
                            </div>
                        </article>
                    @endforeach
                </div>
                @if($showSliderArrows)
                    <button type="button" class="home-carousel__arrow home-carousel__arrow--prev" data-carousel-prev aria-label="{{ __('Previous slide') }}">‹</button>
                    <button type="button" class="home-carousel__arrow home-carousel__arrow--next" data-carousel-next aria-label="{{ __('Next slide') }}">›</button>
                @endif
            </div>
            @if($sliderOptions['progress'] && $homeSlides->count() > 1)
                <div class="home-carousel__progress" aria-hidden="true"><span data-carousel-progress></span></div>
            @endif
            @if($sliderOptions['playPause'] && $homeSlides->count() > 1)
                <div class="home-carousel__controls"><button type="button" class="home-carousel__toggle" data-carousel-toggle aria-pressed="{{ $sliderOptions['autoplay'] ? 'true' : 'false' }}">{{ $sliderOptions['autoplay'] ? __('Pause') : __('Play') }}</button></div>
            @endif
            @if($showSliderDots)
                <nav class="home-carousel__dots" aria-label="{{ __('Slider navigation') }}" data-carousel-dots>
                    @foreach($homeSlides as $index => $slide)
                        <button type="button" data-carousel-dot="{{ $index }}" aria-label="{{ __('Go to slide') }} {{ $index + 1 }}" aria-current="{{ $index === 0 ? 'true' : 'false' }}"></button>
                    @endforeach
                </nav>
            @endif
        </section>
    @else
        <section class="home-carousel home-carousel--static" aria-label="{{ __('Online batches') }}" style="--carousel-height:460px;--carousel-mobile-height:auto;margin-bottom:28px;">
            <div class="home-carousel__viewport">
                <div class="home-carousel__track">
                    <article class="home-carousel__slide">
                        <div class="home-carousel__content">
                            <span class="home-carousel__eyebrow">{{ __('Online Course') }}</span>
                            <h2>{{ $homeBatchesTitle }}</h2>
                            <p>{{ __('Book your seat now! Choose an available batch and start learning with Alom’s English Center.') }}</p>
                            <a class="btn primary" href="{{ route('courses.index') }}">{{ __('Book your seat now!') }}</a>
                        </div>
                        <div class="home-carousel__batch-list" aria-label="{{ __('Available online batches') }}">
                            @forelse($openBatches->take(2) as $batch)
                                @if($batch->course)
                                    <a class="home-carousel__batch-card" href="{{ route('courses.show', $batch->course) }}">
                                        <small>{{ __('Online Batch') }}</small><strong>{{ $batch->title }}</strong>
                                        <span>{{ $batch->course->title }}{{ $batch->teacher ? ' · '.$batch->teacher->name : '' }}</span><b aria-hidden="true">↗</b>
                                    </a>
                                @endif
                            @empty
                                @forelse($publishedCourses->take(2) as $course)
                                    <a class="home-carousel__batch-card" href="{{ route('courses.show', $course) }}">
                                        <small>{{ __('Featured Course') }}</small><strong>{{ $course->title }}</strong>
                                        <span>{{ \Illuminate\Support\Str::limit(strip_tags($course->description), 76) }}</span><b aria-hidden="true">↗</b>
                                    </a>
                                @empty
                                    <div class="home-carousel__batch-empty"><strong>{{ __('New batches are coming soon!') }}</strong><span>{{ __('Visit all courses to explore current learning opportunities.') }}</span><a href="{{ route('courses.index') }}">{{ __('See All Courses') }} →</a></div>
                                @endforelse
                            @endforelse
                        </div>
                    </article>
                </div>
            </div>
        </section>
    @endif


    @if($homepageTemplate !== 'redwans-method')
    <section class="home-welcome">
        <div class="home-welcome__copy"><span class="home-eyebrow">{{ $siteName }}</span><h1>{{ $homeTitle }}</h1><p>{{ $homeSubtitle }}</p>
            <div class="home-actions"><a href="{{ route('courses.index') }}" class="home-button home-button--light">{{ $homePrimaryLabel }}</a>
                @if($isGuest)
                    <a href="{{ route('register') }}" class="home-button home-button--outline">{{ __('Create student account') }}</a>
                @else
                    <a href="{{ url('/dashboard') }}" class="home-button home-button--outline">{{ __('Go to dashboard') }}</a>
                @endif
            </div>
        </div>
        <div class="home-welcome__art" aria-hidden="true"><div class="home-orbit"></div><div class="home-orbit home-orbit--two"></div><div class="home-book"><span>ABC</span><strong>{{ __('Learn') }}</strong><small>{{ __('Practice · Improve · Succeed') }}</small></div><div class="home-floating home-floating--one">Aa</div><div class="home-floating home-floating--two">✓</div></div>
    </section>
    @endif
    <section class="home-overview">
        <div class="home-overview__panel home-overview__panel--batches"><span class="home-section-kicker">{{ __('Admissions & batches') }}</span><h2>{{ $homeBatchesTitle }}</h2><p>{{ __('Book your seat in an available class.') }}</p>
            @forelse($openBatches as $batch)
                @if($batch->course)
                    <a class="home-batch" href="{{ route('courses.show', $batch->course) }}">
                        <span class="home-batch__icon">▣</span>
                        <span class="home-batch__details">
                            <strong>{{ $batch->title }}</strong>
                            <small>{{ $batch->course->title }}{{ $batch->teacher ? ' · '.$batch->teacher->name : '' }}</small>
                        </span>
                        <span>↗</span>
                    </a>
                @endif
            @empty
                <div class="home-empty"><strong>{{ __('No active batches at the moment') }}</strong><span>{{ __('New batch announcements will appear here when published by the admin.') }}</span></div>
            @endforelse
            <a class="home-text-link" href="{{ route('courses.index') }}">{{ __('See all courses') }} →</a>
        </div>
        </div>
        <div class="home-overview__panel home-overview__panel--stats"><span class="home-section-kicker">{{ $siteName }}</span><h2>{{ $homeStatsTitle }}</h2><p>{{ __('Live platform statistics') }}</p>
            <div class="home-stat-grid"><div class="home-stat"><span>▦</span><strong>{{ number_format($activeStudentCount) }}</strong><small>{{ __('Students') }}</small></div><div class="home-stat"><span>▶</span><strong>{{ number_format($publishedCourseCount) }}</strong><small>{{ __('Courses') }}</small></div><div class="home-stat"><span>♙</span><strong>{{ number_format($teacherCount) }}</strong><small>{{ __('Teachers') }}</small></div><div class="home-stat"><span>▣</span><strong>{{ number_format($publishedLessonCount) }}</strong><small>{{ __('Videos / lessons') }}</small></div></div>
            <a class="home-text-link" href="{{ route('courses.index') }}">{{ __('See all courses') }} →</a>
        </div>
    </section>
    @if($courseCarouselEnabled)
    <section class="home-paths"><div class="home-section-heading"><span class="home-heading-kicker">{{ __('Start learning today') }}</span><h2>{{ $homeFeaturedTitle }}</h2><p>{{ __('Explore courses published by Alom’s English Center.') }}</p></div>
        @if($hasPublishedCourses)
            <div class="home-course-carousel">
                <div class="home-course-carousel__controls" aria-label="{{ __('Course carousel controls') }}">
                    <button type="button" data-course-prev aria-label="{{ __('Scroll courses left') }}">‹</button>
                    <button type="button" data-course-next aria-label="{{ __('Scroll courses right') }}">›</button>
                </div>
                <div class="home-course-grid" data-course-carousel-track tabindex="0" aria-label="{{ __('Published courses') }}" style="--course-cards:{{ $courseCarouselCards }};--course-gaps:{{ ($courseCarouselCards - 1) * 16 }}px;">
                @foreach($publishedCourses as $course)
                    <a class="home-course-tile" href="{{ route('courses.show',$course) }}">
                        @if($course->image_path)
                            <img class="home-course-tile__image" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($course->image_path) }}" alt="{{ $course->title }}" loading="lazy">
                        @else
                            <span class="home-course-tile__icon" aria-hidden="true">{{ ['✦','▤','◈','⌘','◎','✎'][$loop->index % 6] }}</span>
                        @endif
                        <strong>{{ $course->title }}</strong>
                        <span>{{ \Illuminate\Support\Str::limit(strip_tags($course->description),88) }}</span>
                        <small>{{ $course->lessons_count }} {{ __('lessons') }} <b>↗</b></small>
                    </a>
                @endforeach
                </div>
            </div>
        @else
            <div class="home-empty home-empty--light"><strong>{{ __('Our learning paths are being prepared') }}</strong><span>{{ __('Published courses will appear here automatically.') }}</span></div>
        @endif
        <div class="home-paths__footer"><a class="home-button home-button--primary" href="{{ route('courses.index') }}">{{ __('Browse all courses') }}</a></div>
    </section>
    @endif
    @php
        $testimonialRows = [
            $learnerVoices->values()->slice(0, 6),
            $learnerVoices->values()->slice(6, 6),
        ];
        if ($testimonialRows[1]->isEmpty()) {
            $testimonialRows[1] = $testimonialRows[0];
        }
    @endphp
    <section class="home-testimonials"><div class="home-section-heading home-section-heading--dark"><span class="home-heading-kicker">{{ __('Student reviews') }}</span><h2>{{ $homeReviewsTitle }}</h2><p>{{ __('Approved feedback from our learners') }}</p></div>
        @foreach(($websiteCarousels ?? collect()) as $websiteCarousel)
        @include('carousel-builder::public.carousel', ['carousel' => $websiteCarousel])
    @endforeach

    @if($hasLearnerVoices)
            @foreach($testimonialRows as $rowIndex => $row)
                <div class="home-testimonial-viewport">
                    <div class="home-testimonial-track {{ $rowIndex === 1 ? 'home-testimonial-track--reverse' : '' }}">
                        @foreach([0, 1] as $copy)
                            @foreach($row as $comment)
                                <article class="home-quote-card" aria-hidden="{{ $copy === 1 ? 'true' : 'false' }}">
                                    <span class="home-quote-mark">“</span>
                                    <p>{{ \Illuminate\Support\Str::limit(strip_tags($comment->body), 190) }}</p>
                                    <div class="home-quote-author">
                                        <span class="home-quote-avatar">{{ mb_strtoupper(mb_substr($comment->author?->name ?? 'S', 0, 1)) }}</span>
                                        <strong>{{ $comment->author?->name ?? __('Student') }}</strong>
                                    </div>
                                </article>
                            @endforeach
                        @endforeach
                    </div>
                </div>
            @endforeach
        @else
            <p class="home-empty home-empty--dark">{{ __('Student reviews will appear here after approved feedback is received.') }}</p>
        @endif
    </section>

    @php
        $builderSections = $hasSettingsTable ? json_decode((string) \App\Models\Setting::value('website_sections', '[]'), true) : [];
        $builderSections = is_array($builderSections) ? $builderSections : [];
    @endphp
    @foreach($builderSections as $section)
        @php
            $sectionEnabled = (bool) ($section['enabled'] ?? false);
            $sectionHasContent = trim((string) ($section['title'] ?? '')) !== '' || trim((string) ($section['body'] ?? '')) !== '';
        @endphp
        @if($sectionEnabled && $sectionHasContent)
            @php
                $sectionImage = !empty($section['image_id']) ? \App\Models\MediaAsset::find($section['image_id']) : null;
                $sectionBackground = preg_match('/^#[0-9A-Fa-f]{6}$/', $section['background_color'] ?? '') ? $section['background_color'] : '#ffffff';
                $sectionTextColor = preg_match('/^#[0-9A-Fa-f]{6}$/', $section['text_color'] ?? '') ? $section['text_color'] : '#18212f';
                $sectionSpacing = ['compact' => '16px', 'normal' => '24px', 'airy' => '40px'][$section['spacing'] ?? 'normal'] ?? '24px';
                $sectionLayout = in_array($section['layout'] ?? 'standard', ['standard', 'reversed', 'centered'], true) ? ($section['layout'] ?? 'standard') : 'standard';
                $sectionGridStyle = $sectionLayout === 'centered' ? 'grid-template-columns:1fr;text-align:center;' : ($sectionLayout === 'reversed' ? 'direction:rtl;' : '');
                $sectionHasTitle = trim((string) ($section['title'] ?? '')) !== '';
                $sectionHasBody = trim((string) ($section['body'] ?? '')) !== '';
                $sectionHasButton = ! empty($section['button_label']) && ! empty($section['button_url']);
                $sectionHasImage = $sectionImage && str_starts_with($sectionImage->mime_type, 'image/');
            @endphp
            <section class="panel" style="margin-top:28px;background-color:{{ $sectionBackground }};color:{{ $sectionTextColor }};padding:{{ $sectionSpacing }};">
                <div class="grid grid-2" style="align-items:center;{{ $sectionGridStyle }}">
                    <div style="direction:ltr;text-align:{{ $sectionLayout === 'centered' ? 'center' : 'left' }};">
                        @if($sectionHasTitle)
                            <h2>{{ $section['title'] }}</h2>
                        @endif
                        @if($sectionHasBody)
                            <p style="white-space:pre-line;line-height:1.8;">{{ $section['body'] }}</p>
                        @endif
                        @if($sectionHasButton)
                            <a class="btn primary" href="{{ $section['button_url'] }}" rel="nofollow noopener">{{ $section['button_label'] }}</a>
                        @endif
                    </div>
                    @if($sectionHasImage)
                        <div style="direction:ltr;{{ $sectionLayout === 'centered' ? 'text-align:center;' : '' }}"><img src="{{ $sectionImage->url }}" alt="{{ $sectionImage->alt_text }}" loading="lazy" style="width:100%;max-width:{{ $sectionLayout === 'centered' ? '560px' : '100%' }};max-height:340px;object-fit:cover;border-radius:14px;"></div>
                    @endif
                </div>
            </section>
        @endif
    @endforeach
    </div>

@push('scripts')
<script>
(() => {
    const track = document.querySelector('[data-course-carousel-track]');
    if (!track) return;
    const previous = document.querySelector('[data-course-prev]');
    const next = document.querySelector('[data-course-next]');
    const updateControls = () => {
        if (previous) previous.disabled = track.scrollLeft <= 2;
        if (next) next.disabled = track.scrollLeft + track.clientWidth >= track.scrollWidth - 2;
    };
    const scroll = (direction) => track.scrollBy({
        left: direction * Math.max(220, Math.round(track.clientWidth * 0.8)),
        behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
    });
    previous?.addEventListener('click', () => scroll(-1));
    next?.addEventListener('click', () => scroll(1));
    track.addEventListener('scroll', updateControls, {passive:true});
    window.addEventListener('resize', updateControls);
    updateControls();
})();

(() => {
    const root = document.querySelector('[data-home-carousel]');
    if (!root) return;
    const track = root.querySelector('[data-carousel-track]');
    const slides = [...root.querySelectorAll('[data-carousel-slide]')];
    const dots = [...root.querySelectorAll('[data-carousel-dot]')];
    const previous = root.querySelector('[data-carousel-prev]');
    const next = root.querySelector('[data-carousel-next]');
    const viewport = root.querySelector('[data-carousel-viewport]');
    if (slides.length < 2 || !track) return;

    let active = 0;
    let timer = null;
    let hovered = false;
    let focused = false;
    let inViewport = true;
    const motionPreference = window.matchMedia('(prefers-reduced-motion: reduce)');
    let reducedMotion = motionPreference.matches;
    let playing = root.dataset.autoplay === 'true';
    const interval = Math.max(3000, Math.min(12000, Number(root.dataset.interval) || 5000));
    const pauseOnHover = root.dataset.pauseHover === 'true';
    const transition = ['slide','fade','zoom'].includes(root.dataset.transition) ? root.dataset.transition : 'slide';
    const progress = root.querySelector('[data-carousel-progress]');
    const toggle = root.querySelector('[data-carousel-toggle]');

    const render = (index) => {
        active = (index + slides.length) % slides.length;
        if (transition === 'slide') track.style.transform = 'translate3d(' + (-active * 100) + '%,0,0)';
        slides.forEach((slide, i) => {
            slide.setAttribute('aria-hidden', i === active ? 'false' : 'true');
            slide.classList.toggle('is-active', i === active);
        });
        dots.forEach((dot, i) => dot.setAttribute('aria-current', i === active ? 'true' : 'false'));
        if (progress) {
            progress.classList.remove('is-running');
            void progress.offsetWidth;
        }
    };

    const stop = () => {
        if (timer !== null) {
            window.clearInterval(timer);
            timer = null;
        }
        if (progress) progress.classList.remove('is-running');
    };

    const canAutoplay = () => playing && inViewport && !document.hidden
        && !hovered && !focused && !reducedMotion;

    const start = () => {
        stop();
        if (canAutoplay()) {
            if (progress) progress.classList.add('is-running');
            timer = window.setInterval(() => {
                render(active + 1);
                if (progress) progress.classList.add('is-running');
            }, interval);
        }
        if (toggle) {
            toggle.setAttribute('aria-pressed', playing ? 'true' : 'false');
            toggle.textContent = playing ? 'Pause' : 'Play';
        }
    };

    const interact = (index) => {
        render(index);
        start();
    };

    toggle?.addEventListener('click', () => {
        playing = !playing;
        start();
    });
    document.addEventListener('visibilitychange', start);
    previous?.addEventListener('click', () => interact(active - 1));
    next?.addEventListener('click', () => interact(active + 1));
    dots.forEach((dot) => dot.addEventListener('click', () => interact(Number(dot.dataset.carouselDot))));

    if (pauseOnHover) {
        root.addEventListener('mouseenter', () => { hovered = true; start(); });
        root.addEventListener('mouseleave', () => { hovered = false; start(); });
    }
    root.addEventListener('focusin', () => { focused = true; start(); });
    root.addEventListener('focusout', (event) => {
        if (!root.contains(event.relatedTarget)) {
            focused = false;
            start();
        }
    });

    viewport?.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowLeft') { event.preventDefault(); interact(active - 1); }
        if (event.key === 'ArrowRight') { event.preventDefault(); interact(active + 1); }
    });

    let touchStartX = null;
    viewport?.addEventListener('touchstart', (event) => {
        touchStartX = event.changedTouches[0]?.clientX ?? null;
    }, {passive:true});
    viewport?.addEventListener('touchend', (event) => {
        if (touchStartX === null) return;
        const delta = (event.changedTouches[0]?.clientX ?? touchStartX) - touchStartX;
        if (Math.abs(delta) > 45) interact(active + (delta < 0 ? 1 : -1));
        touchStartX = null;
    }, {passive:true});

    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(([entry]) => {
            inViewport = Boolean(entry?.isIntersecting);
            start();
        }, {threshold:0.15});
        observer.observe(root);
    }

    const refreshLayout = () => render(active);
    if ('ResizeObserver' in window) {
        const resizeObserver = new ResizeObserver(refreshLayout);
        resizeObserver.observe(root);
        if (viewport) resizeObserver.observe(viewport);
    } else {
        window.addEventListener('resize', refreshLayout, {passive:true});
    }
    slides.forEach((slide) => slide.querySelectorAll('img').forEach((image) => {
        if (!image.complete) image.addEventListener('load', refreshLayout, {once:true});
    }));

    const onMotionPreferenceChange = (event) => {
        reducedMotion = event.matches;
        start();
    };
    if (typeof motionPreference.addEventListener === 'function') {
        motionPreference.addEventListener('change', onMotionPreferenceChange);
    } else if (typeof motionPreference.addListener === 'function') {
        motionPreference.addListener(onMotionPreferenceChange);
    }

    render(active);
    start();
})();
</script>
@endpush

@endsection
