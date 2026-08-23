<?php

declare(strict_types=1);

/**
 * Self-contained, dependency-free RED preflight for S1-WP01A (Foundation).
 * Runs with plain `php`, no Composer/vendor/PHPUnit required, because none
 * of those exist yet. Each case name states the requirement/outcome it
 * checks, not just a file-existence fact; failure messages cite the
 * canonical doc that owns the requirement. Immutable base for this run:
 * e171f4cc8c78e6702228a3ce18e68b1a67ec2988 (frozen S1-WP01A scope).
 */
$repoRoot = dirname(__DIR__, 2);

/**
 * @return array<int, array{name: string, fn: callable(string): (string|null)}>
 */
function s1wp01aPreflightCases(): array
{
    return [
        ['name' => 'root_composer_json_exists', 'fn' => 'checkRootComposerJsonExists'],
        ['name' => 'composer_json_locks_php_8_3_and_laravel_13', 'fn' => 'checkComposerJsonLocksVersions'],
        ['name' => 'domain_layer_directory_exists', 'fn' => 'checkDomainLayerDirectoryExists'],
        ['name' => 'domain_layer_has_no_illuminate_dependency', 'fn' => 'checkDomainLayerHasNoIlluminateDependency'],
        ['name' => 'domain_layer_declares_strict_types_everywhere', 'fn' => 'checkDomainLayerDeclaresStrictTypes'],
        ['name' => 'core_module_registry_declares_all_sixteen_core_manifests', 'fn' => 'checkCoreModuleRegistryDeclaresAllSixteen'],
        ['name' => 'core_manifests_declare_mandatory_ai_off_no_credit_contract', 'fn' => 'checkCoreManifestsDeclareAiOffContract'],
        ['name' => 'health_check_route_registered_at_up', 'fn' => 'checkHealthCheckRouteRegistered'],
        ['name' => 'env_layering_present_for_dev_staging_prod', 'fn' => 'checkEnvLayeringPresent'],
        ['name' => 'env_layering_config_mapping_matches_example_files', 'fn' => 'checkEnvLayeringConfigMappingMatchesExampleFiles'],
        ['name' => 'frontend_package_json_declares_flowbite_first_without_nextjs', 'fn' => 'checkFrontendPackageJsonFlowbiteFirst'],
        ['name' => 'shadcn_ui_components_are_source_owned_locally', 'fn' => 'checkShadcnComponentsAreSourceOwned'],
        ['name' => 'vite_laravel_asset_pipeline_configured', 'fn' => 'checkViteLaravelAssetPipelineConfigured'],
        ['name' => 'ci_workflow_declares_non_docker_build_lint_test_chain', 'fn' => 'checkCiWorkflowDeclaresNonDockerChain'],
        ['name' => 'owasp_asvs_baseline_checklist_exists', 'fn' => 'checkOwaspAsvsBaselineChecklistExists'],
        ['name' => 'deployment_root_has_no_docker_artifacts', 'fn' => 'checkDeploymentRootHasNoDockerArtifacts'],
        ['name' => 'single_deployment_root_no_nested_laravel_app', 'fn' => 'checkSingleDeploymentRootNoNestedApp'],
    ];
}

function loadJsonFile(string $path): ?array
{
    if (! is_file($path)) {
        return null;
    }
    $decoded = json_decode((string) file_get_contents($path), true);

    return is_array($decoded) ? $decoded : null;
}

/**
 * @return array<int, string>
 */
function listPhpFilesRecursively(string $dir): array
{
    if (! is_dir($dir)) {
        return [];
    }
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $fileInfo) {
        if ($fileInfo->isFile() && $fileInfo->getExtension() === 'php') {
            $files[] = $fileInfo->getPathname();
        }
    }
    sort($files);

    return $files;
}

function checkRootComposerJsonExists(string $repoRoot): ?string
{
    $path = $repoRoot.'/composer.json';
    if (! is_file($path)) {
        return "composer.json bulunamadı ({$path}). Dondurulmuş S1-WP01A kapsamı, "
            .'kökte tek deployment bir Laravel uygulaması gerektirir (docs/03 ADR-L01, '
            .'docs/26 S1-WP01) — henüz kurulmadı.';
    }

    return null;
}

