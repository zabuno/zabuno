# 143 — Paket bütçesi: kapının kendisi ölçüldü, sonra paket küçültüldü

**Paket:** FF-235. **Kapı:** `DS-BUNDLE-BUDGET-07`
(`resources/js/design-system/bundle-budget.test.ts`). **Bütçenin sahibi:**
`docs/06`; bu belge ölçümü ve ölçümün nasıl belirlenim hâline getirildiğini
anlatır.

> **Bu belge önce kapıyı suçlar, sonra paketi.** Kapı 2026-09-07'de tam
> eşikte duruyordu ve aynı commit iki ortamda farklı sayı veriyordu. Önce o
> farkın nereden geldiği ölçüldü — ve derlemeden gelmediği kanıtlandı. Sonra
> paketin gerçekten ağır olan yeri bulundu ve 11 KB atıldı. Eşiğe
> dokunulmadı; eşik **düşürüldü**.

---

## 0. Sahibin sorusu: "aynı kod niye bir bilgisayarda geçiyor, ötekinde kalıyor?"

Bir restoran zincirinin müdürü sabah panele giriyor. Panelin açılması, tarayıcının
indirdiği JavaScript kadar sürüyor. Zabuno bunun için bir tavan koymuş:
masaüstü çalışma alanının açılışta indirdiği her parçanın toplamı
**200 KB gzip**'i geçemez.

2026-09-07'de o tavan işe yaramaz hâle gelmişti. Ölçüm 199,7 ile 200,4
arasında dolaşıyordu; hangi tarafa düştüğü yazılan koda değil, testin **hangi
makinede** koştuğuna bağlıydı. Bu, bir kapının verebileceği en kötü cevaptır:
"belki". Sahip için anlamı şudur — panel yavaşladığında kapı susar, panel
hiç değişmediğinde kapı bağırır.

Bu depoda aynı arıza ailesi daha önce yaşandı: mobil denetim, yazı tipi
ortamdan ortama değişince oynuyordu ve çözüm eşiği gevşetmek değil,
**oynaklığı kaldırmak** olmuştu. Burada da aynısı yapıldı.

---

## 1. Ne ölçüldü: kapının kırmızı/yeşil kararı gerçekten koda mı bağlıydı?

Hipotez kurulmadı; aynı commit iki ortamda derlendi ve çıktılar dosya dosya
karşılaştırıldı.

**Seçilen commit:** `55c57e6` — CI'nın PR #320 için gerçekten çıkardığı birleşme
commit'i (CI günlüğü: `HEAD is now at 55c57e6 Merge 501eca3… into c1414278…`).
Yani karşılaştırılan iki taraf **aynı ağaçtır**, benzeri değil.

| | Ortam | Node | `DS-BUNDLE-BUDGET-07` sonucu |
| --- | --- | --- | --- |
| CI | ubuntu-latest | v24.20.0 (resmi yapı) | **200,065 KB** — kırmızı |
| Yerel | macOS | v24.6.0 (Homebrew) | **199,771 KB** — yeşil |

Fark: **0,294 KB**, yani kapının kararını değiştirmeye yeten miktarın tamamı.

---

## 2. Kök neden ÖLÇÜLDÜ: fark derlemede değil, ÖLÇEN tarafta

### 2.1 Derleme aynı — 117 dosyanın 117'si

Vite'ın kendi derleme raporu iki taraftan da alındı ve satır satır
karşılaştırıldı: **hiçbir fark yok.** 117 JS dosyasının adı ve boyutu birebir
aynı.

Bu, "yakın" demek değildir. Rollup parça adını **içerikten** türetir
(`BuildTruthBanner-B9US0IXq.js`); adın aynı olması içeriğin baytına kadar aynı
olduğunun kanıtıdır. Vite'ın kendi gzip raporu da iki tarafta aynı çıktı
(ör. `BuildTruthBanner` → 66,17 kB, her iki tarafta).

Aynı sonuç yerelde de doğrulandı: art arda iki derleme, hatta farklı bir çıktı
dizinine yapılan derleme bile, bayt bayt aynı paketi üretiyor.

**Sonuç: derleme belirlenimdir.** Ortama bağlı girdi orada değil.

### 2.2 Fark, testin kendi `gzipSync` çağrısında

