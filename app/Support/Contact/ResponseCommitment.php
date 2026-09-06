<?php

declare(strict_types=1);

namespace App\Support\Contact;

use App\Support\Localization\SiteText;

/**
 * Yanıt taahhüdü — TEK KAYNAK (`docs/125` §3).
 *
 * Sayı SAHİBİN kararıdır ve `SUPPORT_RESPONSE_COMMITMENT_HOURS` ile gelir;
 * cümle katalogdadır (`site.support.commitment`, `{hours}` yer tutuculu).
 * Üç yüzey (iletişim sayfası, alındı e-postası, panel) bu sınıftan okur.
 *
 * DEĞER YOKSA CÜMLE YOKTUR. "En kısa sürede", "7/24", "genellikle bir gün
 * içinde" gibi bir yedek cümle bilerek yazılmadı: yedek cümle de bir
 * vaattir ve kimse onu vermedi.
 *
 * Sıfır, negatif ya da sayı olmayan bir değer de "yok" sayılır: yanlış
 * yazılmış bir ortam değişkeni sayfaya "0 saat içinde" yazdırmamalı.
 */
final class ResponseCommitment
{
    public function __construct(private readonly SiteText $siteText) {}

    public function hours(): ?int
    {
        $raw = config('support.response_commitment_hours');

        if ($raw === null || $raw === '' || $raw === false) {
            return null;
        }

        if (! is_int($raw) && ! (is_string($raw) && preg_match('/^\d+$/', trim($raw)) === 1)) {
            return null;
        }

        $hours = (int) $raw;

        return $hours > 0 ? $hours : null;
    }

    public function sentence(?string $locale = null): ?string
    {
        $hours = $this->hours();

        if ($hours === null) {
            return null;
        }

        return str_replace('{hours}', (string) $hours, $this->siteText->get('site.support.commitment', $locale));
    }
}
