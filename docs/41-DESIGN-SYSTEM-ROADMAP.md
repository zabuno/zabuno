# 41 — Tasarım sistemi yol haritası (adlandırılmış ek plan: `DESIGN-2030-v1`)

**Bu belge sabit 38-WP payda sayacını DEĞİŞTİRMEZ.** `docs/17` §4 kuralı
gereği kapsam adlandırılmış bir ek planla büyür. `docs/26` matrisindeki WP
satırları ve `docs/17` §4 sayacı olduğu gibi kalır.

Kaynak: `docs/36` §5 (külliyattan çıkarılan sekiz kanonik karar),
`docs/37` (frontend ana planı), `docs/35` (Storybook/bileşen fabrikası
sözleşmesi) ve `docs/design-corpus/`.

## Neden bir ek plan gerekti

Owner 2026-08-27'de panelin *"güncel bir SaaS projesi için bile uygun
olmadığını"* söyledi ve 2030 vizyonunu hedef olarak hatırlattı. İlk bakışta
bu bir zevk itirazı gibi görünür. Ölçünce öyle olmadığı çıktı.

**Bulunan kök sebep:** token kökü, geometri için gerçekten kök değil.

`resources/css/app.css` `--space-1..8` yayınlar. Bileşenler ise Tailwind'in
kendi `--spacing` ölçeğini kullanır ve o ölçek bu depoda **hiç
tanımlanmamıştır** — Tailwind varsayılanı (`0.25rem`) devrededir. Derlenmiş
CSS bunu açıkça gösterir:

```
.p-4 { padding: calc(var(--spacing) * 4) }   /* --spacing: .25rem  ← Tailwind */
:root { --space-4: 16px }                     /* ← tasarım sistemi, tüketen yok */
```

İki ölçek sayısal olarak çakışıyor (4/8/12/16…), bu yüzden kimse fark
etmemiş. Ama `--space-4`'ü değiştirmek ekranda hiçbir şeyi değiştirmez.
Owner'ın istediği *"master değişince hepsi değişir"* davranışı boşlukta
yoktur — tipografide olmadığı gibi.

Bu, külliyatın **kendi en önemli maddesi** olarak işaretlediği kuralın
ihlalidir (`docs/36` §5.4): *"bileşen hiçbir zaman doğrudan 8px, 16px, 12px
radius bilmez; yalnız semantic token bilir."*

## Ölçülen açık (2026-08-27)

| Kanonik karar | Durum | Ölçüm |
| --- | --- | --- |
| §5.4 bileşen ham geometri bilmez | ✗ | **323** ham boşluk, **56** ham radius |
| §5.4 token zinciri tek kök | ✗ | `--space-*` yayınlanıyor, tüketen yok |
| §5.1 tipografi önceliği | ⚠ kilitli | **283** kullanım gövde tabanının altında |
| §5.5 density height+padding ile | ⚠ ölü | 3 mod tanımlı, **hiçbir bileşen uygulamıyor** |
| §5.2 contextual cards | ⚠ | **21** kart kullanımı gözden geçirilmedi |
| §5.6 320px-first, container-query | ⚠ | **2** container query, **12** breakpoint sınıfı |
| §5.6 RTL-native, logical property | ✓ | **0** fiziksel yön sınıfı, 9 logical |
| §5.8 tema/density R1'de çözülür | ✓ | token seviyesinde, bileşene sızmıyor |
| `docs/35` Storybook zinciri | ⚠ | **58** story / **191** bileşen |

İyi haber iki tanedir ve gerçektir: RTL temiz, tema/density doğru katmanda.
Kalanı sırayla kapanır.

---

## Faz 1 — Token kökü gerçekten kök olsun (Stage 1 kalanı)

**Neden önce:** Faz 2'deki her görsel düzeltme boşluk ve radius kullanır.
Kök bağlanmadan yapılan düzeltme, kök bağlanınca yeniden yapılır.

| # | İş | Kanıt |
| --- | --- | --- |
| **1-a** | `--spacing` depo token'ına bağlanır; `p-4`/`gap-2` artık tasarım sistemine çözülür | Kökteki tek değer değişince 323 kullanım birden değişir |
| **1-b** | Radius semantic token'a alınır (`--radius-control`, `--radius-surface`…) | 56 ham `rounded-*` → 0; kapı artışı engeller |
| **1-c** | Tipografi borcu eritilir | `typography-debt.json` 283 → 0, kademeli |
| **1-d** | Density modları canlandırılır | `.density-*` bir kapsayıcıya uygulanır; satır yüksekliği font'a dokunmadan değişir |

**Not (1-d):** altyapı doğru yazılmış ama ölü. `.density-comfortable` 52px,
`.density-compact` 36px tanımlı; hiçbir bileşen uygulamıyor. Yani külliyatın
"comfortable / standard / compact" kararı bugün bir ürün yeteneği değil.

