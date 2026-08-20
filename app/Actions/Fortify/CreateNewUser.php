<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * Referenced by FortifyServiceProvider::configureActions(). Sign-in is by
 * username (config/fortify.php), so registration must collect a unique one.
 */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required', 'string', 'min:3', 'max:50',
                'regex:/^[a-z0-9._-]+$/',
                Rule::unique(User::class),
            ],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => $this->passwordRules(),
        ], [
            'username.regex' => __('Usernames may contain lowercase letters, numbers, dots, dashes and underscores.'),
        ])->validate();

        return User::create([
            'name' => $validated['name'],
            'username' => mb_strtolower($validated['username']),
            'email' => mb_strtolower($validated['email']),
            'password' => $validated['password'],
        ]);
    }
}
