<?php

namespace App\Actions\Business;

use App\Enums\BusinessRole;
use App\Models\User;
use App\Modules\Business\Models\Business;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateBusinessAction
{
    /**
     * Create a business and assign its creator the owner role.
     *
     * @param  array{name: string}  $data
     */
    public function execute(User $user, array $data): Business
    {
        $name = trim($data['name'] ?? '');

        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => 'The business name is required.',
            ]);
        }

        // Retry only when concurrent requests claim the same globally unique slug.
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                return DB::transaction(function () use ($user, $name): Business {
                    $business = Business::query()->create([
                        'name' => $name,
                        'slug' => $this->generateUniqueSlug($name),
                    ]);

                    $business->members()->create([
                        'user_id' => $user->getKey(),
                        'role' => BusinessRole::Owner,
                    ]);

                    return $business;
                });
            } catch (QueryException $exception) {
                if (! $this->isSlugUniqueConstraintViolation($exception) || $attempt === 3) {
                    throw $exception;
                }
            }
        }

        // The loop either returns a Business or rethrows the database exception.
        throw new \LogicException('Unable to create the business.');
    }

    /**
     * Generate an available slug, using a numeric suffix when necessary.
     */
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

    /**
     * Determine whether a query exception was caused by the unique slug index.
     */
    private function isSlugUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? (string) $exception->getCode();
        $message = strtolower($exception->getMessage());

        return in_array($sqlState, ['23000', '23505'], true)
            && str_contains($message, 'slug');
    }
}
