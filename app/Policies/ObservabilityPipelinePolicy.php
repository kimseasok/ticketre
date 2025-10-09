<?php

namespace App\Policies;

use App\Models\ObservabilityPipeline;
use App\Models\User;

class ObservabilityPipelinePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('observability.pipelines.view');
    }

    public function view(User $user, ObservabilityPipeline $pipeline): bool
    {
        return $user->can('observability.pipelines.view')
            && $this->sameTenant($user, $pipeline)
            && $this->brandAccessible($user, $pipeline);
    }

    public function create(User $user): bool
    {
        return $user->can('observability.pipelines.manage');
    }

    public function update(User $user, ObservabilityPipeline $pipeline): bool
    {
        return $user->can('observability.pipelines.manage')
            && $this->sameTenant($user, $pipeline)
            && $this->brandAccessible($user, $pipeline);
    }

    public function delete(User $user, ObservabilityPipeline $pipeline): bool
    {
        return $user->can('observability.pipelines.manage')
            && $this->sameTenant($user, $pipeline)
            && $this->brandAccessible($user, $pipeline);
    }

    protected function sameTenant(User $user, ObservabilityPipeline $pipeline): bool
    {
        return (int) $user->tenant_id === (int) $pipeline->tenant_id;
    }

    protected function brandAccessible(User $user, ObservabilityPipeline $pipeline): bool
    {
        if ($pipeline->brand_id === null) {
            return true;
        }

        if ($user->brand_id === null) {
            return $user->hasRole('Admin');
        }

        return (int) $user->brand_id === (int) $pipeline->brand_id;
    }
}
