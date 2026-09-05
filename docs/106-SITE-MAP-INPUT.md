<!--
    SAHİBİN VERDİĞİ GİRDİ — 2026-09-04. Bu dosya bir KAYNAKTIR, bir karar
    belgesi değil.

    Depoya kopyalandı çünkü sayfa registry'si bu dosyadan üretiliyor
    (`php artisan site:import-map`) ve üretimin tekrar edilebilir olması
    gerekiyor: kaynak `~/Downloads` altında kalsaydı, seed'i bir daha kimse
    aynı girdiyle çalıştıramazdı.

    Kararlar burada değil `docs/105`'te. İkisi çeliştiğinde `docs/105` kazanır.

    2026-09-05: sıra bir kademe daha uzadı. Sahibin kararı, bugünün kararlarını
    MASTER yapıyor. Çelişkide: `docs/118` > `docs/105` > bu dosya. İkiz girdi
    (`docs/119`, uygulama yönergesi) de aynı gün depoya alındı.
-->

# Zabuno.com Tam Site Haritası

Bu doküman, incelenen yerli ve global QR menü, restoran SaaS, sipariş ve ödeme platformlarının anlamlı kurumsal sayfa türlerini tek bir Zabuno web sitesi altında birleştirir.

## 1. Kapsam ve kurallar

- Kapsam: Tanıtım sitesi, kurumsal sayfalar, ürün sayfaları, çözüm sayfaları, kaynak merkezi, yardım merkezi, geliştirici dokümantasyonu ve yasal sayfalar.
- Kapsam dışı: Zabuno SaaS panelinin iç ekranları, tenant yönetimi ve restoran müşterilerine ait yayınlanmış menü sayfaları.
- `{locale}`: `tr` veya `en`.
- `{slug}`: Veritabanından veya CMS'ten üretilen detay sayfası.
- `[P0]`: İlk yayında bulunmalı.
- `[P1]`: Büyüme ve SEO döneminde açılmalı.
- `[P2]`: Ürün kapsamı genişlediğinde açılmalı.
- `[TEMPLATE]`: Tek tek elle yaratılmayacak, ortak şablondan üretilecek detay sayfası.
- `[EXTERNAL]`: Zabuno ekosisteminde ayrı uygulamaya yönlenen bağlantı.
- Her canonical sayfa bu ağaçta yalnızca bir kez yer alır. Header, footer, mega menü ve içerik içi bağlantılar aynı URL'yi tekrar kullanır.
- Aynı arama niyetine cevap veren yakın anlamlı sayfalar birleştirilir; farklı adlarla SEO kopyaları oluşturulmaz.
- Entegrasyon, özellik veya sektör sayfası; ürün gerçekten desteklemiyorsa yayınlanmamalıdır.
- Türkçe ve İngilizce sayfaların içerikleri bire bir makine çevirisi olmamalı; ülke, para birimi, mevzuat ve arama niyetine göre yerelleştirilmelidir.

## 2. Alan adı ve URL düzeni

- `https://zabuno.com/`
  - Dil seçimi veya kullanıcının tercih ettiği dile yönlendirme.
- `https://zabuno.com/tr/`
  - Türkçe ana site.
- `https://zabuno.com/en/`
  - İngilizce ana site.
- `https://app.zabuno.com/` `[EXTERNAL]`
  - SaaS uygulaması, giriş ve kayıt.
- `https://status.zabuno.com/` `[EXTERNAL]`
  - Servis durumu ve olay geçmişi.

Canonical sayfalar dil dizini altında yayınlanır. Bu dokümanda tekrarları önlemek için yalnızca Türkçe canonical ağaç gösterilmiştir. İngilizce sürüm aynı bilgi mimarisini kendi yerelleştirilmiş slug'larıyla takip eder ve ağaçta ikinci kez listelenmez.

## 3. Ana navigasyon

### 3.1 Masaüstü üst menü

- Ürün
- Çözümler
- Entegrasyonlar
- Müşteriler
- Kaynaklar
- Fiyatlandırma

Sağ taraftaki işlemler:

- Dil seçimi
- Giriş yap
- Demo iste
- Ücretsiz başla

### 3.2 Mobil ana menü

- Ürün
- Çözümler
- Entegrasyonlar
- Müşteriler
- Kaynaklar
- Fiyatlandırma
- Yardım Merkezi
- İletişim
- Giriş yap
- Ücretsiz başla

## 4. Tam site ağacı

