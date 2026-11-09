<?php

namespace App\Modules\Tenancy\Services;

use App\Modules\Business\Models\Business;

class CurrentBusiness
{
    private ?Business $business = null;

    public function set(Business $business): void
    {
        $this->business = $business;
    }

    public function get(): ?Business
    {
        return $this->business;
    }

    public function has(): bool
    {
        return $this->business !== null;
    }

    public function clear(): void
    {
        $this->business = null;
    }
}