function checkComposerJsonLocksVersions(string $repoRoot): ?string
{
    $path = $repoRoot.'/composer.json';
    $composer = loadJsonFile($path);
    if ($composer === null) {
        return "composer.json okunamadı veya yok ({$path}); require.php=^8.3 ve "
            .'require.laravel/framework=^13.0 kilidi (frozen S1-WP01A scope, docs/16 DEP-01) '
            .'doğrulanamıyor.';
    }
    $require = $composer['require'] ?? [];
    $phpConstraint = $require['php'] ?? null;
    $laravelConstraint = $require['laravel/framework'] ?? null;

    if ($phpConstraint !== '^8.3') {
        return "composer.json require.php beklenen '^8.3' değil, bulunan: "
            .var_export($phpConstraint, true).'.';
    }
    if ($laravelConstraint !== '^13.0') {
        return "composer.json require.laravel/framework beklenen '^13.0' değil, bulunan: "
            .var_export($laravelConstraint, true).'.';
    }

    return null;
}

function checkDomainLayerDirectoryExists(string $repoRoot): ?string
{
    $path = $repoRoot.'/app/Domain';
    if (! is_dir($path)) {
        return "app/Domain dizini yok ({$path}); Onion bağımlılık yönünün "
            .'(Domain ← Application ← Infrastructure/Adapters ← Delivery, docs/03 ADR-L02) '
            .'en iç katmanı henüz kurulmadı.';
    }

    return null;
}

function checkDomainLayerHasNoIlluminateDependency(string $repoRoot): ?string
{
    $domainDir = $repoRoot.'/app/Domain';
    if (! is_dir($domainDir)) {
        return "app/Domain dizini yok; Domain katmanının Laravel'den bağımsız olduğu "
            .'(Eloquent/Facade/Request sızmaz, docs/03 ADR-L02 §Sınır) doğrulanamıyor.';
    }
    $offending = [];
    foreach (listPhpFilesRecursively($domainDir) as $file) {
        $contents = (string) file_get_contents($file);
        if (str_contains($contents, 'Illuminate\\') || str_contains($contents, 'use Illuminate')) {
            $offending[] = substr($file, strlen($repoRoot) + 1);
        }
    }
    if ($offending !== []) {
        return 'Domain katmanında Illuminate\\* bağımlılığı bulundu (ADR-L02 §Sınır ihlali): '
            .implode(', ', $offending);
    }

    return null;
}

function checkDomainLayerDeclaresStrictTypes(string $repoRoot): ?string
{
    $domainDir = $repoRoot.'/app/Domain';
    if (! is_dir($domainDir)) {
        return 'app/Domain dizini yok; strict_types=1 taban çizgisi '
            .'(docs/03 ADR-L03 Strict OOP) her dosyada doğrulanamıyor.';
    }
    $files = listPhpFilesRecursively($domainDir);
    if ($files === []) {
        return 'app/Domain dizini boş; strict_types=1 taban çizgisi (docs/03 ADR-L03) '
            .'doğrulanacak hiçbir Domain sınıfı yok.';
    }
    $missing = [];
    foreach ($files as $file) {
        $contents = (string) file_get_contents($file);
        if (! preg_match('/^\s*declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;/m', $contents)) {
            $missing[] = substr($file, strlen($repoRoot) + 1);
        }
    }
    if ($missing !== []) {
        return 'declare(strict_types=1) eksik dosyalar (docs/03 ADR-L03): '
            .implode(', ', $missing);
    }

    return null;
}

function checkCoreModuleRegistryDeclaresAllSixteen(string $repoRoot): ?string
{
    $path = $repoRoot.'/config/core-modules.php';
    if (! is_file($path)) {
        return "config/core-modules.php yok ({$path}); CORE-05 data-only registry bootstrap'ın "
            .'sabit CORE-01..CORE-16 manifest listesini yayınlaması gerekir '
            .'(docs/04 §2, modules/core-module-registry.md).';
    }
    $manifests = include $path;
    if (! is_array($manifests)) {
        return 'config/core-modules.php bir array döndürmüyor; CORE-01..CORE-16 manifest '
            .'kayıtları beklenirken '.gettype($manifests).' döndü.';
    }
    $expectedKeys = array_map(
        static fn (int $n): string => sprintf('CORE-%02d', $n),
        range(1, 16)
    );
    $missingKeys = array_diff($expectedKeys, array_keys($manifests));
    $unexpectedKeys = array_diff(array_keys($manifests), $expectedKeys);
    if ($missingKeys !== [] || $unexpectedKeys !== []) {
        $parts = [];
        if ($missingKeys !== []) {
            $parts[] = 'eksik: '.implode(', ', $missingKeys);
        }
        if ($unexpectedKeys !== []) {
            $parts[] = 'beklenmeyen: '.implode(', ', $unexpectedKeys);
        }

        return 'CORE-01..CORE-16 tam kümesi eşleşmiyor (docs/04 §5 "Core sınırsız büyümez" '
            .'kuralı ile tutarlı sabit 16 kayıt) — '.implode('; ', $parts);
    }

    return null;
}