- `zabuno.com`
  - `/tr/` — Ana sayfa `[P0]`
  - `/tr/urun/` — Ürün genel bakış `[P0]`
    - `/tr/urun/qr-menu/` — QR, dijital, mobil ve temassız menü özelliklerini tek sayfada anlatır `[P0]`
    - `/tr/urun/tablet-menu/` — Yalnızca ayrı bir tablet ürünü geliştirildiğinde açılır `[P2]`
    - `/tr/urun/menu-yonetimi/` — Menü yönetimi `[P0]`
      - `/tr/urun/menu-yonetimi/kategoriler/` — Menü kategorileri `[P0]`
      - `/tr/urun/menu-yonetimi/urunler/` — Ürün yönetimi `[P0]`
      - `/tr/urun/menu-yonetimi/varyantlar/` — Boyut ve varyantlar `[P0]`
      - `/tr/urun/menu-yonetimi/ekstralar/` — Ekstra ve ürün seçenekleri `[P0]`
      - `/tr/urun/menu-yonetimi/urun-fiyatlari/` — Menü ürünlerinin fiyat yönetimi `[P0]`
      - `/tr/urun/menu-yonetimi/stok-durumu/` — Tükendi ve stok durumu `[P0]`
      - `/tr/urun/menu-yonetimi/zamanlanmis-yayin/` — Zamanlanmış menü ve ürün yayını `[P1]`
      - `/tr/urun/menu-yonetimi/toplu-islemler/` — Toplu ürün işlemleri `[P1]`
      - `/tr/urun/menu-yonetimi/menu-kopyalama/` — Menü kopyalama `[P1]`
      - `/tr/urun/menu-yonetimi/menu-versiyonlari/` — Menü sürümleri ve geri alma `[P1]`
    - `/tr/urun/masa-ve-qr-yonetimi/` — Masa ve QR yönetimi `[P0]`
      - `/tr/urun/masa-ve-qr-yonetimi/masalar/` — Masa tanımlama `[P0]`
      - `/tr/urun/masa-ve-qr-yonetimi/alanlar/` — Salon, kat, teras ve bölümler `[P1]`
      - `/tr/urun/masa-ve-qr-yonetimi/qr-kod-uretimi/` — QR kod üretimi `[P0]`
      - `/tr/urun/masa-ve-qr-yonetimi/qr-kod-tasarimi/` — QR tasarım stüdyosu `[P1]`
      - `/tr/urun/masa-ve-qr-yonetimi/qr-yonlendirme/` — Değiştirilebilir QR hedefi `[P1]`
      - `/tr/urun/masa-ve-qr-yonetimi/qr-urun-kartlari/` — Ürün bazlı QR kartları `[P2]`
    - `/tr/urun/tasarim-ve-marka/` — Tasarım ve markalama `[P0]`
      - `/tr/urun/tasarim-ve-marka/tema-editoru/` — Tema editörü `[P0]`
      - `/tr/urun/tasarim-ve-marka/marka-kimligi/` — Logo, renk ve tipografi `[P0]`
      - `/tr/urun/tasarim-ve-marka/ozel-alan-adi/` — Özel alan adı `[P1]`
      - `/tr/urun/tasarim-ve-marka/white-label/` — White-label menü `[P2]`
      - `/tr/urun/tasarim-ve-marka/ozel-css/` — Gelişmiş görünüm özelleştirmesi `[P2]`
    - `/tr/urun/gorsel-ve-medya/` — Görsel ve medya yönetimi `[P0]`
      - `/tr/urun/gorsel-ve-medya/medya-kutuphanesi/` — Medya kütüphanesi `[P0]`
      - `/tr/urun/gorsel-ve-medya/gorsel-optimizasyonu/` — Görsel boyutlandırma ve optimizasyon `[P0]`
      - `/tr/urun/gorsel-ve-medya/video/` — Ürün videosu `[P2]`
      - `/tr/urun/gorsel-ve-medya/otomatik-arka-plan/` — Görsel arka plan düzenleme `[P2]`
    - `/tr/urun/coklu-dil-ve-para-birimi/` — Çoklu dil ve para birimi `[P0]`
      - `/tr/urun/coklu-dil-ve-para-birimi/menu-cevirisi/` — Menü çevirisi `[P0]`
      - `/tr/urun/coklu-dil-ve-para-birimi/otomatik-dil/` — Tarayıcı dilini algılama `[P1]`
      - `/tr/urun/coklu-dil-ve-para-birimi/coklu-para-birimi/` — Çoklu para birimi `[P1]`
      - `/tr/urun/coklu-dil-ve-para-birimi/bolgesel-fiyat/` — Bölgesel fiyatlandırma `[P2]`
    - `/tr/urun/beslenme-ve-alerjen/` — Beslenme ve alerjen bilgileri `[P1]`
      - `/tr/urun/beslenme-ve-alerjen/alerjenler/` — Alerjen etiketleri `[P1]`
      - `/tr/urun/beslenme-ve-alerjen/besin-degerleri/` — Besin değerleri `[P1]`
      - `/tr/urun/beslenme-ve-alerjen/kalori/` — Kalori bilgisi `[P1]`
      - `/tr/urun/beslenme-ve-alerjen/diyet-etiketleri/` — Vegan, vejetaryen, glutensiz ve benzeri etiketler `[P1]`
      - `/tr/urun/beslenme-ve-alerjen/mevzuat-uyumu/` — Menü mevzuatı ve bilgilendirme uyumu `[P1]`
    - `/tr/urun/coklu-sube/` — Çoklu şube yönetimi `[P0]`
      - `/tr/urun/coklu-sube/merkezi-menu/` — Merkezi menü yönetimi `[P0]`
      - `/tr/urun/coklu-sube/sube-fiyatlari/` — Şubeye özel fiyat `[P1]`
      - `/tr/urun/coklu-sube/roller-ve-yetkiler/` — Rol ve yetki yönetimi `[P1]`
      - `/tr/urun/coklu-sube/merkezi-raporlama/` — Merkezi raporlama `[P1]`
      - `/tr/urun/coklu-sube/franchise-yonetimi/` — Franchise standartları `[P2]`
    - `/tr/urun/analitik/` — Analitik ve raporlama `[P0]`
      - `/tr/urun/analitik/menu-analitigi/` — Menü görüntülenme analitiği `[P0]`
      - `/tr/urun/analitik/urun-performansi/` — Ürün performansı `[P0]`
      - `/tr/urun/analitik/satis-analitigi/` — Satış analitiği `[P1]`
      - `/tr/urun/analitik/musteri-davranisi/` — Misafir davranışı `[P1]`
      - `/tr/urun/analitik/sube-karsilastirma/` — Şube karşılaştırma `[P1]`
      - `/tr/urun/analitik/menu-muhendisligi/` — Menü mühendisliği `[P1]`
      - `/tr/urun/analitik/raporlar/` — Raporlar `[P1]`
      - `/tr/urun/analitik/aksiyon-merkezi/` — Önerilen aksiyonlar `[P2]`
    - `/tr/urun/zabuno-ai/` — Zabuno AI genel bakış `[P0]`
      - `/tr/urun/zabuno-ai/menu-ice-aktarma/` — OCR kullanarak fotoğraf ve PDF'den menü aktarımı `[P0]`
      - `/tr/urun/zabuno-ai/menu-olusturucu/` — AI menü oluşturucu `[P1]`
      - `/tr/urun/zabuno-ai/icerik-asistani/` — Ürün adı ve açıklama asistanı `[P1]`
      - `/tr/urun/zabuno-ai/ceviri-asistani/` — AI çeviri asistanı `[P1]`
      - `/tr/urun/zabuno-ai/gorsel-asistani/` — Görsel iyileştirme asistanı `[P2]`
      - `/tr/urun/zabuno-ai/beslenme-asistani/` — Beslenme ve alerjen asistanı `[P2]`
      - `/tr/urun/zabuno-ai/raporlama-asistani/` — AI raporlama asistanı `[P2]`
      - `/tr/urun/zabuno-ai/pazarlama-asistani/` — Kampanya ve içerik asistanı `[P2]`
    - `/tr/urun/siparis/` — Dijital sipariş `[P1]`
      - `/tr/urun/siparis/masaya-siparis/` — QR ile masaya sipariş `[P1]`
      - `/tr/urun/siparis/gel-al/` — Gel-al siparişi `[P1]`
      - `/tr/urun/siparis/paket-servis/` — Paket servis siparişi `[P1]`
      - `/tr/urun/siparis/on-siparis/` — Ön sipariş `[P2]`
      - `/tr/urun/siparis/coklu-satici/` — Multi-vendor sipariş `[P2]`
      - `/tr/urun/siparis/acik-hesap/` — Açık hesap ve dijital adisyon `[P2]`
    - `/tr/urun/odeme/` — Dijital ödeme `[P2]`
      - `/tr/urun/odeme/masada-ode/` — Pay at Table `[P2]`
      - `/tr/urun/odeme/hizli-odeme/` — Hızlı ödeme `[P2]`
      - `/tr/urun/odeme/hesap-bolme/` — Hesap bölme `[P2]`
      - `/tr/urun/odeme/bahsis/` — Dijital bahşiş `[P2]`
      - `/tr/urun/odeme/on-odeme/` — Ön ödeme `[P2]`
      - `/tr/urun/odeme/online-odeme/` — Online ödeme `[P2]`
    - `/tr/urun/misafir-etkilesimi/` — Misafir etkileşimi `[P1]`
      - `/tr/urun/misafir-etkilesimi/garson-cagirma/` — Garson çağırma `[P1]`
      - `/tr/urun/misafir-etkilesimi/geri-bildirim/` — Geri bildirim toplama `[P1]`
      - `/tr/urun/misafir-etkilesimi/yorum-yonetimi/` — Online yorum yönetimi `[P2]`
      - `/tr/urun/misafir-etkilesimi/crm/` — Misafir CRM `[P2]`
      - `/tr/urun/misafir-etkilesimi/sadakat/` — Sadakat programı `[P2]`
      - `/tr/urun/misafir-etkilesimi/rezervasyon/` — Rezervasyon `[P2]`
      - `/tr/urun/misafir-etkilesimi/dijital-tab/` — Dijital hesap sekmesi `[P2]`
    - `/tr/urun/kampanyalar/` — Kampanya yönetimi `[P1]`
      - `/tr/urun/kampanyalar/indirimler/` — İndirimler `[P1]`
      - `/tr/urun/kampanyalar/happy-hour/` — Happy hour `[P1]`
      - `/tr/urun/kampanyalar/kuponlar/` — Kuponlar `[P2]`
      - `/tr/urun/kampanyalar/urun-onerileri/` — Upsell ve çapraz satış `[P1]`
      - `/tr/urun/kampanyalar/hediye-karti/` — Hediye kartı `[P2]`
    - `/tr/urun/operasyon/` — Restoran operasyonu `[P2]`
      - `/tr/urun/operasyon/mutfak-ekrani/` — Kitchen Display System `[P2]`
      - `/tr/urun/operasyon/self-servis-kiosk/` — Self-order kiosk `[P2]`
      - `/tr/urun/operasyon/garson-el-terminali/` — Garson el terminali `[P2]`
      - `/tr/urun/operasyon/kurye-takip/` — Kurye takip `[P2]`
      - `/tr/urun/operasyon/stok-ve-maliyet/` — Stok ve maliyet `[P2]`
      - `/tr/urun/operasyon/gorev-yonetimi/` — Operasyon görevleri `[P2]`
  - `/tr/cozumler/` — Çözümler genel bakış `[P0]`
    - `/tr/cozumler/isletme-turleri/` — İşletme türleri `[P0]`
      - `/tr/cozumler/restoran/` — Fine dining ve casual dining dahil restoranlar `[P0]`
      - `/tr/cozumler/fast-food/` — Fast food, pizza, burger ve quick service `[P0]`
      - `/tr/cozumler/kafe-ve-pastane/` — Kafe, kahve dükkânı, pastane ve bakery `[P0]`
      - `/tr/cozumler/bar-ve-gece-kulubu/` — Bar ve gece kulübü `[P1]`
      - `/tr/cozumler/otel-ve-resort/` — Otel ve resort `[P1]`
      - `/tr/cozumler/paket-servis-ve-bulut-mutfak/` — Paket servis ve ghost kitchen `[P1]`
      - `/tr/cozumler/mobil-ve-sezonluk-isletme/` — Food truck ve sezonluk işletme `[P2]`
      - `/tr/cozumler/food-hall/` — Food hall ve multi-vendor `[P2]`
      - `/tr/cozumler/kurumsal-yeme-icme/` — Üniversite, kampüs ve kurumsal yemek alanları `[P2]`
      - `/tr/cozumler/etkinlik-ve-eglence-mekanlari/` — Catering, festival, stadyum, beach club, golf, bowling ve tema parkları `[P2]`
    - `/tr/cozumler/organizasyon-turleri/` — Organizasyon türleri `[P0]`
      - `/tr/cozumler/tek-subeli-isletme/` — Tek şubeli işletme `[P0]`
      - `/tr/cozumler/coklu-sube/` — Çok şubeli işletme `[P0]`
      - `/tr/cozumler/zincir-ve-franchise/` — Zincir ve franchise `[P0]`
      - `/tr/cozumler/enterprise/` — Kurumsal işletmeler `[P1]`
      - `/tr/cozumler/ajans-ve-danisman/` — Ajans ve danışmanlar `[P2]`
    - `/tr/cozumler/isletme-hedefleri/` — Maliyet, hız, sepet, verimlilik, çoklu dil, mevzuat, geri bildirim ve merkezi yönetim hedeflerini tek sayfada toplar `[P1]`
  - `/tr/entegrasyonlar/` — Entegrasyonlar genel bakış `[P0]`
    - `/tr/entegrasyonlar/pos-ve-adisyon/` — POS ve adisyon sistemleri `[P0]`
      - `/tr/entegrasyonlar/sambapos/` `[TEMPLATE] [P1]`
      - `/tr/entegrasyonlar/menulux/` `[TEMPLATE] [P1]`
      - `/tr/entegrasyonlar/narpos/` `[TEMPLATE] [P1]`
      - `/tr/entegrasyonlar/adisyo/` `[TEMPLATE] [P1]`
      - `/tr/entegrasyonlar/robotpos/` `[TEMPLATE] [P1]`
      - `/tr/entegrasyonlar/lightspeed/` `[TEMPLATE] [P2]`
      - `/tr/entegrasyonlar/oracle-micros-simphony/` `[TEMPLATE] [P2]`
      - `/tr/entegrasyonlar/untill/` `[TEMPLATE] [P2]`
      - `/tr/entegrasyonlar/trivec/` `[TEMPLATE] [P2]`
      - `/tr/entegrasyonlar/vectron/` `[TEMPLATE] [P2]`
      - `/tr/entegrasyonlar/ncr-aloha/` `[TEMPLATE] [P2]`
    - `/tr/entegrasyonlar/pazar-yerleri/` — Yemek ve teslimat pazar yerleri `[P1]`
      - `/tr/entegrasyonlar/yemeksepeti/` `[TEMPLATE] [P1]`
      - `/tr/entegrasyonlar/getir-yemek/` `[TEMPLATE] [P1]`
      - `/tr/entegrasyonlar/trendyol-yemek/` `[TEMPLATE] [P1]`
      - `/tr/entegrasyonlar/migros-yemek/` `[TEMPLATE] [P1]`
      - `/tr/entegrasyonlar/wolt/` `[TEMPLATE] [P2]`
      - `/tr/entegrasyonlar/glovo/` `[TEMPLATE] [P2]`
      - `/tr/entegrasyonlar/bolt-food/` `[TEMPLATE] [P2]`
      - `/tr/entegrasyonlar/foodora/` `[TEMPLATE] [P2]`
      - `/tr/entegrasyonlar/uber-eats/` `[TEMPLATE] [P2]`
      - `/tr/entegrasyonlar/doordash/` `[TEMPLATE] [P2]`
      - `/tr/entegrasyonlar/grubhub/` `[TEMPLATE] [P2]`
      - `/tr/entegrasyonlar/takeaway-com/` `[TEMPLATE] [P2]`
    - `/tr/entegrasyonlar/odeme/` — Ödeme sistemleri `[P1]`
      - `/tr/entegrasyonlar/iyzico/` `[TEMPLATE] [P1]`
      - `/tr/entegrasyonlar/paytr/` `[TEMPLATE] [P1]`
      - `/tr/entegrasyonlar/moka/` `[TEMPLATE] [P1]`
      - `/tr/entegrasyonlar/param/` `[TEMPLATE] [P1]`
      - `/tr/entegrasyonlar/odeal/` `[TEMPLATE] [P1]`
      - `/tr/entegrasyonlar/paycell/` `[TEMPLATE] [P2]`
      - `/tr/entegrasyonlar/nkolay/` `[TEMPLATE] [P2]`
      - `/tr/entegrasyonlar/mollie/` `[TEMPLATE] [P2]`
      - `/tr/entegrasyonlar/ingenico/` `[TEMPLATE] [P2]`
      - `/tr/entegrasyonlar/beko-tsm/` `[TEMPLATE] [P2]`
      - `/tr/entegrasyonlar/pax/` `[TEMPLATE] [P2]`
      - `/tr/entegrasyonlar/worldline/` `[TEMPLATE] [P2]`
      - `/tr/entegrasyonlar/global-payments/` `[TEMPLATE] [P2]`
      - `/tr/entegrasyonlar/dojo/` `[TEMPLATE] [P2]`
    - `/tr/entegrasyonlar/muhasebe-ve-e-donusum/` — Muhasebe ve e-dönüşüm `[P1]`
      - `/tr/entegrasyonlar/logo/` `[TEMPLATE] [P1]`
      - `/tr/entegrasyonlar/parasut/` `[TEMPLATE] [P1]`
      - `/tr/entegrasyonlar/mikro/` `[TEMPLATE] [P1]`
      - `/tr/entegrasyonlar/nebim/` `[TEMPLATE] [P2]`
      - `/tr/entegrasyonlar/uyumsoft/` `[TEMPLATE] [P2]`
      - `/tr/entegrasyonlar/sap/` `[TEMPLATE] [P2]`
      - `/tr/entegrasyonlar/e-fatura/` `[TEMPLATE] [P1]`
    - `/tr/entegrasyonlar/kurye-ve-teslimat/` — Kurye ve teslimat `[P2]`
      - `/tr/entegrasyonlar/deliverect/` `[TEMPLATE] [P2]`
      - `/tr/entegrasyonlar/shipday/` `[TEMPLATE] [P2]`
      - `/tr/entegrasyonlar/fiyuu/` `[TEMPLATE] [P2]`
      - `/tr/entegrasyonlar/hemen-kurye/` `[TEMPLATE] [P2]`
      - `/tr/entegrasyonlar/hemen-yolda/` `[TEMPLATE] [P2]`
      - `/tr/entegrasyonlar/restajet/` `[TEMPLATE] [P2]`
      - `/tr/entegrasyonlar/gloriafood/` `[TEMPLATE] [P2]`
    - `/tr/entegrasyonlar/otel-sistemleri/` — Otel PMS sistemleri `[P2]`
      - `/tr/entegrasyonlar/minerva/` `[TEMPLATE] [P2]`
      - `/tr/entegrasyonlar/otel-pms-diger/` `[TEMPLATE] [P2]`
    - `/tr/entegrasyonlar/sadakat-ve-crm/` — Sadakat ve CRM `[P2]`
      - `/tr/entegrasyonlar/leat/` `[TEMPLATE] [P2]`
      - `/tr/entegrasyonlar/zubizu/` `[TEMPLATE] [P2]`
    - `/tr/entegrasyonlar/donanim/` — Donanım `[P2]`
      - `/tr/entegrasyonlar/yazarkasa-pos/` `[TEMPLATE] [P2]`
      - `/tr/entegrasyonlar/fis-yazicilari/` `[TEMPLATE] [P2]`
      - `/tr/entegrasyonlar/kiosk-cihazlari/` `[TEMPLATE] [P2]`
      - `/tr/entegrasyonlar/kitchen-display/` `[TEMPLATE] [P2]`
    - `/tr/entegrasyonlar/api-ve-webhook/` — API ve webhook entegrasyonları `[P1]`
    - `/tr/entegrasyonlar/talep-et/` — Yeni entegrasyon talebi `[P1]`
  - `/tr/musteriler/` — Müşteriler genel bakış `[P0]`
    - `/tr/musteriler/menu-ornekleri/` — Canlı QR menü örnekleri `[P0]`
      - `/tr/musteriler/menu-ornekleri/{slug}/` — Menü örneği detay sayfası `[TEMPLATE] [P0]`
    - `/tr/musteriler/musteri-hikayeleri/` — Müşteri hikayeleri `[P0]`
      - `/tr/musteriler/musteri-hikayeleri/{slug}/` — Vaka çalışması `[TEMPLATE] [P0]`
    - `/tr/musteriler/referanslar/` — Referans logoları `[P0]`
    - `/tr/musteriler/yorumlar/` — Müşteri yorumları `[P1]`
    - `/tr/musteriler/sektorler/` — Sektöre göre müşteri örnekleri `[P1]`
    - `/tr/musteriler/basari-metrikleri/` — Toplu başarı ve kullanım metrikleri `[P1]`
    - `/tr/musteriler/topluluk/` — Zabuno topluluğu `[P2]`
  - `/tr/fiyatlandirma/` — Ücretsiz, Pro ve Business planlarını; plan karşılaştırmasını ve fiyatlandırma SSS'lerini tek sayfada toplar `[P0]`
    - `/tr/fiyatlandirma/enterprise/` — Enterprise plan `[P1]`
    - `/tr/fiyatlandirma/coklu-sube/` — Zincir ve çoklu şube fiyatı `[P1]`
    - `/tr/fiyatlandirma/eklentiler/` — Add-on fiyatları `[P1]`
    - `/tr/fiyatlandirma/donanim/` — Donanım fiyatları `[P2]`
  - `/tr/kaynaklar/` — Kaynak merkezi `[P0]`
    - `/tr/blog/` — Blog ana sayfa `[P0]`
      - `/tr/blog/qr-menu/` — QR menü içerikleri `[P0]`
      - `/tr/blog/restoran-yonetimi/` — Restoran yönetimi `[P1]`
      - `/tr/blog/menu-muhendisligi/` — Menü mühendisliği `[P1]`
      - `/tr/blog/restoran-pazarlamasi/` — Restoran pazarlaması `[P1]`
      - `/tr/blog/yapay-zeka/` — Restoranlarda yapay zekâ `[P1]`
      - `/tr/blog/siparis-ve-odeme/` — Sipariş ve ödeme `[P2]`
      - `/tr/blog/entegrasyonlar/` — Entegrasyon içerikleri `[P1]`
      - `/tr/blog/mevzuat/` — Menü, KVKK ve ödeme mevzuatı `[P1]`
      - `/tr/blog/sektor-rehberleri/` — Sektör rehberleri `[P1]`
      - `/tr/blog/{slug}/` — Blog yazısı `[TEMPLATE] [P0]`
      - `/tr/blog/yazar/{slug}/` — Yazar sayfası `[TEMPLATE] [P1]`
      - `/tr/blog/etiket/{slug}/` — Etiket arşivi `[TEMPLATE] [P2]`
    - `/tr/rehberler/` — Uzun rehberler `[P0]`
      - `/tr/rehberler/qr-menu-nedir/` — QR menü nedir? `[P0]`
      - `/tr/rehberler/qr-menu-kurulum-rehberi/` — QR menü nasıl yapılır ve kurulur? `[P0]`
      - `/tr/rehberler/qr-menu-yazilimi-secme/` — Yazılım seçme rehberi `[P0]`
      - `/tr/rehberler/dijital-menu-tasarimi/` — Menü tasarım rehberi `[P1]`
      - `/tr/rehberler/menu-muhendisligi/` — Menü mühendisliği rehberi `[P1]`
      - `/tr/rehberler/self-servis-kiosk/` — Kiosk satın alma rehberi `[P2]`
      - `/tr/rehberler/qr-siparis-kurulumu/` — QR sipariş uygulama rehberi `[P1]`
      - `/tr/rehberler/otel-dijital-siparis/` — Otel sipariş rehberi `[P2]`
      - `/tr/rehberler/personel-eksikliginde-dijitallesme/` — Personel verimliliği rehberi `[P1]`
      - `/tr/rehberler/upsell-psikolojisi/` — Dijital menüde upsell `[P1]`
      - `/tr/rehberler/{slug}/` — Rehber detay sayfası `[TEMPLATE] [P1]`
    - `/tr/araclar/` — Ücretsiz araçlar `[P1]`
      - `/tr/araclar/qr-kod-olusturucu/` — QR kod oluşturucu `[P1]`
      - `/tr/araclar/menu-sagligi-testi/` — Menü sağlığı testi `[P1]`
      - `/tr/araclar/menu-kontrolu/` — Menü eksik bilgi kontrolü `[P1]`
      - `/tr/araclar/qr-menu-maliyet-hesaplayici/` — QR menü maliyet hesaplayıcı `[P1]`
      - `/tr/araclar/yatirim-getirisi-hesaplayici/` — ROI hesaplayıcı `[P1]`
      - `/tr/araclar/alerjen-kontrolu/` — Alerjen kontrol aracı `[P2]`
      - `/tr/araclar/menu-gorseli-optimizasyonu/` — Görsel optimizasyon aracı `[P2]`
      - `/tr/araclar/menu-ocr-demo/` — Fotoğraf/PDF menü OCR demosu `[P1]`
      - `/tr/araclar/menu-sablonlari/` — İndirilebilir menü şablonları `[P1]`
    - `/tr/karsilastirmalar/` — Yazılım karşılaştırmaları `[P1]`
      - `/tr/karsilastirmalar/qr-menu-ve-kagit-menu/` — QR menü ve kâğıt menü `[P0]`
      - `/tr/karsilastirmalar/qr-menu-ve-pdf-menu/` — QR menü ve PDF menü `[P1]`
      - `/tr/karsilastirmalar/en-iyi-qr-menu-yazilimlari/` — En iyi QR menü yazılımları `[P1]`
      - `/tr/karsilastirmalar/zabuno-ve-finedine/` `[P1]`
      - `/tr/karsilastirmalar/zabuno-ve-menum/` `[P1]`
      - `/tr/karsilastirmalar/zabuno-ve-menulux/` `[P1]`
      - `/tr/karsilastirmalar/zabuno-ve-narpos/` `[P1]`
      - `/tr/karsilastirmalar/zabuno-ve-adisyo/` `[P1]`
      - `/tr/karsilastirmalar/zabuno-ve-qrmenum/` `[P1]`
      - `/tr/karsilastirmalar/zabuno-ve-toast/` `[P2]`
      - `/tr/karsilastirmalar/zabuno-ve-lightspeed/` `[P2]`
      - `/tr/karsilastirmalar/zabuno-ve-choice/` `[P2]`
      - `/tr/karsilastirmalar/zabuno-ve-sunday/` `[P2]`
      - `/tr/karsilastirmalar/zabuno-ve-jamezz/` `[P2]`
      - `/tr/karsilastirmalar/zabuno-ve-menu-tiger/` `[P2]`
      - `/tr/karsilastirmalar/zabuno-ve-mydigimenu/` `[P2]`
      - `/tr/karsilastirmalar/{slug}/` — Karşılaştırma şablonu `[TEMPLATE] [P1]`
    - `/tr/e-kitaplar/` — E-kitaplar ve raporlar `[P1]`
      - `/tr/e-kitaplar/restoran-dijital-donusum-raporu/` `[P1]`
      - `/tr/e-kitaplar/restoran-teknoloji-trendleri/` `[P2]`
      - `/tr/e-kitaplar/{slug}/` — E-kitap tanıtım ve indirme formu `[TEMPLATE] [P1]`
    - `/tr/webinarlar/` — Webinarlar `[P1]`
      - `/tr/webinarlar/yaklasan/` — Yaklaşan webinarlar `[P1]`
      - `/tr/webinarlar/kayitlar/` — Webinar kayıtları `[P1]`
      - `/tr/webinarlar/{slug}/` — Webinar detay sayfası `[TEMPLATE] [P1]`
    - `/tr/akademi/` — Eğitim kataloğu ve sertifikalar `[P1]`
      - `/tr/akademi/{slug}/` — Rehberleri tekrarlamayan, uygulamalı eğitim detay sayfası `[TEMPLATE] [P1]`
    - `/tr/sozluk/` — QR menü ve restoran teknolojisi sözlüğü `[P1]`
      - `/tr/sozluk/{slug}/` — Terim detay sayfası `[TEMPLATE] [P1]`
    - `/tr/sablonlar-ve-kontrol-listeleri/` — Şablonlar `[P1]`
      - `/tr/sablonlar-ve-kontrol-listeleri/menu-dijitallestirme-listesi/` `[P1]`
      - `/tr/sablonlar-ve-kontrol-listeleri/restoran-acilis-listesi/` `[P1]`
      - `/tr/sablonlar-ve-kontrol-listeleri/alerjen-bilgi-sablonu/` `[P1]`
      - `/tr/sablonlar-ve-kontrol-listeleri/menu-fotograf-cekimi/` `[P1]`
    - `/tr/arastirma-ve-veriler/` — Sektör verileri `[P2]`
      - `/tr/arastirma-ve-veriler/restoran-teknoloji-endeksi/` `[P2]`
      - `/tr/arastirma-ve-veriler/menu-fiyat-trendleri/` `[P2]`
      - `/tr/arastirma-ve-veriler/tuketici-davranislari/` `[P2]`
  - `/tr/is-ortaklari/` — İş ortaklığı genel bakış `[P1]`
    - `/tr/is-ortaklari/bayi-ve-reseller/` — Bayi ve reseller programı `[P1]`
    - `/tr/is-ortaklari/affiliate/` — Affiliate programı `[P1]`
    - `/tr/is-ortaklari/referans-programi/` — İşletme önerme programı `[P1]`
    - `/tr/is-ortaklari/entegrasyon-ortaklari/` — Entegrasyon ortakları `[P1]`
    - `/tr/is-ortaklari/teknoloji-ortaklari/` — Teknoloji ortakları `[P2]`
    - `/tr/is-ortaklari/ajanslar/` — Ajans programı `[P2]`
    - `/tr/is-ortaklari/cozum-ortaklari/` — Çözüm ortakları `[P1]`
    - `/tr/is-ortaklari/ortak-dizini/` — Partner dizini `[P2]`
      - `/tr/is-ortaklari/ortak-dizini/{slug}/` — Partner detay sayfası `[TEMPLATE] [P2]`
    - `/tr/is-ortaklari/basvuru/` — İş ortaklığı başvurusu `[P1]`
    - `/tr/is-ortaklari/portal/` — Partner portalına giriş `[EXTERNAL] [P2]`
  - `/tr/gelistiriciler/` — Geliştirici merkezi `[P1]`
    - `/tr/gelistiriciler/baslangic/` — API başlangıç `[P1]`
    - `/tr/gelistiriciler/kimlik-dogrulama/` — Authentication `[P1]`
    - `/tr/gelistiriciler/api-referansi/` — API referansı `[P1]`
      - `/tr/gelistiriciler/api-referansi/hesaplar/` `[P1]`
      - `/tr/gelistiriciler/api-referansi/subeler/` `[P1]`
      - `/tr/gelistiriciler/api-referansi/menuler/` `[P1]`
      - `/tr/gelistiriciler/api-referansi/kategoriler/` `[P1]`
      - `/tr/gelistiriciler/api-referansi/urunler/` `[P1]`
      - `/tr/gelistiriciler/api-referansi/masalar/` `[P1]`
      - `/tr/gelistiriciler/api-referansi/siparisler/` `[P2]`
      - `/tr/gelistiriciler/api-referansi/odemeler/` `[P2]`
      - `/tr/gelistiriciler/api-referansi/musteriler/` `[P2]`
    - `/tr/gelistiriciler/webhooklar/` — Webhook dokümantasyonu `[P1]`
    - `/tr/gelistiriciler/hata-kodlari/` — Hata kodları `[P1]`
    - `/tr/gelistiriciler/hiz-limitleri/` — Rate limits `[P1]`
    - `/tr/gelistiriciler/sandbox/` — Test ortamı `[P1]`
    - `/tr/gelistiriciler/sdk/` — SDK'lar `[P2]`
    - `/tr/gelistiriciler/ornekler/` — Kod örnekleri `[P1]`
    - `/tr/gelistiriciler/changelog/` — API değişiklik günlüğü `[P1]`
    - `/tr/gelistiriciler/durum/` — API servis durumu `[EXTERNAL] [P1]`
    - `/tr/gelistiriciler/destek/` — Geliştirici desteği `[P1]`
  - `/tr/yardim/` — Yardım Merkezi `[P0]`
    - `/tr/yardim/baslangic/` — Başlangıç `[P0]`
      - `/tr/yardim/baslangic/hesap-olusturma/` `[P0]`
      - `/tr/yardim/baslangic/ilk-menuyu-olusturma/` `[P0]`
      - `/tr/yardim/baslangic/qr-kod-yayinlama/` `[P0]`
      - `/tr/yardim/baslangic/canliya-gecis-listesi/` `[P0]`
    - `/tr/yardim/hesap-ve-faturalandirma/` `[P0]`
    - `/tr/yardim/menu-yonetimi/` `[P0]`
    - `/tr/yardim/tasarim-ve-medya/` `[P0]`
    - `/tr/yardim/qr-ve-masa-yonetimi/` `[P0]`
    - `/tr/yardim/coklu-dil/` `[P0]`
    - `/tr/yardim/coklu-sube/` `[P1]`
    - `/tr/yardim/siparis-ve-odeme/` `[P1]`
    - `/tr/yardim/analitik-ve-raporlar/` `[P1]`
    - `/tr/yardim/entegrasyonlar/` `[P1]`
    - `/tr/yardim/zabuno-ai/` `[P1]`
    - `/tr/yardim/sorun-giderme/` `[P0]`
    - `/tr/yardim/guvenlik-ve-gizlilik/` `[P0]`
    - `/tr/yardim/veri-aktarimi/` — Başka sistemden geçiş `[P1]`
    - `/tr/yardim/uygulama-surumleri/` — Ürün değişiklik günlüğü `[P1]`
    - `/tr/yardim/makale/{slug}/` — Yardım makalesi `[TEMPLATE] [P0]`
    - `/tr/yardim/destek-talebi/` — Destek talebi oluştur `[P0]`
    - `/tr/yardim/sistem-durumu/` — Durum sayfasına geçiş `[EXTERNAL] [P0]`
  - `/tr/kurumsal/` — Kurumsal genel bakış `[P0]`
    - `/tr/kurumsal/hakkimizda/` — Hikâye, misyon, vizyon, değerler ve ekibi tek sayfada toplar `[P0]`
    - `/tr/kurumsal/kariyer/` — Çalışma kültürü ve açık pozisyonları tek sayfada toplar `[P1]`
      - `/tr/kurumsal/kariyer/{slug}/` — İş ilanı `[TEMPLATE] [P1]`
    - `/tr/kurumsal/basin/` — Basın merkezi `[P1]`
      - `/tr/kurumsal/basin/basin-bultenleri/` `[P1]`
      - `/tr/kurumsal/basin/basin-kiti/` `[P1]`
      - `/tr/kurumsal/basin/medyada-zabuno/` `[P2]`
    - `/tr/kurumsal/marka-rehberi/` — Logo ve marka kullanımı `[P1]`
    - `/tr/kurumsal/surdurulebilirlik/` — Sürdürülebilirlik `[P2]`
    - `/tr/kurumsal/erisilebilirlik/` — Erişilebilirlik yaklaşımı `[P1]`
    - `/tr/kurumsal/iletisim/` — Satış, destek, iş ortaklığı ve basın iletişimini tek sayfada yönlendirir `[P0]`
    - `/tr/kurumsal/demo/` — Demo talebi `[P0]`
    - `/tr/kurumsal/teklif/` — Teklif talebi `[P0]`
  - `/tr/guven/` — Güven Merkezi `[P0]`
    - `/tr/guven/guvenlik/` — Güvenlik yaklaşımı `[P0]`
    - `/tr/guven/altyapi/` — Altyapı ve süreklilik `[P1]`
    - `/tr/guven/yedekleme/` — Yedekleme politikası `[P1]`
    - `/tr/guven/olay-yonetimi/` — Güvenlik olayı yönetimi `[P1]`
    - `/tr/guven/alt-isleyenler/` — Subprocessor listesi `[P1]`
    - `/tr/guven/uyum/` — Uyum ve sertifikalar `[P1]`
    - `/tr/guven/sorumlu-aciklama/` — Güvenlik açığı bildirme `[P1]`
  - `/tr/yasal/` — Yasal belgeler merkezi `[P0]`
    - `/tr/yasal/gizlilik-politikasi/` `[P0]`
    - `/tr/yasal/kvkk-aydinlatma-metni/` `[P0]`
    - `/tr/yasal/kisisel-veri-isleme-envanteri/` `[P1]`
    - `/tr/yasal/veri-isleme-sozlesmesi/` — DPA `[P1]`
    - `/tr/yasal/kullanim-sartlari/` `[P0]`
    - `/tr/yasal/abonelik-sozlesmesi/` `[P0]`
    - `/tr/yasal/mesafeli-satis-sozlesmesi/` `[P0]`
    - `/tr/yasal/iptal-ve-iade/` `[P0]`
    - `/tr/yasal/cerez-politikasi/` `[P0]`
    - `/tr/yasal/cerez-tercihleri/` `[P0]`
    - `/tr/yasal/kabul-edilebilir-kullanim/` `[P1]`
    - `/tr/yasal/hizmet-seviyesi-sozlesmesi/` — SLA `[P1]`
    - `/tr/yasal/elektronik-iletisim-izni/` `[P0]`
    - `/tr/yasal/telif-ve-fikri-mulkiyet/` `[P1]`
    - `/tr/yasal/ucuncu-taraf-lisanslari/` `[P1]`
  - `/tr/arama/` — Site içi arama `[P1]`
  - `/tr/sss/` — Genel sık sorulan sorular `[P0]`
  - `/tr/site-haritasi/` — İnsanlar için HTML site haritası `[P1]`
  - `/tr/bulten/abonelik/` — E-posta bülteni aboneliği `[P1]`
  - `/tr/bulten/abonelikten-cik/` — Bülten aboneliğini sonlandırma `[P0]`
  - `/tr/tesekkurler/` — Form başarı sayfası `[P0]`
  - `/tr/404/` — Sayfa bulunamadı `[P0]`
  - `/tr/500/` — Sunucu hatası `[P0]`
  - `/tr/bakim/` — Bakım modu `[P0]`

