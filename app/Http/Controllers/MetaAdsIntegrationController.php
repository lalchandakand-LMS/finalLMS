<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\DashboardAnnouncement;
use App\Models\FlashSale;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Throwable;

class MetaAdsIntegrationController extends Controller
{
    private const GRAPH_VERSION = 'v27.0';

    public function index(Request $request): View
    {
        $boostCourses = Course::query()->where('status', 'published')->orderBy('title')->get(['id', 'title', 'slug', 'short_description', 'image_path']);
        $boostNotices = DashboardAnnouncement::currentlyActive()
            ->where('audience', 'all')->whereNull('branch_id')->orderBy('title')
            ->get(['id', 'title', 'body', 'button_url']);
        $boostOffers = FlashSale::query()->where('status', 'active')
            ->where('starts_at', '<=', now())->where('ends_at', '>=', now())
            ->orderBy('title')->get(['id', 'title', 'description', 'banner_path']);

        $token = $this->accessToken();
        $connected = filled($token);
        $accounts = [];
        $pages = [];
        $campaigns = [];
        $insights = [];
        $selectedAccount = (string) $request->query('account', Setting::value('meta_ads_account_id', ''));
        $error = null;

        if ($connected) {
            try {
                $accounts = $this->graphGet('me/adaccounts', $token, [
                    'fields' => 'id,account_id,name,account_status,currency,timezone_name',
                    'limit' => 100,
                ])['data'] ?? [];
                $pages = $this->graphGet('me/accounts', $token, [
                    'fields' => 'id,name,category,tasks',
                    'limit' => 100,
                ])['data'] ?? [];

                $accounts = array_map(function (array $account): array {
                    $account['account_id'] = preg_replace('/^act_/', '', (string) ($account['account_id'] ?? $account['id'] ?? ''));

                    return $account;
                }, $accounts);
                $allowedAccountIds = collect($accounts)->pluck('account_id')->map(fn ($id) => (string) $id);
                if ($selectedAccount !== '' && $allowedAccountIds->contains($selectedAccount)) {
                    Setting::setValue('meta_ads_account_id', $selectedAccount);
                    $campaigns = $this->graphGet('act_'.$selectedAccount.'/campaigns', $token, [
                        'fields' => 'id,name,status,effective_status,objective,start_time,stop_time',
                        'limit' => 50,
                    ])['data'] ?? [];
                    $insights = $this->graphGet('act_'.$selectedAccount.'/insights', $token, [
                        'fields' => 'spend,impressions,clicks,reach,cpc,cpm,ctr,actions',
                        'date_preset' => 'last_30d',
                    ])['data'][0] ?? [];
                } elseif ($selectedAccount !== '') {
                    $selectedAccount = '';
                    $error = __('The selected ad account is not available to this connection.');
                }
            } catch (Throwable $exception) {
                $error = __('Meta could not return account data. Check the granted permissions and reconnect if needed.');
            }
        }

        return view('admin.marketing.meta-ads', [
            'boostCourses' => $boostCourses,\n            'boostNotices' => $boostNotices,\n            'boostOffers' => $boostOffers,\n            'connected' => $connected,
            'configured' => filled(config('services.meta_ads.app_id')) && filled(config('services.meta_ads.app_secret')),
            'accounts' => $accounts,
            'pages' => $pages,
            'campaigns' => $campaigns,
            'insights' => $insights,
            'selectedAccount' => $selectedAccount,
            'expiresAt' => Setting::value('meta_ads_token_expires_at'),
            'error' => $error,
        ]);
    }

    public function connect(Request $request): RedirectResponse
    {
        abort_unless(filled(config('services.meta_ads.app_id')) && filled(config('services.meta_ads.app_secret')), 503, __('Meta Ads credentials are not configured on the server.'));

        $state = bin2hex(random_bytes(32));
        $request->session()->put('meta_ads_oauth_state', $state);

        $query = http_build_query([
            'client_id' => config('services.meta_ads.app_id'),
            'redirect_uri' => config('services.meta_ads.redirect_uri'),
            'state' => $state,
            'response_type' => 'code',
            'scope' => 'ads_read,ads_management,business_management,pages_read_engagement,pages_show_list',
        ]);

        return redirect('https://www.facebook.com/'.self::GRAPH_VERSION.'/dialog/oauth?'.$query);
    }

