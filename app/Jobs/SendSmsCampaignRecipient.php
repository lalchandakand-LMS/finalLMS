<?php

namespace App\Jobs;

use App\Models\SmsCampaignRecipient;
use App\Services\SmsGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
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

    public function handle(SmsGateway $sms): void
    {
        $recipient = SmsCampaignRecipient::with(['campaign', 'user'])->find($this->recipientId);

        if (! $recipient || $recipient->status !== 'pending' || ! $recipient->campaign) {
            return;
        }

        $campaign = $recipient->campaign;
        $campaign->update(['status' => 'sending']);

        if (
            ! $recipient->user ||
            ! $recipient->user->phone_number ||
            ! $recipient->user->phone_verified_at ||
            ! $recipient->user->sms_marketing_opt_in
        ) {
            $recipient->update(['status' => 'skipped']);
            $campaign->increment('skipped_count');
            $this->finishIfComplete($campaign);

            return;
        }

        try {
            $sms->send($recipient->user->phone_number, $campaign->message);
            $recipient->update(['status' => 'sent', 'sent_at' => now()]);
            $campaign->increment('sent_count');
        } catch (Throwable $exception) {
            if ($this->attempts() < $this->tries) {
                throw $exception;
            }

            report($exception);
            $recipient->update(['status' => 'failed', 'error_message' => 'Delivery failed']);
            $campaign->increment('failed_count');
        }

        $this->finishIfComplete($campaign);
    }

    private function finishIfComplete($campaign): void
    {
        if (! $campaign->recipients()->where('status', 'pending')->exists()) {
            $campaign->update(['status' => 'completed']);
        }
    }
}