# Public Archive Attestation

**PLANNING ONLY.** Bu, `evidence/archive-verification.md`'nin (ham, yerel,
public depoya **dahil edilmeyen** kanıt raporu — bkz.
`docs/31-PUBLIC-REPOSITORY-GOVERNANCE.md` §5, §8) **sanitize edilmiş** genel
özetidir. Bu dosya `.gitignore`'daki `/evidence/*` kuralının **tek
istisnasıdır** — public `zabuno/zabuno` deposuna dahil edilmek üzere
yazılmıştır. Mutlak dosya yolu, inode numarası, sembolik link hedefi, iç
geliştirme-orkestrasyon aracının adı/oturum/kapasite ayrıntısı veya iç Git
işaretçi (pointer) detayı **içermez**.

## 1. Ne yapıldı

Bu planlama paketinin hazırlanması sırasında, önceden var olan proje
kökündeki 99 üst-seviye girdi, tek tek (wildcard veya toplu taşıma araçları
kullanılmadan, içerik düzenlenmeden) bir arşiv dizinine taşındı. Bu planlama
külliyatının kendisi ve aktif çalışma alanları taşıma kapsamı **dışında**
tutuldu.

## 2. Bütünlük doğrulaması

Taşıma öncesi ve sonrası, her girdi için dosya sistemi meta verisi
(tür/aygıt/inode/izin/boyut/sembolik-link-hedefi) programatik olarak
karşılaştırıldı.

- **Karşılaştırılan girdi sayısı**: 99
- **Uyuşmazlık**: 0
- **Sonuç**: 99/99 girdi, taşımadan önce ve sonra birebir eşleşti — bu,
  taşımanın bir yeniden adlandırma/link işlemi olduğunu, içeriğin yeniden
  yazılmadığını doğrular.

## 3. Bilinen bir istisna — not, sızıntı değil

Taşınan girdilerden biri bir sembolik linktir; linkin **hedef metni**
taşımadan önce ve sonra byte-eş olarak korunmuştur. Göreli bir sembolik link
olduğu için, taşındığı yeni konumdan itibaren farklı bir mutlak yola
**çözülebileceği** not edilmiştir — bu bir veri değişikliği değildir, yalnız
göreli bir linkin taşınmasının doğal sonucudur. Link, provenance amacıyla
olduğu gibi korunmuştur; herhangi bir çalışan bağımlılık zinciri olarak
sunulmamaktadır.

## 4. Çalışma alanı sağlığı

Taşıma kapsamındaki alt dizinlerde kayıtlı bağlı yardımcı geliştirme çalışma
alanları, taşımadan sonra geçici olarak bozuk bir iç referans bildirmiştir;
standart bir onarım komutuyla düzeltilmiş ve tamamı sağlıklı duruma
dönmüştür. Hiçbir çalışma alanının commit geçmişi değişmemiştir; ana depo
yalnız onarılan referans dosyaları dışında değiştirilmemiştir.

## 5. Dirty-state açıklaması

Taşıma sırasında ana depoda çok sayıda commit edilmemiş değişiklik mevcuttu;
bu değişikliklere **dokunulmadı** — dosyalar mevcut halleriyle, byte-eş
taşındı. Hiçbir `git add`/`commit`/`push`/`merge` çalıştırılmadı.

## 6. Çakışma kontrolü

Taşıma öncesi hedef arşiv dizininde, gelen hiçbir girdiyle isim çakışması
bulunmadı — arşivleme net-yeni bir ad alanına yazıldı, sessiz üzerine-yazma
riski yoktu.

## 7. Sonuç

Taşıma sonrası proje kökü, beklenen üst-seviye dizin kümesiyle (git deposu,
bu planlama külliyatı, arşiv dizini, aktif çalışma alanları) tam eşleşti.

## 8. Rollback

Geri alma prosedürü tanımlıdır (manifest-driven — her girdinin orijinal yolu
ayrı bir kayıt listesinde tutulur) ama bu paket kapsamında **çalıştırılmadı**.
Tam prosedür detayı (dosya adları dahil) yalnız `evidence/archive-verification.md`
§7'de (yerel, public'e dahil değil) yaşar.

## 9. Kanonik sahiplik

Bu, `evidence/archive-verification.md`'nin sanitize edilmiş genel özetidir —
tek kanonik ham kaynak orada kalır (yerel, `.gitignore` ile public depodan
dışlanmış). Bu dosya onu **tekrar üretmez**, yalnız public-safe özetler.
