# 16 — Gap & Unknown-Unknowns Register

**PLANNING ONLY.** Her kayıt: ID, sınıf (known-unknown veya unknown-unknown
proxy'si), varsayım, etki, öncü sinyal, en ucuz güvenli test, kanıt sahibi,
karar tarihi/tetikleyici, containment/rollback, lifecycle kapısı. "Unknown
unknown" sahte kesinlikle çözülmüş **gösterilmez** — bu register bir discovery
radar'ıdır, kapanmamış madde bırakmak beklenen durumdur.

## Sınıf tanımları

- **known-unknown**: sorunun kendisi bilinir, cevabı bilinmez (örn. "trial kaç
  gün sürer?").
- **unknown-unknown proxy**: sorunun kendisi de tam netleşmemiştir; bu satırlar
  bir *proxy* soru sorarak alanı işaretler (örn. "shared-host'ta hangi PHP
  eklentileri kapalı olabilir, henüz bilmiyoruz — bu yüzden bir capability-probe
  script'i M0'da çalıştırılmalı").

---

## A. İş modeli

| ID | Varsayım | Etki | Öncü sinyal | En ucuz test | Sahip | Tetikleyici | Containment |
|---|---|---|---|---|---|---|---|
| BIZ-01 | Tenant sahibi = ilk kayıt olan kullanıcı, transfer edilebilir | Yüksek (ownership anlaşmazlığı) | Destek talebi hacmi | Transfer akışı prototipi | Owner | MVP Exit Gate | Manuel superadmin müdahalesi |
| BIZ-02 | Bir kullanıcı sınırsız workspace açabilir (limit yok, MVP'de) | Orta (kötüye kullanım riski) | Anormal workspace/kullanıcı oranı | Rate-limit + entitlement izleme | Owner | Post-MVP | Manuel suspend |
| BIZ-03 | Trial süresi 14 gün (varsayım, doğrulanmadı) | Orta | Trial-to-paid dönüşüm oranı | A/B öncesi tek sabit değerle pilot | Owner | GTM Stage öncesi | Plan-level override |
| BIZ-04 | Grace period 7 gün (varsayım) | Orta (müşteri deneyimi) | Şikayet/destek talebi | Pilot kohortunda ölçüm | Owner | GTM Stage öncesi | Manuel uzatma |
| BIZ-05 | Plan düşürülünce limit-üstü veri "read-only"ya döner, hemen silinmez | Yüksek (veri kaybı riski varsa) | — | Politika dokümanı + kullanıcı testi | Owner | Post-MVP | Silme öncesi 2 aşamalı onay |

## B. Restoran operasyonel varyans

| ID | Varsayım | Etki | Öncü sinyal | En ucuz test | Sahip | Tetikleyici | Containment |
|---|---|---|---|---|---|---|---|
| OPS-01 | Her masa için ayrı QR gerekir (varsayım) | Orta (bulk wizard tasarımı) | Onboarding'de masa sayısı dağılımı | Pilot restoran anketi | Product | MVP Exit Gate | Manuel QR ekleme desteklenir |
| OPS-02 | Masa numarası ileride sipariş modülüne (OPT-14) dönüşecek | Düşük şimdi, yüksek sonra | OPT-14 talep sinyali | Veri modelinde alan ayırma (şimdiden) | Architecture | Growth Stage | Şema genişletilebilir bırakıldı |
| OPS-03 | İki çalışan aynı ürünü aynı anda düzenlerse en az uyarı gösterilir (optimistic lock, conflict resolution UI yok MVP'de) | Orta | Çakışan-düzenleme audit log sıklığı | Basit "son kaydeden kazanır + uyarı" testi | Engineering | MVP Exit Gate | Audit log ile sonradan tespit |
| OPS-04 | Şef/Mutfak (Kitchen) rolünün "yayınlama yetkisi yok, yalnız alerjen/içerik/porsiyon onayı" sınırının gerçek bir pilot restoranda doğrulanmadı (`docs/02` §1.2) | Orta (operasyonel sürtünme riski) | Pilot restoran geri bildirimi ("mutfak neden yayınlayamıyor") | Pilot restoranda rol sınırının gözlemlenmesi | Product | MVP Exit Gate | Rol sınırı deny-by-default PDP'de sabit kalır, pilot bulgusu politika değişikliği önerisi olarak toplanır |

## C. Legal / privacy / tax / e-fatura

| ID | Varsayım | Etki | Öncü sinyal | En ucuz test | Sahip | Tetikleyici | Containment |
|---|---|---|---|---|---|---|---|
| LEG-01 | KVKK kapsamında restoran veri sorumlusu, platform veri işleyendir (varsayım — hukuk teyidi gerekir) | Yüksek | Hukuk danışmanlığı geri bildirimi | Hukuk uzmanı review'ı | Owner + Hukuk | GTM Stage öncesi | Sözleşme maddesiyle netleştirme |
| LEG-02 | Alerjen/kalori bildirimi hangi işletme büyüklüğü için zorunlu — mevzuat netliği yok | Yüksek (yasal risk) | Mevzuat güncellemesi izleme | Gıda mevzuatı uzmanı danışmanlığı | Owner | MVP Exit Gate | Standart 14 alerjen listesi + sorumluluk metni |
| LEG-03 | E-fatura/e-arşiv zorunluluğu ödeme modülü kapsamına girer mi — netleşmedi | Orta | Mali müşavir görüşü | Muhasebe danışmanlığı | Finance | Post-MVP | Manuel fatura süreci ile başla |
| LEG-04 | Restricted/minor (yaş kısıtlı, örn. alkol) içeriğin görünürlük/UX kararı ve ilgili mevzuat netleşmedi (`docs/02` §1.3 Restricted/Minor bağlamı) | Yüksek (yasal risk) | Mevzuat/hukuk danışmanlığı geri bildirimi | Hukuk uzmanı review'ı | Owner + Hukuk | GTM Stage öncesi | Varsayılan: yaş kısıtlı içerik yayınlanmaz, manuel işaretleme ile devre dışı bırakılır |

## D. Payment / webhook edge case

| ID | Varsayım | Etki | Öncü sinyal | En ucuz test | Sahip | Tetikleyici | Containment |
|---|---|---|---|---|---|---|---|
| PAY-01 | Iyzico webhook'ları en fazla X dakika gecikmeli gelir (SLA netliği yok) | Orta | Webhook gecikme metriği | Sandbox'ta gecikme testi | Engineering | GTM Stage | Reconciliation job ile telafi |
| PAY-02 | Kısmi refund + proration senaryosu (plan değişikliği ile aynı anda refund) | Orta | Destek talebi | Property-based money test | Engineering | Post-MVP | Manuel finans onayı |

## E. QR fiziksel ortam / yazıcı / tarayıcı

| ID | Varsayım | Etki | Öncü sinyal | En ucuz test | Sahip | Tetikleyici | Containment |
|---|---|---|---|---|---|---|---|
| QR-01 | QR redirect 301 mi 302 mi kullanılacak — netleşmedi | Düşük-Orta (cache davranışı) | — | SEO/cache uzmanı görüşü | Engineering | MVP Exit Gate | 302 ile başla (revoke edilebilirlik için) |
| QR-02 | Düşük kaliteli ev yazıcısı çıktısında minimum kontrast garantisi yok | Orta | Fiziksel test scan başarısızlığı | Farklı yazıcı/kağıt kombinasyonuyla pilot test | Design | MVP Exit Gate | Server-side scannability kapısı + uyarı |
| QR-03 | Revoke edilmiş bir QR destination'ının geri yüklenmesi (restore) veya aynı fiziksel QR'ın yeni bir destination'a yeniden bağlanması (rotate) semantiği tanımlanmadı — `modules/qr-destination.md` | Orta (yanlış müşteri yönlendirmesi riski) | Destek talebi (yanlış menüye yönlenme) | Restore/rotate akışının state machine prototipi | Engineering | MVP Exit Gate | Revoke tek yönlü kabul edilir, yeni QR üretimiyle devam edilir |

## F. Media codec / host capability

| ID | Varsayım | Etki | Öncü sinyal | En ucuz test | Sahip | Tetikleyici | Containment |
|---|---|---|---|---|---|---|---|
| MED-01 | Hedef shared-host sağlayıcılarında Imagick/ffmpeg var mı — **probe 2026-08-26'da yazıldı ve çalıştırıldı** (`php artisan platform:evidence:host-capability`); her çalıştırma `host_capability_evidence` tablosuna bir satır yazar ve devreye giren düşüş planını adıyla basar. Geliştirme makinesi ölçümü: Imagick yok (GD var), ffmpeg yok, Redis yok, `exec` açık, symlink çalışıyor, `upload_max_filesize=2M`. **Hedef sağlayıcı hâlâ seçilmedi** — bu ölçüm yalnız bu makine hakkındadır ve kayıt bunu açıkça söyler | Yüksek (medya pipeline tasarımı) | `tests/Feature/Platform/HostCapabilityProbeTest.php` (MED-01-PROBE-01…EVIDENCE-05); kanıt satırı | Sağlayıcı seçildiğinde aynı komutu orada çalıştırmak (owner kararı: hangi sağlayıcı) | Engineering + Owner | sağlayıcı seçimiyle | Graceful degradation tasarımda **ve** artık kodda: eksik yetenek hard-fail üretmez, planlı düşüş yazılır |
| MED-02 | ICC profil politikası (koru mu, strip mi) netleşmedi | Düşük | — | Tasarım review | Design | Post-MVP | Varsayılan: strip + sRGB'ye dönüştür |
| MED-03 | Local malware-scanner (örn. ClamAV binary) veya harici tarama servisi seçimi ve bunların shared-host'ta kullanılabilirliği/maliyeti/gizlilik etkisi bilinmiyor (`docs/07` §1, §5) | Yüksek (taranmamış dosyanın public'e sızması güvenlik riski) | Hedef shared-host sağlayıcılarında scanner binary/servis erişimi denemesi | Kapasite-probe script'i (`docs/15` §4 ile aynı yöntem) + harici servis maliyet/gizlilik karşılaştırması | Engineering + Security | Upload release (yayına açma) öncesi, MVP başlamadan | Scanner yoksa asset `quarantined` kalır, hiçbir koşulda taranmamış dosya public/published olmaz (safe quarantine, güvenlik degrade edilmez) |

## G. Accessibility / RTL

| ID | Varsayım | Etki | Öncü sinyal | En ucuz test | Sahip | Tetikleyici | Containment |
|---|---|---|---|---|---|---|---|
| A11Y-01 | Arabic RTL için üçüncü taraf bileşenlerin (Flowbite/shadcn) RTL desteği tam mı — doğrulanmadı | Orta | RTL görsel regresyon testi sonucu | Pilot RTL sayfası ile manuel test | Design | MVP Exit Gate | Kritik akışlarda manuel RTL düzeltme |

## H. Tenant isolation / auth policy patlaması

| ID | Varsayım | Etki | Öncü sinyal | En ucuz test | Sahip | Tetikleyici | Containment |
|---|---|---|---|---|---|---|---|
| AUTH-01 | RBAC+ABAC+ReBAC kombinasyonunun performans etkisi ölçülmedi (her istekte kaç policy check?) | Orta | p95 response time | Load test | Engineering | Post-MVP | Policy sonucu cache'lenebilir |
| AUTH-02 | IDOR test kapsamı her modül için otomatikleştirilmiş mi — henüz değil | Yüksek | Pentest bulgusu | `skills/tenant-isolation` eval seti | Security | MVP Exit Gate | Manuel pentest checklist |

## I. Data deletion / retention / export

| ID | Varsayım | Etki | Öncü sinyal | En ucuz test | Sahip | Tetikleyici | Containment |
|---|---|---|---|---|---|---|---|
| DATA-01 | Hesap silme sonrası mali/teknik loglar ne kadar saklanır — mevzuat + muhasebe süresi netleşmedi | Yüksek | Hukuk/muhasebe görüşü | Danışmanlık | Owner | GTM Stage öncesi | Varsayılan: 10 yıl mali kayıt (TR mevzuatı tahmini, teyit gerekir) |

## J. Analytics consent / ad blocker

| ID | Varsayım | Etki | Öncü sinyal | En ucuz test | Sahip | Tetikleyici | Containment |
|---|---|---|---|---|---|---|---|
| ANL-01 | Ad-blocker'ların first-party event ledger'ı engelleme oranı bilinmiyor | Orta | Event kaybı oranı | Gerçek trafik ölçümü (PMF stage) | Engineering | PMF Stage | First-party endpoint zaten üçüncü taraf script değil |
| ANL-03 | GA4 Data API / Yandex Metrica Reporting API inbound adaptörünün kapsamı/quota/şema/attribution modeli ve first-party ledger ile veri-uyuşmazlığı (discrepancy) yönetimi netleşmedi (`docs/12` §5a) | Orta (yanıltıcı karşılaştırma raporu riski) | Mapping tablosunda sürekli yüksek discrepancy oranı | Pilot tenant'ta iki kaynağın (first-party vs. provider) küçük bir zaman diliminde karşılaştırmalı ölçümü | Engineering | Growth Stage öncesi (`docs/22`, `docs/26` §1) | Provider paneli first-party dashboard'dan ayrı gösterilir, outage/uyuşmazlık durumunda yalnız provider paneli "kullanılamıyor" işaretlenir |
| ANL-02 | Unique scan penceresi (aynı ziyaretçinin tekrar taramasının ne zaman "yeni" sayılacağı), fingerprint yöntemi ve consent/privacy etkisi netleşmedi (`docs/12` §3) | Orta (metrik doğruluğu + gizlilik riski) | Şüpheli scan sayısı sıçraması / consent şikayeti | Fingerprint yöntemi seçeneklerinin (session-based vs. cookie-less hash) gizlilik danışmanlığıyla pilot karşılaştırması | Engineering + Owner | MVP Exit Gate | Muhafazakar varsayılan: kısa pencere (örn. 30 dk) + consent-gated |

## K. SEO spam / indexation

| ID | Varsayım | Etki | Öncü sinyal | En ucuz test | Sahip | Tetikleyici | Containment |
|---|---|---|---|---|---|---|---|
| SEO-01 | pSEO ölçeklendiğinde thin-content eşiği nedir — netleşmedi | Orta | Google Search Console manuel aksiyon uyarısı | Kalite kapısı prototipi | SEO | Growth Stage | Kalite kapısı olmadan pSEO açılmaz |
| SEO-02 | `docs/12` §7 governed mapping'indeki nonstandart/belirsiz kısaltmaların (ASEO, AISO, PEO, LEO, KGO, VSO, SERM, Academic SEO) birincil kaynağa bağlı kesin tanımı bu oturumda doğrulanmadı | Düşük-Orta (yanlış/abartılı public iddia riski) | Public dokümanda kanıtsız kısaltma tanımı kullanımı | Bir sonraki review turunda her kısaltma için resmi/endüstri kaynağının canlı birincil kaynak incelemesiyle doğrulanması | SEO | Bir sonraki review turu | Bu etiketler yalnız alias/facet listesinde tutulur, ayrı bir kanıtlanmış tanım public'e taşınmaz |

## L. E-posta/SMS deliverability, fraud, maliyet

| ID | Varsayım | Etki | Öncü sinyal | En ucuz test | Sahip | Tetikleyici | Containment |
|---|---|---|---|---|---|---|---|
| COM-01 | PHP native mail'in gerçek deliverability oranı bilinmiyor | Orta-Yüksek | Bounce/spam oranı | Health check + delivery test (zaten zorunlu, `docs/11`) | Engineering | MVP Exit Gate | SMTP/Mailgun adapter hazır, hızlı geçiş |
| COM-02 | SMS maliyet karşılaştırması (Netgsm/Twilio/Vonage/Verimor) yapılmadı | Orta | Aylık SMS gideri | Canlı teklif alma | Finance | GTM Stage öncesi | Netgsm ile başla, ölçekte revize |

## M. Shared-host tavanları

Bkz. `docs/15` §4 — MED-01 ile aynı kapasite-probe testine bağlı.

## N. App Store policy

| ID | Varsayım | Etki | Öncü sinyal | En ucuz test | Sahip | Tetikleyici | Containment |
|---|---|---|---|---|---|---|---|
| APP-01 | Capacitor shell App Store onayından geçer mi — garanti yok | Orta | Review red sinyali | Erken TestFlight submission | Engineering | Growth Stage | Web/PWA fallback her zaman çalışır |

## O. AI hallucination / tool misuse / vendor drift

| ID | Varsayım | Etki | Öncü sinyal | En ucuz test | Sahip | Tetikleyici | Containment |
|---|---|---|---|---|---|---|---|
| AI-01 | Provider fiyat/politika değişikliği (vendor drift) izlenmiyor henüz | Orta | Maliyet sıçraması | `skills/AI-provider-evaluator` periyodik çalıştırma | Engineering | Post-MVP | Fallback provider tanımlı |
| AI-02 | Prompt injection / content-poisoning testi otomatikleştirilmedi | Yüksek | Eval seti kırmızı sonuç | `docs/14` §3 eval seti | Security | MVP Exit Gate | Tool allowlist zaten sınırlı |
| AI-03 | Çok-dilli AI translation kalitesi için bir eval seti (referans çeviri korpusu, kalite eşiği) tanımlanmadı (`modules/opt-22-ai-translation.md`) | Orta (düşük kaliteli otomatik çeviri riski) | Kullanıcı düzeltme oranı (post-edit rate) | Pilot dil çiftinde küçük referans korpus + BLEU/human-review karşılaştırması | Engineering | Stage 2 Post-MVP (OPT-22 devreye girmeden önce) | İnsan onayı zorunlu kalır (AI çıktısı doğrudan yayınlanmaz) |
| AI-06 | Hesap YAPIŞKANLIĞI bir güvenlik sınırı sanılıyor | Yüksek (yanlış güven) | Tenant izolasyonu hesap seçimine bırakılmış tasarım | Kod incelemesi: izolasyon veri/önbellek/getirme katmanında mı | Security | Stage 1 `AI-S1-01` | İzolasyon politika katmanında zorlanır; yapışkanlık yalnız önbellek/residency/sözleşme için |
| AI-07 | Model tedarik zinciri (lisans, sağlama, revizyon) doğrulanmıyor | Yüksek | Doğrulanmamış model dosyası | Manifest + checksum kapısı | Security | Stage 1 `AI-S1-02` | Doğrulanmayan dosya yüklenmez |
| AI-08 | Nicemleme (quantization) değişimi kalite kaymasına yol açar | Orta | Aynı girdide farklı çıktı | Nicemleme model kimliğinin parçası; eval tekrarı | Engineering | Stage 1 `AI-S1-02` | Eval geçmeden dağıtım yok |
| AI-09 | Tenant'lar arası gömme/önbellek sızıntısı | **Kritik** | Bir tenant'ın ürünü başkasının aramasında | Tenant kimliği vektör alanı + önbellek anahtarında zorunlu | Security | Stage 1 `AI-S1-01` | Getirme filtresi tenant'sız çalışmaz |
| AI-10 | Görünmez prompt enjeksiyonu PDF/görselin içinde | Yüksek | OCR çıktısında talimat benzeri metin | Kırmızı takım fixture'ı | Security | Stage 1 `AI-S1-04` | OCR çıktısı da kullanıcı içeriğidir; veri olarak işaretlenir |
| AI-11 | ECA özyinelemesi ve yinelenen iş uygulaması | Orta | Kuyruk şişmesi, iki kez uygulanan taslak | Idempotency anahtarı + derinlik sınırı testi | Engineering | Stage 1 `AI-S1-05` | Tenant başına oran sınırı |
| AI-12 | Gömme modeli değişince eski vektörler karşılaştırılamaz | Orta | Arama kalitesi düşüşü | Vektör kaydında model kimliği | Engineering | Stage 2 | Yeniden indeksleme işi planlanır |
| AI-13 | İnceleme yığılması → otomasyon yanlılığı (insan okumadan onaylar) | Yüksek | Yüksek onay oranı + yüksek düzeltme oranı | Öneri hacmi sınırı; düşük güvende öneri gösterilmez | Product | Stage 2 | Az ve doğru öneri |
| AI-14 | **Alerjen iddiası** — AI "alerjensizdir" diyemez | **Kritik (sağlık + hukuk)** | Otomatik vegan/alerjensiz etiketi | Şema düzeyinde yasak: yalnız `candidate_allergen` alanı | Product + Legal | Stage 1 `AI-S1-04` | Çapraz bulaşma menü metninden çıkarılamaz; iddia edilemez |
| AI-15 | Model fiyat uydurması | Yüksek | Menüde yanlış fiyat | `uncertain` bayrağı zorunlu; belirsiz fiyat yayınlanamaz | Product | Stage 1 `AI-S1-04` | Yayın kapısı |
| AI-16 | Paylaşımlı barındırma ile 32 GB VPS davranışı sessizce ayrışır | Orta | Yerel sidecar varsayımı | Üç dağıtım profili; `shared-host`'ta yerel AI kapalı | Engineering | Stage 1 `AI-S1-02` | Profil kapısı |

## P. Modül bağımlılık / veri migrasyonu

| ID | Varsayım | Etki | Öncü sinyal | En ucuz test | Sahip | Tetikleyici | Containment |
|---|---|---|---|---|---|---|---|
| MOD-01 | Semantic compatibility resolver'ı hangi paketle uygulanacak — henüz seçilmedi (`docs/04` §6) | Orta | — | Spike | Engineering | Post-MVP | Manuel uyumluluk kontrolü ile başla |
| DEP-01 | **Kısmen kapandı (S1-WP01A, implementation-in-progress).** PHP `^8.3` / Laravel `^13.0` teknik baseline'ı artık kilitli ve kanıtlanmıştır — `composer.json` (`require.php=^8.3`, `require.laravel/framework=^13.0`) + reproducible `composer.lock` (docs/26 S1-WP01, hedefli preflight/PHPUnit kanıtı). Bu, yalnız **teknik framework/PHP sürüm** eksenini kapatır; **hedef shared-host sağlayıcı seti / provider sertifikasyonu** ekseni hâlâ **açıktır** ve kapalı ilan edilemez — brick/money, endroid/qr-code, mPDF gibi diğer Composer bağımlılıklarının hangi major/minor sürümünün seçileceği kararı hâlâ bu ikinci eksene bağlıdır (`docs/08` §1, `docs/09` §1, `docs/28`) | Yüksek (yanlış shared-host sağlayıcı varsayımı sonradan pahalı bir geri alma gerektirir) | Hedef shared-host sağlayıcılarında PHP 8.3 desteği/kapasite probe bulgusu | En ucuz test: hedeflenen shared-host sağlayıcılarının PHP 8.3 desteğini ve kapasite matrisini (docs/15 §4) listeleyen bir uyumluluk probe'u | Engineering | Shared-host sağlayıcı seçimi/sertifikasyonu öncesi (S1-WP03+ bağımlılık seçimlerinden önce)  **Owner kararı (2026-08-27): hedef barındırma = netcup (AMD EPYC), Hetzner, Turhost paylaşımlı, Natro paylaşımlı, Güzel Hosting paylaşımlı — beşinde birden KALICI olarak çalışmak.** Bu, teknoloji seçimini tek yönlü kısıtlar: en dar ortam (paylaşımlı barındırma) taban kabul edilir. Pratik sonuçları `docs/15` §4b'de sayılır ve `platform:evidence:host-capability` ile ölçülür; URL motorunun host'u koda gömmeme kararı da bu kısıttan gelir (`docs/38` §8). |

## Q. Disaster recovery

| ID | Varsayım | Etki | Öncü sinyal | En ucuz test | Sahip | Tetikleyici | Containment |
|---|---|---|---|---|---|---|---|
| DR-01 | **Kapandı (owner kararı, 2026-08-26).** RPO/RTO hedefleri sayısal olarak belirlendi: **RPO 24 saat, RTO 4 saat.** Gerekçe: Zabuno'da menülerin karanlık kalması (RTO) veri kaybından (RPO) daha pahalıdır — kesinti, müşterinin kendi misafirinin önünde yaşanır. Bu hedefler **gerçek hosting'de henüz doğrulanmadı**; yerel SQLite drill'i (DR-02) bir RPO/RTO kanıtı değildir ve kaydın kendi beyanı bunu açıkça reddeder | Yüksek | Gerçek hosting'de ölçülen restore süresinin 4 saati aşması | Hedef sağlayıcıda tek gerçek restore süresi ölçümü | Owner + Engineering | Shared-host sağlayıcı seçimi sonrası doğrulama (DEP-01) | Günlük otomatik backup varsayılan minimum; hedefler ölçümle doğrulanana kadar taahhüt değil, hedef olarak raporlanır |
| DR-02 | **Kısmen kapandı (2026-08-26).** Restore artık gerçek olarak çalıştırıldı: `security:evidence:backup-restore` yerel SQLite online-backup + izole dosya-kopyası drill'i **passed** (git SHA `19c0373`, temiz ağaç, `backup_restore_evidence` tablosunda kayıt). Kaydın kendi beyanı kapsamı sınırlar: bu bir RPO/RTO kanıtı, production DR tatbikatı veya cross-host/point-in-time recovery testi **değildir**; drill 9 satırlık dondurulmuş bir manifest üzerinde koşmuştur. Üretim büyüklüğünde veriyle, gerçek hosting'de restore hâlâ **test edilmemiştir**. **Güncelleme (2026-09-06, `docs/124`):** PostgreSQL koşucusu (`pg_dump --format=custom`, geçici veritabanına `pg_restore`, satır sayısı + içerik özeti karşılaştırması, sonra düşürme) ve medya tatbikatı (`storage/app` medya kökü, tar + dosya başına SHA-256 manifesti) yazıldı; koşucu bağlantıya göre seçiliyor, günlük zamanlama tanımlı, kayıt hangi sürücünün koştuğunu ve `unknown` (denenemedi) durumunu taşıyor. Yerelde PostgreSQL yok: sonuç bilinmiyor, CI `DB_CONNECTION=pgsql` işinde ölçülür. Üretim sunucusunda ilk tatbikat **hâlâ yapılmadı** | Yüksek | Üretim büyüklüğünde restore süresinin DR-01 hedefini aşması | Hedef sağlayıcıda üretim benzeri veriyle tek restore drill'i | Engineering | Shared-host sağlayıcı seçimi sonrası | Drill zorunlu kabul kriteri; yerel drill üretim kanıtı olarak sunulamaz |

## R. Observability / support

| ID | Varsayım | Etki | Öncü sinyal | En ucuz test | Sahip | Tetikleyici | Containment |
|---|---|---|---|---|---|---|---|
| OBS-01 | Cron çalışmazsa sistem nasıl uyarır — mekanizma tanımlanmadı | Orta | Sessiz cron hatası | Heartbeat/dead-man's-switch tasarımı | Engineering | MVP Exit Gate | Health check dashboard'a ekle |

## S. Licensing / IP / open source

| ID | Varsayım | Etki | Öncü sinyal | En ucuz test | Sahip | Tetikleyici | Containment |
|---|---|---|---|---|---|---|---|
| LIC-01 | imageoptimization snapshot'ının lisans durumu belirsiz (bkz. `research/upstream/imageoptimization/UPSTREAM.md`) | Düşük (yalnız kavram referansı, kod portlanmadı) | — | Yeniden kontrol (repo güncellenirse) | Engineering | Exit Ready Stage | Yalnız kavram referansı, kod kullanılmıyor |

## T. Vendor concentration / exit diligence

| ID | Varsayım | Etki | Öncü sinyal | En ucuz test | Sahip | Tetikleyici | Containment |
|---|---|---|---|---|---|---|---|
| EXIT-01 | Iyzico dışında ikinci bir ödeme sağlayıcısı değerlendirilmedi (vendor concentration riski) | Düşük şimdi, yüksek Exit Ready'de | — | Alternatif sağlayıcı taraması | Owner | Exit Ready Stage | Adapter katmanı zaten soyutlanmış (`docs/09`) |

## U. Diğer — mimari/organizasyonel notlar

| ID | Not | Sahip | Tetikleyici |
|---|---|---|---|
| ARCH-01 | Codex ana kapsam SHA-256 ile bağımsız Claude ana kapsam SHA-256'nın aynı girdi metninden mi üretildiği bu oturumda doğrulanamadı (bkz. `docs/00` §2) | Orkestratör | Bir sonraki senkronizasyon noktası |
| ARCH-02 | CORE-01..16 numaralandırmasının orijinal talimat listesiyle birebir eşlenmesi yeniden yapıldı (Queue/Scheduler, API Contract, Security, Observability dağıtıldı) — bu bir kayıp değil, yeniden-eşleme kararıdır (bkz. `docs/04` §2 not) | Architecture | Doküman review |
| ARCH-03 | Modular monolith'ten (ADR-L01, `docs/03`) servis extraction'a geçişin tetikleyicileri/ölçütleri tanımlanmadı (ne zaman, hangi sinyalle mikroservise bölünür); somut bir performans/ölçek sinyali (örn. tek bir modülün bağımsız deploy/scale ihtiyacı) belirene kadar açık kalır | Architecture | Bir sonraki büyük ölçek review'u |

## U2. Modül spec sürecinde ortaya çıkan ek maddeler

| ID | Not | Sahip | Tetikleyici |
|---|---|---|---|
| OPT-COMM-01 | OPT-15 Restaurant Payment için platform komisyon modeli tanımlanmadı | Finance Operator | Growth Stage öncesi |
| OPT-COMM-02 | OPT-27 Marketplace için üçüncü taraf geliştirici gelir paylaşım modeli tanımlanmadı | Owner | Growth Stage (Marketplace açılmadan önce) |

## V. Kaynak doğrulama

| ID | Not | Sahip | Tetikleyici |
|---|---|---|---|
| SRC-01 | `docs/28-SOURCE-REGISTER.md`'deki 21 URL'lik hedef kümenin 18'i bu oturumda doğrudan canlı birincil kaynak incelemesiyle ("erişim doğrulandı"), 3'ü (OpenAI Projects yönetim rehberi, OpenAI Services Agreement, OpenAI rate limit rehberi — sunucu HTTP 403 reddi nedeniyle) daha zayıf bir kanıt seviyesiyle ("official indexed content verified") doğrulandı — bu 3 satır artık pending değildir, ancak doğrudan sayfa erişimi henüz denenmemiştir ve ilgili kararlar `koşullu` sınıfında kalır. "Henüz bu oturumda fetch edilmemiş" tablosundaki geri kalan tüm satırlar (Laravel providers/Vite/deployment/filesystem, Flowbite/shadcn/Radix/Vite, Spatie/Uppy/Intervention, Apple/Google Play/Capacitor, Google Search/Bing/GTM/GA4/Yandex/Metabase, OpenFGA/Spatie Permission, Twilio/Netgsm/Vonage/Verimor, php-gettext, Symfony Workflow, Cloudflare, OpenTelemetry, DORA, OpenAI developers/Codex) tamamen doğrulanmamış kalmıştır — bir sonraki çalışma turunda tek tek canlı birincil kaynak incelemesiyle doğrulanmalı. Güncelleme (ikinci düzeltme paketi, 2026-08-19): dört `developers.openai.com` Admin API referans sayfası (`docs/28` "OpenAI Admin API — erişim doğrulandı" bölümü) bu turda başarıyla canlı fetch edildi (4/4 HTTP 200, içerik terimleri doğrulandı) — önceki turdaki fetch başarısızlığı bu oturumda tekrar üretilemedi; bu dört satır artık **erişim doğrulandı**, yalnız adaptör/production kullanım kararı koşullu kalır (bu, `docs/16` ANL-03/AIV-09 gibi ayrı tasarım kararlarını kapatmaz). Güncelleme (üçüncü düzeltme paketi, 2026-08-19): sekiz ek kaynak bu turda canlı fetch edilip doğrulandı — GA4 Data API (basics/quotas/quickstart, **inbound reporting**, `docs/28` "GA4 / Yandex Metrica / PWA" bölümü), Yandex Metrica Reports API (data/authorization/quotas, **inbound reporting**), web.dev PWA update + service-worker lifecycle rehberleri (8/8 HTTP 200, içerik terimleri doğrulandı) — bu sekiz satır artık **erişim doğrulandı**, adaptör/production kararı koşullu. Ayrıca brick/money (release `0.14.1`), endroid/qr-code (tag `6.1.3`/`6.0.0`/`5.1.0`) ve mPDF (tag `v8.3.1`) sürüm/PHP-uyumluluk bilgileriyle yeniden doğrulandı, `kanıtlanmış`tan `koşullu`ya düzeltildi (`docs/08` §1, `docs/09` §1, `docs/16` DEP-01). **Netlik**: yukarıdaki "tamamen doğrulanmamış" listesindeki "GA4/Yandex" ifadesi yalnız **outbound** ecommerce/tagging sayfaları (`docs/12` §5 GA4 enhanced ecommerce, Yandex ecommerce şeması) için hâlâ geçerlidir — bu sekiz yeni satırın kapsadığı **inbound** Data/Reports API sayfaları ve PWA lifecycle sayfaları artık doğrulanmış kümededir, karıştırılmamalıdır | Engineering | Bir sonraki review turu |

## W. AI Capability Plane — hesap/vault/postmortem/public-repo belirsizlikleri

| ID | Varsayım | Etki | Öncü sinyal | En ucuz test | Sahip | Tetikleyici | Containment |
|---|---|---|---|---|---|---|---|
| AIV-01 | Provider hesabının legal entity/account ownership'i (hangi tüzel kişi adına açıldığı) netleşmedi | Yüksek (sözleşme/fatura anlaşmazlığı) | Fatura/sözleşme uyuşmazlığı | Hukuk/finans danışmanlığı | Owner | Post-MVP | Manuel hesap sahipliği kaydı |
| AIV-02 | Provider şartlarındaki drift (fiyat/politika değişikliği) sistematik izlenmiyor | Orta | Maliyet sıçraması | `skills/ai-provider-evaluator` periyodik çalıştırma | Engineering | Post-MVP | Fallback provider tanımlı |
| AIV-03 | Tenant BYOK desteğinin hangi plan/edition'da açılacağı netleşmedi | Orta | Talep sinyali (destek talebi) | Pilot tenant anketi | Product | Stage 3 GTM öncesi | Platform-owned havuzla başla |
| AIV-04 | Data residency ve cross-provider consent politikası (bir tenant'ın verisi hangi coğrafyada işlenebilir) netleşmedi | Yüksek (yasal risk) | Hukuk danışmanlığı geri bildirimi | Hukuk uzmanı review'ı | Owner + Hukuk | GTM Stage öncesi | Residency-denial deterministik reddi zaten tasarımda (`docs/14` §1) |
| AIV-05 | AI credit'in iç değerlemesi (1 credit = ne kadar provider maliyeti) henüz fiyatlandırılmadı | Orta | İlk fatura döneminde marj sapması | Pilot dönemde ölçüm | Finance | Stage 3 GTM öncesi | Manuel fiyat revizyonu |
| AIV-06 | Hesap health-check'in kendisinin maliyeti (her check bir provider çağrısı mı) ölçülmedi | Düşük-Orta | Health-check maliyet kalemi | Health-check sıklığı spike testi | Engineering | Post-MVP | Düşük frekanslı varsayılan check aralığı |
| AIV-07 | No-credit degraded UX'in gerçek kullanıcı testinde ne kadar "tatmin edici" algılandığı ölçülmedi | Orta | Destek talebi/şikayet oranı | Pilot kullanıcı testi | Design | MVP Exit Gate sonrası | Şablon/manuel yol zaten tasarımda |
| AIV-08 | Django/FastAPI postmortem'indeki (`docs/30`) kök neden analizi bu oturumda bağımsız doğrulanmadı — owner beyanı ve önceki denetim raporlarının aktarımıdır, birincil kanıt (log/test çıktısı) bu külliyatta yeniden üretilmedi | Orta (yanlış genelleme riski) | Yeni bir denetim turunda çelişen bulgu | Orijinal test/coverage raporlarının bir sonraki turda yeniden incelenmesi | Engineering | Bir sonraki review turu | `docs/30` §2 kaynağını "owner beyanı/aktarım" olarak açıkça etiketliyor |
| AIV-09 | External secret vault adaptörü (örn. HashiCorp Vault, cloud KMS) seçimi ve shared-host feasibility'si (bu adaptörün shared-host ortamında hiç çalışamayabileceği ihtimali) değerlendirilmedi (`modules/ai-provider-account-vault.md`) | Orta (shared-host'ta external vault kurulamazsa fallback gerekir) | Shared-host sağlayıcısında external vault erişimi denemesi başarısız olur | Hedef shared-host sağlayıcılarında outbound bağlantı/kurulum kapasite-probe testi | Engineering + Security | Post-MVP | Varsayılan: platform DB'de encrypted-at-rest custody (`modules/ai-provider-account-vault.md` §Security), external vault yalnız kapasite varsa opsiyonel katman |
| LIC-02 | Public `zabuno/zabuno` deposu için açık kaynak lisansı seçilip seçilmeyeceği owner kararına bağlı, henüz verilmedi | Düşük (yalnız görünürlük, kullanım hakkı değil) | — | Owner kararı | Owner | Exit Ready Stage öncesi herhangi bir zaman | `docs/31` §4 — LICENSE eklenmeden public kalınabilir |

## Kanonik sahiplik

Bu register tek kanonik gap/unknown-unknown kaynağıdır. Diğer dokümanlar buraya
link verir, kendi gap listelerini tutmaz.
