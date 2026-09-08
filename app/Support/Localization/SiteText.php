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
            'brand' => 'site.brand',
            'titleSuffix' => 'site.title.suffix',
            'navPrimary' => 'site.nav.primary',
            // Yasal belge şablonunun etiketleri (FF-198): belge metni
            // kütüphanede, etiketler katalogda.
            'legalReviewPending' => 'site.legal.review.pending',
            'legalVersion' => 'site.legal.version',
            'legalEffective' => 'site.legal.effective',
            'legalContents' => 'site.legal.contents',
            'cookiesPreferenceHeading' => 'site.legal.cookies.preference.heading',
            'cookiesPreferenceCurrent' => 'site.legal.cookies.preference.current',
            'cookiesPreferenceGranted' => 'site.legal.cookies.preference.granted',
            'cookiesPreferenceDenied' => 'site.legal.cookies.preference.denied',
            'cookiesPreferenceUndecided' => 'site.legal.cookies.preference.undecided',
            // Çerez seçim şeridi (FF-198).
            'consentLabel' => 'site.consent.label',
            'consentBody' => 'site.consent.body',
            'consentAccept' => 'site.consent.accept',
            'consentDecline' => 'site.consent.decline',
            'consentLink' => 'site.consent.link',
            // Hesap verisi talebinin yolu (FF-169): metin ŞABLONDA değil
            // KATALOGDA — bir hukuk metni en çok çevrilmesi gereken metindir.
            'dataRequestHeading' => 'site.legal.dataRequest.heading',
            'dataRequestBody' => 'site.legal.dataRequest.body',
            'dataRequestCta' => 'site.legal.dataRequest.cta',
            'dataRequestAddressLabel' => 'site.legal.dataRequest.addressLabel',
            'dataRequestAddressMissing' => 'site.legal.dataRequest.addressMissing',
            /*
                EKSİK SÖZLEŞME BANDI ve ŞİRKET KİMLİĞİ (FF-216).

                Kimlik etiketleri BURADA, çünkü üç yüzey onları paylaşıyor:
                yasal sayfanın uyarı bandı, `/about` ve `/contact`. Etiketi
                sayfanın kendi denetleyicisine yazmak, aynı alanın iki sayfada
                iki farklı adla görünmesiyle biterdi (`CompanyIdentity`).
            */
            'legalIncompleteHeading' => 'site.legal.incomplete.heading',
            'legalIncompleteBody' => 'site.legal.incomplete.body',
            'legalIncompleteFields' => 'site.legal.incomplete.fields',
            'companyLegalName' => 'site.company.legalName',
            'companyAddress' => 'site.company.address',
            'companyMersis' => 'site.company.mersis',
            'companyTaxOffice' => 'site.company.taxOffice',
            'companyTaxNumber' => 'site.company.taxNumber',
            'companyEmail' => 'site.company.email',
            'companyPhone' => 'site.company.phone',
            'companyValueMissing' => 'site.company.value.missing',
            'skipToContent' => 'site.skipToContent',
            'navFeatures' => 'site.nav.features',
            'navHowItWorks' => 'site.nav.howItWorks',
            'navPricing' => 'site.nav.pricing',
            'navHelp' => 'site.nav.help',
            'navAbout' => 'site.nav.about',
            'navContact' => 'site.nav.contact',
            'navLogin' => 'site.nav.login',
            'navRegister' => 'site.nav.register',
            // Kabuk menüsünü açan sözcük (FF-190). Grup ve madde adları
            // `SiteNavigation` üzerinden çözülür; bu tek dize kabuğun
            // kendisine ait olduğu için burada.
            'navMenu' => 'site.nav.menu',
            // Hazırlanıyor sayfası (FF-117): metin ŞABLONDA değil KATALOGDA.
            'pageState.title' => 'site.pageState.title',
            'pageState.headline' => 'site.pageState.headline',
            'pageState.lede' => 'site.pageState.lede',
            'pageState.maintenanceHeadline' => 'site.pageState.maintenanceHeadline',
            'pageState.maintenanceLede' => 'site.pageState.maintenanceLede',
            'pageState.pageLabel' => 'site.pageState.pageLabel',
            'pageState.stageLabel' => 'site.pageState.stageLabel',
            'pageState.updatedLabel' => 'site.pageState.updatedLabel',
            'pageState.home' => 'site.pageState.home',
            'pageState.explore' => 'site.pageState.explore',
            'pageState.contact' => 'site.pageState.contact',
            'footerProduct' => 'site.footer.product',
            'footerCompany' => 'site.footer.company',
            'footerLegal' => 'site.footer.legal',
            'footerTerms' => 'site.footer.terms',
            'footerPrivacy' => 'site.footer.privacy',
            'footerKvkk' => 'site.footer.kvkk',
            'footerDistanceSales' => 'site.footer.distanceSales',
            'footerPreInformation' => 'site.footer.preInformation',
            'footerDelivery' => 'site.footer.delivery',
            'footerRefundPolicy' => 'site.footer.refundPolicy',
            'footerCookies' => 'site.footer.cookies',
            // FF-237: on üç yasal belgenin tamamı altbilgide.
            'footerMarketingConsent' => 'site.footer.marketingConsent',
            'footerDataProcessing' => 'site.footer.dataProcessing',
            'footerSla' => 'site.footer.sla',
            'footerAcceptableUse' => 'site.footer.acceptableUse',
            'footerThirdPartyLicenses' => 'site.footer.thirdPartyLicenses',
            // Altbilginin alt satırındaki geri dönüş yolu (FF-237).
            'footerBackToTop' => 'site.footer.backToTop',
            'footerTagline' => 'site.footer.tagline',
            /*
                pSEO İÇERİK MENÜLERİ BANDININ BAŞLIĞI (FF-232).

                Bandın İÇİNDEKİ her başlık ve her bağlantı sayfanın KENDİ
                kütük başlığından gelir — katalogdan değil. Bir katalog
                anahtarı yazmak, yayına alınan her sayfa için bir kod
                değişikliği ve bir çeviri borcu üretirdi; oysa bandın bütün
                varlık sebebi, sahibin yayın kararının altbilgiyi kendiliğinden
                zenginleştirmesi. Katalogda duran tek dize, bandı AÇAN sözcük:
                o kabuğa aittir, kütüğe değil.
            */
            'footerContentMenus' => 'site.footer.contentMenus',
            'pricingHeading' => 'site.pricing.heading',
            'pricingLead' => 'site.pricing.lead',
            'pricingEmpty' => 'site.pricing.empty',
            'pricingEmptyCta' => 'site.pricing.empty.cta',
            'perRestaurant' => 'site.pricing.perRestaurant',
            'perRestaurantCta' => 'site.pricing.perRestaurant.cta',
            'unsure' => 'site.pricing.unsure',
            'unsureCta' => 'site.pricing.unsure.cta',
            // Kabul edilen ödeme yöntemi, fiyatın yanında (FF-216).
            'paymentMethods' => 'site.pricing.paymentMethods',
            'paymentMethodsCta' => 'site.pricing.paymentMethods.cta',
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
            'contactIdentityHeading' => 'site.contact.identity.heading',
            'contactFormHeading' => 'site.contact.form.heading',
            // "Kimden alışveriş yapıyorum?" (FF-216).
            'aboutHeading' => 'site.about.heading',
            'aboutLead' => 'site.about.lead',
            'aboutSellerHeading' => 'site.about.seller.heading',
            'aboutSellerBody' => 'site.about.seller.body',
            'aboutServiceHeading' => 'site.about.service.heading',
            'aboutServiceBody' => 'site.about.service.body',
            'aboutServiceScope' => 'site.about.service.scope',
            'aboutPaymentHeading' => 'site.about.payment.heading',
            'aboutPaymentBody' => 'site.about.payment.body',
            'aboutReachHeading' => 'site.about.reach.heading',
            'aboutReachBody' => 'site.about.reach.body',
            'aboutReachCta' => 'site.about.reach.cta',
            'aboutIncompleteHeading' => 'site.about.incomplete.heading',
            'aboutIncompleteBody' => 'site.about.incomplete.body',
            // Gönderim sonrası REFERANS (FF-201, `docs/125`): denetleyici
            // `{reference}` yer tutucusunu doldurur, şablon yalnız yazar.
            'contactSentReference' => 'site.contact.sentReference',
            /*
                ANA SAYFA GÖVDESİ (`docs/100` Faz 2, `docs/138`).

                Metin katalogda; OLGU ürünün kendi genel bakış sayfasında
                (`ProductOverviewPage`). Aşağıdaki `parts` ve `limits`
                başlıkları o sayfanın terimleriyle BİREBİR aynıdır ve
                `HomeSceneContractTest` (HOME-REAL-07) ayrışmayı kırar:
                pazarlama metni ile ürün envanteri arasında ikinci bir
                gerçek kaynak doğamaz.
            */
            'homeMetaTitle' => 'site.home.meta.title',
            'homeMetaDescription' => 'site.home.meta.description',
            'homeHeroHeading' => 'site.home.hero.heading',
            'homeHeroLead' => 'site.home.hero.lead',
            'homeHeroActionsLabel' => 'site.home.hero.actions.label',
            'homeOpenApp' => 'site.home.hero.openApp',
            'homeHeroRegister' => 'site.home.hero.register',
            'homeHeroNote' => 'site.home.hero.note',
            'homeChainHeading' => 'site.home.chain.heading',
            'homeChainLead' => 'site.home.chain.lead',
            'homeChainLabel' => 'site.home.chain.label',
            'homePartsHeading' => 'site.home.parts.heading',
            'homePartsLead' => 'site.home.parts.lead',
            'homeLimitsHeading' => 'site.home.limits.heading',
            'homeLimitsLead' => 'site.home.limits.lead',
            'homeFaqHeading' => 'site.home.faq.heading',
            'homeFaqWhatQuestion' => 'site.home.faq.what.question',
            'homeFaqWhatAnswer' => 'site.home.faq.what.answer',
            'homeFaqAccountQuestion' => 'site.home.faq.account.question',
            'homeFaqAccountAnswer' => 'site.home.faq.account.answer',
            'faqCostQuestion' => 'site.home.faq.cost.question',
            'faqCostAnswer' => 'site.home.faq.cost.answer',
            'homeFaqInstallQuestion' => 'site.home.faq.install.question',
            'homeFaqInstallAnswer' => 'site.home.faq.install.answer',
            'homeFaqPosQuestion' => 'site.home.faq.pos.question',
            'homeFaqPosAnswer' => 'site.home.faq.pos.answer',
            'homeContactLead' => 'site.home.contact.lead',
            'homeContactCta' => 'site.home.contact.cta',
            /*
                YATIRIMCI İLİŞKİLERİ (FF-251).

                Dört sayfanın metni katalogda, OLGULARI `InvestorDossier`de.
                Buradaki dizelerin bir kısmı `{parts}` gibi yer tutucular
                taşır ve onları denetleyici ÖLÇÜLEN değerle doldurur: bir
                rakamı katalog metnine yazmak, `INVESTOR-HONEST-01` kapısını
                kırar — ki maksat tam olarak budur.
            */
            'navInvestors' => 'site.nav.investors',
            'investorsMetaTitle' => 'site.investors.meta.title',
            'investorsMetaDescription' => 'site.investors.meta.description',
            'investorsHeading' => 'site.investors.heading',
            'investorsLead' => 'site.investors.lead',
            'investorsRulesHeading' => 'site.investors.rules.heading',
            'investorsRulesBody' => 'site.investors.rules.body',
            'investorsRulesBody2' => 'site.investors.rules.body2',
            'investorsChainHeading' => 'site.investors.chain.heading',
            'investorsChainLead' => 'site.investors.chain.lead',
            'investorsChainLabel' => 'site.investors.chain.label',
            'investorsBuiltHeading' => 'site.investors.built.heading',
            'investorsBuiltLead' => 'site.investors.built.lead',
            'investorsBuiltCta' => 'site.investors.built.cta',
            'investorsLimitsHeading' => 'site.investors.limits.heading',
            'investorsLimitsLead' => 'site.investors.limits.lead',
            'investorsCommitmentHeading' => 'site.investors.commitment.heading',
            'investorsCommitmentOpen' => 'site.investors.commitment.open',
            'investorsCommitmentMissingLabel' => 'site.investors.commitment.missingLabel',
            'investorsCommitmentSet' => 'site.investors.commitment.set',
            'investorsCommitmentCta' => 'site.investors.commitment.cta',
            'investorsInfrastructureHeading' => 'site.investors.infrastructure.heading',
            'investorsInfrastructureLead' => 'site.investors.infrastructure.lead',
            'investorsInfrastructureUnreadable' => 'site.investors.infrastructure.unreadable',
            'investorsInfrastructureRoleLabel' => 'site.investors.infrastructure.roleLabel',
            'investorsInfrastructureDataLabel' => 'site.investors.infrastructure.dataLabel',
            'investorsInfrastructureLocationLabel' => 'site.investors.infrastructure.locationLabel',
            'investorsVerificationHeading' => 'site.investors.verification.heading',
            'investorsVerificationLead' => 'site.investors.verification.lead',
            'investorsVerificationGates' => 'site.investors.verification.gates',
            'investorsVerificationPresent' => 'site.investors.verification.present',
            'investorsVerificationMissing' => 'site.investors.verification.missing',
            'investorsContactHeading' => 'site.investors.contact.heading',
            'investorsContactLead' => 'site.investors.contact.lead',
            'investorsContactCta' => 'site.investors.contact.cta',
            'investorsDeckCta' => 'site.investors.deckCta',
            'investorsProductMetaTitle' => 'site.investors.product.meta.title',
            'investorsProductMetaDescription' => 'site.investors.product.meta.description',
            'investorsProductHeading' => 'site.investors.product.heading',
            'investorsProductLead' => 'site.investors.product.lead',
            'investorsProductSourceLabel' => 'site.investors.product.sourceLabel',
            'investorsProductSourceMissing' => 'site.investors.product.sourceMissing',
            'investorsProductSourceNone' => 'site.investors.product.sourceNone',
            'investorsDeckMetaTitle' => 'site.investors.deck.meta.title',
            'investorsDeckMetaDescription' => 'site.investors.deck.meta.description',
            'investorsDeckHeading' => 'site.investors.deck.heading',
            'investorsDeckLead' => 'site.investors.deck.lead',
            'investorsDeckEvidenceLabel' => 'site.investors.deck.evidenceLabel',
            'investorsDeckWhatHeading' => 'site.investors.deckWhat.heading',
            'investorsDeckWhatBody' => 'site.investors.deckWhat.body',
            'investorsDeckWhatEvidence' => 'site.investors.deckWhat.evidence',
            'investorsDeckBuiltHeading' => 'site.investors.deckBuilt.heading',
            'investorsDeckBuiltBody' => 'site.investors.deckBuilt.body',
            'investorsDeckBuiltEvidence' => 'site.investors.deckBuilt.evidence',
            'investorsDeckNotBuiltHeading' => 'site.investors.deckNotBuilt.heading',
            'investorsDeckNotBuiltBody' => 'site.investors.deckNotBuilt.body',
            'investorsDeckNotBuiltEvidence' => 'site.investors.deckNotBuilt.evidence',
            'investorsDeckPriceHeading' => 'site.investors.deckPrice.heading',
            'investorsDeckPriceBody' => 'site.investors.deckPrice.body',
            'investorsDeckPriceEvidence' => 'site.investors.deckPrice.evidence',
            'investorsDeckCommitmentHeading' => 'site.investors.deckCommitment.heading',
            'investorsDeckCommitmentBody' => 'site.investors.deckCommitment.body',
            'investorsDeckRunsHeading' => 'site.investors.deckRuns.heading',
            'investorsDeckRunsBody' => 'site.investors.deckRuns.body',
            'investorsDeckRunsEvidence' => 'site.investors.deckRuns.evidence',
            'investorsDeckCheckedHeading' => 'site.investors.deckChecked.heading',
            'investorsDeckCheckedBody' => 'site.investors.deckChecked.body',
            'investorsDeckCheckedEvidence' => 'site.investors.deckChecked.evidence',
            'investorsDeckUnknownHeading' => 'site.investors.deckUnknown.heading',
            'investorsDeckUnknownBody' => 'site.investors.deckUnknown.body',
            'investorsDeckUnknownEvidence' => 'site.investors.deckUnknown.evidence',
            'investorsContactPageMetaTitle' => 'site.investors.contactPage.meta.title',
            'investorsContactPageMetaDescription' => 'site.investors.contactPage.meta.description',
            'investorsContactPageHeading' => 'site.investors.contactPage.heading',
            'investorsContactPageLead' => 'site.investors.contactPage.lead',
            'investorsContactPageWhatHeading' => 'site.investors.contactPage.what.heading',
            'investorsContactPageWhatBody' => 'site.investors.contactPage.what.body',
            'investorsContactPageIdentityHeading' => 'site.investors.contactPage.identity.heading',
            'investorsContactPageIdentityBody' => 'site.investors.contactPage.identity.body',
            'investorsContactPageCommitmentAbsent' => 'site.investors.contactPage.commitment.absent',
            'investorsContactPageCta' => 'site.investors.contactPage.cta',
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
     *
     * Bu doğru davranışın bir bedeli var: eşlemesi unutulan bir yetenek
     * fiyat sayfasından SESSİZCE düşer. `branding.custom` tam olarak öyle
     * kayboldu — iki ücretli kademede satılıyordu, sayfada hiç yazmıyordu.
     * `PlanCatalogueSellsEveryCapabilityTest` artık satılan ve ÇALIŞAN her
     * yeteneğin burada bir karşılığı olduğunu donduruyor.
     *
     * `menu.rich-media` 2026-09-07'de EKLENDİ (`docs/122` Y6). Bu satır
     * yokken hak tanımlı ve kademeliydi ama misafir yüzeyi yazılmamıştı;
     * duyurmak, ödemeden önce söylenmiş bir yalan olurdu. Yüzey indi
     * (`GuestRichMediaTest`), dolayısıyla sayfa artık susmuyor.
     */
    public function entitlementLabel(string $key, ?string $locale = null): ?string
    {
        $map = [
            'qr.bulk-generation' => 'site.plan.qrBulk',
            'analytics.reporting' => 'site.plan.analytics',
            'team.invitations' => 'site.plan.team',
            'branding.custom' => 'site.plan.branding',
            'ordering.basic' => 'site.plan.ordering',
            'menu.rich-media' => 'site.plan.richMedia',
        ];

        return isset($map[$key]) ? $this->get($map[$key], $locale) : null;
    }

    /** @param  list<string>  $accepted */
    public static function pick(?string $preferred): string
    {
        return in_array((string) $preferred, self::SUPPORTED, true) ? (string) $preferred : 'en';
    }
}