## 5. XML sitemap yapısı

- `/sitemap.xml` — Sitemap index `[P0]`
  - `/sitemaps/tr-static.xml` — Türkçe sabit sayfalar `[P0]`
  - `/sitemaps/en-static.xml` — İngilizce sabit sayfalar `[P0]`
  - `/sitemaps/tr-products.xml` — Türkçe ürün sayfaları `[P0]`
  - `/sitemaps/en-products.xml` — İngilizce ürün sayfaları `[P0]`
  - `/sitemaps/tr-solutions.xml` — Türkçe çözüm sayfaları `[P0]`
  - `/sitemaps/en-solutions.xml` — İngilizce çözüm sayfaları `[P0]`
  - `/sitemaps/tr-integrations.xml` — Türkçe aktif entegrasyonlar `[P1]`
  - `/sitemaps/en-integrations.xml` — İngilizce aktif entegrasyonlar `[P1]`
  - `/sitemaps/tr-customers.xml` — Türkçe müşteri hikayeleri ve örnekler `[P0]`
  - `/sitemaps/en-customers.xml` — İngilizce müşteri hikayeleri ve örnekler `[P1]`
  - `/sitemaps/tr-blog.xml` — Türkçe blog yazıları `[P0]`
  - `/sitemaps/en-blog.xml` — İngilizce blog yazıları `[P1]`
  - `/sitemaps/tr-guides.xml` — Türkçe rehberler `[P1]`
  - `/sitemaps/en-guides.xml` — İngilizce rehberler `[P1]`
  - `/sitemaps/tr-tools.xml` — Türkçe ücretsiz araçlar `[P1]`
  - `/sitemaps/en-tools.xml` — İngilizce ücretsiz araçlar `[P1]`
  - `/sitemaps/tr-comparisons.xml` — Türkçe karşılaştırma sayfaları `[P1]`
  - `/sitemaps/en-comparisons.xml` — İngilizce karşılaştırma sayfaları `[P1]`
  - `/sitemaps/tr-help.xml` — Türkçe yardım makaleleri `[P0]`
  - `/sitemaps/en-help.xml` — İngilizce yardım makaleleri `[P1]`
  - `/sitemaps/images.xml` — İndekslenmesi gereken özgün görseller `[P1]`
  - `/sitemaps/videos.xml` — Özgün ürün ve eğitim videoları `[P2]`

