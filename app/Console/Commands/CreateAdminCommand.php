<?php

namespace App\Console\Commands;

use App\Http\Requests\Api\V1\LoginRequest;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;

/**
 * Creates the dashboard admin, or sets a new password for an existing
 * username (the API has no password reset). The password is only typed at a
 * hidden prompt: never an option, so it can't end up in the shell history.
 */
#[Signature('admin:create
    {username : The login name, as typed in the dashboard (Arabic allowed)}
    {--name= : The name the dashboard shows (default: the username, or the current name)}')]
#[Description('Create the dashboard admin, or set a new password for an existing username')]
class CreateAdminCommand extends Command
{
    /** Minimum password length for the only account on a public API. */
    public const MIN_PASSWORD_LENGTH = 12;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $username = LoginRequest::normalizeUsername((string) $this->argument('username'));

        if ($username === '' || mb_strlen($username) > 50) {
            $this->error('The username must be 1 to 50 characters.');

            return self::FAILURE;
        }

        $password = password(label: 'Password', hint: 'At least '.self::MIN_PASSWORD_LENGTH.' characters.');

        if (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $this->error('The password must be at least '.self::MIN_PASSWORD_LENGTH.' characters.');

            return self::FAILURE;
        }

        if (password(label: 'Confirm the password') !== $password) {
            $this->error('The two passwords are different.');

            return self::FAILURE;
        }

        $user = User::firstOrNew(['username' => $username]);
        $isNew = ! $user->exists;

        $user->name = $this->option('name') ?? ($isNew ? $username : $user->name);
        $user->password = $password;
        $user->save();

        if ($isNew) {
            $this->info("Admin \"{$username}\" created.");

            return self::SUCCESS;
        }

        // A new password logs every device out: the old one may be why it changed.
        $user->tokens()->delete();
        $this->info("Password changed for \"{$username}\". All its devices are logged out.");

        return self::SUCCESS;
    }
}
