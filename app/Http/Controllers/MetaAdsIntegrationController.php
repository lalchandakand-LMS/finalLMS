<?php

namespace App\\Http\\Controllers;

use App\\Models\\Setting;
use Illuminate\\Http\\RedirectResponse;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\Crypt;
use Illuminate\\Support\\Facades\\Http;
use Illuminate\\View\\View;
use Throwable;

class MetaAdsIntegrationController extends Controller
{
    private const GRAPH_VERSION = 'v27.0';

    public function index(Request $request): View
    {
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
                    'fields' => 'id,name,account_status,currency,timezone_name',
                    'limit' => 100,
                ])['data'] ?? [];
                $pages = $this->graphGet('me/accounts', $token, [
                    'fields' => 'id,name,category,tasks',
                    'limit' => 100,
                ])['data'] ?? [];

                $allowedAccountIds = collect($accounts)->pluck('id')->map(fn ($id) => (string) $id);
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
                report($exception);
                $error = __('Meta could not return account data. Check the granted permissions and reconnect if needed.');
            }
        }

        return view('admin.marketing.meta-ads', [
            'connected' => $connected,
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
            'scope' => 'ads_read,ads_management,business_management,pages_read_engagement',
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
            report($exception);

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
        $data = $request->validate([
            'account_id' => ['required', 'string', 'regex:/^\\d{5,30}$/'],
        ]);

        $token = $this->accessToken();
        abort_unless($token, 409, __('Connect Meta Ads first.'));

        try {
            $accounts = $this->graphGet('me/adaccounts', $token, ['fields' => 'id', 'limit' => 100])['data'] ?? [];
            $validAccount = collect($accounts)->contains(fn ($account) => (string) ($account['id'] ?? '') === $data['account_id']);
            abort_unless($validAccount, 403, __('This ad account is not accessible to the connected Meta user.'));

            Http::asForm()->acceptJson()->post('https://graph.facebook.com/'.self::GRAPH_VERSION.'/'.$campaignId, [
                'status' => 'PAUSED',
                'access_token' => $token,
            ])->throw();

            return redirect()->route('admin.marketing.meta-ads.index', ['account' => $data['account_id']])->with('success', __('Meta campaign paused.'));
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('admin.marketing.meta-ads.index', ['account' => $data['account_id']])->with('error', __('Meta could not pause this campaign. Check its status and permissions.'));
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
        return Http::acceptJson()->get('https://graph.facebook.com/'.self::GRAPH_VERSION.'/'.$path, [
            ...$query,
            'access_token' => $token,
        ])->throw()->json() ?? [];
    }
}
