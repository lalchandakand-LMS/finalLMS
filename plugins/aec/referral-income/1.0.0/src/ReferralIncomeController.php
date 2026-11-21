<?php

declare(strict_types=1);

namespace Aloms\ReferralIncome;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class ReferralIncomeController extends Controller
{
    public function dashboard(Request $request, ReferralIncomeService $service): View
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['student', 'teacher'], true), 403);
        $code = $service->createOrGetCode($user);
        $pendingCode = $request->session()->pull('aec_referral_code');
        if (is_string($pendingCode) && $pendingCode !== '') {
            try { $service->applyCode($user, $pendingCode); }
            catch (\Illuminate\Validation\ValidationException) { /* Keep existing account attribution unchanged. */ }
        }
        $commissions = DB::table('referral_commissions')->where('referrer_user_id', $user->id)->latest('id')->paginate(20);
        $payouts = DB::table('referral_payout_requests')->where('user_id', $user->id)->latest('id')->limit(10)->get();
        $stats = DB::table('referral_commissions')->where('referrer_user_id', $user->id)
            ->selectRaw("SUM(CASE WHEN status = 'pending' THEN commission_amount ELSE 0 END) as pending")
            ->selectRaw("SUM(CASE WHEN status = 'approved' THEN commission_amount ELSE 0 END) as available")
            ->selectRaw("SUM(CASE WHEN status = 'paid' THEN commission_amount ELSE 0 END) as paid")->first();
        $referralCount = DB::table('referral_attributions')->where('referrer_user_id', $user->id)->where('status', 'active')->count();
        return view('referral-income::dashboard', compact('code', 'commissions', 'payouts', 'stats', 'referralCount'));
    }

    public function track(string $code, Request $request): RedirectResponse
    {
        $valid = DB::table('referral_codes')->where('code', strtoupper($code))->where('is_active', true)->exists();
        if (!$valid) return redirect('/register')->withErrors(['referral_code' => 'This referral link is invalid or inactive.']);
        $request->session()->put('aec_referral_code', strtoupper($code));
        return redirect('/register')->with('status', 'Referral code saved. Sign in or register to apply it.');
    }

    public function apply(Request $request, ReferralIncomeService $service): RedirectResponse
    {
        $data = $request->validate(['referral_code' => ['required', 'string', 'alpha_num', 'min:6', 'max:32']]);
        $service->applyCode($request->user(), $data['referral_code']);
        return back()->with('success', 'Referral code applied successfully.');
    }

    public function requestPayout(Request $request, ReferralIncomeService $service): RedirectResponse
    {
        $data = $request->validate([
            'method' => ['required', 'in:bkash,nagad,bank'],
            'account_details' => ['required', 'string', 'min:6', 'max:500'],
        ]);
        $service->requestPayout($request->user(), $data['method'], $data['account_details']);
        return back()->with('success', 'Payout request submitted for review.');
    }

    public function adminIndex(): View
    {
        $commissions = DB::table('referral_commissions')->join('users', 'users.id', '=', 'referral_commissions.referrer_user_id')
            ->select('referral_commissions.*', 'users.name as referrer_name')->latest('referral_commissions.id')->paginate(30, ['*'], 'commissions_page');
        $payouts = DB::table('referral_payout_requests')->join('users', 'users.id', '=', 'referral_payout_requests.user_id')
            ->select('referral_payout_requests.*', 'users.name as owner_name')->latest('referral_payout_requests.id')->paginate(30, ['*'], 'payouts_page');
        $payouts->getCollection()->transform(function ($row): object {
            try { $row->account_details = Crypt::decryptString($row->account_details_encrypted); }
            catch (\Throwable) { $row->account_details = '[Unable to decrypt payout destination]'; }
            return $row;
        });
        $rules = DB::table('referral_income_rules')->orderBy('owner_type')->get();
        return view('referral-income::admin', compact('commissions', 'payouts', 'rules'));
    }

    public function updateRules(Request $request, ReferralIncomeService $service): RedirectResponse
    {
        $data = $request->validate([
            'student_commission_percent' => ['required', 'numeric', 'min:0', 'max:50'],
            'teacher_commission_percent' => ['required', 'numeric', 'min:0', 'max:50'],
            'student_minimum_payout' => ['required', 'numeric', 'min:1', 'max:10000000'],
            'teacher_minimum_payout' => ['required', 'numeric', 'min:1', 'max:10000000'],
        ]);
        $service->updateRules((int) $request->user()->id, $data);
        return back()->with('success', 'Referral rules updated.');
    }

    public function reviewCommission(Request $request, int $commission, ReferralIncomeService $service): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:approved,rejected'], 'admin_note' => ['nullable', 'string', 'max:1000', 'required_if:status,rejected']]);
        $service->reviewCommission($commission, (int) $request->user()->id, $data['status'], $data['admin_note'] ?? null);
        return back()->with('success', 'Commission review saved.');
    }

    public function reviewPayout(Request $request, int $payout, ReferralIncomeService $service): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:approved,paid,rejected'], 'admin_note' => ['nullable', 'string', 'max:1000', 'required_if:status,paid']);
        $service->reviewPayout($payout, (int) $request->user()->id, $data['status'], $data['admin_note'] ?? null);
        return back()->with('success', 'Payout review saved.');
    }
}
