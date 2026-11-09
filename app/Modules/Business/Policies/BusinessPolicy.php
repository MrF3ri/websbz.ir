<?php

namespace App\Modules\Business\Policies;

use App\Enums\BusinessRole;
use App\Models\User;
use App\Modules\Business\Models\Business;

class BusinessPolicy
{
    public function view(User $user, Business $business): bool
    {
        return $business->members()
            ->where('user_id', $user->getKey())
            ->whereIn('role', [
                BusinessRole::Owner->value,
                BusinessRole::Admin->value,
                BusinessRole::Manager->value,
            ])
            ->exists();
    }

    public function update(User $user, Business $business): bool
    {
        return $business->members()
            ->where('user_id', $user->getKey())
            ->whereIn('role', [
                BusinessRole::Owner->value,
                BusinessRole::Admin->value,
            ])
            ->exists();
    }

    public function delete(User $user, Business $business): bool
    {
        return $business->members()
            ->where('user_id', $user->getKey())
            ->where('role', BusinessRole::Owner->value)
            ->exists();
    }
}
