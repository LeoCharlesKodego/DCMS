<?php

namespace App\Http\Controllers;

use App\Services\Phase4BSettingService;
use App\Services\AuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Phase4BSettingsController extends Controller
{
    public function show(Request $request, Phase4BSettingService $settings, AuthorizationService $authorization): JsonResponse
    {
        abort_unless($request->user()->is_system_admin || $authorization->allows($request->user(), 'document.submission.verify'), 403, 'KMT verification or system configuration authority is required.');
        $keys = $request->user()->is_system_admin ? ['same_kmt_verify_release', 'title_size_pt'] : ['title_size_pt'];
        $history = DB::table('phase4b_setting_history')->join('users', 'users.id', '=', 'phase4b_setting_history.changed_by_user_id')
            ->whereIn('key', $keys)->orderByDesc('changed_at')->limit(100)
            ->get(['key', 'old_value', 'new_value', 'users.name as changed_by', 'changed_at']);
        return response()->json([
            'same_kmt_verify_release' => $request->user()->is_system_admin ? $settings->get('same_kmt_verify_release') === '1' : null,
            'title_size_pt' => (int) $settings->get('title_size_pt'),
            'history' => $history,
        ]);
    }

    public function updateSameAccount(Request $request, Phase4BSettingService $settings): JsonResponse
    {
        abort_unless($request->user()->is_system_admin, 403, 'System administrator access is required for this system setting.');
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        $settings->set('same_kmt_verify_release', $data['enabled'] ? '1' : '0', $request->user());
        return response()->json(['same_kmt_verify_release' => $data['enabled']]);
    }

    public function updateTitleSize(Request $request, Phase4BSettingService $settings, AuthorizationService $authorization): JsonResponse
    {
        abort_unless($authorization->allows($request->user(), 'document.submission.verify'), 403, 'KMT submission verification permission is required.');
        $data = $request->validate(['title_size_pt' => ['required', 'integer', 'min:1']]);
        $settings->set('title_size_pt', (string) $data['title_size_pt'], $request->user());
        return response()->json(['title_size_pt' => (int) $data['title_size_pt']]);
    }
}
