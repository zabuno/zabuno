<?php

declare(strict_types=1);

namespace App\Http\Requests\Team;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Domain\Authorization\Permission;
use App\Domain\Tenancy\MembershipRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

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
            /*
                Davet edilebilir roller TEK YERDEN gelir (`docs/70`).

                Elle yazılmış bir liste, yeni bir rol eklendiği gün burada
                unutulur ve rol ürünün yarısında var yarısında yok olurdu.
                `Owner` listede değildir: sahiplik davetle verilmez,
                devredilir.
            */
            'role' => ['required', 'string', Rule::in(array_map(
                static fn (MembershipRole $role): string => $role->value,
                MembershipRole::invitable(),
            ))],
        ];
    }
}
