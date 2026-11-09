<?php

namespace App\Modules\Business\Policies;

use App\Models\User;
use App\Modules\Business\Models\Business;

class BusinessPolicy
{
    public function view(User $user, Business $business): bool
    {
        return $business->users()
            ->whereKey($user->getKey())
            ->exists();
    }

    public function update(User $user, Business $business): bool
    {
        return $business->users()
            ->whereKey($user->getKey())
            ->exists();
    }

    public function delete(User $user, Business $business): bool
    {
        return $business->users()
            ->whereKey($user->getKey())
            ->exists();
    }
}
