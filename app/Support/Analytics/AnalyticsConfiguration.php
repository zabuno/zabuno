<?php

declare(strict_types=1);

namespace App\Support\Analytics;

/**
 * `config/analytics.php`'yi okuyup iki soruya cevap veren tek yer:
 * "ölçüm açık mı?" ve "CSP hangi adreslere izin vermeli?".
 *
 * Bu sınıf olmadan CSP kodu ile yapılandırma birbirinden habersiz yaşardı:
 * biri araç ekler, diğeri engellerdi ve sonuç sessiz bir veri kaybı olurdu —
 * sayfa çalışır, raporlar boş gelirdi. Sessiz veri kaybı, gürültülü hatadan
 * çok daha pahalıdır; bu yüzden izinler açılan araçtan TÜRETİLİR, elle
 * yazılmaz.
 */
final class AnalyticsConfiguration
{
    /**
     * @param  array<string, bool>  $destinations
     * @param  array<string, array<string, list<string>>>  $sources
     */
    public function __construct(
        private readonly string $containerId,
        private readonly array $destinations,
        private readonly array $sources,
    ) {}

    /**
     * KASADAN GELENLER ÖNCE, YAPILANDIRMA (env) SONRA — `docs/135` §3.
     *
     * `$vault` boşken davranış BUGÜNKÜNÜN AYNISIDIR: kimlik ortam
     * değişkeninden gelir. Bu bir nezaket değil zorunluluk — kasaya kimlik
     * girilmemiş her kurulum, bu paketten sonra da ölçmeye devam etmeli
     * (`docs/126` §1).
     *
     * Hedefler kasada kapalı uçlu bir dizedir (`on`/`off`); yalnız TAM
     * `on` açar. Beklenmedik bir değer KAPALI sayılır: bir hedefi yanlışlıkla
     * açmak, tarayıcıya fazladan bir adres yetkisi vermek demektir ve
     * belirsizlikte doğru taraf kapalı olandır.
     *
     * @param  array<string, string>  $vault  Kasadan çözülmüş alanlar; boş olabilir.
     */
    public static function fromConfig(array $vault = []): self
    {
        /** @var array<string, mixed> $config */
        $config = (array) config('analytics', []);

        /** @var array<string, mixed> $destinations */
        $destinations = (array) ($config['destinations'] ?? []);

        foreach (array_keys($destinations) as $name) {
            if (array_key_exists((string) $name, $vault)) {
                $destinations[$name] = $vault[(string) $name] === 'on';
            }
        }

        $containerId = array_key_exists('container_id', $vault)
            ? $vault['container_id']
            : (string) ($config['gtm_container_id'] ?? '');

        return new self(
            containerId: trim($containerId),
            destinations: $destinations,
            sources: (array) ($config['csp_sources'] ?? []),
        );
    }

    /**
     * Konteyner kimliği yoksa ölçüm yoktur.
     *
     * "Kapalı" burada gerçekten kapalı demektir: script yüklenmez, CSP
     * gevşemez. Yerel geliştirme ve test ortamı bu durumdadır, dolayısıyla
     * testler ağ üzerinden bir ölçüm aracına ASLA bağlanmaz.
     */
    public function isEnabled(): bool
    {
        return $this->containerId !== '';
    }

    public function containerId(): string
    {
        return $this->containerId;
    }

    /**
     * `dataLayer`'a basılacak ilk kayıt.
     *
     * Görünümde değil BURADA kurulur, çünkü bu bir SÖZLEŞMEDİR: alan adları
     * GTM'deki değişken tanımlarıyla birebir eşleşmek zorundadır. Blade
     * içinde dursaydı hem test edilemez olurdu hem de dört ayrı görünümde
     * dört ayrı biçimde yazılabilirdi.
     *
     * `zabuno_tenant_id` ve `zabuno_tenant_slug` her zaman VARDIR; yüzey
     * bilmiyorsa boş geçer. Alanın hiç olmaması ile boş olması GTM'de farklı
     * şeylerdir: olmayan alan sessizce "tanımsız" olur ve o olay tenant
     * kırılımının dışına düşer.
     *
     * @param  array<string, string>  $context
     * @return array<string, string>
     */
    public function dataLayerPayload(array $context, string $locale): array
    {
        return array_merge([
            'zabuno_surface' => 'public',
            'zabuno_tenant_id' => '',
            'zabuno_tenant_slug' => '',
            'zabuno_locale' => $locale,
        ], $context);
    }

    /**
     * Açık araçların bir CSP yönergesi için gerektirdiği adresler.
     *
     * GTM her zaman dahildir (kapıyı o açar); diğerleri yalnız açıksa gelir.
     *
     * @return list<string>
     */
    public function cspSourcesFor(string $directive): array
    {
        if (! $this->isEnabled()) {
            return [];
        }

        $active = ['gtm'];

        foreach ($this->destinations as $name => $enabled) {
            if ($enabled === true) {
                $active[] = (string) $name;
            }
        }

        $collected = [];

        foreach ($active as $tool) {
            foreach ((array) ($this->sources[$tool][$directive] ?? []) as $source) {
                $collected[] = (string) $source;
            }
        }

        return array_values(array_unique($collected));
    }
}
