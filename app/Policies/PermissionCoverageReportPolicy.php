<?php

namespace App\Policies;

use App\Models\PermissionCoverageReport;
use App\Models\User;

class PermissionCoverageReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('security.permission_coverage.view');
    }

    public function view(User $user, PermissionCoverageReport $report): bool
    {
        return $user->can('security.permission_coverage.view')
            && $this->sameTenant($user, $report)
            && $this->brandAccessible($user, $report);
    }

    public function create(User $user): bool
    {
        return $user->can('security.permission_coverage.manage');
    }

    public function update(User $user, PermissionCoverageReport $report): bool
    {
        return $user->can('security.permission_coverage.manage')
            && $this->sameTenant($user, $report)
            && $this->brandAccessible($user, $report);
    }

    public function delete(User $user, PermissionCoverageReport $report): bool
    {
        return $user->can('security.permission_coverage.manage')
            && $this->sameTenant($user, $report)
            && $this->brandAccessible($user, $report);
    }

    protected function sameTenant(User $user, PermissionCoverageReport $report): bool
    {
        return (int) $user->tenant_id === (int) $report->tenant_id;
    }

    protected function brandAccessible(User $user, PermissionCoverageReport $report): bool
    {
        if ($report->brand_id === null) {
            return true;
        }

        if ($user->brand_id === null) {
            return $user->hasRole('Admin');
        }

        return (int) $user->brand_id === (int) $report->brand_id;
    }
}
