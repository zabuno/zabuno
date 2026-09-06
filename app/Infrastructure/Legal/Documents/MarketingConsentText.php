<?php

declare(strict_types=1);

namespace App\Infrastructure\Legal\Documents;

use App\Domain\Legal\LegalDocument;
use App\Domain\Legal\LegalSection;

/**
 * Ticari Elektronik İleti İzni metni — İNGİLİZCE KAYNAK METİN (FF-198).
 *
 * Kanal yalnız E-POSTA: depoda SMS ya da arama kanalı yok ve olmayan bir
 * kanal için izin istenmez. Geri alma yolu, ürünün BUGÜN sunduğu yoldur
 * (iletişim formu); ürün içi anahtar yoktur ve vaat edilmez.
 */
final class MarketingConsentText
{
    public static function document(): LegalDocument
    {
        return new LegalDocument(
            key: 'marketing-consent',
            version: '0.1',
            effectiveDate: '2026-09-06',
            title: 'Electronic Commercial Message Consent',
            summary: 'What you agree to when you tick the optional commercial message box at registration, and how you withdraw that consent.',
            sections: [
                new LegalSection('What you consent to', [
                    'By ticking the commercial message box on the registration screen, you agree that {company.legal_name} may send you commercial electronic messages by e-mail about Zabuno: product news, new features, offers and events.',
                    'E-mail is the only channel we use for these messages. We do not send commercial messages by SMS or by phone.',
                ]),
                new LegalSection('Legal basis', [
                    'This consent is requested under Law No. 6563 on the Regulation of Electronic Commerce and the Regulation on Commercial Communication and Commercial Electronic Messages. Giving it is voluntary; the service works exactly the same whether or not you tick the box.',
                ]),
                new LegalSection('Withdrawing your consent', [
                    'You can withdraw your consent at any time and free of charge through the contact form on this site or by writing to {company.email} from the e-mail address on your account. Once the withdrawal is processed we stop sending commercial messages and record the withdrawal next to the original consent.',
                ]),
                new LegalSection('How your consent is recorded', [
                    'Your consent is recorded with your account together with the date and time, the version of this text, your network address and your browser identifier at that moment. If you do not tick the box, nothing is recorded and no commercial message is sent.',
                ]),
                new LegalSection('Messages that are not commercial', [
                    'Messages needed to run your account are sent regardless of this consent: e-mail verification, password reset, team invitations, security notices and payment confirmations. They are part of the service, not marketing.',
                ]),
            ],
        );
    }
}
