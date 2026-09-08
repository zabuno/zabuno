<?php

declare(strict_types=1);

namespace App\Domain\Assurance;

use InvalidArgumentException;

/**
 * Tek bir güvence iddiası — FF-252.
 *
 * Dört olgu taşır ve dördü de bir kurumsal alıcının ya da bir erişilebilirlik
 * denetçisinin sorduğu şeydir:
 *
 *   · `subject`  — NE hakkında konuşuyoruz (yedekleme, kontrast, sertifika…)
 *   · `state`    — ölçüldü mü, ölçülmedi mi, kusurlu mu, hiç yok mu
 *   · `detail`   — cümlenin kendisi, İngilizce kaynak metin
 *   · `evidence` — ölçen şeyin ADI: depoda bir dosya yolu ya da `artisan …`
 *
 * ═══ KANIT NEDEN BİR DİZE, BİR SONUÇ DEĞİL ═══
 *
 * Bu sayfa bir kapının SONUCUNU yayımlamaz. Bir "geçti" rozeti, o rozet
 * basıldığı andan sonraki her koşuda yalana dönebilir ve ziyaretçi rozeti
 * gördüğünde ne zaman ölçüldüğünü bilemez. Yayımlanan şey, ölçümün KENDİSİ:
 * hangi komut, hangi dosya. Okuyan kişi isterse depoyu açıp bakabilir —
 * yazılım açık kaynak.
 *
 * Kanıtın var olduğu ise ölçülür: `AssuranceHonestyGateTest` her `Measured`
 * ve `KnownGap` iddiasının kanıtını dosya sisteminde ya da kayıtlı artisan
 * komutları arasında arar. Yani bir kapı silindiğinde ya da yeniden
 * adlandırıldığında bu sayfa sessizce eskimez — test kırılır.
 */
final readonly class AssuranceClaim
{
    /**
     * @param  string  $evidence  Depoya göre bir dosya yolu (`scripts/mobile-ux-audit`)
     *                            ya da `artisan <komut>` biçiminde bir komut adı.
     *                            Kanıtı olmayan hâllerde `null`.
     */
    public function __construct(
        public string $subject,
        public ClaimState $state,
        public string $detail,
        public ?string $evidence = null,
    ) {
        foreach (['subject' => $subject, 'detail' => $detail] as $field => $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException("Assurance claim field \"{$field}\" cannot be empty.");
            }
        }

        /*
            "ÖLÇÜLÜYOR" DEMEK, ÖLÇENİ GÖSTERMEKTİR.

            Kanıtsız bir `Measured` iddiası, bu paketin engellemek için var
            olduğu şeyin ta kendisidir: ölçülmemiş bir şeyin ölçülmüş gibi
            yazılması. Kural burada, nesnenin kendisinde duruyor — bir testte
            değil — çünkü bir test unutulabilir ama bir kurucu atlanamaz.
        */
        if ($state->requiresEvidence() && ($evidence === null || trim($evidence) === '')) {
            throw new InvalidArgumentException(
                "Assurance claim \"{$subject}\" is {$state->value} but names no evidence. ".
                'A measured claim has to say what measures it.'
            );
        }
    }
}
