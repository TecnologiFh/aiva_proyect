<?php

namespace App\Modules\Identity\Application\AuthenticateUser;

use App\Modules\Identity\Models\User;

class AuthenticateUserResult
{
    public function __construct(
        public readonly User $user,
        public readonly string $token
    ) {}

    public function toArray(): array
    {
        return [
            'user' => [
                'uuid' => $this->user->uuid,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'status' => $this->user->status->value,
            ],
            'access_token' => $this->token,
            'token_type' => 'Bearer',
        ];
    }
}
