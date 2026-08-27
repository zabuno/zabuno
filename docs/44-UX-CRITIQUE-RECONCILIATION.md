# 44 — UX eleştirisinin külliyatla uzlaştırılması

Owner kararı (2026-08-27): **tasarım felsefesi belgeleri master'dır.** Dışarıdan
gelen UX eleştirisi bu belgelerin yerine geçmez; onların içinde uygulanır.

Bu belge o uzlaştırmayı yapar. Eleştirinin her önemli maddesi üç kovadan birine
düşer: **külliyatla aynı şeyi söylüyor**, **külliyatın ince olduğu yeri
dolduruyor**, ya da **külliyatla çelişiyor**. Üçüncü durumda külliyat kazanır ve
neden kazandığı yazılır.

## Nasıl ölçüldü

Külliyat metinlerinde kavram başına geçiş sayısı sayıldı. Sayı tek başına
kanıt değil ama neyin dondurulmuş bir karar, neyin boşluk olduğunu gösterir:

| Kavram | Külliyattaki geçiş |
| --- | ---: |
| yüzey / surface | 110 |
| disabled | 14 |
| badge / rozet | 7 |
| empty state / boş durum | 6 |
| **user journey** | **1** |

Sonuç şu: külliyat **görsel sistemi** ayrıntılı biçimde donduruyor, ama
**kullanıcı yolculuğunu** neredeyse hiç ele almıyor. Eleştirinin asıl katkısı
tam orada.

---

## 1. Aynı şeyi söyleyenler — yeni karar yok, uygulama var

| Eleştiri maddesi | Külliyattaki karşılığı |
| --- | --- |
| "Deterministic before AI" | §5.7 — *AI düzenin katılımcısıdır, otoritesi değildir; kritik yolculuk AI kapalıyken deterministik yürür* |
| "AI embedded, not appended" | §5.7 — *AI slot'lar üzerinden çalışır* |
| "Aesthetic means signal-to-noise" | Öncelik sırası — estetik **en son** sırada; görev tamamlama ilk |
| "One page, one primary outcome" | Flat 2.0 affordance disiplini |
| "Journey before module" | 2030 vizyonu — *ekranlar semantik görev tanımı* |
| Yüzey katmanları (canvas → overlay) | Külliyatın yüzey sistemi (110 geçiş) |
| "Her input full-width olmamalı" | Form disiplinleri |

Bunlar için tartışma yok. Külliyat zaten söylüyor; **yapılmamış olması bir
uygulama borcudur, bir karar eksikliği değil.**

## 2. Külliyatın ince olduğu yerler — eleştiri gerçekten ekliyor

| Eleştirinin getirdiği | Neden değerli |
| --- | --- |
| **User journey haritaları** | Külliyatta bir geçiş var. Navigasyonun modül listesi olmaması ilkesi var ama yolculuğun kendisi tanımlı değil |
| **Boş durum 4-parça standardı** (ne yok / neden / anlamı / şimdi ne) | Külliyat boş durumu altı yerde anıyor, standardı yok |
| **Rozet standardı** — kullanıcı durumu, entegrasyon durumu değil | `Invitations connected` gibi rozetler geliştirme durumunu kullanıcıya taşıyor |
| **Sayfa durum envanteri** (loading/empty/populated/validation/system error/permission/prerequisite/plan/degraded/success) | Külliyatta yok. "State is design" ilkesi doğru ve eksikti |
| **Devre dışı kontrol standardı** | Bir şey yalnız *planlandığı için* devre dışı gösterilmez |
| **Tenant / platform / engineering yüzey ayrımı** | Bilgi mimarisi kararı; külliyat görsel sistemi dondurur, yüzey ayrımını ele almaz |
| **"3 Neden" kapısı** (bu kullanıcı / şimdi / burada) | Külliyatın öncelik sırasını her öğe için uygulanabilir bir teste çevirir |

Bunlar külliyata **eklenir**, onu değiştirmez.

## 3. Çelişen tek madde — ve külliyat neden kazanır

**Eleştiri §11.2**, sayfa genişliklerini sabit piksel aralıklarıyla veriyor:
onboarding 560–720 px, ayarlar 720–880 px, liste 1200–1440 px.

