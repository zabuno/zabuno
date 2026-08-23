<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Application\Identity\UseCase\RegisterUser;
use App\Domain\Identity\Exception\EmailAlreadyRegisteredException;
use App\Infrastructure\Identity\Persistence\EloquentUserRepository;
use App\Infrastructure\Identity\Security\LaravelPasswordHasher;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

final class CreateNewUser implements CreatesNewUsers
{
    public function create(array $input): Authenticatable
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'confirmed', Password::default()],
        ])->validate();

        $useCase = new RegisterUser(new EloquentUserRepository, new LaravelPasswordHasher);

        try {
            $userId = $useCase->handle((string) $input['name'], (string) $input['email'], (string) $input['password']);
        } catch (EmailAlreadyRegisteredException) {
            throw ValidationException::withMessages([
                'email' => [__('auth.registration_generic_failure')],
            ]);
        }

        return User::query()->findOrFail($userId);
    }
}
