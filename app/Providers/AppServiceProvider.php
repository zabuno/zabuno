<?php

declare(strict_types=1);

namespace App\Providers;

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
use App\Application\Media\Port\MalwareScannerPort;
use App\Application\Media\Port\MediaAssetProcessorPort;
use App\Application\Media\Port\MediaRepositoryPort;
use App\Application\MenuCatalog\Api\Port\MenuCatalogApiContextPort;
use App\Application\MenuCatalog\Port\MenuCatalogRepositoryPort;
use App\Application\Platform\Port\HostCapabilityProbePort;
use App\Application\Platform\Port\PlatformAuthorizationPort;
use App\Application\Platform\Port\PlatformWorkspaceQueryPort;
use App\Application\Publication\Port\MenuIdentityPort;
use App\Application\Publication\Port\PublicationRepositoryPort;
use App\Application\Publication\Port\PublicMenuAddressPort;
use App\Application\QrDestination\Port\BulkQrCreationPort;
use App\Application\QrDestination\Port\QrCodeImageExportPort;
use App\Application\QrDestination\Port\QrCodePdfExportPort;
use App\Application\QrDestination\Port\QrCodeRepositoryPort;
use App\Application\Reference\Port\MarketReferencePort;
use App\Application\Security\Port\BackupRestoreDrillRunnerPort;
use App\Application\Security\Port\BackupRestoreEvidenceRepositoryPort;
use App\Application\Security\Port\SecurityEvidenceSnapshotPort;
use App\Application\Security\Port\TenantIsolationEvidenceRepositoryPort;
use App\Application\Security\Port\TenantIsolationSuiteRunnerPort;
use App\Application\Team\Port\TeamInvitationRepositoryPort;
use App\Application\Team\Port\TeamMemberRepositoryPort;
use App\Application\Tenancy\Port\WorkspaceContextSessionPort;
use App\Application\Tenancy\Port\WorkspaceRepositoryPort;
use App\Application\Tenancy\Profile\Port\BrandRepositoryPort;
use App\Application\Tenancy\Profile\Port\LocationRepositoryPort;
use App\Domain\Url\CanonicalUrl;
use App\Domain\Url\UrlNormalizer;
use App\Domain\Url\UrlPolicy;
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
use App\Infrastructure\Media\Persistence\EloquentMediaRepository;
use App\Infrastructure\Media\Processing\UnavailableMediaAssetProcessor;
use App\Infrastructure\Media\Scanning\ClamavMalwareScanner;
use App\Infrastructure\Media\Scanning\UnavailableMalwareScanner;
use App\Infrastructure\MenuCatalog\Persistence\EloquentMenuCatalogRepository;
use App\Infrastructure\Persistence\MenuCatalog\Api\EloquentMenuCatalogApiContext;
use App\Infrastructure\Platform\Capability\RuntimeHostCapabilityProbe;
use App\Infrastructure\Platform\Persistence\EloquentPlatformAuthorization;
use App\Infrastructure\Platform\Persistence\EloquentPlatformWorkspaceQuery;
use App\Infrastructure\Publication\Persistence\EloquentMenuIdentity;
use App\Infrastructure\Publication\Persistence\EloquentPublicationRepository;
use App\Infrastructure\Publication\Persistence\EloquentPublicMenuAddress;
use App\Infrastructure\QrDestination\Persistence\EloquentBulkQrCreationRepository;
use App\Infrastructure\QrDestination\Persistence\EloquentQrCodeRepository;
use App\Infrastructure\QrDestination\Rendering\EndroidQrCodeImageExportAdapter;
use App\Infrastructure\QrDestination\Rendering\MpdfQrCodePdfExportAdapter;
use App\Infrastructure\Reference\IcuMarketReference;
use App\Infrastructure\Security\Execution\SqliteBackupRestoreDrillRunner;
use App\Infrastructure\Security\Execution\SymfonyTenantIsolationSuiteRunner;
use App\Infrastructure\Security\Persistence\BackupRestoreEvidenceRepository;
use App\Infrastructure\Security\Persistence\TenantIsolationEvidenceRepository;
use App\Infrastructure\Security\Source\GitSecurityEvidenceSnapshot;
use App\Infrastructure\Team\Persistence\EloquentTeamInvitationRepository;
use App\Infrastructure\Team\Persistence\EloquentTeamMemberRepository;
use App\Infrastructure\Tenancy\Persistence\EloquentWorkspaceRepository;
use App\Infrastructure\Tenancy\Persistence\SessionWorkspaceContext;
use App\Infrastructure\Tenancy\Profile\Persistence\EloquentBrandRepository;
use App\Infrastructure\Tenancy\Profile\Persistence\EloquentLocationRepository;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        $this->app->bind(MediaRepositoryPort::class, EloquentMediaRepository::class);
        $this->app->bind(MalwareScannerPort::class, function (): MalwareScannerPort {
            if (config('media.scanner.driver') === 'clamav') {
                return new ClamavMalwareScanner(
                    (string) config('media.scanner.clamav.binary_path'),
                    (float) config('media.scanner.clamav.timeout_seconds'),
                );
            }

            return new UnavailableMalwareScanner;
        });
        $this->app->bind(MediaAssetProcessorPort::class, UnavailableMediaAssetProcessor::class);
        $this->app->bind(PublicationRepositoryPort::class, EloquentPublicationRepository::class);
        $this->app->bind(PublicMenuAddressPort::class, EloquentPublicMenuAddress::class);
        $this->app->bind(MenuIdentityPort::class, EloquentMenuIdentity::class);
        $this->app->bind(QrCodeRepositoryPort::class, EloquentQrCodeRepository::class);
        $this->app->bind(BulkQrCreationPort::class, EloquentBulkQrCreationRepository::class);
        $this->app->bind(QrCodeImageExportPort::class, EndroidQrCodeImageExportAdapter::class);
        $this->app->bind(QrCodePdfExportPort::class, MpdfQrCodePdfExportAdapter::class);
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
        // Aynı IP'den dakikada 60 QR çözümlemesi: bir restoranda makul,
        // token taraması için değersiz.
        RateLimiter::for('qr-resolve', static fn (Request $request): Limit => Limit::perMinute(60)->by($request->ip()));

        //
    }
}
