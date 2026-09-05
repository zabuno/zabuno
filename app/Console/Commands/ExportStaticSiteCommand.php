<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Content\PagePublicationStatus;
use App\Http\Controllers\FoundationStatusController;
use App\Http\Controllers\PublicSite\ShowContactFormController;
use App\Http\Controllers\PublicSite\ShowHelpController;
use App\Models\ContentPage;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Kurumsal sayfaların STATİK HTML ÖNİZLEMESİ — sahibin talebi (2026-09-05).
 *
 * *"Paralel olarak statik html dosyaları yaratalım."* Sebep basit ve
 * teknik değil: sahip kod bilmiyor, sunucu çalıştırmak istemiyor ve
 * tasarımı görmek için yapmak istediği tek şey bir dosyaya çift tıklamak.
 *
 * ═══ BU ÇIKTI DAĞITILMAZ ═══
 *
 * Önizleme, sitenin kendisi DEĞİLDİR ve `.gitignore`dadır. Dağıtılan bir
 * kopya bir gün asıl siteden ayrışır ve o gün hangisinin doğru olduğu
 * bilinmez. Tek işi, o anki kabuğun ve sayfaların nasıl göründüğünü
 * göstermek.
 *
 * ═══ NE ÜRETİLİR, NE ÜRETİLMEZ ═══
 *
 * Yalnız GERÇEKTEN 200 dönen kurumsal sayfalar. Hazırlanıyor ekranı bir 404
 * gövdesidir; onu bir dosya olarak yazmak, olmayan bir sayfayı varmış gibi
 * göstermek olurdu — kapının (`PageGate`) tam olarak engellediği şey.
 *
 * ═══ NEDEN ADRESLER YENİDEN YAZILIR ═══
 *
 * `href="/help"` bir `file://` belgesinde diskin köküne bakar ve hiçbir
 * zaman açılmaz. Önizlemenin tek işi gezilebilmek olduğu için iç bağlantılar
 * göreli dosya yollarına çevrilir. Önizlemede karşılığı OLMAYAN adresler
 * (`/login`, `/app`) mutlak adrese çevrilir: sessizce kırık bir bağlantı
 * bırakmak yerine, gerçek sitedeki yerini gösterir.
 */
final class ExportStaticSiteCommand extends Command
{
    protected $signature = 'site:export-static
        {--out=storage/app/site-preview : Çıktı dizini}
        {--base-url= : Sayfaların çizileceği kök adres; boşsa `app.url`}';

    protected $description = 'Kurumsal sayfaların statik HTML önizlemesini üretir (dağıtılmaz).';

    /**
     * Kurumsal kabuğu giyen denetleyiciler.
     *
     * Liste burada duruyor ama SAYFA LİSTESİ elle yazılmıyor: rotalar
     * taranıyor. Yarın yeni bir kurumsal sayfa açıldığında bu komut onu
     * kendiliğinden üretir; elle yazılmış bir liste ise o gün eskir.
     */
    private const SHELL_CONTROLLERS = [
        FoundationStatusController::class,
        ShowHelpController::class,
        ShowContactFormController::class,
    ];

    public function handle(HttpKernel $kernel): int
    {
        $out = $this->absoluteOut();
        /*
            KÖK ADRES YAPILANDIRMADAN GELİR, KODA GÖMÜLMEZ.

            Bu yazılım tek bir alan adına ait değil (`SAAS-DOMAIN`): kendi
            alan adıyla kuran biri, önizlemesinde de kendi adresini görmeli.
        */
        $baseUrl = rtrim(
            (string) ($this->option('base-url') ?: config('app.url')),
            '/',
        );

        $paths = [...$this->livePaths(), ...$this->publishedRegistryPaths()];

        if ($paths === []) {
            $this->error('Üretilecek kurumsal sayfa bulunamadı.');

            return self::FAILURE;
        }

        // Önce HEPSİ çizilir, sonra yazılır: bağlantı yeniden yazımı, hangi
        // adreslerin önizlemede GERÇEKTEN olduğunu bilmek zorunda.
        $rendered = [];

        foreach ($paths as $path) {
            $html = $this->render($kernel, $path, $baseUrl);

            if ($html !== null) {
                $rendered[$path] = $html;
            }
        }

        File::deleteDirectory($out);
        File::ensureDirectoryExists($out);

        $this->copyBuiltAssets($out);

        foreach ($rendered as $path => $html) {
            $file = $out.'/'.$this->fileFor($path);
            File::ensureDirectoryExists(dirname($file));
            File::put($file, $this->rewrite($html, $path, array_keys($rendered), $baseUrl));
        }

        $this->info(count($rendered).' sayfa üretildi: '.$out);
        $this->line('Açmak için: '.$out.'/index.html');

        return self::SUCCESS;
    }

    private function absoluteOut(): string
    {
        $out = (string) $this->option('out');

        return str_starts_with($out, '/') ? rtrim($out, '/') : base_path(rtrim($out, '/'));
    }

