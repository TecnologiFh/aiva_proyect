<?php

namespace App\Modules\Identity\Application\RegisterUser;

use App\Core\Contracts\EventBus\EventBusInterface;
use App\Core\Contracts\Repositories\UserRepositoryInterface;
use App\Core\Enums\UserStatus;
use App\Core\Events\UserRegistered;
use App\Core\Services\AuditService;
use Illuminate\Support\Facades\Hash;

class RegisterUserAction
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly EventBusInterface $eventBus,
        private readonly AuditService $auditService
    ) {}

    public function execute(RegisterUserInput $input): RegisterUserResult
    {
        $user = $this->userRepository->create([
            'tenant_id' => $input->tenantId,
            'name' => $input->name,
            'email' => strtolower($input->email),
            'password' => Hash::make($input->password),
            'status' => UserStatus::ACTIVE->value,
        ]);

        $token = $user->createToken('aiva_auth_token')->plainTextToken;

        // Registrar auditoría
        $this->auditService->record(
            module: 'Identity',
            action: 'UserRegistered',
            newValues: ['uuid' => $user->uuid, 'email' => $user->email, 'tenant_id' => $input->tenantId]
        );

        // Despachar Domain Event
        $this->eventBus->dispatch(new UserRegistered($user));

        return new RegisterUserResult($user, $token);
    }
}
