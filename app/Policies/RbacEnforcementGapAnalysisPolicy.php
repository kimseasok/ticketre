<?php

namespace App\Policies;

use App\Models\RbacEnforcementGapAnalysis;
use App\Models\User;

class RbacEnforcementGapAnalysisPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('security.rbac_gaps.view');
    }

    public function view(User $user, RbacEnforcementGapAnalysis $analysis): bool
    {
        return $user->can('security.rbac_gaps.view')
            && $this->sameTenant($user, $analysis)
            && $this->brandAccessible($user, $analysis);
    }

    public function create(User $user): bool
    {
        return $user->can('security.rbac_gaps.manage');
    }

    public function update(User $user, RbacEnforcementGapAnalysis $analysis): bool
    {
        return $user->can('security.rbac_gaps.manage')
            && $this->sameTenant($user, $analysis)
            && $this->brandAccessible($user, $analysis);
    }

    public function delete(User $user, RbacEnforcementGapAnalysis $analysis): bool
    {
        return $user->can('security.rbac_gaps.manage')
            && $this->sameTenant($user, $analysis)
            && $this->brandAccessible($user, $analysis);
    }

    protected function sameTenant(User $user, RbacEnforcementGapAnalysis $analysis): bool
    {
        return (int) $user->tenant_id === (int) $analysis->tenant_id;
    }

    protected function brandAccessible(User $user, RbacEnforcementGapAnalysis $analysis): bool
    {
        if ($analysis->brand_id === null) {
            return true;
        }

        if ($user->brand_id === null) {
            return $user->hasRole('Admin');
        }

        return (int) $user->brand_id === (int) $analysis->brand_id;
    }
}