XML sitemap'e alınmaması gerekenler:

- Uygulama giriş, kayıt ve şifre sıfırlama sayfaları.
- Arama sonuçları.
- Filtre ve sıralama parametreleri.
- Form başarı ve hata sayfaları.
- Çerez tercih ekranı.
- Taslak, arşivlenmiş veya desteklenmeyen entegrasyon sayfaları.
- Müşteri/tenant menü URL'leri bu kurumsal sitemap'e karıştırılmamalı; gerekiyorsa ayrı tenant sitemap altyapısı kullanılmalıdır.
- Aynı içeriğin query string, UTM veya kampanya parametreli kopyaları.

## 6. Navigasyon ilkesi

Header, mega menü, mobil menü ve footer yeni sayfalar oluşturmaz. Bunların tamamı 4. bölümde yalnızca bir kez tanımlanan canonical URL'lere bağlanır. Bu nedenle aynı sayfalar navigasyon başlıkları altında yeniden listelenmemiştir.

Reklam ve kampanya trafiği de ayrı kopya landing sayfalarına değil ilgili canonical ürün, çözüm veya fiyatlandırma sayfasına yönlendirilir. Kampanya ölçümü UTM parametreleriyle yapılır; canonical URL parametresiz sayfadır.

## 7. CMS içerik tipleri

