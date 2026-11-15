<?php

namespace Database\Factories;

use App\Enums\BusinessRole;
use App\Modules\Business\Models\Business;
use App\Modules\Business\Models\BusinessMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessMember>
 */
class BusinessMemberFactory extends Factory
{
    protected $model = BusinessMember::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'user_id' => User::factory(),
            'role' => BusinessRole::Owner,
        ];
    }
}
