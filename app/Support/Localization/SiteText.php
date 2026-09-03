<?php

declare(strict_types=1);

namespace App\Support\Localization;

use App\Application\Localization\Port\TranslationPort;

/**
 * Tanıtım sitesinin metinleri — `docs/88` (P1-01).
 *
 * Misafir menüsünden (`GuestText`) ayrıdır: orası RESTORANIN yüzeyi ve dili
 * restoranın dilidir; burası ÜRÜNÜN yüzeyi ve dili ziyaretçinin tarayıcı
 * tercihinden gelir.
 */
final class SiteText
{
    private const DOMAIN = 'site';

    /** Bu yüzeyin bugün konuştuğu diller. */
    private const SUPPORTED = ['en', 'tr'];

    public function __construct(private readonly TranslationPort $translations) {}

    public function get(string $key, ?string $locale = null): string
    {
        return $this->translations->translate(self::DOMAIN, $key, $locale ?? 'en');
    }

    /**
     * Sayfanın ihtiyaç duyduğu metinler tek haritada.
     *
     * Şablonda tek tek çağırmak yerine harita verilmesi, "şablonda sabit
     * kullanıcı metni yok" kuralının test edilebilmesi için (`docs/85` ile
     * aynı gerekçe).
     *
     * @return array<string, string>
     */
    public function all(?string $locale = null): array
    {
        $keys = [
            'pricingHeading' => 'site.pricing.heading',
            'pricingLead' => 'site.pricing.lead',
            'pricingEmpty' => 'site.pricing.empty',
            'pricingEmptyCta' => 'site.pricing.empty.cta',
            'perRestaurant' => 'site.pricing.perRestaurant',
            'perRestaurantCta' => 'site.pricing.perRestaurant.cta',
            'unsure' => 'site.pricing.unsure',
            'unsureCta' => 'site.pricing.unsure.cta',
            'includedHeading' => 'site.pricing.included.heading',
            'includedBody' => 'site.pricing.included.body',
            'free' => 'site.pricing.free',
            'perMonth' => 'site.pricing.perMonth',
            'adds' => 'site.pricing.adds',
            'contactHeading' => 'site.contact.heading',
            'contactLead' => 'site.contact.lead',
            'contactSent' => 'site.contact.sent',
            'contactName' => 'site.contact.name',
            'contactEmail' => 'site.contact.email',
            'contactMessage' => 'site.contact.message',
            'contactSubmit' => 'site.contact.submit',
            'contactHoneypot' => 'site.contact.honeypot',
            'faqCostQuestion' => 'site.home.faq.cost.question',
            'faqCostAnswer' => 'site.home.faq.cost.answer',
            'homeContactLead' => 'site.home.contact.lead',
            'homeContactCta' => 'site.home.contact.cta',
        ];

        $out = [];

        foreach ($keys as $name => $key) {
            $out[$name] = $this->get($key, $locale);
        }

        return $out;
    }

    /**
     * Yetenek anahtarının müşteri diline çevrilmiş hâli.
     *
     * `qr.bulk-generation` geliştirici dilidir ve müşteri sayfasında
     * görünmemeli; tanınmayan bir anahtar da GÖSTERİLMEZ (null döner),
     * çünkü ham anahtar basmak sessizce sızdırmak olurdu.
     */
    public function entitlementLabel(string $key, ?string $locale = null): ?string
    {
        $map = [
            'qr.bulk-generation' => 'site.plan.qrBulk',
            'analytics.reporting' => 'site.plan.analytics',
            'team.invitations' => 'site.plan.team',
        ];

        return isset($map[$key]) ? $this->get($map[$key], $locale) : null;
    }

    /** @param  list<string>  $accepted */
    public static function pick(?string $preferred): string
    {
        return in_array((string) $preferred, self::SUPPORTED, true) ? (string) $preferred : 'en';
    }
}
