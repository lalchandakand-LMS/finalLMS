<?php

namespace App\Jobs;

use App\Models\SmsCampaign;
use App\Models\SmsCampaignRecipient;
use App\Services\Modules\ModuleRegistry;
use App\Services\SmsGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendSmsCampaignRecipient implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120];

    public function __construct(public int $recipientId)
    {
    }

    public function handle(ModuleRegistry $modules, SmsGateway $sms): void
    {
        $campaignId = DB::transaction(function () use ($modules, $sms): ?int {
            // Serialize all deliveries for this recipient. A duplicate queued job
            // must wait, then observe the committed sent/skipped/failed state.
            $recipient = SmsCampaignRecipient::query()
                ->whereKey($this->recipientId)
                ->lockForUpdate()
                ->first();

            if (! $recipient) {
                return null;
            }

            $recipient->load(['campaign', 'user']);
            $campaign = $recipient->campaign;

            if ($recipient->status !== 'pending' || ! $campaign) {
                return $campaign?->id;
            }

            // Keep the recipient row lock until the provider response and local
            // status/counter updates commit, preventing concurrent duplicate sends.
            if (! $modules->isEnabled('sms')) {
                $recipient->update(['status' => 'skipped']);
                $campaign->increment('skipped_count');

                return $campaign->id;
            }

            $campaign->update(['status' => 'sending']);

            if (
                ! $recipient->user ||
                ! $recipient->user->phone_number ||
                ! $recipient->user->phone_verified_at ||
                ! $recipient->user->sms_marketing_opt_in
            ) {
                $recipient->update(['status' => 'skipped']);
                $campaign->increment('skipped_count');

                return $campaign->id;
            }

            try {
                $sms->send($recipient->user->phone_number, $campaign->message);
            } catch (Throwable $exception) {
                if ($this->attempts() < $this->tries) {
                    // Throwing rolls back the transaction and leaves the row
                    // pending so the queue retry can safely claim it again.
                    throw $exception;
                }

                report($exception);
                $recipient->update(['status' => 'failed', 'error_message' => 'Delivery failed']);
                $campaign->increment('failed_count');

                return $campaign->id;
            }

            $recipient->update(['status' => 'sent', 'sent_at' => now()]);
            $campaign->increment('sent_count');

            return $campaign->id;
        }, 3);

        // Finalize outside the delivery transaction so concurrent deliveries
        // cannot use an older transaction snapshot when checking pending rows.
        if ($campaignId !== null) {
            SmsCampaign::query()
                ->whereKey($campaignId)
                ->whereDoesntHave('recipients', fn ($query) => $query->where('status', 'pending'))
                ->update(['status' => 'completed']);
        }
    }
}
