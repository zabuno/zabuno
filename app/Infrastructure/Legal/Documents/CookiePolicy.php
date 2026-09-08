<?php

declare(strict_types=1);

namespace App\Infrastructure\Legal\Documents;

use App\Domain\Legal\LegalDocument;
use App\Domain\Legal\LegalSection;

/**
 * Çerez Politikası — İNGİLİZCE KAYNAK METİN (FF-198).
 *
 * Çerez listesi depodan ÖLÇÜLDÜ: oturum çerezi (`config/session.php`),
 * `XSRF-TOKEN`, "beni hatırla" çerezi, `zabuno_guest_locale`
 * (`GuestLocale::COOKIE`), `zabuno_measurement_consent`
 * (`MeasurementConsent::COOKIE`). Tema tercihi çerez DEĞİL, tarayıcı
 * yerel depolamasıdır (`theme-bootstrap.blade.php`) ve öyle yazıldı.
 */
final class CookiePolicy
{
    public static function document(): LegalDocument
    {
        return new LegalDocument(
            key: 'cookies',
            version: '0.1',
            effectiveDate: '2026-09-06',
            title: 'Cookie Policy',
            summary: 'Which cookies Zabuno sets, what each one does, how long it lasts, and how you can change your choice about measurement cookies.',
            sections: [
                new LegalSection('What cookies are', [
                    'A cookie is a small text record that a website stores in your browser and reads back on later requests. Some cookies are needed for the site to work at all; others are used only for measurement and are set by third-party tools.',
                    'This policy is issued by {company.legal_name} and covers the Zabuno website, the workspace application and the published menu pages.',
                ]),
                new LegalSection('Cookies the service itself sets', [
                    'Session cookie (its name ends in "-session"): keeps you logged in and remembers the state of the page you are on. Lifetime: the browser session.',
                    'XSRF-TOKEN: protects forms and the application against requests forged by another site. Lifetime: the browser session.',
                    'Remember-me cookie (its name starts with "remember_"): set only if you choose to stay logged in, so that you are not asked for your password on every visit.',
                    'zbn_language: remembers the optional website language choice you make (English or Türkçe) across pages and visits. Lifetime: 365 days. It is a preference cookie, not a sign-in or measurement cookie; you can use the site without choosing a language or remove the preference in your browser settings. It applies to this host across the site, is unavailable to browser scripts (HttpOnly), uses SameSite=Lax, and is sent securely on HTTPS connections.',
                    'zabuno_guest_locale: on a published menu, remembers the language a guest chose. Lifetime: one year.',
                    'zabuno_measurement_consent: remembers whether you accepted or declined measurement cookies, so that the question is not asked on every page. Lifetime: one year.',
                    'Your light or dark theme choice is not a cookie; it is kept in the browser\'s local storage under the name zabuno-theme and never leaves your device.',
                ]),
                new LegalSection('Measurement cookies set by third parties', [
                    'Measurement tools are loaded through Google Tag Manager only after you accept them. Until you decide, no tag manager and no measurement tool is loaded, and no third-party cookie is set.',
                    'Which tools are active depends on how the service is configured; today the tools that can be switched on are Google Analytics 4 and Yandex Metrica. Their cookies are set by those providers and are governed by the providers\' own policies.',
                    'The measurement tools receive which page was viewed and whether a conversion happened (for example that the pricing page was read or the contact form was sent). They never receive the contents of a form, an e-mail address or a name.',
                ]),
                new LegalSection('Your choice', [
                    'The cookie choice bar appears at the bottom of the page until you accept or decline. You can change your choice at any time in the preference section on this page; the new choice applies from the next page you open.',
                    'Declining keeps the service fully usable. Measurement cookies already set by a third-party tool can be removed in your browser settings.',
                ]),
                new LegalSection('Browser settings', [
                    'Every browser lets you view, block and delete cookies. Blocking the session cookie prevents you from logging in, because the application cannot then tell your requests apart from anyone else\'s.',
                ]),
            ],
        );
    }
}