**Külliyat §5.3** ise şunu donduruyor: *UI geometrisi logical design token;
responsive `fr`/`%`/container unit*. Ve `ASG-320`: **320px gerçek başlangıç
noktasıdır, container-query önceliklidir.**

İkisi aynı şeyi istiyor — okunabilir satır uzunluğu — ama farklı mekanizmayla.
Sabit piksel:

- kapsayıcıya değil ekrana tepki verir, yani kenar çubuğu açılıp kapandığında
  yanlış olur;
- külliyatın "bileşen ham geometri bilmez" kuralını (§5.4, külliyatın kendi
  **en önemli** maddesi) doğrudan çiğner;
- 320px-first yaklaşımıyla ters yönde çalışır.

**Karar:** genişlik hedefleri kabul edilir, **ifade biçimi reddedilir.** Aynı
sonuç semantic token ve container query ile ifade edilir:

```
--measure-form      /* dar: tek sütunlu form */
--measure-content   /* standart okuma genişliği */
--measure-table     /* geniş: karşılaştırmalı veri */
```

Bileşen `560px` bilmez; `--measure-form` bilir.

**İkincil dikkat:** eleştirinin medya grid'i ve kart yoğun düzenleri,
külliyatın *"her bilgi grubunu karta sokmak yasak"* kuralıyla çarpışabilir.
Galeri bağlamsal bir karttır ve serbesttir; ama form bölümleri, durum satırları
ve liste öğeleri karta sokulmaz.

## 4. Tasarım kararı olmayanlar — sahibinin alanı

Eleştirinin bazı maddeleri UX estetiği değil, **ürün kapsamı** kararıdır ve
tasarım paketinde çözülmez:

| Madde | Neden owner kararı |
| --- | --- |
| Billing/Ledger/manuel ödeme → Platform Administration | Hangi yüzeyin hangi ürüne ait olduğu; yetki ve fiyatlandırma sonucu |
| Launch readiness → Engineering Console | Aynı |
| Analytics MVP kapsamı | `docs/18` ve `docs/12`'de kayıtlı; değişirse yeni plan |
| Hash yerine gerçek route yapısı | Mimari değişiklik; ayrı paket, ayrı risk |

Bunlar `docs/41` planına **iş** olarak girer, ama tetikleyicileri owner
kararıdır.

---

## 5. Uygulama sırası — `DESIGN-2030-v1` içine yerleşimi

Eleştiri yeni bir plan açmaz; var olan planın fazlarına dağılır.

| Eleştiri maddesi | Faz | Neden orada |
| --- | --- | --- |
| Yüzey katmanları, `--measure-*` token'ları | **Faz 1** | Token kökü işi; kalan her şey bunun üstüne kurulur |
| Rozet standardı, devre dışı standardı | **Faz 1** | Token ve semantik; bileşenden önce |
| Boş durum bileşeni, sayfa durum envanteri | **Faz 2** | Ekranda görünen kusur |
| Tema seçicinin içerikten çıkması | **Faz 2** | Zaten kayıtlı |
| Full-width buton ve form disiplini | **Faz 2** | Ekranda görünen kusur |
| Ölü kontrollerin gizlenmesi (arama, bildirim) | **Faz 2** | Çalışmayanı göstermemek |
| Görev odaklı navigasyon, yolculuk haritaları | **Faz 6** | 2030 vizyonunun kendisi; kök bağlanmadan yapılırsa görüntü katmanı olur |
| Yüzey ayrımı (tenant/platform/engineering) | Owner kararı sonrası | Ürün kapsamı |

**Sıra değişmiyor.** Faz 1 önce gelir çünkü eleştirinin görsel maddeleri
boşluk, yüzey ve radius kullanır; kök bağlanmadan yapılan düzeltme kök
bağlanınca yeniden yapılır.

## 6. Kabul ölçütü

Bu uzlaştırma ancak şunlar doğruysa uygulanmış sayılır:

- Eleştiriden gelen hiçbir madde külliyatın dondurulmuş bir kararını sessizce
  değiştirmemiş olmalı.
- Çelişen madde (sabit px) token olarak ifade edilmiş olmalı; kodda ham
  piksel genişlik bulunmamalı.
- Owner kararı gerektiren maddeler tasarım paketinde uygulanmamış, plana
  yazılmış olmalı.
