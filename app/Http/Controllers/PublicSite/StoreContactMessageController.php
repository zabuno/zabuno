<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Application\Support\Dto\NewSupportRequest;
use App\Application\Support\UseCase\SubmitSupportRequest;
use App\Domain\Support\SupportChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreContactMessageRequest;
use Illuminate\Http\RedirectResponse;

/**
 * Gelen mesajı bir DESTEK TALEBİ olarak alır — `docs/88` (P1-01), FF-201
 * ile referans ve alındı bildirimi kazandı (`docs/125`).
 *
 * Saklamak göndermekten önce gelir; sıra `SubmitSupportRequest` içinde
 * yazılı ve panelle ortaktır. Burada kalan iş kamu formuna özgü iki şey:
 * bal küpü ve konunun mesajdan türetilmesi.
 */
final class StoreContactMessageController extends Controller
{
    /** Konu sütununun sınırı; kamu formunda konu alanı yok. */
    private const SUBJECT_LENGTH = 80;

    public function __construct(
        private readonly SubmitSupportRequest $submit,
    ) {}

    public function __invoke(StoreContactMessageRequest $request): RedirectResponse
    {
        $honeypot = trim((string) $request->validated('website'));

        /*
            BAL KÜPÜ dolduysa istek SESSİZCE düşer ve başarı gibi görünür.

            Bota "yakalandın" demek, bir sonraki denemede o alanı atlamasını
            öğretirdi. İnsan bu alanı görmez, dolayısıyla dolduramaz.
            Referans da yoktur: olmayan bir kaydın numarası uydurulmaz.
        */
        if ($honeypot !== '') {
            return redirect('/contact')->with('contact.sent', true);
        }

        $body = (string) $request->validated('message');

        $submitted = $this->submit->handle(new NewSupportRequest(
            name: (string) $request->validated('name'),
            email: (string) $request->validated('email'),
            subject: self::subjectFrom($body),
            message: $body,
            channel: SupportChannel::PublicContact,
            locale: $request->getPreferredLanguage(['tr', 'en']),
            workspaceId: null,
            userId: null,
        ));

        return redirect('/contact')
            ->with('contact.sent', true)
            ->with('contact.reference', $submitted->request->reference);
    }

    /**
     * Konu MESAJIN İLK SATIRIDIR, kırpılmış. Kamu formuna konu alanı
     * eklemek bir seçenekti; ama fiyat soran biri için "konu" fazladan
     * bir engel ve sahibin listesinde boş bir sütun, ilk satırdan kötü.
     */
    private static function subjectFrom(string $body): string
    {
        $firstLine = trim((string) preg_replace('/\s+/u', ' ', strtok($body, "\r\n") ?: $body));

        if (mb_strlen($firstLine) <= self::SUBJECT_LENGTH) {
            return $firstLine;
        }

        return rtrim(mb_substr($firstLine, 0, self::SUBJECT_LENGTH - 1)).'…';
    }
}