Sitemap'in yönetilebilir olması için CMS'te aşağıdaki içerik tipleri ayrı tanımlanmalıdır:

- `ProductPage`
- `FeaturePage`
- `SolutionPage`
- `IndustryPage`
- `GoalPage`
- `IntegrationCategory`
- `IntegrationPage`
- `CustomerStory`
- `MenuShowcase`
- `BlogPost`
- `BlogCategory`
- `Guide`
- `ComparisonPage`
- `ToolPage`
- `Ebook`
- `Webinar`
- `Course`
- `GlossaryTerm`
- `HelpCategory`
- `HelpArticle`
- `ApiDocPage`
- `PartnerPage`
- `JobPosting`
- `PressRelease`
- `LegalDocument`

## 8. SEO ve yayınlama kuralları

- Her ürün sayfası tek bir ana kullanıcı problemi ve tek bir arama niyetine odaklanmalıdır.
- Özellik sayfaları, ürünün gerçekten çalışan özelliğini göstermelidir.
- Entegrasyon detay sayfalarında bağlantı yöntemi, aktarılan veriler, kurulum adımları, sınırlamalar ve destek durumu bulunmalıdır.
- Rakip karşılaştırmaları ölçülebilir ve güncel kriterlerle yazılmalı; yanıltıcı üstünlük iddiası kullanılmamalıdır.
- Sektör sayfaları yalnızca başlık değiştirilmiş kopyalar olmamalıdır; sektöre özgü iş akışı, sorun, örnek menü ve müşteri kanıtı içermelidir.
- Müşteri hikayelerinde başlangıç durumu, kullanılan Zabuno özellikleri ve ölçülebilir sonuç birlikte verilmelidir.
- Türkçe ve İngilizce sayfalar arasında `hreflang` bağlantıları kurulmalıdır.
- Listeleme, filtreleme ve arama sayfalarında canonical ve robots kuralları merkezi olarak yönetilmelidir.
- Yapılandırılmış veri türleri uygun sayfalarda kullanılmalıdır: `Organization`, `SoftwareApplication`, `Product`, `FAQPage`, `HowTo`, `Article`, `BreadcrumbList`, `Review`, `VideoObject`.
- Blog, yardım merkezi ve geliştirici dokümantasyonu ayrı içerik depolarına bölünse bile kullanıcı açısından tek Zabuno arama deneyiminde birleşmelidir.

