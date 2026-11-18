<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportTicketController extends Controller
{
    public function index(Request $request): View
    {
        $query = SupportTicket::query()->with('user:id,name,role')->latest();

        if (!$request->user()->isAdmin()) {
            $query->where('user_id', $request->user()->id);
        }

        $tickets = $query->paginate(20);

        return view('support.index', compact('tickets'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'min:4', 'max:180'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $text = mb_strtolower($data['subject'].' '.$data['description']);
        [$category, $priority, $diagnosis] = $this->diagnose($text);

        $ticket = SupportTicket::create([
            'user_id' => $request->user()->id,
            'subject' => trim($data['subject']),
            'description' => trim($data['description']),
            'category' => $category,
            'priority' => $priority,
            'status' => 'open',
            'diagnostic_summary' => $diagnosis,
        ]);

        return redirect()->route('support.index')->with('success', 'Support request created. Initial diagnostic suggestions are ready. Ticket #'.$ticket->id);
    }

    public function update(Request $request, SupportTicket $ticket): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'status' => ['required', 'in:open,in_progress,waiting,resolved,closed'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $ticket->update($data);

        return back()->with('success', 'Support ticket updated.');
    }

    private function diagnose(string $text): array
    {
        $rules = [
            'payment' => ['payment', 'bkash', 'nagad', 'পেমেন্ট', 'বিকাশ', 'নগদ'],
            'login' => ['login', 'password', 'লগইন', 'পাসওয়ার্ড', 'পাসওয়ার্ড'],
            'video' => ['video', 'lesson', 'play', 'ভিডিও', 'লেসন', 'ক্লাস'],
            'course' => ['course', 'enroll', 'batch', 'কোর্স', 'ভর্তি', 'ব্যাচ'],
            'technical' => ['error', 'bug', 'crash', 'slow', 'ত্রুটি', 'সমস্যা'],
        ];

        $category = 'general';
        foreach ($rules as $name => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($text, mb_strtolower($keyword))) {
                    $category = $name;
                    break 2;
                }
            }
        }

        $priority = $category === 'payment' ? 'high' : 'normal';
        $suggestions = [
            'payment' => 'Check the payment reference and transaction status. Do not share PIN, OTP, or passwords. A staff member must verify gateway records.',
            'login' => 'Confirm the registered email, check email verification, and use the password reset flow. Never send your password to support.',
            'video' => 'Check your internet connection, reload the lesson, and try an updated browser. Staff should verify lesson access and media availability.',
            'course' => 'Check your enrollment approval and batch assignment. Contact the branch administrator if the course is missing.',
            'technical' => 'Reload the page, record the exact error and page URL, and retry once. Staff should inspect sanitized application logs.',
            'general' => 'A support member will review this request. Include the page name and exact steps to reproduce the issue; do not include secrets.',
        ];

        return [$category, $priority, $suggestions[$category]];
    }
}
