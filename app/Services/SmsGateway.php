<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class SmsGateway
{
    public function isConfigured(): bool
    {
        return (bool) (config('services.twilio.sid') && config('services.twilio.token') && config('services.twilio.from'));
    }

    public function send(string $to, string $message): void
    {
        $sid = config('services.twilio.sid');
        $token = config('services.twilio.token');
        $from = config('services.twilio.from');

        if (! $this->isConfigured()) {
            throw new RuntimeException('Twilio SMS is not configured.');
        }

        Http::asForm()
            ->withBasicAuth($sid, $token)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'To' => $to,
                'From' => $from,
                'Body' => $message,
            ])
            ->throw();
    }
}