function checkCoreManifestsDeclareAiOffContract(string $repoRoot): ?string
{
    $path = $repoRoot.'/config/core-modules.php';
    if (! is_file($path)) {
        return 'config/core-modules.php yok; her CORE manifestinin zorunlu deterministic '
            .'AI-off/no-credit kabiliyet sözleşmesi (deterministic_baseline=required, '
            .'ai_posture tanımlı — docs/32, modules/core-module-registry.md §AI Capability '
            .'Manifest) doğrulanamıyor.';
    }
    $manifests = include $path;
    if (! is_array($manifests)) {
        return 'config/core-modules.php bir array döndürmüyor; AI-off/no-credit sözleşmesi '
            .'doğrulanamıyor.';
    }
    $violations = [];
    foreach ($manifests as $key => $manifest) {
        if (! is_array($manifest)) {
            $violations[] = "{$key}: manifest bir array değil";

            continue;
        }
        $deterministicBaseline = $manifest['deterministic_baseline'] ?? null;
        $aiPosture = $manifest['ai_posture'] ?? null;
        if ($deterministicBaseline !== 'required') {
            $violations[] = "{$key}: deterministic_baseline='required' değil (bulunan: "
                .var_export($deterministicBaseline, true).')';
        }
        if ($aiPosture === null || $aiPosture === '') {
            $violations[] = "{$key}: ai_posture tanımlı değil";
        }
    }
    if ($violations !== []) {
        return 'Zorunlu deterministic AI-off/no-credit sözleşmesi ihlalleri (docs/32 '
            .'"deterministic_baseline: required 61/61 modülde sabittir"): '
            .implode('; ', $violations);
    }

    return null;
}

function checkHealthCheckRouteRegistered(string $repoRoot): ?string
{
    $bootstrapPath = $repoRoot.'/bootstrap/app.php';
    $routesPath = $repoRoot.'/routes/web.php';

    $bootstrapHasHealth = is_file($bootstrapPath)
        && preg_match('/health\s*:\s*[\'"]\/up[\'"]/', (string) file_get_contents($bootstrapPath)) === 1;
    $routesHasHealth = is_file($routesPath)
        && preg_match('/Route::get\(\s*[\'"]\/up[\'"]/', (string) file_get_contents($routesPath)) === 1;

    if (! $bootstrapHasHealth && ! $routesHasHealth) {
        return "Ne bootstrap/app.php (withRouting health: '/up') ne de routes/web.php "
            ."(Route::get('/up', ...)) /up health endpoint'ini kayıtlı gösteriyor "
            ."(frozen S1-WP01A scope: '/up health'). Dosyalar mevcut değil veya "
            .'beklenen kaydı içermiyor.';
    }

    return null;
}

function checkEnvLayeringPresent(string $repoRoot): ?string
{
    $expected = ['.env.example', '.env.staging.example', '.env.production.example'];
    $missing = array_values(array_filter(
        $expected,
        static fn (string $name): bool => ! is_file($repoRoot.'/'.$name)
    ));
    if ($missing !== []) {
        return 'Dev/staging/prod env/config katmanlama dosyaları eksik (frozen S1-WP01A '
            .'scope: "Dev/staging/prod env/config layering"; docs/26 S1-WP01): '
            .implode(', ', $missing);
    }

    return null;
}

