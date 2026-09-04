<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Ai\Port\AiAvailabilityPort;
use App\Application\Ai\Port\EmbeddingPort;
use App\Application\Ai\Port\StructuredGenerationPort;
use App\Application\Ai\Port\VisionExtractionPort;
use App\Application\Analytics\Port\AnalyticsRepositoryPort;
use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Billing\Port\IyzicoSandboxGatewayPort;
use App\Application\Billing\Port\IyzicoSandboxTransactionRepositoryPort;
use App\Application\Billing\Port\PlanCatalogRepositoryPort;
use App\Application\Billing\Port\PlanManagementRepositoryPort;
use App\Application\Billing\Port\SubscriptionRepositoryPort;
use App\Application\Entitlement\Port\EntitlementRepositoryPort;
use App\Application\Ledger\Port\LedgerPort;
use App\Application\Localization\Port\TranslationPort;
use App\Application\Mail\Port\MailTransportSelectorPort;
use App\Application\Media\Port\MalwareScannerPort;
use App\Application\Media\Port\MediaAssetProcessorPort;
use App\Application\Media\Port\MediaAuditPort;
use App\Application\Media\Port\MediaFolderRepositoryPort;
use App\Application\Media\Port\MediaProcessingJobPort;
use App\Application\Media\Port\MediaQuotaPort;
use App\Application\Media\Port\MediaRegenerationPort;
use App\Application\Media\Port\MediaRepositoryPort;
use App\Application\Media\Port\MenuMediaPort;
use App\Application\MenuCatalog\Api\Port\MenuCatalogApiContextPort;
use App\Application\MenuCatalog\Port\MenuCatalogRepositoryPort;
use App\Application\MenuCatalog\Port\OutOfStockPort;
use App\Application\Platform\Port\AccountRoutingPort;
use App\Application\Platform\Port\ConnectionProbePort;
use App\Application\Platform\Port\CredentialResolverPort;
use App\Application\Platform\Port\HostCapabilityProbePort;
use App\Application\Platform\Port\PlatformAuthorizationPort;
use App\Application\Platform\Port\PlatformConnectionAdminPort;
use App\Application\Platform\Port\PlatformCredentialAdminPort;
use App\Application\Platform\Port\PlatformWorkspaceQueryPort;
use App\Application\Publication\Port\MenuIdentityPort;
use App\Application\Publication\Port\PublicationRepositoryPort;
use App\Application\Publication\Port\PublicMenuAddressPort;
use App\Application\QrDestination\Port\BulkQrCreationPort;
use App\Application\QrDestination\Port\DiningAreaRepositoryPort;
use App\Application\QrDestination\Port\QrCardExportPort;
use App\Application\QrDestination\Port\QrCodeImageExportPort;
use App\Application\QrDestination\Port\QrCodePdfExportPort;
use App\Application\QrDestination\Port\QrCodeRepositoryPort;
use App\Application\QrDestination\Port\QrPrintSheetPort;
use App\Application\Reference\Port\MarketReferencePort;
use App\Application\Security\Port\BackupRestoreDrillRunnerPort;
use App\Application\Security\Port\BackupRestoreEvidenceRepositoryPort;
use App\Application\Security\Port\SecurityEvidenceSnapshotPort;
use App\Application\Security\Port\TenantIsolationEvidenceRepositoryPort;
use App\Application\Security\Port\TenantIsolationSuiteRunnerPort;
use App\Application\Team\Port\TeamInvitationRepositoryPort;
use App\Application\Team\Port\TeamMemberRepositoryPort;
use App\Application\Tenancy\Port\FeatureFlagPort;
use App\Application\Tenancy\Port\WorkspaceContextSessionPort;
use App\Application\Tenancy\Port\WorkspaceRepositoryPort;
use App\Application\Tenancy\Profile\Port\BrandRepositoryPort;
use App\Application\Tenancy\Profile\Port\LocationRepositoryPort;
use App\Application\Workspace\Port\WorkspaceAuditTrailPort;
use App\Domain\Ai\Capability;
use App\Domain\Media\SlotCatalogue;
use App\Domain\Media\SvgSanitizer;
use App\Domain\Platform\Credential\CredentialProvider;
use App\Domain\Url\CanonicalUrl;
use App\Domain\Url\UrlNormalizer;
use App\Domain\Url\UrlPolicy;
use App\Infrastructure\Ai\AnthropicTextProvider;
use App\Infrastructure\Ai\ArtifactSchemaValidator;
use App\Infrastructure\Ai\ConfiguredAvailability;
use App\Infrastructure\Ai\FakeProvider;
use App\Infrastructure\Ai\GeminiEmbeddingProvider;
use App\Infrastructure\Ai\GeminiTextProvider;
use App\Infrastructure\Ai\GeminiVisionProvider;
use App\Infrastructure\Ai\OpenAiCompatibleTextProvider;
use App\Infrastructure\Ai\OpenAiVisionProvider;
use App\Infrastructure\Ai\StructuredGenerationRouter;
use App\Infrastructure\Ai\VisionExtractionRouter;
use App\Infrastructure\Analytics\Persistence\EloquentAnalyticsRepository;
use App\Infrastructure\Authorization\Persistence\EloquentAuthorizationDecisionPoint;
use App\Infrastructure\Billing\Persistence\EloquentIyzicoSandboxTransactionRepository;
use App\Infrastructure\Billing\Persistence\EloquentPlanCatalogRepository;
use App\Infrastructure\Billing\Persistence\EloquentPlanManagementRepository;
use App\Infrastructure\Billing\Persistence\EloquentSubscriptionRepository;
use App\Infrastructure\Billing\Provider\IyzipaySandboxGateway;
use App\Infrastructure\Entitlement\DatabaseEntitlementRepository;
use App\Infrastructure\Ledger\DatabaseLedger;
use App\Infrastructure\Localization\MoFileTranslator;
use App\Infrastructure\Mail\VaultMailTransportSelector;
use App\Infrastructure\Media\Persistence\EloquentMediaAudit;
use App\Infrastructure\Media\Persistence\EloquentMediaFolderRepository;
use App\Infrastructure\Media\Persistence\EloquentMediaProcessingJobs;
use App\Infrastructure\Media\Persistence\EloquentMediaRegeneration;
use App\Infrastructure\Media\Persistence\EloquentMediaRepository;
use App\Infrastructure\Media\Persistence\EloquentMenuMedia;
use App\Infrastructure\Media\Processing\GdMediaAssetProcessor;
use App\Infrastructure\Media\Processing\SvgMediaAssetProcessor;
use App\Infrastructure\Media\Processing\UnavailableMediaAssetProcessor;
use App\Infrastructure\Media\Quota\ConfigMediaQuota;
use App\Infrastructure\Media\Scanning\ClamavMalwareScanner;
use App\Infrastructure\Media\Scanning\UnavailableMalwareScanner;
use App\Infrastructure\MenuCatalog\Persistence\EloquentMenuCatalogRepository;
use App\Infrastructure\MenuCatalog\Persistence\EloquentOutOfStock;
use App\Infrastructure\Persistence\MenuCatalog\Api\EloquentMenuCatalogApiContext;
use App\Infrastructure\Platform\Capability\RuntimeHostCapabilityProbe;
use App\Infrastructure\Platform\Credential\EloquentPlatformCredentialStore;
use App\Infrastructure\Platform\Credential\HttpConnectionProbe;
use App\Infrastructure\Platform\Credential\StickyAccountRouter;
use App\Infrastructure\Platform\Persistence\EloquentPlatformAuthorization;
use App\Infrastructure\Platform\Persistence\EloquentPlatformWorkspaceQuery;
use App\Infrastructure\Publication\Persistence\EloquentMenuIdentity;
use App\Infrastructure\Publication\Persistence\EloquentPublicationRepository;
use App\Infrastructure\Publication\Persistence\EloquentPublicMenuAddress;
use App\Infrastructure\QrDestination\Persistence\EloquentBulkQrCreationRepository;
use App\Infrastructure\QrDestination\Persistence\EloquentDiningAreaRepository;
use App\Infrastructure\QrDestination\Persistence\EloquentQrCodeRepository;
use App\Infrastructure\QrDestination\Rendering\EndroidQrCodeImageExportAdapter;
use App\Infrastructure\QrDestination\Rendering\MpdfQrCardPdfAdapter;
use App\Infrastructure\QrDestination\Rendering\MpdfQrCodePdfExportAdapter;
use App\Infrastructure\QrDestination\Rendering\MpdfQrPrintSheetAdapter;
use App\Infrastructure\Reference\IcuMarketReference;
use App\Infrastructure\Security\Execution\SqliteBackupRestoreDrillRunner;
use App\Infrastructure\Security\Execution\SymfonyTenantIsolationSuiteRunner;
use App\Infrastructure\Security\Persistence\BackupRestoreEvidenceRepository;
use App\Infrastructure\Security\Persistence\TenantIsolationEvidenceRepository;
use App\Infrastructure\Security\Source\GitSecurityEvidenceSnapshot;
use App\Infrastructure\Team\Persistence\EloquentTeamInvitationRepository;
use App\Infrastructure\Team\Persistence\EloquentTeamMemberRepository;
use App\Infrastructure\Tenancy\Features\PennantFeatureFlags;
use App\Infrastructure\Tenancy\Persistence\EloquentWorkspaceRepository;
use App\Infrastructure\Tenancy\Persistence\SessionWorkspaceContext;
use App\Infrastructure\Tenancy\Profile\Persistence\EloquentBrandRepository;
use App\Infrastructure\Tenancy\Profile\Persistence\EloquentLocationRepository;
use App\Infrastructure\Workspace\EloquentWorkspaceAuditTrail;
use App\Support\Localization\SiteText;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(UrlPolicy::class, static fn (): UrlPolicy => new UrlPolicy(
            (array) config('url-policy', []),
        ));
        $this->app->singleton(UrlNormalizer::class, static fn ($app): UrlNormalizer => new UrlNormalizer(
            $app->make(UrlPolicy::class),
        ));
        $this->app->singleton(CanonicalUrl::class, static fn ($app): CanonicalUrl => new CanonicalUrl(
            $app->make(UrlPolicy::class),
            $app->make(UrlNormalizer::class),
        ));

        $this->app->bind(EntitlementRepositoryPort::class, DatabaseEntitlementRepository::class);
        $this->app->bind(WorkspaceRepositoryPort::class, EloquentWorkspaceRepository::class);
        $this->app->bind(WorkspaceContextSessionPort::class, SessionWorkspaceContext::class);
        $this->app->bind(AuthorizationPort::class, EloquentAuthorizationDecisionPoint::class);
        $this->app->bind(BrandRepositoryPort::class, EloquentBrandRepository::class);
        $this->app->bind(LocationRepositoryPort::class, EloquentLocationRepository::class);
        $this->app->bind(MenuCatalogRepositoryPort::class, EloquentMenuCatalogRepository::class);
        $this->app->bind(LedgerPort::class, DatabaseLedger::class);
        $this->app->singleton(
            MarketReferencePort::class,
            static fn (): IcuMarketReference => new IcuMarketReference
        );

        $this->app->singleton(TranslationPort::class, static fn (): MoFileTranslator => new MoFileTranslator(base_path('lang/mo')));
        $this->app->bind(MenuCatalogApiContextPort::class, EloquentMenuCatalogApiContext::class);
        /*
            AI sağlayıcısı: bugün SAHTE olan, deterministik dayanak.

            Gerçek sağlayıcı (OpenAI) ayrı bir paket ve anahtar olmadan
            gerçek API'ye karşı doğrulanamaz. Bağlama burada tek satırdır —
            anahtar geldiği gün değişecek yer burası (`docs/92`).
        */
        $this->app->bind(AiAvailabilityPort::class, ConfiguredAvailability::class);

        /*
            Şema doğrulayıcı — GERÇEKTEN YAPILANDIRILMIŞ (`docs/97` R14-R15).

            Sınıf uzun süredir vardı ama hiçbir yerden çağrılmıyordu — sağlayıcı
            cevabının "şemaya uymayan asla kullanıcıya ulaşmaz" garantisi
            (`docs/51` UNK-02) çalışma zamanında aktif değildi. Yasak alan
            kontrolü (alerjen vb.) her şemada geçerlidir; zorunlu alan listesi
            yalnız ADI SABİT şemalara anlamlıdır — `product-description.v1`'in
            tek alanı `description`. `menu-extract.v1`'in satırları dinamik
            adlıdır (`row.1`, `row.2`...); zorunluluk orada ayrı bir katmanda
            (`ApplyMenuArtifact::readRows`) zaten zorlanıyor. `embedding.v1`
            bu doğrulayıcıdan hiç geçmez — `EmbeddingPort` bir `AiArtifact`
            değil, çıplak vektör döner; FieldValue/forbidden-field yüzeyi yok.
        */
        $this->app->singleton(ArtifactSchemaValidator::class, static fn (): ArtifactSchemaValidator => (new ArtifactSchemaValidator)
            ->withRequired(Capability::ProductDescription, ['description']));

        /*
            Görüntü çıkarımı: kasada OpenAI yapılandırılmış VE AI açıksa gerçek
            adaptör, aksi hâlde deterministik sahte sağlayıcı. Karar her istekte
            kasadan okunur — superadmin anahtarı girdiği an sağlayıcı değişir,
            deploy gerekmez. Anahtar yokken (CI, yerel) sahte sağlayıcı kalır
            ve bütün zincir çalışmaya devam eder (`docs/94` Faz 5).
        */
        /*
            Görme zinciri sırası GEMİNİ → OPENAI → sahte sağlayıcı.

            `docs/51` §4b.1: "görme yeteneği Gemini'de başlar (ucuz, güçlü),
            yetmezse OpenAI, en son Claude." Kasada hangi sağlayıcı
            yapılandırılmışsa o kullanılır; ikisi de varsa Gemini kazanır.
            AI kapalıyken (kill switch) ikisi de bağlanmaz — sahte sağlayıcı
            kalır (`docs/94`).
        */
        $this->app->bind(VisionExtractionPort::class, function ($app): VisionExtractionPort {
            if (config('ai.enabled') !== true) {
                return $app->make(FakeProvider::class);
            }

            $credentials = $app->make(CredentialResolverPort::class);
            $candidates = [];

            if ($credentials->isConfigured(CredentialProvider::Gemini)) {
                $candidates[] = $app->make(GeminiVisionProvider::class);
            }

            if ($credentials->isConfigured(CredentialProvider::OpenAi)) {
                $candidates[] = $app->make(OpenAiVisionProvider::class);
            }

            if ($candidates === []) {
                return $app->make(FakeProvider::class);
            }

            /*
                Yalnız BAĞLANMA ANI seçimi değil, CANLI yedek zinciri
                (`docs/97` R10-R12). Bir aday çalışma zamanında başarısız
                olursa aynı istek listedeki bir sonrakine gider.
            */
            return new VisionExtractionRouter($candidates);
        });

        /*
            Şemaya bağlı metin üretimi (ürün açıklaması, çeviri taslağı) —
            `docs/96` Faz 2 + Faz 3.

            SIRA UCUZDAN PAHALIYA, sonra son çare. Gemini önce (ucuz, yüksek
            hacim), Anthropic sonra (marka sesi için en güçlü ama en pahalı —
            `docs/51` §4b.2), Kimi ardından (ucuz yedek), özel uç nokta en
            sonda (uyumluluğu garanti DEĞİL, `docs/51` §4.5).

            Bu SABİT bir sıradır ve bilinçli olarak öyle: yeteneğe göre model
            seçen ağırlıklı/maliyet/gecikme yönlendirmesi Faz 5'in işi
            (`docs/95`), ve ölçülmemiş bir politikayı bugün yazmak, hangi
            hesabın ne kadar harcadığını bilmeden karar vermek olurdu.
        */
        $this->app->bind(StructuredGenerationPort::class, function ($app): StructuredGenerationPort {
            if (config('ai.enabled') !== true) {
                return $app->make(FakeProvider::class);
            }

            $credentials = $app->make(CredentialResolverPort::class);
            $candidates = [];

            if ($credentials->isConfigured(CredentialProvider::Gemini)) {
                $candidates[] = $app->make(GeminiTextProvider::class);
            }

            if ($credentials->isConfigured(CredentialProvider::Anthropic)) {
                $candidates[] = $app->make(AnthropicTextProvider::class);
            }

            foreach ([CredentialProvider::Kimi, CredentialProvider::CustomEndpoint] as $compatible) {
                if ($credentials->isConfigured($compatible)) {
                    $candidates[] = new OpenAiCompatibleTextProvider(
                        $compatible,
                        $credentials,
                        $app->make(AccountRoutingPort::class),
                        $app->make(HttpFactory::class),
                        $app->make(ConfigRepository::class),
                    );
                }
            }

            if ($candidates === []) {
                return $app->make(FakeProvider::class);
            }

            return new StructuredGenerationRouter($candidates);
        });

        /*
            Metin gömme — taksonomi yinelenen-terim tespiti (`docs/96` Faz 2).

            `docs/51` §4.4 yerel-first şart koşuyor ama `ai-local` sidecar
            bugün yok (§3.5); Gemini GEÇİCİ bulut yedeği — port aynı kaldığı
            için `vps-ai` kurulunca binding değişir, tüketici kod değişmez.
        */
        $this->app->bind(EmbeddingPort::class, function ($app): EmbeddingPort {
            if (config('ai.enabled') !== true) {
                return $app->make(FakeProvider::class);
            }

            if ($app->make(CredentialResolverPort::class)->isConfigured(CredentialProvider::Gemini)) {
                return $app->make(GeminiEmbeddingProvider::class);
            }

            return $app->make(FakeProvider::class);
        });

        /*
            Platform kimlik-bilgisi kasası — iki port, TEK örnek.

            Admin portu (yalnız-yazılır) ile resolver portu (yalnız-tüketici)
            aynı depoya bağlanır ama farklı yüzeylerdir: HTTP katmanı admin
            portunu alır ve sırrı GERİ OKUYAMAZ; posta/AI adaptörü resolver
            portunu alır ve çözer. Singleton, çünkü şifreleyici durumu ve
            sorgu tekrarını tek yerde toplar.
        */
        $this->app->bind(AccountRoutingPort::class, StickyAccountRouter::class);
        $this->app->bind(ConnectionProbePort::class, HttpConnectionProbe::class);
        $this->app->singleton(EloquentPlatformCredentialStore::class);
        $this->app->bind(
            PlatformCredentialAdminPort::class,
            EloquentPlatformCredentialStore::class,
        );
        $this->app->bind(
            CredentialResolverPort::class,
            EloquentPlatformCredentialStore::class,
        );
        // Faz 3 (`docs/95`): aynı depo, çok-bağlantı yüzeyi. Ayrı bir port,
        // çünkü eski sağlayıcı-düzeyi yüzey çalışan bir paneli besliyor ve
        // onu kırmanın bir kazancı yok.
        $this->app->bind(
            PlatformConnectionAdminPort::class,
            EloquentPlatformCredentialStore::class,
        );

        // Posta sürücüsü seçimi kasadan beslenir (`docs/94` Faz 3).
        $this->app->bind(MailTransportSelectorPort::class, VaultMailTransportSelector::class);

        $this->app->bind(MediaRepositoryPort::class, EloquentMediaRepository::class);
        // Medya klasörleri (`docs/108` §3 madde 1): kütüphanede gezinmeyi
        // aramaya bağımlı olmaktan kurtaran raf düzeni.
        $this->app->bind(MediaFolderRepositoryPort::class, EloquentMediaFolderRepository::class);
        // Medya denetim izi (`docs/49` Faz 7 madde 4): "bu fotoğrafı kim
        // sildi?" sorusunun cevabını tutan yer.
        $this->app->bind(MediaAuditPort::class, EloquentMediaAudit::class);
        // KUYRUK (`docs/108` §3 madde 5): "takıldı mı, yoksa hâlâ çalışıyor
        // mu?" — işler tabloya yazılıyordu, hiçbir ekranda görünmüyordu.
        // SALT OKUNUR bir port: burada iş başlatılmaz.
        $this->app->bind(MediaProcessingJobPort::class, EloquentMediaProcessingJobs::class);
        // BOYUT MOTORU (`docs/108` §6.1): "yeniden üretimi başlatırsam kaç
        // dosya etkilenir" sorusunun GERÇEK cevabını sayan yer.
        $this->app->bind(MediaRegenerationPort::class, EloquentMediaRegeneration::class);
        $this->app->bind(WorkspaceAuditTrailPort::class, EloquentWorkspaceAuditTrail::class);
        $this->app->bind(MediaQuotaPort::class, ConfigMediaQuota::class);
        $this->app->bind(FeatureFlagPort::class, PennantFeatureFlags::class);
        $this->app->bind(MenuMediaPort::class, EloquentMenuMedia::class);
        $this->app->bind(OutOfStockPort::class, EloquentOutOfStock::class);
        $this->app->bind(MalwareScannerPort::class, function (): MalwareScannerPort {
            if (config('media.scanner.driver') === 'clamav') {
                return new ClamavMalwareScanner(
                    (string) config('media.scanner.clamav.binary_path'),
                    (float) config('media.scanner.clamav.timeout_seconds'),
                );
            }

            return new UnavailableMalwareScanner;
        });
        $this->app->bind(MediaAssetProcessorPort::class, function (): MediaAssetProcessorPort {
            // Varsayılan GERÇEK işleyicidir. Eskiden burada, yüklenen her
            // fotoğrafı sonsuza kadar bekleten bir yer tutucu bağlıydı; bu
            // "güvenli varsayılan" değil, sessizce bozuk olmaktı (`docs/76`).
            //
            // GD PHP ile birlikte gelir; yine de yokluğu varsayılmaz:
            // olmayan bir eklentiyle ölümcül hata vermektense dürüstçe
            // "işleyemiyorum" demek gerekir.
            $slots = SlotCatalogue::fromArray((array) config('media-slots.slots', []));

            /*
                SVG, GD'nin ÖNÜNDE ele alınır (sahip kararı 2026-09-05,
                `docs/108` §6.2). GD bir raster kütüphanesidir ve SVG'yi hiç
                çözemez; vektörün türevi de kendisidir — temizlenmiş hâliyle.
                GD yoksa bile SVG işlenebilir olmalı, bu yüzden sarmalayıcı
                her iki iç işleyicinin de üstüne geçer.
            */
            $inner = extension_loaded('gd')
                ? new GdMediaAssetProcessor($slots)
                : new UnavailableMediaAssetProcessor;

            return new SvgMediaAssetProcessor($inner, new SvgSanitizer, $slots);
        });
        $this->app->bind(PublicationRepositoryPort::class, EloquentPublicationRepository::class);
        $this->app->bind(PublicMenuAddressPort::class, EloquentPublicMenuAddress::class);
        $this->app->bind(MenuIdentityPort::class, EloquentMenuIdentity::class);
        $this->app->bind(QrCodeRepositoryPort::class, EloquentQrCodeRepository::class);
        $this->app->bind(BulkQrCreationPort::class, EloquentBulkQrCreationRepository::class);
        $this->app->bind(QrCodeImageExportPort::class, EndroidQrCodeImageExportAdapter::class);
        $this->app->bind(QrCodePdfExportPort::class, MpdfQrCodePdfExportAdapter::class);
        // Tek kodun kâğıdı ile kesilip dağıtılacak kart destesi AYRI işlerdir
        // (`docs/104` Döngü 8).
        $this->app->bind(QrPrintSheetPort::class, MpdfQrPrintSheetAdapter::class);
        // Kart PDF'i, kartın SVG'sinden üretilir — ikinci bir besteci yok.
        $this->app->bind(QrCardExportPort::class, MpdfQrCardPdfAdapter::class);
        // Salonun bölümleri: "Area 1" bir yer tutucudur, salon adı değil.
        $this->app->bind(DiningAreaRepositoryPort::class, EloquentDiningAreaRepository::class);
        $this->app->bind(AnalyticsRepositoryPort::class, EloquentAnalyticsRepository::class);
        $this->app->bind(TeamMemberRepositoryPort::class, EloquentTeamMemberRepository::class);
        $this->app->bind(TeamInvitationRepositoryPort::class, EloquentTeamInvitationRepository::class);
        $this->app->bind(PlanCatalogRepositoryPort::class, EloquentPlanCatalogRepository::class);
        $this->app->bind(PlanManagementRepositoryPort::class, EloquentPlanManagementRepository::class);
        $this->app->bind(HostCapabilityProbePort::class, RuntimeHostCapabilityProbe::class);
        $this->app->bind(PlatformAuthorizationPort::class, EloquentPlatformAuthorization::class);
        $this->app->bind(SubscriptionRepositoryPort::class, EloquentSubscriptionRepository::class);
        $this->app->bind(PlatformWorkspaceQueryPort::class, EloquentPlatformWorkspaceQuery::class);
        $this->app->bind(IyzicoSandboxTransactionRepositoryPort::class, EloquentIyzicoSandboxTransactionRepository::class);
        $this->app->bind(IyzicoSandboxGatewayPort::class, IyzipaySandboxGateway::class);
        $this->app->bind(TenantIsolationSuiteRunnerPort::class, SymfonyTenantIsolationSuiteRunner::class);
        $this->app->bind(SecurityEvidenceSnapshotPort::class, GitSecurityEvidenceSnapshot::class);
        $this->app->bind(TenantIsolationEvidenceRepositoryPort::class, TenantIsolationEvidenceRepository::class);
        $this->app->bind(BackupRestoreEvidenceRepositoryPort::class, BackupRestoreEvidenceRepository::class);
        $this->app->bind(BackupRestoreDrillRunnerPort::class, function (): SqliteBackupRestoreDrillRunner {
            if (config('database.default') !== 'sqlite') {
                throw new \RuntimeException('The backup/restore drill runner only supports the sqlite default database driver.');
            }

            $database = (string) config('database.connections.sqlite.database');

            if ($database === '' || $database === ':memory:' || ! is_file($database) || ! is_readable($database)) {
                throw new \RuntimeException('The backup/restore drill runner requires a real, readable sqlite database file.');
            }

            return new SqliteBackupRestoreDrillRunner(
                $database,
                storage_path('app/private/security-evidence/backup-restore'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
            ÖZELLİK BAYRAKLARI (`docs/98` FF-74, Pennant). Kapsam çalışma
            alanı. Bugün tek gerçek bayrak var: FF-73'ün Home "şimdi"
            kutusu — yeni bir acemi yüzeyi bir kiracıda sorun çıkarırsa
            yalnız o kiracıda kapatılır, kod dağıtımı gerekmez. Bayrak
            kullanılmayan bir yerde tanımlanmaz: tanımı olup okuyanı
            olmayan bayrak ölü koddur.
        */
        Feature::define('novice-home', static fn (mixed $scope): bool => true);

        // Aynı IP'den dakikada 60 QR çözümlemesi: bir restoranda makul,
        // token taraması için değersiz.
        RateLimiter::for('qr-resolve', static fn (Request $request): Limit => Limit::perMinute(60)->by($request->ip()));
        // Toplu AI sayfa işleri: kiracı başına dakikalık bütçe (`docs/98` FF-75).
        RateLimiter::for('ai-batch', static fn (object $job): Limit => Limit::perMinute((int) config('ai.batch.per_minute', 6))
            ->by('ws:'.((int) ($job->workspaceId ?? 0))));

        /*
            TANITIM SİTESİNİN METNİ HER ZAMAN VAR — `docs/88`.

            Metin katalogdan geliyor ve her görünüme elle geçirilseydi,
            geçirmeyi unutan bir çağıran sayfayı boş etiketlerle basar ya da
            çökertirdi — ve bu, ekranda görülene kadar fark edilmezdi.
            Besteci, çağıranın unutabileceği bir adım bırakmıyor.

            Elle verilen değer KAZANIR: bir denetleyici ziyaretçinin diline
            göre farklı bir harita geçirebilir.
        */
        /*
            Yardım makaleleri `resources/views` DIŞINDA yaşar (`docs/89`):
            belge, arayüz etiketi değil. Ayrı bir alan olarak kaydedilir.
        */
        View::addNamespace('help', resource_path('help'));

        /*
            Kabuk ve kimlik şablonları da katalog metnine erişir (FF-93):
            sekme başlıkları Blade'e sabit yazılıydı ve çevrilemiyordu.
        */
        View::composer(['public.*', 'auth.*', 'workspace-app', 'platform-app'], function ($view): void {
            $data = $view->getData();

            if (! array_key_exists('st', $data)) {
                $view->with('st', app(SiteText::class)->all());
            }

            if (! array_key_exists('plans', $data)) {
                $view->with('plans', []);
            }
        });

        //
    }
}