    public function callback(Request $request): RedirectResponse
    {
        $expectedState = (string) $request->session()->pull('meta_ads_oauth_state', '');
        $receivedState = (string) $request->query('state', '');

        if ($expectedState === '' || $receivedState === '' || ! hash_equals($expectedState, $receivedState)) {
            return redirect()->route('admin.marketing.meta-ads.index')->with('error', __('Meta connection could not be verified. Please try again.'));
        }

        if ($request->filled('error')) {
            return redirect()->route('admin.marketing.meta-ads.index')->with('error', __('Meta authorization was cancelled or denied.'));
        }

        $request->validate(['code' => ['required', 'string', 'max:4096']]);

        try {
            $response = Http::acceptJson()->get('https://graph.facebook.com/'.self::GRAPH_VERSION.'/oauth/access_token', [
                'client_id' => config('services.meta_ads.app_id'),
                'client_secret' => config('services.meta_ads.app_secret'),
                'redirect_uri' => config('services.meta_ads.redirect_uri'),
                'code' => $request->query('code'),
            ])->throw()->json();

            $shortLivedToken = (string) ($response['access_token'] ?? '');
            abort_if($shortLivedToken === '', 422, __('Meta did not return an access token.'));

            $longLived = Http::acceptJson()->get('https://graph.facebook.com/'.self::GRAPH_VERSION.'/oauth/access_token', [
                'grant_type' => 'fb_exchange_token',
                'client_id' => config('services.meta_ads.app_id'),
                'client_secret' => config('services.meta_ads.app_secret'),
                'fb_exchange_token' => $shortLivedToken,
            ])->throw()->json();

            $token = (string) ($longLived['access_token'] ?? $shortLivedToken);
            Setting::setValue('meta_ads_access_token', Crypt::encryptString($token));
            Setting::setValue('meta_ads_token_expires_at', now()->addSeconds((int) ($longLived['expires_in'] ?? $response['expires_in'] ?? 3600))->toIso8601String());
            Setting::setValue('meta_ads_connected_at', now()->toIso8601String());

            return redirect()->route('admin.marketing.meta-ads.index')->with('success', __('Meta Ads account connected. Campaigns remain under your control.'));
        } catch (Throwable $exception) {

            return redirect()->route('admin.marketing.meta-ads.index')->with('error', __('Meta connection failed. Verify the app settings, redirect URI and permissions.'));
        }
    }

    public function disconnect(): RedirectResponse
    {
        Setting::query()->whereIn('key', [
            'meta_ads_access_token',
            'meta_ads_token_expires_at',
            'meta_ads_connected_at',
            'meta_ads_account_id',
        ])->delete();

        return redirect()->route('admin.marketing.meta-ads.index')->with('success', __('Meta Ads connection removed from this LMS.'));
    }

    public function pauseCampaign(Request $request, string $campaignId): RedirectResponse
    {
        abort_unless(preg_match('/^\d{5,30}$/', $campaignId) === 1, 404);

        $data = $request->validate([
            'account_id' => ['required', 'string', 'regex:/^\d{5,30}$/'],
        ]);

        $token = $this->accessToken();
        abort_unless($token, 409, __('Connect Meta Ads first.'));

        try {
            $accounts = $this->graphGet('me/adaccounts', $token, ['fields' => 'id,account_id', 'limit' => 100])['data'] ?? [];
            $validAccount = collect($accounts)->contains(function ($account) use ($data): bool {
                $rawId = (string) ($account['account_id'] ?? $account['id'] ?? '');

                return preg_replace('/^act_/', '', $rawId) === $data['account_id'];
            });
            abort_unless($validAccount, 403, __('This ad account is not accessible to the connected Meta user.'));

            $campaign = $this->graphGet($campaignId, $token, ['fields' => 'id,account_id']);
            $campaignAccountId = preg_replace('/^act_/', '', (string) ($campaign['account_id'] ?? ''));
            abort_unless($campaignAccountId === $data['account_id'], 403, __('This campaign does not belong to the selected ad account.'));

            Http::withToken($token)->asForm()->acceptJson()->post('https://graph.facebook.com/'.self::GRAPH_VERSION.'/'.$campaignId, [
                'status' => 'PAUSED',
            ])->throw();

            return redirect()->route('admin.marketing.meta-ads.index', ['account' => $data['account_id']])->with('success', __('Meta campaign paused.'));
        } catch (Throwable $exception) {
            

            return redirect()->route('admin.marketing.meta-ads.index', ['account' => $data['account_id']])->with('error', __('Meta could not pause this campaign. Check its status and permissions.'));
        }
    }

