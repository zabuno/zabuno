<?php

declare(strict_types=1);

namespace App\Http\Requests\Support;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Domain\Authorization\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

final class StoreWorkspaceSupportRequestRequest extends FormRequest
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

    protected function prepareForValidation(): void
    {
        foreach (['subject', 'message'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim((string) $this->input($field))]);
            }
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            // Konu sütunu 160 karakter; bir konu satırı bundan uzunsa
            // zaten mesajın kendisidir.
            'subject' => ['required', 'string', 'min:1', 'max:160'],
            // Kamu formuyla AYNI sınır (`StoreContactMessageRequest`).
            'message' => ['required', 'string', 'min:1', 'max:4000'],
        ];
    }
}
