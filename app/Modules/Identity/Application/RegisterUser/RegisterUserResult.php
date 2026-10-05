<?php

namespace App\Modules\Identity\Application\RegisterUser;

use App\Modules\Identity\Models\User;

class RegisterUserResult
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
                'created_at' => $this->user->created_at?->toIso8601String(),
            ],
            'access_token' => $this->token,
            'token_type' => 'Bearer',
        ];
    }
}
