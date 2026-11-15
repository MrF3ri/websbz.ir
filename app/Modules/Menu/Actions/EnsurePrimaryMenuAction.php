<?php

namespace App\Modules\Menu\Actions;

use App\Modules\Business\Models\Business;
use App\Modules\Menu\Enums\MenuStatus;
use App\Modules\Menu\Models\Menu;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class EnsurePrimaryMenuAction
{
    public function execute(Business $business): Menu
    {
        $existing = $business->menu()->first();

        if ($existing instanceof Menu) {
            return $existing;
        }

        try {
            return DB::transaction(fn (): Menu => Menu::query()->create([
                'business_id' => $business->getKey(),
                'title' => 'منوی اصلی',
                'slug' => 'main',
                'status' => MenuStatus::Draft,
                'default_locale' => 'fa',
            ]));
        } catch (QueryException $exception) {
            $menu = $business->menu()->first();

            if ($menu instanceof Menu) {
                return $menu;
            }

            throw $exception;
        }
    }
}