function checkEnvLayeringConfigMappingMatchesExampleFiles(string $repoRoot): ?string
{
    $path = $repoRoot.'/config/environments.php';
    if (! is_file($path)) {
        return 'config/environments.php yok; dev/staging/prod katmanlamasının deterministik '
            .'sözleşmesi (hangi .env.*.example dosyası hangi APP_ENV/APP_DEBUG değerine karşılık '
            .'gelir — docs/26 S1-WP01) data-only bir mapping olarak yayınlanmadı.';
    }
    $mapping = include $path;
    if (! is_array($mapping)) {
        return 'config/environments.php bir array döndürmüyor.';
    }
    $expectedKeys = ['local', 'staging', 'production'];
    if (array_keys($mapping) !== $expectedKeys) {
        return 'config/environments.php tam olarak local/staging/production sırasını '
            .'bildirmiyor; bulunan anahtarlar: '.implode(', ', array_keys($mapping));
    }
    $violations = [];
    foreach ($mapping as $environment => $definition) {
        if (! is_array($definition) || ! isset($definition['file'], $definition['app_env'])) {
            $violations[] = "{$environment}: 'file'/'app_env' alanları eksik";

            continue;
        }
        $examplePath = $repoRoot.'/'.$definition['file'];
        if (! is_file($examplePath)) {
            $violations[] = "{$environment}: {$definition['file']} yok";

            continue;
        }
        $contents = (string) file_get_contents($examplePath);
        if (! str_contains($contents, "APP_ENV={$definition['app_env']}")) {
            $violations[] = "{$environment}: {$definition['file']} APP_ENV={$definition['app_env']} bildirmiyor";
        }
        if (in_array($environment, ['staging', 'production'], true) && ! str_contains($contents, 'APP_DEBUG=false')) {
            $violations[] = "{$environment}: {$definition['file']} APP_DEBUG=false bildirmiyor";
        }
    }
    if ($violations !== []) {
        return 'config/environments.php mapping ile .env.*.example dosyaları tutarsız: '
            .implode('; ', $violations);
    }

    return null;
}

function checkFrontendPackageJsonFlowbiteFirst(string $repoRoot): ?string
{
    $path = $repoRoot.'/package.json';
    $package = loadJsonFile($path);
    if ($package === null) {
        return "package.json yok veya okunamadı ({$path}); React 19+TypeScript+Vite Laravel "
            .'asset pipeline, Flowbite-first bileşen kütüphanesi (docs/03 ADR-L06) '
            .'doğrulanamıyor.';
    }
    $deps = array_merge($package['dependencies'] ?? [], $package['devDependencies'] ?? []);
    if (! array_key_exists('flowbite-react', $deps) && ! array_key_exists('flowbite', $deps)) {
        return 'package.json bağımlılıklarında flowbite-react/flowbite yok; Flowbite React '
            .'birincil bileşen kütüphanesi olarak bildirilmedi (docs/03 ADR-L06).';
    }
    if (array_key_exists('next', $deps)) {
        return "package.json 'next' bağımlılığını içeriyor; Next.js hiçbir amaçla "
            .'kullanılamaz (docs/03 ADR-L06, frozen S1-WP01A scope prohibited tech).';
    }

    return null;
}

function checkShadcnComponentsAreSourceOwned(string $repoRoot): ?string
{
    $uiDir = $repoRoot.'/resources/js/components/ui';
    if (! is_dir($uiDir)) {
        return 'resources/js/components/ui dizini yok; shadcn/ui source-owned ikincil '
            .'bileşen konfigürasyonunun (kod projeye kopyalanır, npm bağımlılığı değil — '
            .'docs/03 ADR-L06) kanıtı bulunamadı.';
    }
    $tsxFiles = glob($uiDir.'/*.tsx') ?: [];
    if ($tsxFiles === []) {
        return 'resources/js/components/ui dizini boş; source-owned shadcn bileşeni yok.';
    }
    $package = loadJsonFile($repoRoot.'/package.json');
    if ($package !== null) {
        $deps = array_merge($package['dependencies'] ?? [], $package['devDependencies'] ?? []);
        foreach (array_keys($deps) as $depName) {
            if (preg_match('/shadcn/i', (string) $depName) === 1) {
                return "package.json shadcn'i bir npm bağımlılığı ({$depName}) olarak listeliyor; "
                    .'shadcn/ui source-owned olmalı, kurulan bir paket olmamalı (docs/03 ADR-L06).';
            }
        }
    }

    return null;
}

function checkViteLaravelAssetPipelineConfigured(string $repoRoot): ?string
{
    $candidates = ['vite.config.ts', 'vite.config.js'];
    $found = null;
    foreach ($candidates as $candidate) {
        if (is_file($repoRoot.'/'.$candidate)) {
            $found = $repoRoot.'/'.$candidate;
            break;
        }
    }
    if ($found === null) {
        return 'vite.config.ts/js yok; React+TypeScript+Vite Laravel asset pipeline entegrasyonu '
            .'(docs/03 ADR-L06) kurulmadı.';
    }
    $contents = (string) file_get_contents($found);
    if (! str_contains($contents, 'laravel-vite-plugin')) {
        return basename($found)." laravel-vite-plugin referansı içermiyor; Vite'in Laravel "
            .'asset pipeline içinde çalıştığı doğrulanamıyor (docs/03 ADR-L06).';
    }

    return null;
}

