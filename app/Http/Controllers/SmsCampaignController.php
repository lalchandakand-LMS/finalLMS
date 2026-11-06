<?php

namespace App\Http\Controllers;

use App\Jobs\SendSmsCampaignRecipient;
use App\Models\SmsCampaign;
use App\Models\SmsCampaignRecipient;
use App\Models\User;
use App\Services\SmsGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SmsCampaignController extends Controller
{
    public function index(): View
    {
        $campaigns = SmsCampaign::with('creator')->latest()->paginate(20);
        $eligibleCount = User::whereNotNull('phone_number')
            ->whereNotNull('phone_verified_at')
            ->where('sms_marketing_opt_in', true)
            ->count();

        return view('admin.sms.index', compact('campaigns', 'eligibleCount'));
    }

    public function store(Request $request, SmsGateway $sms): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:1500'],
            'audience' => ['required', 'in:all,student,teacher'],
        ]);

        $users = User::query()
            ->whereNotNull('phone_number')
            ->whereNotNull('phone_verified_at')
            ->where('sms_marketing_opt_in', true);

        if ($validated['audience'] !== 'all') {
            $users->where('role', $validated['audience']);
        }

        $recipientCount = (clone $users)->count();
        if ($recipientCount === 0) {
            return back()->with('error', 'No opted-in, phone-verified recipients match this audience.');
        }

        if (! $sms->isConfigured()) {
            return back()->with('error', 'Configure Twilio credentials in the server environment before creating a campaign.');
        }

        $campaign = DB::transaction(function () use ($validated, $users, $recipientCount): SmsCampaign {
            $campaign = SmsCampaign::create([
                'created_by' => auth()->id(),
                'title' => $validated['title'],
                'message' => $validated['message'],
                'audience' => $validated['audience'],
                'status' => 'queued',
                'recipient_count' => $recipientCount,
            ]);

            (clone $users)->select('id')->chunkById(500, function ($recipients) use ($campaign): void {
                $now = now();
                SmsCampaignRecipient::insert($recipients->map(fn (User $user) => [
                    'sms_campaign_id' => $campaign->id,
                    'user_id' => $user->id,
                    'status' => 'pending',
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            });

            return $campaign;
        });

        $campaign->recipients()->select('id')->chunkById(100, function ($recipients): void {
            foreach ($recipients as $recipient) {
                SendSmsCampaignRecipient::dispatch($recipient->id);
            }
        });

        return redirect()->route('admin.sms-campaigns.index')->with('success', 'SMS campaign queued for eligible recipients.');
    }
}