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
            /*
                SÖZLEŞME ONAYI ZORUNLU (FF-198, `docs/107` Faz 1.2).

                `accepted` örtük bir kuraldır: alan hiç gelmezse de düşer.
                Aynı doğrulama geçişinde duruyor ki ad, e-posta, parola ve onay
                hataları TEK yanıtta gelsin — kullanıcı önce parolasını
                düzeltip sonra bir de onayı öğrenmesin.

                Ticari ileti izni İSTEĞE BAĞLI: yokluğu hata değil, "hayır"
                da değil — kayıt hiç yazılmaz (`ConsentRecorder`).
            */
            'terms_accepted' => ['accepted'],
            /*
                AYDINLATMA BEYANI AYRI BİR ALAN (REG-LEGAL-01).

                `terms_accepted` ile birleştirilmez: sunucu kabul ile beyanı
                ancak ayrı alanlarla ayırt edebilir ve defter (`ConsentRecorder`)
                ikisini ayrı kayıt olarak yazar. `accepted` örtük olarak
                zorunludur: alan hiç gelmezse doğrulama düşer.
            */
            'privacy_acknowledged' => ['accepted'],
            'marketing_consent' => ['sometimes', 'boolean'],
        ], [
            'terms_accepted.accepted' => __('auth.terms_required'),
            'privacy_acknowledged.accepted' => __('auth.privacy_acknowledgement_required'),
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