function checkCiWorkflowDeclaresNonDockerChain(string $repoRoot): ?string
{
    $workflowDir = $repoRoot.'/.github/workflows';
    if (! is_dir($workflowDir)) {
        return '.github/workflows dizini yok; non-Docker CI build/lint/test iskeleti '
            .'(docs/26 S1-WP01 "temel CI iskeleti (build/lint/test)") kurulmadı.';
    }
    $workflowFiles = array_merge(
        glob($workflowDir.'/*.yml') ?: [],
        glob($workflowDir.'/*.yaml') ?: []
    );
    if ($workflowFiles === []) {
        return '.github/workflows içinde .yml/.yaml workflow dosyası yok.';
    }
    $matchingWorkflow = null;
    foreach ($workflowFiles as $workflowFile) {
        $contents = (string) file_get_contents($workflowFile);
        $hasComposer = str_contains($contents, 'composer install');
        $hasNpm = str_contains($contents, 'npm ci') || str_contains($contents, 'npm install');
        $hasTest = str_contains($contents, 'phpunit') || str_contains($contents, 'artisan test');
        $hasDocker = stripos($contents, 'docker') !== false;
        if ($hasComposer && $hasNpm && $hasTest && ! $hasDocker) {
            $matchingWorkflow = $workflowFile;
            break;
        }
    }
    if ($matchingWorkflow === null) {
        return 'Hiçbir workflow dosyası composer install + npm ci/install + phpunit/artisan test '
            .'zincirini Docker referansı olmadan içermiyor (docs/03 ADR-L08 No Docker, '
            .'docs/26 S1-WP01 CI iskeleti).';
    }

    return null;
}

function checkOwaspAsvsBaselineChecklistExists(string $repoRoot): ?string
{
    $path = $repoRoot.'/security/OWASP-ASVS-BASELINE.md';
    if (! is_file($path)) {
        return 'security/OWASP-ASVS-BASELINE.md yok; docs/26 S1-WP01 "OWASP ASVS temel '
            .'checklist bağlanır" outcome\'unu karşılayan, docs/15 §6 standardına bağlanan '
            .'bir production checklist artefaktı bulunamadı.';
    }
    $contents = (string) file_get_contents($path);
    if (stripos($contents, 'ASVS') === false) {
        return 'security/OWASP-ASVS-BASELINE.md dosyası ASVS terimini içermiyor.';
    }

    return null;
}

function checkDeploymentRootHasNoDockerArtifacts(string $repoRoot): ?string
{
    $forbidden = ['Dockerfile', 'docker-compose.yml', 'docker-compose.yaml', 'compose.yml', 'compose.yaml'];
    $found = array_values(array_filter(
        $forbidden,
        static fn (string $name): bool => is_file($repoRoot.'/'.$name)
    ));
    if ($found !== []) {
        return 'Docker artefaktları bulundu (docs/03 ADR-L08 "No Docker, shared-host default" '
            .'kesin yasağı ihlal ediyor): '.implode(', ', $found);
    }

    return null;
}

function checkSingleDeploymentRootNoNestedApp(string $repoRoot): ?string
{
    $suspiciousDirs = ['backend', 'api', 'server', 'app-server'];
    $found = [];
    foreach ($suspiciousDirs as $dir) {
        $candidate = $repoRoot.'/'.$dir.'/composer.json';
        if (is_file($candidate)) {
            $found[] = $dir.'/composer.json';
        }
    }
    if ($found !== []) {
        return 'İç içe/ikinci bir Laravel kurulum kökü bulundu; frozen S1-WP01A scope tek '
            .'public zabuno/zabuno deposunun kökte tek deployment olmasını gerektirir '
            .'(docs/03 ADR-L01): '.implode(', ', $found);
    }

    return null;
}

function runS1Wp01aPreflight(string $repoRoot): int
{
    $cases = s1wp01aPreflightCases();
    $failures = 0;

    echo "S1-WP01A foundation preflight — repo root: {$repoRoot}\n";
    echo str_repeat('-', 72)."\n";

    foreach ($cases as $case) {
        $result = ($case['fn'])($repoRoot);
        if ($result === null) {
            echo "[PASS] {$case['name']}\n";
        } else {
            $failures++;
            echo "[FAIL] {$case['name']}\n";
            echo "       {$result}\n";
        }
    }

    echo str_repeat('-', 72)."\n";
    $total = count($cases);
    $passed = $total - $failures;
    echo "{$passed}/{$total} passed, {$failures} failed.\n";

    return $failures > 0 ? 1 : 0;
}

exit(runS1Wp01aPreflight($repoRoot));
