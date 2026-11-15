<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class GrantPlatformAdminCommand extends Command
{
    protected $signature = 'platform-admin:grant {email : Email address of the platform owner}';

    protected $description = 'Grant the single platform administrator role to a user';

    public function handle(): int
    {
        $user = User::query()
            ->where('email', (string) $this->argument('email'))
            ->first();

        if ($user === null) {
            $this->error('No user found with that email address.');

            return self::FAILURE;
        }

        $existingAdmin = User::query()
            ->where('is_platform_admin', true)
            ->first();

        if ($existingAdmin !== null && $existingAdmin->isNot($user)) {
            $this->error('A platform administrator is already configured.');

            return self::FAILURE;
        }

        $user->forceFill(['is_platform_admin' => true])->save();

        $this->info('Platform administrator access granted.');

        return self::SUCCESS;
    }
}
