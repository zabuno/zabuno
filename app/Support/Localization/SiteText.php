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
        /*
            Locale verilmezse UYGULAMANIN dili kullanılır (FF-93). Önceden
            burada sabit `'en'` vardı ve her çağıran locale'i elle geçirmek
            zorundaydı; geçirmeyi unutan yüzey (kimlik kabukları) Türkçe bir
            ziyaretçiye bile İngilizce metin veriyordu. Dil artık istekte
            seçiliyor; ikinci bir varsayılan tutmak o seçimi görmezden
            gelmek olurdu.
        */
        return $this->translations->translate(self::DOMAIN, $key, $locale ?? app()->getLocale());
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
            // Mühendislik kabuğunun sekme başlığı (`docs/98` FF-66) — Blade'e
            // sabit dize yazmak çevrilemez borcu büyütürdü (I18N-SSR-RATCHET-16).
            'engineeringTitle' => 'site.engineering.title',
            // Masterpage (`docs/100` §2): gezinti ve altbilgi metni katalogdan.
            // Kabuk sekme başlıkları (FF-93).
            'titleLogin' => 'site.title.login',
            'titleRegister' => 'site.title.register',
            'titleForgotPassword' => 'site.title.forgotPassword',
            'titleResetPassword' => 'site.title.resetPassword',
            'titleVerifyEmail' => 'site.title.verifyEmail',
            'titleEmailVerified' => 'site.title.emailVerified',
            'titleInvitation' => 'site.title.invitation',
            'titleWorkspace' => 'site.title.workspace',
            'titlePlatform' => 'site.title.platform',
            'skipToContent' => 'site.skipToContent',
            'navFeatures' => 'site.nav.features',
            'navHowItWorks' => 'site.nav.howItWorks',
            'navPricing' => 'site.nav.pricing',
            'navHelp' => 'site.nav.help',
            'navContact' => 'site.nav.contact',
            'navLogin' => 'site.nav.login',
            'navRegister' => 'site.nav.register',
            'footerProduct' => 'site.footer.product',
            'footerLegal' => 'site.footer.legal',
            'footerTerms' => 'site.footer.terms',
            'footerPrivacy' => 'site.footer.privacy',
            'footerKvkk' => 'site.footer.kvkk',
            'footerTagline' => 'site.footer.tagline',
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
            // Ana sayfa gövdesi (`docs/100` Faz 2): 29 dize Blade'den katalog'a.
            'homeMetaTitle' => 'site.home.meta.title',
            'homeMetaDescription' => 'site.home.meta.description',
            'homeHeroHeading' => 'site.home.hero.heading',
            'homeHeroLead' => 'site.home.hero.lead',
            'homeHeroActionsLabel' => 'site.home.hero.actions.label',
            'homeOpenApp' => 'site.home.hero.openApp',
            'homeFeaturesHeading' => 'site.home.features.heading',
            'homeFeatureWorkspaceTitle' => 'site.home.features.workspace.title',
            'homeFeatureWorkspaceBody' => 'site.home.features.workspace.body',
            'homeFeatureMenuTitle' => 'site.home.features.menu.title',
            'homeFeatureMenuBody' => 'site.home.features.menu.body',
            'homeFeaturePublicationTitle' => 'site.home.features.publication.title',
            'homeFeaturePublicationBody' => 'site.home.features.publication.body',
            'homeFeatureMediaTitle' => 'site.home.features.media.title',
            'homeFeatureMediaBody' => 'site.home.features.media.body',
            'homeHowItWorksHeading' => 'site.home.howItWorks.heading',
            'homeStepSetupTitle' => 'site.home.howItWorks.setup.title',
            'homeStepSetupBody' => 'site.home.howItWorks.setup.body',
            'homeStepBuildTitle' => 'site.home.howItWorks.build.title',
            'homeStepBuildBody' => 'site.home.howItWorks.build.body',
            'homeStepPublishTitle' => 'site.home.howItWorks.publish.title',
            'homeStepPublishBody' => 'site.home.howItWorks.publish.body',
            'homeStepUpdateTitle' => 'site.home.howItWorks.update.title',
            'homeStepUpdateBody' => 'site.home.howItWorks.update.body',
            'homeFaqHeading' => 'site.home.faq.heading',
            'homeFaqWhatQuestion' => 'site.home.faq.what.question',
            'homeFaqWhatAnswer' => 'site.home.faq.what.answer',
            'homeFaqAccountQuestion' => 'site.home.faq.account.question',
            'homeFaqAccountAnswer' => 'site.home.faq.account.answer',
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
