# 55 — Bilgi mimarisi: modül listesi değil, kullanıcının yolu

**Paket:** `FF-01c` — `docs/50` §5'in uygulanması.

## 1. Neyi düzeltiyor

Kenar çubuğu dokuz düz maddeydi ve bu liste **backend modüllerinin** listesiydi,
kullanıcının işlerinin değil. Dokuz eşit seçenek, kullanıcıyı her seferinde
listenin tamamını okumaya zorlar.

`FF-01b`'de bir gruplama yapmıştım (`Your restaurant / Your menu / Your
business`) — **sahibin tarif ettiği mimari o değildi.** `docs/50` §5 net:
`Primary / Management / Utility`, ve belirli taşımalar. Bu paket onu uyguluyor.

## 2. Yeni yapı

| Grup | Hedefler |
| --- | --- |
| **Operations** | Home, Menus, QR codes, Insights |
| **Management** | Locations, Media, Team |
| **Settings** | Settings |

### Adlar yolculuğa göre değişti

| Önce | Sonra | Neden |
| --- | --- | --- |
| Dashboard | **Home** | Bir gösterge paneli değil, her gün dönülen başlangıç noktası |
| Menu | **Menus** | Bir çalışma alanında birden fazla menü olabilir |
| Analytics | **Insights** | "Analytics" bir modül adı; aranan şey içgörü |

### Ana menüden çıkanlar

| Bölüm | Yeni yeri | Neden |
| --- | --- | --- |
| Brand | Settings → Brand | Marka bir kez kurulur; günlük operasyon değil |
| Billing | Settings → Plan & billing | Ayda bir bakılır |
| Publication | Menus → **Preview & publish** | Yayınlama bir MENÜYE aittir; çalışma alanında birden fazla menü olabilir ve ana menüdeki "Publication" hangi menüyü yayınladığını söylemiyordu |

### Yeni hedef: QR codes

Yayın sayfasının en altında gömülüydü. Restoran sahibi QR koduna yayınlamak
için değil **basmak** için gelir; bu iki iş aynı gün bile yapılmaz. Sık
yapılan bir iş, nadir yapılan bir işin arkasına saklanmıştı.

### Workspace switcher kenar çubuğunun ÜSTÜNE

Önce dibindeydi ve "Switch workspace" adlı bir bağlantıydı — yani her gün
gidilen hedeflerin arasına karışmış bir gezinti maddesi. Oysa bu bir hedef
değil, hepsinin üzerindeki **bağlam**: "hangi restorandayım" sorusu listenin
içinde aranmaz, listenin başında cevaplanır.

## 3. Üçüncü hâl: listelenmez ama adresi çalışır

Kayıt defterine bilinçli bir üçüncü durum eklendi. Bir bölümün **grubu yoksa**
kenar çubuğunda görünmez, ama adresi çalışmaya devam eder.

Bir adresin çalışması ile bir hedefin listelenmesi **ayrı sorulardır**.
Paylaşılmış bir `/brand` bağlantısı, o bölüm menüde görünmüyor diye
kırılmamalı. Testler artık ikisini ayrı ayrı sınıyor.

## 4. Sekmeler gerçek adres

`settings/billing` gerçek bir adrestir; sekme bileşen durumu değil. Böylece
paylaşılabilir, yer imine eklenebilir ve geri tuşu beklendiği gibi çalışır.
Bölüm çözümlemesi yalnız ilk parçaya bakar, ikinciyi sayfa kendisi okur.

Sekmeler `role="tablist"` taşır. Bir dizi düğmeyi sekme gibi GÖSTERMEK
yetmez: ekran okuyucu kullanan biri kaç sekme olduğunu ve hangisinde
bulunduğunu ancak rollerden öğrenir.

## 5. Kanıt

1001 PHP testi, 1015 ön yüz testi, pint / eslint / prettier temiz.
Dondurulmuş i18n kataloğu 444 → 457 olarak yeniden mühürlendi.

Yolda bir React derleyici hatası da düzeltildi: yeni yayın-durumu kancası
efekt içinde eşzamanlı `setState` çağırıyordu. "Yükleniyor" bağımsız bir bilgi
değil — elimizdeki cevabın şu anki isteğe ait olup olmadığıdır; artık
türetiliyor.

## 6. Kalan

Bağlam paneli içeriği, form standardı (`docs/47` §4), gerçek route yapısı
(`/app/:workspace/menus/:menuId/...`), omnibox, sayfa şablonu kataloğu,
medya asset/version/rendition modeli ve Engineering/Platform yüzey ayrımı.
