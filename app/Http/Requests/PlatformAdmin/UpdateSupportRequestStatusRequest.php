<?php

declare(strict_types=1);

namespace App\Http\Requests\PlatformAdmin;

use App\Domain\Support\SupportRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Yetki ROTADA (`EnsurePlatformSuperAdmin`), burada yalnız biçim:
 * durum, enum'un tanıdığı üç kelimeden biri olmak zorunda. Liste enum'dan
 * türetilir — elle yazılsaydı dördüncü durum eklendiği gün burada unutulurdu.
 */
final class UpdateSupportRequestStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(SupportRequestStatus::values())],
        ];
    }
}
