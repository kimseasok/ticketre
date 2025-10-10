<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\RbacEnforcementGapAnalysis;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RbacEnforcementGapAnalysis>
 */
class RbacEnforcementGapAnalysisFactory extends Factory
{
    protected $model = RbacEnforcementGapAnalysis::class;

    public function definition(): array
    {
        $title = 'RBAC Gap Review '.$this->faker->unique()->company();

        return [
            'tenant_id' => Tenant::factory(),
            'brand_id' => Brand::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.$this->faker->numberBetween(100, 999),
            'status' => $this->faker->randomElement(RbacEnforcementGapAnalysis::STATUSES),
            'analysis_date' => now()->subDays($this->faker->numberBetween(0, 7)),
            'audit_matrix' => [
                [
                    'type' => 'route',
                    'identifier' => 'GET /api/v1/tickets',
                    'required_permissions' => ['tickets.view'],
                    'roles' => ['Admin', 'Agent'],
                    'notes' => 'NON-PRODUCTION sample entry.',
                ],
                [
                    'type' => 'command',
                    'identifier' => 'queue:work',
                    'required_permissions' => ['tickets.manage'],
                    'roles' => ['Admin'],
                    'notes' => null,
                ],
            ],
            'findings' => [
                [
                    'priority' => 'high',
                    'summary' => 'Portal submission lacks ability middleware.',
                    'owner' => 'Security Engineering',
                    'eta_days' => 14,
                    'status' => 'open',
                ],
                [
                    'priority' => 'medium',
                    'summary' => 'Queue worker role assignment missing review.',
                    'owner' => 'Platform Ops',
                    'eta_days' => 30,
                    'status' => 'planned',
                ],
            ],
            'remediation_plan' => [
                'milestone_one' => [
                    'name' => 'Implement portal middleware',
                    'due_days' => 14,
                ],
                'milestone_two' => [
                    'name' => 'Publish queue access SOP',
                    'due_days' => 30,
                ],
            ],
            'review_minutes' => 'NON-PRODUCTION recap of the RBAC remediation meeting and target deadlines.',
            'notes' => 'NON-PRODUCTION data for demo/testing purposes only.',
            'owner_team' => 'Trust & Safety',
            'reference_id' => Str::uuid()->toString(),
        ];
    }

    public function unscopedBrand(): self
    {
        return $this->state(fn () => ['brand_id' => null]);
    }
}