    /**
     * Bugün sunucuda yaşayan kurumsal adresler — rotalardan TÜRETİLİR.
     *
     * @return list<string>
     */
    private function livePaths(): array
    {
        $paths = [];

        foreach (Route::getRoutes()->getRoutesByMethod()['GET'] ?? [] as $route) {
            $action = $route->getActionName();

            foreach (self::SHELL_CONTROLLERS as $controller) {
                // Desen taşıyan bir rota (`{locale}`) bir sayfa değildir.
                if (str_starts_with($action, $controller) && ! str_contains($route->uri(), '{')) {
                    $paths[] = '/'.ltrim($route->uri(), '/');
                }
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * Kütükten çizilen YAYINLANMIŞ sayfalar.
     *
     * @return list<string>
     */
    private function publishedRegistryPaths(): array
    {
        try {
            return ContentPage::query()
                ->where('publication_status', PagePublicationStatus::Published->value)
                ->where('is_template', false)
                ->where('is_external', false)
                ->orderBy('canonical_path')
                ->pluck('canonical_path')
                ->all();
        } catch (Throwable) {
            // Kütük okunamıyorsa yaşayan sayfalar yine de üretilir; önizleme
            // eksik olur ama hiç üretilmemesinden iyidir.
            $this->warn('Sayfa kütüğü okunamadı; yalnız yaşayan sayfalar üretildi.');

            return [];
        }
    }

    /**
     * Sayfayı uygulamanın KENDİ yığınından geçirerek çizer.
     *
     * Şablonu doğrudan çizmek daha kolay olurdu ve YANLIŞ olurdu: ara
     * katmanlar, dil pazarlığı ve kapı kararı çıktının bir parçasıdır.
     * Önizleme, ziyaretçinin gördüğü şeyi göstermeli.
     */
    private function render(HttpKernel $kernel, string $path, string $baseUrl): ?string
    {
        /*
            SONDAKİ SLASH DÜŞÜRÜLÜR.

            Kütükteki canonical yol `/tr/cozumler/` biçimindedir ama site o
            adresi 301 ile `/tr/cozumler`e taşır (URL politikası). İstek
            olduğu gibi yapılsaydı önizleme her kütük sayfası için bir
            yönlendirme gövdesi yazardı — yani boş bir sayfa.
        */
        $requestPath = rtrim($path, '/');

        /*
            İstek GERÇEK adresle yapılır. Kanonik etiket isteğin host'undan
            türer; `localhost` ile çizilseydi önizlemedeki her sayfa kendi
            kanonik adresi olarak `http://localhost/...` ilan ederdi ve sahip
            bir sayfayı paylaştığında yanlış adresi paylaşırdı.
        */
        $response = $kernel->handle(Request::create($baseUrl.($requestPath === '' ? '/' : $requestPath), 'GET'));

        if ($response->getStatusCode() !== 200) {
            // 404 gövdesi (hazırlanıyor ekranı) bir sayfa değildir.
            $this->warn("atlandı [{$path}] — {$response->getStatusCode()}");

            return null;
        }

        return (string) $response->getContent();
    }

    /** Adres → dosya. Kök `index.html`, gerisi `<yol>/index.html`. */
    private function fileFor(string $path): string
    {
        $trimmed = trim($path, '/');

        return $trimmed === '' ? 'index.html' : $trimmed.'/index.html';
    }

    /**
     * Derlenmiş CSS/JS önizlemenin YANINA kopyalanır.
     *
     * Depodaki `public/build`e göreli bir yol vermek de mümkündü ama
     * önizleme o zaman taşındığı anda çıplak kalırdı: sahip klasörü
     * masaüstüne sürüklediğinde sayfa stilsiz açılırdı.
     */
    private function copyBuiltAssets(string $out): void
    {
        $build = public_path('build');

        if (! is_dir($build)) {
            $this->warn('public/build yok — önizleme stilsiz açılır. Önce `npm run build`.');

            return;
        }

        File::copyDirectory($build, $out.'/build');
    }

    /**
     * Mutlak adresleri, `file://` altında çalışan hâline çevirir.
     *
     * @param  list<string>  $exported
     */
    private function rewrite(string $html, string $path, array $exported, string $baseUrl): string
    {
        $depth = substr_count(trim($path, '/'), '/') + (trim($path, '/') === '' ? 0 : 1);
        $up = str_repeat('../', $depth);

        $exportedFiles = [];

        foreach ($exported as $exportedPath) {
            $exportedFiles[rtrim($exportedPath, '/') === '' ? '/' : rtrim($exportedPath, '/')] =
                $up.$this->fileFor($exportedPath);
        }

        /*
            DERLENMİŞ VARLIKLAR MUTLAK ADRESLE ÇIKAR.

            `@vite` `asset()` üzerinden tam adres yazar
            (`https://<alan-adi>/build/assets/app-x.css`). Yalnız kök-göreli
            yolları çevirseydik, önizleme internet olmadan STİLSİZ açılırdı —
            ve stilsiz bir kabuk, kabuğu göstermek için üretilen bir
            önizlemenin tek işini yapamaz.
        */
        $html = (string) preg_replace(
            '~(href|src)="https?://[^"/]+/build/([^"]*)"~',
            '$1="'.$up.'build/$2"',
            $html,
        );

        return (string) preg_replace_callback(
            '#(href|src)="(/[^"]*)"#',
            function (array $match) use ($exportedFiles, $up, $baseUrl): string {
                [$attribute, $target] = [$match[1], $match[2]];

                // Derlenmiş varlık: önizlemenin kendi `build/` klasörüne.
                if (str_starts_with($target, '/build/')) {
                    return $attribute.'="'.$up.ltrim($target, '/').'"';
                }

                $key = rtrim(explode('#', $target)[0], '/');
                $key = $key === '' ? '/' : $key;

                if (isset($exportedFiles[$key])) {
                    $fragment = str_contains($target, '#') ? '#'.explode('#', $target, 2)[1] : '';

                    return $attribute.'="'.$exportedFiles[$key].$fragment.'"';
                }

                /*
                    Önizlemede karşılığı yok: uygulama ve kimlik adresleri
                    (`/login`, `/register`, `/app`) kurumsal site değildir
                    (`docs/105` §4.4). Sessizce kırık bırakmak yerine gerçek
                    sitedeki yerini gösteriyoruz.
                */
                return $attribute.'="'.$baseUrl.$target.'"';
            },
            $html,
        );
    }
}
