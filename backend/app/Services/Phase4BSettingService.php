<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class Phase4BSettingService
{
    public function __construct(private readonly DocumentAuditService $audit) {}
    public function get(string $key): string
    {
        return (string) (DB::table('phase4b_settings')->where('key', $key)->value('value') ?? match ($key) {
            'same_kmt_verify_release' => '0', 'title_size_pt' => '14', default => '',
        });
    }

    public function set(string $key, string $value, User $actor): void
    {
        DB::transaction(function () use ($key, $value, $actor): void {
            $old = DB::table('phase4b_settings')->where('key', $key)->value('value');
            DB::table('phase4b_settings')->updateOrInsert(['key' => $key], [
                'value' => $value, 'changed_by_user_id' => $actor->id, 'updated_at' => now(), 'created_at' => now(),
            ]);
            DB::table('phase4b_setting_history')->insert([
                'key' => $key, 'old_value' => $old, 'new_value' => $value,
                'changed_by_user_id' => $actor->id, 'changed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $settingId = (int) DB::table('phase4b_settings')->where('key', $key)->value('id');
            $this->audit->record('phase4b_setting', $settingId, null, $actor, 'configuration.phase4b_setting_changed', $old, $value, null, ['setting_key' => $key]);
        });
    }
}
