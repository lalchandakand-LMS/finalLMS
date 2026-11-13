<?php

namespace App\Http\Controllers;

use App\Services\CPanelQuotaService;
use Illuminate\View\View;

class StorageManagerController extends Controller
{
    public function index(CPanelQuotaService $cpanelQuota): View
    {
        return view('admin.storage.index', [
            'cpanelQuota' => $cpanelQuota->getAccountQuota(),
            'configuredLocalQuotaBytes' => (int) config('storage-manager.local_quota_bytes'),
            'warningThresholds' => config('storage-manager.warning_thresholds', [70, 85, 95]),
        ]);
    }
}
