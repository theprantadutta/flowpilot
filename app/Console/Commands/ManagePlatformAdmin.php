<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Platform administration is granted from the server, never from the web
 * app, so nobody can promote themselves through a request.
 */
#[Signature('platform:admin {email : The account to change} {--revoke : Take platform administration away instead}')]
#[Description('Grant or revoke FlowPilot platform administration for an account')]
class ManagePlatformAdmin extends Command
{
    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->components->error("No account uses {$email}.");

            return self::FAILURE;
        }

        $grant = ! $this->option('revoke');

        if ($user->is_platform_admin === $grant) {
            $this->components->info($grant ? "{$user->email} is already a platform administrator." : "{$user->email} is not a platform administrator.");

            return self::SUCCESS;
        }

        if ($grant && $user->email_verified_at === null) {
            $this->components->error("{$user->email} has not verified their email address yet.");

            return self::FAILURE;
        }

        $user->forceFill(['is_platform_admin' => $grant])->save();

        Log::notice($grant ? 'Platform administration granted' : 'Platform administration revoked', ['user' => $user->id]);

        $this->components->info($grant
            ? "{$user->email} can now open platform administration at /platform."
            : "{$user->email} is no longer a platform administrator.");

        return self::SUCCESS;
    }
}