    public function resumeCampaign(Request $request, string $campaignId): RedirectResponse
    {
        abort_unless(preg_match('/^\d{5,30}$/', $campaignId) === 1, 404);

        $data = $request->validate([
            'account_id' => ['required', 'string', 'regex:/^\d{5,30}$/'],
        ]);

        $token = $this->accessToken();
        abort_unless($token, 409, __('Connect Meta Ads first.'));

        try {
            $accounts = $this->graphGet('me/adaccounts', $token, ['fields' => 'id,account_id', 'limit' => 100])['data'] ?? [];
            $validAccount = collect($accounts)->contains(fn ($account) => preg_replace('/^act_/', '', (string) ($account['account_id'] ?? $account['id'] ?? '')) === $data['account_id']);
            abort_unless($validAccount, 403, __('This ad account is not accessible to the connected Meta user.'));

            $campaign = $this->graphGet($campaignId, $token, ['fields' => 'id,account_id,status']);
            $campaignAccountId = preg_replace('/^act_/', '', (string) ($campaign['account_id'] ?? ''));
            abort_unless($campaignAccountId === $data['account_id'], 403, __('This campaign does not belong to the selected ad account.'));
            abort_unless(($campaign['status'] ?? '') === 'PAUSED', 409, __('Only a paused campaign can be resumed.'));

            // For campaigns built by this LMS, activate the children first and the parent last.
            // The campaign remains paused if a child activation fails, preventing delivery.
            $localCampaign = \App\Models\MetaBoostCampaign::where('meta_campaign_id', $campaignId)->first();
            if ($localCampaign && filled($localCampaign->meta_adset_id) && filled($localCampaign->meta_ad_id)) {
                foreach ([$localCampaign->meta_ad_id, $localCampaign->meta_adset_id] as $childId) {
                    Http::withToken($token)->asForm()->acceptJson()
                        ->post('https://graph.facebook.com/'.self::GRAPH_VERSION.'/'.$childId, ['status' => 'ACTIVE'])
                        ->throw();
                }
            }

            Http::withToken($token)->asForm()->acceptJson()->post('https://graph.facebook.com/'.self::GRAPH_VERSION.'/'.$campaignId, [
                'status' => 'ACTIVE',
            ])->throw();

            if ($localCampaign) {
                $localCampaign->update(['status' => 'running']);
            }

            return redirect()->route('admin.marketing.meta-ads.index', ['account' => $data['account_id']])->with('success', __('Meta campaign resumed. It may now deliver ads and spend its configured budget.'));
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('admin.marketing.meta-ads.index', ['account' => $data['account_id']])->with('error', __('Meta could not resume this campaign. Check its status and permissions.'));
        }
    }

