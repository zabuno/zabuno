<?php

declare(strict_types=1);

namespace App\Application\Media\Port;

/**
 * OLGUNLUK KANITI — bir iddianın bu depoda karşılığı var mı?
 *
 * Olgunluk ekranı bir puan gösterir ve o puan, elle yazılmış bir sayı
 * OLMAMALIDIR. Bu port, "şu yetenek şu basamağı geçti" iddiasının
 * sorgulanabilir hâlidir: her basamak bir ya da daha çok KANIT
 * referansına bağlanır, referans da buradan çözülür.
 *
 * ÜÇ CEVAP, İKİ DEĞİL. `hasRequirement` ve `hasTestMethod` `null`
 * dönebilir ve bu "hayır" DEĞİLDİR: test paketi bu ortamda okunamıyor
 * demektir (dağıtımda `tests/` klasörü bulunmayabilir). Göremediğimiz bir
 * şeyi "geçti" saymak da "kaldı" saymak da yalan olurdu; ekran
 * "denetlenemedi" der.
 *
 * `hasEndpoint` iki cevaplıdır ve olabilir: yönlendirici koleksiyonu her
 * zaman belleğe yüklüdür, okunamama diye bir hâli yoktur.
 */
interface MediaEvidencePort
{
    /**
     * Bu uç GERÇEKTEN kayıtlı mı?
     *
     * @param  string  $method  HTTP yöntemi, büyük harf (`GET`, `POST`…)
     * @param  string  $uri  yönlendiricinin gördüğü yol
     *                       (`api/workspaces/{workspace}/media`)
     */
    public function hasEndpoint(string $method, string $uri): bool;

    /**
     * Adlandırılmış gereksinim kimliği (`MEDIA-INTAKE-SIZE-REJECT-01`) test
     * paketinde geçiyor mu? `null` = denetlenemedi.
     */
    public function hasRequirement(string $requirementId): ?bool;

    /**
     * Adı verilen test yöntemi o test sınıfında duruyor mu?
     * `null` = denetlenemedi.
     */
    public function hasTestMethod(string $class, string $method): ?bool;
}
