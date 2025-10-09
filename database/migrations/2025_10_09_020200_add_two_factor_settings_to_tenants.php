<?php

use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Tenant::withoutGlobalScopes()->each(function (Tenant $tenant): void {
            $settings = $tenant->settings ?? [];

            $twoFactor = $settings['security']['two_factor'] ?? [];

            if (! array_key_exists('required_roles', $twoFactor)) {
                $twoFactor['required_roles'] = ['Admin', 'Agent'];
            }

            if (! array_key_exists('enforced', $twoFactor)) {
                $twoFactor['enforced'] = true;
            }

            if (! array_key_exists('session_ttl_minutes', $twoFactor)) {
                $twoFactor['session_ttl_minutes'] = 30;
            }

            $settings['security']['two_factor'] = $twoFactor;

            $tenant->forceFill(['settings' => $settings])->saveQuietly();
        });
    }

    public function down(): void
    {
        Tenant::withoutGlobalScopes()->each(function (Tenant $tenant): void {
            $settings = $tenant->settings ?? [];

            if (! isset($settings['security']['two_factor'])) {
                return;
            }

            unset($settings['security']['two_factor']);

            if (empty($settings['security'])) {
                unset($settings['security']);
            }

            $tenant->forceFill(['settings' => $settings])->saveQuietly();
        });
    }
};