    /**
     * Create a complete traffic campaign hierarchy in PAUSED state.
     * Nothing can deliver or spend until an administrator explicitly resumes it.
     */
    public function createCampaign(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'account_id' => ['required', 'string', 'regex:/^\d{5,30}$/'],
            'page_id' => ['required', 'string', 'regex:/^\d{5,30}$/'],
            'name' => ['nullable', 'string', 'max:180'],
            'message' => ['nullable', 'string', 'max:2000'],
            'source_reference' => ['nullable', 'string', 'regex:/^(course|notice|offer):[0-9]+$/'],
            'destination_url' => ['nullable', 'url:http,https', 'max:2048'],
            'image_url' => ['nullable', 'url:http,https', 'max:2048'],
            'daily_budget_bdt' => ['required', 'integer', 'min:100', 'max:1000000'],
            'age_min' => ['required', 'integer', 'min:18', 'max:65'],
            'age_max' => ['required', 'integer', 'min:18', 'max:65', 'gte:age_min'],
        ]);

        if (filled($data['source_reference'] ?? null)) {
            [$sourceType, $sourceId] = explode(':', $data['source_reference'], 2);
            $source = match ($sourceType) {
                'course' => Course::query()->where('status', 'published')->findOrFail((int) $sourceId),
                'notice' => DashboardAnnouncement::currentlyActive()->where('audience', 'all')->whereNull('branch_id')->findOrFail((int) $sourceId),
                'offer' => FlashSale::query()->where('status', 'active')->where('starts_at', '<=', now())->where('ends_at', '>=', now())->findOrFail((int) $sourceId),
            };

            $sourceMessage = match ($sourceType) {
                'course' => (string) ($source->short_description ?: $source->title),
                'notice' => (string) ($source->body ?: $source->title),
                'offer' => (string) ($source->description ?: $source->title),
            };
            $sourceUrl = match ($sourceType) {
                'course' => route('courses.show', $source),
                'notice' => (is_string($source->button_url) && filter_var($source->button_url, FILTER_VALIDATE_URL) && in_array(parse_url($source->button_url, PHP_URL_SCHEME), ['http', 'https'], true))
                    ? $source->button_url : route('notices.index'),
                'offer' => route('flash-sales.public'),
            };
            $imagePath = match ($sourceType) {
                'course' => $source->image_path,
                'offer' => $source->banner_path,
                default => null,
            };
            $data['name'] = filled($data['name'] ?? null) ? $data['name'] : $source->title.' - Meta Boost';
            $data['message'] = filled($data['message'] ?? null) ? $data['message'] : mb_substr(trim(strip_tags($sourceMessage)), 0, 2000);
            $data['destination_url'] = filled($data['destination_url'] ?? null) ? $data['destination_url'] : $sourceUrl;
            if (! filled($data['image_url'] ?? null) && filled($imagePath)) {
                $data['image_url'] = url(Storage::disk('public')->url($imagePath));
            }
        }

        Validator::make($data, [
            'name' => ['required', 'string', 'max:180'],
            'message' => ['required', 'string', 'max:2000'],
            'destination_url' => ['required', 'url:http,https', 'max:2048'],
            'image_url' => ['required', 'url:http,https', 'max:2048'],
        ])->validate();

        $token = $this->accessToken();
        abort_unless($token, 409, __('Connect Meta Ads first.'));

        $accountId = $data['account_id'];
        $redirect = ['account' => $accountId];

        try {
            $accounts = $this->graphGet('me/adaccounts', $token, [
                'fields' => 'id,account_id,currency',
                'limit' => 100,
            ])['data'] ?? [];
            $account = collect($accounts)->first(function (array $item) use ($accountId): bool {
                $id = preg_replace('/^act_/', '', (string) ($item['account_id'] ?? $item['id'] ?? ''));

                return $id === $accountId;
            });
            abort_unless($account, 403, __('This ad account is not accessible to the connected Meta user.'));
            abort_unless(strtoupper((string) ($account['currency'] ?? '')) === 'BDT', 422, __('Choose a BDT ad account. This form accepts budgets in Bangladeshi Taka only.'));

            $pages = $this->graphGet('me/accounts', $token, [
                'fields' => 'id,name,tasks',
                'limit' => 100,
            ])['data'] ?? [];
            $page = collect($pages)->first(fn (array $item) => (string) ($item['id'] ?? '') === $data['page_id']);
            abort_unless($page, 403, __('This Facebook Page is not accessible to the connected Meta user.'));
            abort_unless(in_array('ADVERTISE', $page['tasks'] ?? [], true), 403, __('The connected Meta user needs the Advertise task on this Facebook Page.'));

            // Meta expects monetary values in the account currency's minor unit (poisha for BDT).
            $budgetInPoisha = (int) $data['daily_budget_bdt'] * 100;
            $accountPath = 'act_'.$accountId;

            $campaign = Http::withToken($token)->asForm()->acceptJson()
                ->post('https://graph.facebook.com/'.self::GRAPH_VERSION.'/'.$accountPath.'/campaigns', [
                    'name' => $data['name'],
                    'objective' => 'OUTCOME_TRAFFIC',
                    'status' => 'PAUSED',
                    'special_ad_categories' => '[]',
                ])->throw()->json();
            $campaignId = (string) ($campaign['id'] ?? '');
            abort_if($campaignId === '', 502, __('Meta did not return a campaign ID.'));

            $targeting = [
                'geo_locations' => ['countries' => ['BD']],
                'age_min' => (int) $data['age_min'],
                'age_max' => (int) $data['age_max'],
                'publisher_platforms' => ['facebook', 'instagram'],
                'facebook_positions' => ['feed'],
                'instagram_positions' => ['stream'],
            ];

            $adSet = Http::withToken($token)->asForm()->acceptJson()
                ->post('https://graph.facebook.com/'.self::GRAPH_VERSION.'/'.$accountPath.'/adsets', [
                    'name' => $data['name'].' - Bangladesh audience',
                    'campaign_id' => $campaignId,
                    'daily_budget' => $budgetInPoisha,
                    'billing_event' => 'IMPRESSIONS',
                    'optimization_goal' => 'LINK_CLICKS',
                    'bid_strategy' => 'LOWEST_COST_WITHOUT_CAP',
                    'destination_type' => 'WEBSITE',
                    'targeting' => json_encode($targeting, JSON_THROW_ON_ERROR),
                    'status' => 'PAUSED',
                ])->throw()->json();
            $adSetId = (string) ($adSet['id'] ?? '');
            abort_if($adSetId === '', 502, __('Meta did not return an ad set ID.'));

            $story = [
                'page_id' => $data['page_id'],
                'link_data' => [
                    'link' => $data['destination_url'],
                    'message' => $data['message'],
                    'image_url' => $data['image_url'],
                    'call_to_action' => [
                        'type' => 'LEARN_MORE',
                        'value' => ['link' => $data['destination_url']],
                    ],
                ],
            ];
            $creative = Http::withToken($token)->asForm()->acceptJson()
                ->post('https://graph.facebook.com/'.self::GRAPH_VERSION.'/'.$accountPath.'/adcreatives', [
                    'name' => $data['name'].' - creative',
                    'object_story_spec' => json_encode($story, JSON_THROW_ON_ERROR),
                ])->throw()->json();
            $creativeId = (string) ($creative['id'] ?? '');
            abort_if($creativeId === '', 502, __('Meta did not return an ad creative ID.'));

            $ad = Http::withToken($token)->asForm()->acceptJson()
                ->post('https://graph.facebook.com/'.self::GRAPH_VERSION.'/'.$accountPath.'/ads', [
                    'name' => $data['name'].' - ad',
                    'adset_id' => $adSetId,
                    'creative' => json_encode(['creative_id' => $creativeId], JSON_THROW_ON_ERROR),
                    'status' => 'PAUSED',
                ])->throw()->json();
            $adId = (string) ($ad['id'] ?? '');
            abort_if($adId === '', 502, __('Meta did not return an ad ID.'));

            \App\Models\MetaBoostCampaign::create([
                'title' => $data['name'],
                'destination_url' => $data['destination_url'],
                'objective' => 'traffic',
                'audience_description' => __('Age :min–:max, Facebook and Instagram feeds', ['min' => $data['age_min'], 'max' => $data['age_max']]),
                'target_location' => 'Bangladesh',
                'daily_budget_bdt' => $data['daily_budget_bdt'],
                'status' => 'paused',
                'meta_campaign_id' => $campaignId,
                'meta_adset_id' => $adSetId,
                'meta_creative_id' => $creativeId,
                'meta_ad_id' => $adId,
                'meta_page_id' => $data['page_id'],
                'created_by' => $request->user()->id,
                'notes' => __('Created from LMS. Campaign, ad set and ad are all PAUSED pending explicit approval.'),
            ]);

            return redirect()->route('admin.marketing.meta-ads.index', $redirect)
                ->with('success', __('Meta campaign, ad set and ad created in PAUSED mode. Review the details before resuming; no ad is delivering now.'));
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('admin.marketing.meta-ads.index', $redirect)
                ->with('error', __('Meta could not finish creating the paused campaign. Check the account currency, Page permissions, image URL, budget minimum and Meta App Review status. Any objects already created remain paused.'));
        }
    }

    private function accessToken(): ?string
    {
        $encrypted = Setting::value('meta_ads_access_token');
        if (! filled($encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString((string) $encrypted);
        } catch (Throwable) {
            return null;
        }
    }

    private function graphGet(string $path, string $token, array $query = []): array
    {
        return Http::withToken($token)->acceptJson()->get('https://graph.facebook.com/'.self::GRAPH_VERSION.'/'.$path, $query)->throw()->json() ?? [];
    }
}