## 9. Rakiplerden alınan yapısal örüntüler

- FineDine: Ürün, AI, çözüm, özellik ve kullanım senaryolarının ayrı katmanlar hâlinde sunulması.
- NarPOS: Blog, işletme türü, özellik ve entegrasyon detaylarında geniş SEO kapsamı.
- Menulux: Restoran programı, dijital menü, kiosk, robot ve sadakat ürün ailelerinin ayrıştırılması.
- Adisyo: Sektör, özellik, bilgi merkezi, akademi ve API'nin aynı kurumsal yapı içinde bulunması.
- QrMenum: QR menü özellikleri ve AI asistanlarının ayrıntılı mega menüde gösterilmesi.
- Toast ve Lightspeed: Ürün platformu, sektörler, müşteri kanıtı, entegrasyon ve kaynak merkezinin ölçekli organizasyonu.
- Choice: Ürün çeşitliliği, ülke ve dil yerelleştirmesi.
- sunday: Sayfaların teknik özellik yerine işletme hedeflerine göre de gruplanması.
- Jamezz: Çözüm, sektör, entegrasyon ve satın alma rehberlerinin birbirine bağlanması.
- QikServe: Sipariş, ödeme, kiosk ve mutfak operasyonlarının aynı ürün ailesinde gösterilmesi.
- MENU TIGER ve Menu Points: Rehber, karşılaştırma, ücretsiz araç ve yüksek niyetli SEO içeriği.
- MyDigiMenu: Ürünün yanında çeviri, beslenme uzmanı ve yemek fotoğrafçılığı gibi hizmetlerin sunulması.

## 10. Son karar

Zabuno'nun yapısı üç seviyede kurulmalıdır:

1. İlk yayın: QR menü, menü yönetimi, tasarım, çoklu dil, çoklu şube, analitik, Zabuno AI, fiyatlandırma, örnek menüler ve yardım merkezi.
2. Büyüme: Sektör sayfaları, entegrasyonlar, müşteri hikayeleri, rehberler, karşılaştırmalar, ücretsiz araçlar ve partner programı.
3. Platformlaşma: Sipariş, ödeme, kiosk, KDS, CRM, sadakat, rezervasyon, kurye ve operasyon modülleri.

Bu ayrım korunursa site ilk günden anlaşılır kalır; ürün büyüdükçe Toast veya Lightspeed benzeri geniş bir platform mimarisine dönüşebilir.
