<?php

namespace App\Actions\Business;

use App\Enums\BusinessRole;
use App\Models\User;
use App\Modules\Branch\Actions\EnsurePrimaryBranchAction;
use App\Modules\Business\Models\Business;
use App\Modules\Menu\Actions\EnsurePrimaryMenuAction;
use App\Modules\Subscription\Actions\StartTrialAction;
use App\Modules\Subscription\Models\Plan;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateBusinessAction
{
    public function __construct(
        private readonly StartTrialAction $startTrialAction,
        private readonly EnsurePrimaryMenuAction $ensurePrimaryMenuAction,
        private readonly EnsurePrimaryBranchAction $ensurePrimaryBranchAction,
    ) {}

    /**
     * Create a business, its owner membership, and its selected plan trial atomically.
     *
     * @param  array{name: string, plan_code: string}  $data
     */
    public function execute(User $user, array $data): Business
    {
        $name = trim($data['name']);

        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => 'The business name is required.',
            ]);
        }

        $plan = Plan::query()
            ->where('code', $data['plan_code'])
            ->where('is_active', true)
            ->first();

        if ($plan === null) {
            throw ValidationException::withMessages([
                'plan_code' => 'The selected plan is invalid or inactive.',
            ]);
        }

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                return DB::transaction(function () use ($user, $name, $plan): Business {
                    $business = Business::query()->create([
                        'name' => $name,
                        'slug' => $this->generateUniqueSlug($name),
                    ]);

                    $business->members()->create([
                        'user_id' => $user->getKey(),
                        'role' => BusinessRole::Owner,
                    ]);

                    // Keep primary menu, membership and trial creation atomic.
                    $this->ensurePrimaryMenuAction->execute($business);
                    $this->ensurePrimaryBranchAction->execute($business);
                    $this->startTrialAction->execute($business, $plan);

                    return $business;
                });
            } catch (QueryException $exception) {
                if (! $this->isSlugUniqueConstraintViolation($exception) || $attempt === 3) {
                    throw $exception;
                }
            }
        }

        throw new \LogicException('Unable to create the business.');
    }

    private function generateUniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name);

        if ($baseSlug === '') {
            $baseSlug = 'business';
        }

        $slug = $baseSlug;
        $suffix = 2;

        while (Business::query()->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function isSlugUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? (string) $exception->getCode();
        $message = strtolower($exception->getMessage());

        return in_array($sqlState, ['23000', '23505'], true)
            && str_contains($message, 'slug');
    }
}