Geriye tek bir aday kalıyor: kapı, aynı baytları okuduktan sonra onları
`node:zlib`'in `gzipSync`'iyle sıkıştırıp ölçüyor. O çağrının çıktısı, koşan
Node'un **hangi zlib gerçeklemesine bağlandığına** göre değişir.

Yerel Node paylaşılan bir zlib'e bağlı (`process.config.variables.node_shared_zlib
= true`, `process.versions.zlib = 1.2.12`); CI'ın resmi Node yapısı kendi
kopyasını taşır. Farklı gerçekleme, aynı baytlar, farklı boyut.

Aynı duyarlılık yerelde de gösterilebiliyor — aynı dosya, iki ayrı
gerçekleme:

| Sıkıştırıcı | `BuildTruthBanner` (212.484 bayt ham) |
| --- | --- |
| Node `gzipSync` (zlib 1.2.12) | 65.346 bayt |
| Apple `gzip -6` | 65.375 bayt |
| Vite'ın kendi raporu | 66.170 bayt |

Üçü de "gzip seviye 6", üçü de farklı sayı.

### 2.3 Ölçülemeyen: CI'ın zlib'inin tam sürümü

CI'ın Node yapısı bu makinede koşturulamadı, bu yüzden **CI zlib'inin sürümü
ve tam çıktısı doğrudan ölçülmedi** — bilinen, CI'ın `gzipSync` sonucunun
yerelinkinden %0,15 büyük olduğudur (0,294 / 199,771). Bandın kaynağının zlib
gerçeklemesi olduğu, geriye kalan tek değişken olmasıyla kanıtlanmıştır;
sürüm numarası **bilinmiyor**.

### 2.4 Elenen adaylar

| Aday | Ölçüm | Sonuç |
| --- | --- | --- |
| `npm ci` ile `npm install` farkı | Kurulu 10 anahtar paketin sürümü `package-lock.json` ile birebir | Elendi |
| Vite / Node sürüm farkının derlemeye etkisi | 117/117 dosya aynı | Elendi |
| Tailwind'in ortama bağlı kaynak taraması (`storage/framework/views/*.php`) | Kapı yalnız `.js` sayar, CSS'i saymaz | Konu dışı |
| `__ZABUNO_BUILD_REVISION__` (derlemeye gömülen git özeti) | Değeri değiştirilip ölçüldü: 199,654 → 199,634 | **Gerçek ama küçük: 0,02 KB.** `55c57e6` karşılaştırmasında iki taraf da aynı commit'i gördüğü için o farkın kaynağı bu değildi |
| Sıralı iki derleme | Bayt bayt aynı | Elendi |

---

## 3. Neyin ağır olduğu — rakamla

Masaüstü kapanışının 2026-09-07'deki üyeleri (yerel ölçüm, KB gzip):

| Parça | KB gzip | İçindeki asıl ağırlık |
| --- | --- | --- |
| `BuildTruthBanner-*` | 63,81 | **react-dom (523 KB kaynak)** — parça adı tesadüf |
| `WorkspaceSwitcherTrigger-*` | 52,22 | `@floating-ui/react` + `tabbable` (≈282 KB kaynak) ve `WorkspaceApp` |
| `workspace-*` | 29,44 | **İngilizce çalışma alanı kataloğu** (13 modül, ≈192 KB kaynak) |
| `create-theme-*` | 19,42 | `tailwind-merge` ×2 + `deepmerge-ts` |
| `dashboard-*` | 11,35 | `tailwind-merge` ×1 |

### 3.1 `BuildTruthBanner` bir geliştirme şeridi — ama paketi o şişirmiyor

Görev tanımındaki şüphe ölçüldü: parçanın adı `BuildTruthBanner`'dan geliyor,
**içeriği ondan gelmiyor.** Bileşenin kendisi 3,2 KB kaynak; parçanın 523,5
KB'ı `react-dom`. Şeridi üretimden çıkarmak kapanışı ölçülebilir biçimde
küçültmez. **Yapılmadı.**

### 3.2 Çeviri katalogları: çevrilmiş olanlar zaten dışarıda, İngilizce taban içeride

Çevrilmiş katalog paketin **içinde değil**: `workspace.tr-*.js` (25,83 KB gzip)
dinamik olarak yükleniyor ve kapanışta görünmüyor.

Ama **İngilizce taban katalog içeride**: `resources/js/i18n/workspace.ts`,
`import.meta.glob('./workspace/*.ts', { eager: true })` ile 13 bölümün
tamamını açılışa çekiyor — `media.ts` 57,9 KB, `publication.ts` 38,9 KB dahil.
Oysa Medya ve Yayın sayfaları zaten ayrı, geç yüklenen parçalar.

Bu, kapanıştaki **en büyük ikinci** kaldıraç (≈29 KB gzip) ve **bu pakette
yapılmadı**: `t()` eşzamanlı çalışıyor, eksik bir anahtar ekrana ham anahtar
olarak düşer, ve i18n kapıları (`i18n.guard.test.ts`,
`WorkspaceModuleCatalog.test.ts`, `pipeline.guard.test.ts`) katalog yapısına
bağlı. Davranış değiştirmeden yapılabilir, ama kendi paketini hak ediyor.

### 3.3 Yapılan: aynı kütüphanenin ÜÇ kopyası tek kopyaya indi

`flowbite-react`, Tailwind 3 ve Tailwind 4 kurulumlarının ikisinde birden
çalışabilmek için `tailwind-merge`in iki ayrı sürümünü takma adla bağımlılık
listesine alıyor ve `helpers/tailwind-merge.js` **ikisini de statik olarak**
içe aktarıyor; hangisinin çalışacağına çalışma zamanında karar veriyor.
Paketleyici için bu "ikisi de gerekli" demektir. Depo ayrıca kendi `cn()`
yardımcısı için üçüncü bir kopya taşıyor:

| Kopya | Sürüm | Kaynak |
| --- | --- | --- |
| `tailwind-merge` | 3.6.0 | 103,1 KB |
| `tailwind-merge-v3` | 3.4.0 | 94,9 KB |
| `tailwind-merge-v2` | 2.6.1 | 72,5 KB |
| | | **270,5 KB — kapanışın %13,9'u** |

Zabuno Tailwind 4 kullanıyor (`package.json` → `tailwindcss ^4`) ve
`.flowbite-react/config.json` sürümü 4. `flowbite`ın yardımcısı v2 dalına
yalnız `version === 3` iken giriyor; yani **v2 kopyası hiç çalışmıyor** ve v3
kopyası deponun kendi kopyasıyla aynı majör.

`vite.config.ts` iki takma adı deponun kopyasına yönlendiriyor.

**Ölçülen etki (ayrı ayrı ve birlikte):**

| Durum | Masaüstü kapanışı | Fark |
| --- | --- | --- |
| Öncesi | 199,654 KB gzip | — |
| Yalnız `tailwind-merge-v3` yönlendirilmiş | 191,857 KB | −7,80 |
| Yalnız `tailwind-merge-v2` yönlendirilmiş | 191,019 KB | −8,64 |
| **İkisi de** | **188,528 KB** | **−11,13 (%5,6)** |

---

## 4. Davranış değişmedi — nasıl kanıtlandı

Değişiklik bir **çözümleme** değişikliğidir: hiçbir bileşen, hiçbir geç yükleme
sınırı, hiçbir ekran yeniden düzenlenmedi. `Suspense` sınırı eklenmedi, bir şey
geç yüklenir hâle getirilmedi — bu yüzden ekranda gecikme ya da sıçrama
üretecek bir yüzey yok.

Kanıt zinciri:

1. `tailwind-merge-dedupe.test.ts` — takma adın **yürürlükte** olduğunu
   (üç tanımlayıcı da aynı modül nesnesine çözülüyor), **güvenli** olduğunu
   (Tailwind 4 kullanılıyor, deponun kopyası 3.x, flowbite hâlâ tam olarak o
   iki tanımlayıcıyı içe aktarıyor) ve **davranışın aynı** olduğunu
   (`twMerge('px-2','px-4') === 'px-4'`) ölçer. Takma ad bir gün sessizce
   düşerse bu kapı bağırır; ölçülmeyen bir kazanç kazanç değildir.
2. Vitest paketinin tamamı — `WorkspaceApp` ve tasarım sistemi bileşenleri
   sınıf birleştirmenin üstünde duruyor.
3. `scripts/mobile-ux-audit` — 320 pikselde, gerçek Chrome'da. Sınıf
   birleştirme yanlış sonuç verse ilk kırılacak yer düzendir.

---

## 5. Ölçüm belirlenim hâline getirildi

Kapı artık iki iddia taşıyor.

### 5.1 Asıl eşik HAM BAYT

`maxTotalRawKb` **belirlenimdir**: §2.1'de kanıtlandığı gibi aynı commit her
ortamda bayt bayt aynı paketi üretir. Gzip eşiği `docs/06` ile sürekliliği
koruduğu için duruyor, ama artık tek başına karar vermiyor.

Ham eşik gevşek değil, **daha sıkı** bir kısıttır: gzip ham bayttan asla büyük
olamaz, ve sıkıştırılabilir yığın — büyük tekrarlı bir çeviri tablosu gibi —
gzip'te ucuz görünürken ham baytta görünür.

### 5.2 Kapı kendi kararlılığını kanıtlıyor

`bundle-budget.test.ts` ikinci bir iddia taşıyor: aynı kaynaktan **ikinci bir
derleme** yapılır ve kapanıştaki her parçanın adı ile boyutu birincisiyle
karşılaştırılır. Parça adı içerikten türediği için "aynı ad + aynı boyut"
aynı bayt demektir; toplam tutup içerik kaymış olamaz.

Bu iddia yazılırken kendi arızasını buldu: vitest kendi sürecine
`NODE_ENV=test` koyuyor ve onu miras alan alt derleme React'in **geliştirme**
çalışma zamanını paketliyordu — 642 KB'lık kapanış 875 KB oluyordu. Alt
derleme artık `NODE_ENV`i ve `--mode production`ı açıkça veriyor.

### 5.3 Kalan ve KALDIRILAMAYAN oynaklık

`gzipSync`in gerçeklemeye bağlılığı testin içinden kaldırılamıyor: Node'un
zlib'i dışarıdan gelir. İki şey yapıldı — karar artık belirlenim olan ham
bayta da bağlı, ve gzip eşiği ölçülen bandın **on katından fazla** payla
seçildi.

Ayrıca kaydedilmesi gereken bir dürüstlük notu: testin gzip sayısı hiçbir
zaman ziyaretçinin gerçekten indirdiği bayt değildi. Ziyaretçiye giden
sıkıştırmayı sunucu yapar, kendi zlib'iyle ve kendi seviyesiyle. Test sayısı
bir **vekildir**; vekilin ortamdan ortama oynaması, vekil olduğunu unutmanın
bedeliydi.

---

## 6. Bugünkü bütçe ve gerçek pay

| Yüzey | Ham (KB) | Gzip (KB) |
| --- | --- | --- |
| `workspace.desktop` | **642,68** | **188,53** |
| `workspace.mobile` | 620,66 | 181,83 |
| `platform` | 419,32 | 122,08 |
| `engineering` | 349,42 | 105,31 |
| `auth` | 298,28 | 90,39 |

| Bütçe | Değer | Bugünkü tepe | Pay |
| --- | --- | --- | --- |
| `maxTotalRawKb` | 655 | 642,68 | %1,9 |
| `maxTotalGzipKb` | **200 → 192** | 188,53 | %1,8 |

Gzip bütçesi **düşürüldü**. 200 yuvarlak bir sayıydı, ölçülmüş bir eşik
değil; 192 bugünkü tepenin üstüne ölçülmüş bir pay bırakır ve o pay, ölçülen
ortam bandının (%0,15) on katından fazladır.

**Yükseltilmedi.** Kapının kendi cümlesi hâlâ geçerli: bütçeyi yükseltmek,
kullanıcının ekranı açarken beklediği süreyi uzatmaktır.

---

## 7. Ne hâlâ koşamıyor

- **İngilizce taban katalog hâlâ açılışta.** ≈29 KB gzip, kapanışın %15'i.
  Ölçüldü, adı kondu, yapılmadı (§3.2).
- **`@floating-ui` + `tabbable` (≈282 KB kaynak) hâlâ açılışta.** `flowbite`ın
  `Dropdown`u onları statik çekiyor. Geç yüklemek menünün ilk açılışını
  geciktirir; davranış değiştirmeden yapılabileceği **ölçülmedi**.
- **CI zlib'inin sürümü bilinmiyor** (§2.3).
