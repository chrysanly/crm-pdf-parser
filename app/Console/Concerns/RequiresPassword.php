<?php

declare(strict_types=1);

namespace App\Console\Concerns;

use Illuminate\Support\Facades\Hash;

use function Laravel\Prompts\password;

/**
 * Gates a console command behind the maintainer authorization signature.
 *
 * The expected value is stored only as a one-way hash in config('integrity.signature')
 * (see config/integrity.php). Verification is constant-time via Hash::check(). An empty
 * stored value disables the gate entirely.
 */
trait RequiresPassword
{
    /**
     * Prompt for and verify the maintainer authorization signature.
     *
     * Returns true when authorized (or the gate is disabled), false otherwise.
     * Reads the value from the --password option when provided (non-interactive).
     */
    protected function confirmPassword(): bool
    {
        $expected = (string) config('integrity.signature', '');

        // Empty stored signature => gate disabled.
        if ($expected === '') {
            return true;
        }

        $provided = $this->option('password');

        if (! is_string($provided) || $provided === '') {
            $provided = password(
                label: 'Authorization required to continue',
                required: true,
            );
        }

        if (Hash::check($provided, $expected)) {
            return true;
        }

        $this->components->error('Authorization failed.');

        return false;
    }
}
