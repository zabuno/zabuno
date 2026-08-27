# 39 — URL/SEO yol haritası (adlandırılmış ek plan: `URL-SEO-v1`)

**Bu belge sabit 38-WP payda sayacını DEĞİŞTİRMEZ.** `docs/17` §4 kuralı
kapsam değiştiğinde önceki sayacın geriye yazılmamasını, bunun yerine
adlandırılmış bir plan açılmasını şart koşar. Bu, o plandır: adı
`URL-SEO-v1`, kendi maddeleri var ve her maddesi **mevcut bir stage'e**
eşlenir. `docs/26` matrisindeki WP satırları ve `docs/17` §4 sayacı olduğu
gibi kalır.

Kaynak: `docs/38-URL-POLICY.md`. Orada "henüz yapılmayanlar" diye geçen her
madde burada bir faza, bir tetikleyiciye ve bir kanıta bağlanır — çünkü
"sonra bakarız" listesi, bakılmayan listedir.

## Neden bir ek plan gerekti

URL motoru kurulurken bazı işler **bilerek** yapılmadı. Sebep tembellik
değildi: o işler ya henüz var olmayan URL'lere ya da alınmamış kararlara
bağlıydı. Olmayan bir adres için yönlendirme tablosu kurmak, çalışmayan bir
şeyi bakımı gereken bir şeye çevirir.

Ama iki karar 2026-08-27'de alındı ve tabloyu değiştirdi:

- **Menüler arama motorunda görünecek.** Bu, keşif katmanını (sitemap,
  hreflang, yapılandırılmış veri) spekülasyon olmaktan çıkarıp gereksinim
  yaptı.
- **Beş barındırıcıda birden kalıcı çalışılacak.** Bu, özel alan adı işini
  ucuzlatmıyor; tersine, sertifika otomasyonunun paylaşımlı barındırmada
  mümkün olmadığını baştan kabul ettiriyor.

## Faz 1 — Menü keşfi (Stage 1 kalanı)

**Tetikleyici zaten oluştu:** menülerin indekslenmesi kararı ve
`/menu/{key}/{slug}` kalıcı adresinin var olması. Bu faz olmadan karar
kâğıt üstünde kalır: adres vardır ama arama motoru onu bulamaz.

| # | İş | Neden şimdi | Kanıt |
| --- | --- | --- | --- |
| 1.1 | `sitemap.xml` (+ 50k üstü için index) | Arama motorunun menüleri keşfetme yolu; iç bağlantı yok | Yalnız 200 dönen, kanonik, indekslenebilir URL'ler; token İÇERMEZ |
| 1.2 | `hreflang` kümesi | Altı locale zaten var; karşılıklı olmayan küme Google tarafından toptan yok sayılır | Karşılıklı + kendini içeren küme, `x-default` |
| 1.3 | Schema.org `Restaurant`/`Menu` JSON-LD | Menü sayfasının zengin sonuç uygunluğu; görünür içerikle birebir | Sayfada olmayan hiçbir şey işaretlenmez |
| 1.4 | `is_indexable` kalite kapısı | Sütun var, onu ayarlayan kural yok. Boş/deneme menüyü aramaya açmak alan adı kalitesini düşürür | Yayınlanmış + en az bir görünür ürün + deneme tenant değil |

**Owner kararı gerekmez.** Karar zaten verildi; bu, kararın uygulanması.

## Faz 2 — Adres yaşam döngüsü (Stage 2)

**Tetikleyici:** marka/şube slug'ının URL'e girmesi veya bir işletmenin adını
değiştirmesi. Bugün menü adresi kendini onarıyor (`key` kimlik, yanlış slug
301), yani acil değil.

| # | İş | Tetikleyici | Not |
| --- | --- | --- | --- |
| 2.1 | Slug geçmişi + yönlendirme tablosu | Marka slug'ı bir URL'de görünür hâle gelirse | Bugün `key` sayesinde gerek yok; girdiği gün zorunlu |
| 2.2 | Admin sorgu parametresi allowlist | Admin filtre/sıralama durumu URL'e taşınırsa | Bugün React state'te; URL'e taşımak ayrı bir ürün kararı |
| 2.3 | Tenant başına indeks tercihi | Bir işletme "menüm aramada çıkmasın" derse | Ürün kararı; varsayılan Faz 1.4'teki kalite kapısı |
| 2.4 | CSP ihlal raporu uç noktası | Politika sıkılaştıkça kör noktayı görmek için | ASVS V3'ün açık bıraktığı madde |

## Faz 3 — Özel alan adı (Stage 3, GTM)

**Tetikleyici:** bir işletmenin kendi alan adını bağlamak istemesi (premium
yetenek). Talep gelmeden yapılmaz.

| # | İş | Not |
| --- | --- | --- |
| 3.1 | Alan adı sahiplik doğrulaması + durum makinesi | `pending → verified → active → suspended → detached → quarantined` |
| 3.2 | Sertifika otomasyonu | **Paylaşımlı barındırmada mümkün DEĞİL** (`docs/15` §4b). Yalnız netcup/Hetzner profilinde; paylaşımlı planlarda bu yetenek yoktur ve müşteriye öyle söylenir |
| 3.3 | Kanonik host geçişi | Tenant için hangi host kanonik — tek merkezden |
| 3.4 | Devralma (takeover) koruması | Tenant kapanınca DNS kaydı kalırsa içerik servis edilmez; karantina süresi |

**Owner kararı gerekir:** özel alan adı hangi pakette, hangi barındırma
profilinde sunulacak. Paylaşımlı barındırmada teknik olarak veremeyeceğimiz
bir sözü satmamak için bu karar önden gerekir.

## Faz 4 — Yönetişim otomasyonu (Stage 5+)

**Tetikleyici:** tenant sayısının elle denetlenemeyecek kadar artması.

| # | İş | Not |
| --- | --- | --- |
| 4.1 | Route sapma bekçisi | Yeni route'un indeks/auth/kanonik meta verisi eksikse PR'da uyarır |
| 4.2 | Üretim tarayıcısı | Soft 404, kanonik uyuşmazlığı, yönlendirme zinciri |
| 4.3 | QR filo sağlığı | Aktif kodlarda 302/`no-store`/hedef host doğrulaması |

Bunlar bugün yapılmaz çünkü denetlenecek yüzey elle görülebilecek kadar
küçük. Otomasyonu erken kurmak, bakımı olan ama karşılığı olmayan bir şey
üretir.

## Bu planın kendi durumu

`URL-SEO-v1`: **0/4 faz tamam.** Faz 1 sıradaki iştir ve owner kararı
gerektirmez.

Sayaç bu belgede sahiplenilir; `docs/17` §4'teki 0/8 stage sayacıyla
karıştırılmaz — o, stage Exit Gate'lerini sayar, bu ise bu ek planın
fazlarını.
