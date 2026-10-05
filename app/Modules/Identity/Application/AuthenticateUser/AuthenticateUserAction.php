<?php

namespace App\Modules\Identity\Application\AuthenticateUser;

use App\Core\Contracts\EventBus\EventBusInterface;
use App\Core\Contracts\Repositories\UserRepositoryInterface;
use App\Core\Events\UserLoggedIn;
use App\Core\Services\AuditService;
use App\Core\Services\PermissionService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;

class AuthenticateUserAction
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly EventBusInterface $eventBus,
        private readonly AuditService $auditService,
        private readonly PermissionService $permissionService
    ) {}

    public function execute(AuthenticateUserInput $input, ?string $ipAddress = null): AuthenticateUserResult
    {
        $user = $this->userRepository->findByEmail($input->email, $input->tenantId);

        if (!$user || !Hash::check($input->password, $user->password)) {
            throw new AuthenticationException('Credenciales inválidas para esta empresa.');
        }

        // Actualizar datos de inicio de sesión
        $this->userRepository->update($user->id, [
            'last_login_at' => now(),
            'last_login_ip' => $ipAddress,
        ]);

        $token = $user->createToken('aiva_auth_token')->plainTextToken;

        // Precarga inmediata de permisos en caché Redis (Cache Warm-up)
        $this->permissionService->warmupCache($user);

        // Registrar auditoría y evento
        $this->auditService->record('Identity', 'UserLogin', newValues: ['uuid' => $user->uuid, 'tenant_id' => $input->tenantId]);
        $this->eventBus->dispatch(new UserLoggedIn($user, $ipAddress));

        return new AuthenticateUserResult($user, $token);
    }
}