---

## Faz 2 — Ekranda görünen kusurlar (Stage 1 kalanı)

**Bağımlılık: Faz 1.** Hepsi 1440×900 ve 800×450 ekran görüntüsüyle
doğrulanmıştır; hiçbiri tahmin değildir.

| # | İş | Neden |
| --- | --- | --- |
| **2-a** | Tema anahtarı içeriğin üstünden kalkar | Ekranın altında yüzüyor ve formun üstünü örtüyor. Kalıcı bir tercih kontrolü, içeriğin önünde duran bir katman olamaz. **21 test çevreliyor** — ayrı paket şart |
| **2-b** | Birincil eylem disiplini formlara da uygulanır | "Kategori ekle" hâlâ tam genişlik marka-sarısı bant. Menü satırlarında düzeltildi, formlarda düzeltilmedi |
| **2-c** | Kart disiplini | §5.2: *her bilgi grubunu karta sokmak yasak*; spacing ve proximity zaten gruplama üretir. 21 kullanım tek tek değerlendirilir |
| **2-d** | Yatay alan kullanımı | 1440px'de içerik tek dar kolonda; sağda geniş boşluk. Data-dense kimlikle çelişir |
| **2-e** | Kenar çubuğu hiyerarşisi | Gezinme öğeleri ile "ACCOUNT" bölüm etiketi aynı ağırlıkta; hiyerarşi yok |

---

## Faz 3 — Katman sözleşmesi R1–R8 (Stage 2)

**Bağımlılık: Faz 1.**

`docs/36` §6 açıkça kaydeder: bu depodaki micro/compound/macro modeli,
külliyattaki R1–R8 modelinin **kaba bir yaklaşımıdır**. Bilinen sapma: R4
görsel primitive ile R6 bileşen aynı kutuya düşüyor, bu yüzden *"yatay ve
yukarı bağımlılık yasak"* kuralı burada tam uygulanamıyor.

| # | İş |
| --- | --- |
| **3-a** | `10-frontend-katman-mimarisi.md` ve `13-foundation-contract.md` depoya taşınır — bugün yalnız atıfla anılıyorlar |
| **3-b** | micro/compound/macro → R1–R8 inceltmesi |
| **3-c** | Bağımlılık yasağı kapıyla zorlanır |

---

## Faz 4 — Storybook zinciri (Stage 2)

**Bağımlılık: Faz 1, Faz 3.** Owner bu zinciri tekrar tekrar istedi.

| # | İş | Bugün |
| --- | --- | --- |
| **4-a** | Storybook bilgi mimarisi (00–08) ve kapı kuralı | `docs/35`'te sözleşme var, uygulama kısmi |
| **4-b** | Kapsam | **58 story / 191 bileşen** |
| **4-c** | Token dokümantasyonu Storybook'ta yayınlanır | Yok |

---

## Faz 5 — Frontpages aynı zincire (Stage 2)

**Bağımlılık: Faz 1.** Owner kuralı açıkça koydu: *aynı frontend kuralları
yalnız panele değil frontpage'lere de uygulanır.*

Açılış ve yasal sayfalar bugün SSR Blade'dir ve panelle aynı token zincirini
kullanmaz. `docs/40` Faz 3 ile kesişir: aynı sayfalardaki **71 çevrilemez
dize** de oradadır. İkisi tek geçişte yapılır — dosyalar aynı.

---

## Faz 6 — 2030 vizyonu (Stage 3+)

**Bağımlılık: Faz 1–4.** Owner'ın hedef olarak koyduğu ufuk budur ve
külliyatta tanımı vardır (`docs/36` §5.7, `saas-panel-tasarim-sistemi.md`).

| # | İş | Kural |
| --- | --- | --- |
| **6-a** | Ekranlar semantik görev tanımı olarak modellenir | Görev-uyarlamalı arayüz; düzen sabit şablon değil, görevin sonucu |
| **6-b** | AI slot'lar üzerinden katılır | **AI düzenin katılımcısıdır, otoritesi değildir.** Kritik yolculuk AI kapalıyken deterministik yürür |

Faz 6 önden yapılmaz. Kök bağlanmadan, katman sözleşmesi netleşmeden ve
Storybook zinciri kurulmadan "görev-uyarlamalı arayüz" yalnız bir görüntü
katmanı olur — külliyatın uyardığı şey tam olarak budur.

## İlerleme

`DESIGN-2030-v1`: **0/6 faz tamam.** Faz 1 sıradaki iştir ve owner kararı
gerektirmez — kararlar `docs/36` §5'te zaten donmuştur.

Bu sayaç `docs/17` §4'teki sabit 38-WP payda sayacından ayrıdır ve onun
yerine geçmez.
