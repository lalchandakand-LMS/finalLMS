<?php

namespace App\Http\Controllers;

use App\Models\MetaBoostCampaign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MetaBoostCampaignController extends Controller
{
    public function index(): View
    {
        $campaigns = MetaBoostCampaign::with('creator')->latest()->paginate(20);

        return view('admin.marketing.boost-campaigns', compact('campaigns'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'post_url' => ['nullable', 'url:http,https', 'max:2048'],
            'destination_url' => ['required', 'url:http,https', 'max:2048'],
            'objective' => ['required', Rule::in(['awareness', 'traffic', 'engagement', 'leads', 'sales'])],
            'audience_description' => ['nullable', 'string', 'max:2000'],
            'target_location' => ['nullable', 'string', 'max:160'],
            'daily_budget_bdt' => ['required', 'numeric', 'min:1', 'max:100000000'],
            'total_budget_bdt' => ['nullable', 'numeric', 'min:1', 'max:1000000000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $data['created_by'] = $request->user()->id;
        $data['status'] = 'draft';

        MetaBoostCampaign::create($data);

        return redirect()->route('admin.marketing.boost-campaigns.index')
            ->with('success', __('Boost plan saved as a draft. No ad has been published or charged.'));
    }

    public function update(Request $request, MetaBoostCampaign $campaign): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['draft', 'planned', 'running', 'paused', 'completed'])],
            'meta_campaign_id' => ['nullable', 'string', 'max:120'],
            'actual_spend_bdt' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            'impressions' => ['required', 'integer', 'min:0'],
            'clicks' => ['required', 'integer', 'min:0'],
            'leads' => ['required', 'integer', 'min:0'],
            'conversions' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $campaign->update($data);

        return back()->with('success', __('Boost plan and manually reported results updated.'));
    }

    public function destroy(MetaBoostCampaign $campaign): RedirectResponse
    {
        abort_unless($campaign->status === 'draft', 422);

        $campaign->delete();

        return back()->with('success', __('Draft boost plan deleted.'));
    }
}
