<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Media\Port\MediaRepositoryPort;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthenticatedUserController extends Controller
{
    public function __construct(private readonly MediaRepositoryPort $media) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        /*
            Profil fotoğrafının önizleme adresi medya deposundan gelir; burada
            ikinci bir türetme kuralı yazılmaz. Aksi hâlde bir gün türev adresi
            değiştiğinde avatar sessizce kırık kalırdı.
        */
        $avatarAsset = $user->avatar_media_asset_id === null
            ? null
            : $this->media->find((int) $user->avatar_media_asset_id);

        return response()->json([
            'id' => $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
            /*
                Profil fotoğrafı: varlık kimliği ve DEĞİŞMEZ küçük adres.
                Ekran ikisini de alır — kimlik "hangi görsel" sorusunu,
                adres "ne çizilecek" sorusunu cevaplar.
            */
            'avatarMediaAssetId' => $user->avatar_media_asset_id === null
                ? null
                : (int) $user->avatar_media_asset_id,
            'avatarUrl' => $avatarAsset?->previewUrl,
            /*
                HESABIN YAŞI — dakika olarak, ZAMAN DAMGASI OLARAK DEĞİL
                (`docs/112` §4.1, `first_publish_completed`).

                Ölçmek istediğimiz sayı "Time to First QR": sahibin
                kaydolmaktan menüsünü yayınlamaya kadar geçen süresi. Bugün
                hiçbir yerde ölçülmüyor ve `docs/110` §7'deki "5 dakika mı 15
                dakika mı" tartışması bu sayı olmadan kapanamaz.

                Neden süre, neden damga değil: damga gönderseydik tarayıcı
                farkı kendi saatine göre hesaplardı, ve o saat kullanıcının
                kendi ayarıdır — düzenli olarak yanlıştır (yanlış saat dilimi,
                elle geri alınmış saat, uykudan yeni uyanmış bir dizüstü).
                Yanlış saatli tek bir cihaz "-180 dakika" gibi bir satır
                üretir ve ortalamayı sessizce bozar. Süre gönderdiğimizde
                istemci yalnız sayfanın açık kaldığı süreyi ekler; o da monoton
                bir sayaçla ölçülür ve saat değişiminden etkilenmez.

                `created_at` yoksa alan GÖNDERİLMEZ. Uydurma bir sıfır,
                "bilmiyorum"un yerine geçemez (`docs/112` §3.4): "0 dakikada
                yayınladı" ile "ne zaman yayınladığını bilmiyoruz" aynı
                ortalamada toplanamaz.
            */
            ...($user->created_at === null ? [] : [
                'signedUpMinutesAgo' => max(0, (int) $user->created_at->diffInMinutes(now())),
            ]),
        ]);
    }
}
