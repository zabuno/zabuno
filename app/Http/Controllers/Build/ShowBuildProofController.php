<?php

declare(strict_types=1);

namespace App\Http\Controllers\Build;

use App\Support\Build\BuildIdentity;
use Illuminate\Http\JsonResponse;

/**
 * DAĞITIM NE DAĞITTI? — `docs/142`.
 *
 * Sağlık kontrolü "bir şey 200 dönüyor mu?" diye sorar ve eski sürüm de 200
 * döner. 2026-09-08'de dağıtım her adımı yeşil tamamladı, sağlık kontrolü
 * geçti, ve canlı site birleştirilen commit'i çalıştırmıyordu. Eksik olan
 * tespit değil, DIŞARIDAN KARŞILAŞTIRILABİLİR bir cevaptı.
 *
 * Neden ayrı bir uç, neden bir başlık ya da HTML etiketi değil:
 *
 * - `<meta>` ETİKETİ ZATEN VAR ama yalnız uygulama kabuğunu basan
 *   sayfalarda (`/login`, `/app`, …); kurumsal ana sayfa onu hiç basmıyor.
 *   Dağıtım kapısının hangi sayfanın hangi kabuğu kullandığına bağlı olması
 *   kırılgan bir sözleşmedir.
 * - HTTP BAŞLIĞI her yanıtta taşınırdı ama tarayıcıdan bakan bir insana
 *   görünmez ve vekil katmanında sessizce düşürülebilir. Sahibi teknik
 *   değil: adresi açıp cevabı okuyabilmeli.
 * - `/up` DEĞİŞTİRİLMEDİ. O, konteynerin `HEALTHCHECK`'i ve dağıtımın
 *   bekleme döngüsüdür; gövdesini değiştirmek çalışan bir kapıyı riske
 *   atardı. Kanıt onun YERİNE değil, YANINA gelir.
 *
 * Adres `/up/build`: `/build` statik varlık dizinidir (nginx onu diskten
 * sunar), dolayısıyla kanıt oraya konulamazdı.
 */
final class ShowBuildProofController
{
    public function __invoke(): JsonResponse
    {
        $identity = BuildIdentity::resolve();

        /*
         * İKİ YARI AYRI BİLDİRİLİR. Bugünkü arıza tam da ayrışmalarıydı:
         * PHP bir noktaya kadar güncel, derlenmiş varlıklar bir önceki
         * tasarımdan. Tek bir "sürüm" alanı bunu yapısal olarak gösteremez.
         *
         * Alan kümesi BİLEREK DAR. Buradan okunan her ek alan, sürüm
         * kimliğini ilan etmek için gerekmeyen bir bilgidir ve sunucunun
         * içini tarif etmeye başlar.
         */
        return response()
            ->json([
                'revision' => $identity->revision(),
                'short_revision' => $identity->shortRevision(),
                'assets_revision' => $identity->assetsRevision(),
                'assets_match' => $identity->assetsMatchApplication(),
            ])
            /*
             * Bayatlığı ölçen bir ucun kendisi bayat cevap veremez.
             * Önbelleğe alınırsa dağıtım kapısı eski bir cevabı okuyup
             * "yeşil" derdi — yani kapı, kapatmak için var olduğu arızanın
             * aynısını üretirdi.
             */
            ->header('Cache-Control', 'no-store, max-age=0');
    }
}
