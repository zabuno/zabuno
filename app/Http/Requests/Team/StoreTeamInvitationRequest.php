<?php

declare(strict_types=1);

namespace App\Http\Requests\Team;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Domain\Authorization\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

final class StoreTeamInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $userId = (int) $this->user()->getKey();
        $workspaceId = (int) $this->route('workspace');

        return $this->container->make(AuthorizationPort::class)
            ->can($userId, Permission::WorkspaceManage, $workspaceId);
    }

    /**
     * @return never
     */
    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            new JsonResponse(['message' => 'Not Found.'], 404)
        );
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', 'string', 'in:editor'],
        ];
    }
}
