<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

final class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            /*
                MEVCUT ŞİFRE SORULUR.

                Sorulmasaydı, açık bırakılmış bir bilgisayar başına oturan
                kişi hesabın tamamını ele geçirirdi: oturum zaten açık,
                şifreyi değiştirmek ise diğer her cihazı dışarı atardı.
            */
            'currentPassword' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail): void {
                $user = $this->user();

                if ($user === null || ! Hash::check((string) $value, (string) $user->password)) {
                    $fail('Mevcut şifreniz doğrulanamadı.');
                }
            }],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }
}
