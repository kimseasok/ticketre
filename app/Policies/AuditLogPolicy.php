<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;

class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('audit_logs.view');
    }

    public function view(User $user, AuditLog $log): bool
    {
        return $user->can('audit_logs.view');
    }
}
