<?php

declare(strict_types=1);

namespace App\Http\Controllers\PlatformAdmin;

use App\Domain\Modules\ModuleManifest;
use App\Http\Controllers\Controller;
use App\Infrastructure\Modules\RepositoryModuleInventory;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Modül envanteri — `docs/111` adım 1.
 *
 * Sahibin sorusu tek cümleydi: "mevcutta hangi modüller var?". Bugün bu
 * soruyu cevaplamanın tek yolu depoyu elle taramaktır.
 *
 * KAYNAK İKİ DOSYA, ÜÇÜNCÜSÜ YOK:
 *  - `config/core-modules.php` — 16 CORE modülü. `ModuleManifest` her alanı
 *    sınırda reddediyor, iki test bunu donduruyor (`docs/111` §3.1).
 *  - `config/module-dependency-dag.json` — bağlamlar arası GÖZLENMİŞ
 *    bağımlılıklar; her kenarda onu kanıtlayan dosya yolu (§3.2).
 *
 * `modules/*.md` OKUNMAZ (§3.4). O 62 dosyanın hepsi kendini "PLANNING ONLY
 * — şu an çalıştırılamaz" ilan ediyor ve en az 18'inde bu yanlış. Bir
 * envanterin durum sütununu oradan doldurmak, yanlış cümleyi ürünün en
 * görünür yerine taşımak olurdu.
 *
 * ADIM 3 + ADIM 4 (FF-210) BU KAYNAĞA BİR ÜÇÜNCÜSÜNÜ EKLEDİ ve o da kod:
 *  - `modules/*.md` içindeki tek satırlık `contexts:` alanı — tanım ile kod
 *    bağlamı arasındaki eşleme (§4.2 B). Bu alan bir DURUM değil bir ADRES
 *    taşır; dosyanın geri kalanı hâlâ okunmaz.
 *  - Bu adresten ölçülen gözlem (dizin, rota, tablo, test) ve ondan türetilen
 *    durum rozeti (§4.1). Rozet elle girilmez ve eşleme kurulamayan modül
 *    "yok" değil BİLİNMİYOR olur.
 *
 * SALT OKUNUR ve öyle kalır: modül açma/kapama bu depoda hiçbir yerde
 * modellenmiş değil (§5.1). Uçta taşınmayan bir alan ekrana da çıkamaz —
 * adım 5 hâlâ yalnız bir karardır ve bu uçta tek bir yazma yolu yoktur.
 */
final class ListCoreModulesController extends Controller
{
    /**
     * Kanıt grafiği Laravel `config/` dizininde ama bir Laravel config'i
     * DEĞİL: `scripts/module-graph-gate` onu depo kökünden okuyor ve iki
     * okuyucunun aynı baytı görmesi kapının anlamıdır.
     */
    private const DEPENDENCY_GRAPH_PATH = 'config/module-dependency-dag.json';

    public function __invoke(): JsonResponse
    {
        $inventory = new RepositoryModuleInventory(base_path());

        return response()->json([
            'modules' => $this->modules(),
            'contextGraph' => $this->contextGraph(),
            /*
                62 modül TANIMI ve her birinin koddaki ölçülmüş karşılığı.
                Ayrı bir anahtar altında duruyor çünkü ayrı bir şey: yukarıdaki
                `modules` doğrulanmış bir KAYIT dosyasıdır, buradaki liste bir
                ÖLÇÜMDÜR. İkisini tek listeye karıştırmak, kaynağı okunamaz
                yapardı.
            */
            'specModules' => $inventory->specModules(),
            /*
                Eşlemenin öteki yarısı: kodda bir bağlamı olan ama hiçbir modül
                tanımının sahiplenmediği isimler. Sessizce eşleştirmek yerine
                AYRI listelenir (`docs/111` §4.2) — bir bağlamın tanımsız
                olması da bir cevaptır.
            */
            'unmappedContexts' => $inventory->unmappedContexts(),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function modules(): array
    {
        /** @var array<string, array<string, mixed>> $registry */
        $registry = config('core-modules', []);

        $modules = [];

        foreach ($registry as $code => $row) {
            /*
                Doğrulama ATLANMAZ. Ham diziyi yayınlasaydık, "bu kaynak
                doğrulanmıştır" cümlesi uç için yanlış olurdu: dosyada geçerli
                olmayan bir satır sessizce ekrana çıkardı. Sınır zaten var —
                kullanılır.
            */
            $manifest = ModuleManifest::fromArray([
                'code' => (string) $code,
                'name' => (string) ($row['name'] ?? ''),
                'moduleClass' => (string) ($row['module_class'] ?? ''),
                'version' => (string) ($row['version'] ?? ''),
                'dependencies' => $row['dependencies'] ?? [],
                'deterministicBaseline' => (string) ($row['deterministic_baseline'] ?? ''),
                'aiPosture' => (string) ($row['ai_posture'] ?? ''),
            ]);

            $modules[] = [
                'code' => $manifest->code(),
                'name' => $manifest->name(),
                'moduleClass' => $manifest->moduleClass(),
                'version' => $manifest->version(),
                'dependencies' => $manifest->dependencies(),
                'deterministicBaseline' => $manifest->deterministicBaseline(),
                'aiPosture' => $manifest->aiPosture(),
            ];
        }

        return $modules;
    }

    /**
     * @return array{nodes: list<string>, edges: list<array<string, string>>}
     */
    private function contextGraph(): array
    {
        $path = base_path(self::DEPENDENCY_GRAPH_PATH);
        $raw = is_file($path) ? file_get_contents($path) : false;

        if ($raw === false) {
            throw new RuntimeException(self::DEPENDENCY_GRAPH_PATH.' okunamadı; kanıt grafiği depoda olmalı.');
        }

        /** @var array{nodes?: list<string>, edges?: list<array<string, mixed>>} $graph */
        $graph = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        $edges = [];

        foreach ($graph['edges'] ?? [] as $edge) {
            /*
                YALNIZ DOĞRULANMIŞ KENAR. Dosyanın kendi cümlesi kenarları
                "verified current source-import" diye tanımlıyor; ileride
                doğrulanmamış bir kenar eklenirse o bir NİYETTİR, ölçüm
                değil, ve bu yüzeyde ölçümden başkası çizilmez.
            */
            if (($edge['verified'] ?? false) !== true) {
                continue;
            }

            $evidencePath = (string) ($edge['evidence']['path'] ?? '');

            if ($evidencePath === '') {
                continue;
            }

            $edges[] = [
                'from' => (string) $edge['from'],
                'to' => (string) $edge['to'],
                // Kanıt kenarla BİRLİKTE taşınır, sonradan aranmaz: rozetin
                // yanında ölçümü göstermeyen her iddia er ya da geç bayatlar
                // (`docs/109` §8.7).
                'evidencePath' => $evidencePath,
            ];
        }

        return [
            'nodes' => array_values($graph['nodes'] ?? []),
            'edges' => $edges,
        ];
    }
}
