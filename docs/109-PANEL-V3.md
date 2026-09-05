# 109 — Panel v3: KANONİK kaynak ve yenileme planı

**Kaynak:** `docs/reference/panel-v3/` — sahibin 2026-09-05'te verdiği
`Zabuno Paneli-v3.html` paketinden çıkarılmış iki dosya:

- `panel.dc.html` — panel kabuğu ve on ekran
- `MedyaModulu.dc.html` — medya modülü ve on bir bölümü

**Sahibin cümlesi:** *"burası master data. eski UI tamamen silinebilir (eğer
gerekli ise). bir tercih yapmak gerektiğinde buradaki data esastır. bu
görevin amacı mevcut repo daki tasarımı güncellemek değil YENİLEMEKTİR.
benzetmek değil DEĞİŞTİRMEKTİR."*

## 0. Bu belge neden var

`docs/108` bir önceki sürümün planıydı ve o sürüm **eksik uygulandı** —
sahip yeni sürümü tam da bu yüzden gönderdi. Bu belge onun yerine geçmez,
üstüne yazar: v3 kanoniktir, `docs/108` tarihsel kayıttır.

Bir önceki turda yapılan hata şuydu: ekranlar "benzetildi". Var olan bileşen
korunup üstüne jeton sürüldü; kaynağın GETİRDİĞİ yeni yetenekler (toplu
işlem, yönetişim, AI önerileri, menü hapları, CSV, fotoğraftan aktar) hiç
doğmadı. Bu turda ölçü şudur: **kaynakta olan ve üründe olmayan her bölüm,
ya doğar ya da neden doğmadığı yazılır.**

## 1. Panel — on ekran

| Ekran | Kaynakta ne var | Depoda durum |
| --- | --- | --- |
| Home | Karşılama, "Şimdi" kartı, **AI önerileri (3, onaylı)**, **4 hızlı eylem**, sparkline'lı sayaçlar, Kurulum 3/5, **Bugün en çok bakılanlar** | kısmi — AI önerileri, hızlı eylemler, sparkline, en çok bakılanlar YOK |
| Menüler | **Menü hapları** (Ana menü yayında · Kahvaltı 07–11 · Ramazan kapalı), kategori rayı + sayaç, ürün satırı, **Fotoğraftan aktar**, **CSV**, **Önizle** | kısmi — haplar, çoklu menü, CSV, fotoğraftan aktar YOK |
| QR kodlar | Masa kartları ızgarası + tarama sayısı, sağ panel (tema/boyut/PDF/yazdır), **ölçülmüş kontrast**, toplu kod üretimi | kısmi |
| Insights | KPI kartları, **çubuk+çizgi grafik**, **saat ısı haritası**, **şube halkası**, masa sıralaması, **aranıp bulunamayanlar + Ekle** | kısmi — grafikler ve "Ekle" eylemi YOK |
| Yayınlama | **Taslak→Önizleme→Yayında adım çizgisi**, değişiklik listesi, hazırlık kontrolü, sürümler + **Geri al**, **Planla**, **Telefonda önizle** | kısmi — adım çizgisi, planla, telefonda önizle YOK |
| Şubeler | Şube kartları (durum, masa, tarama/hafta, saat), Masalar/Düzenle | kısmi |
| Medya | Panelin bir MODÜLÜ; kendi alt gezintisi | aşağıda |
| Takım | Üyeler + rol seçici, bekleyen davetler, **roller ne yapabilir** açıklaması | kısmi |
| Ayarlar | — | var |
| Profil | — | var |

## 2. Medya modülü — on bir bölüm

| Bölüm | Depoda durum |
| --- | --- |
| Kütüphane | var |
| **Toplu işlem** | **YOK** — Kapsam → Eylem → Ayar → Etki → Sonuç sihirbazı |
| Yükle | var |
| Dönüştür | var |
| Görüntüle | var (video oynatıcı YOK) |
| Boyut motoru | var |
| Kuyruk | var |
| Kota ve çöp | var (CDN kartı ve dağıtım ağı anahtarı YOK) |
| **Yönetişim** | **YOK** — yetki matrisi, saklama politikası, yasal saklama, denetim izi |
| Ayarlar | var |
| Olgunluk | **YOK** |

## 3. Kaynağın değişmez kuralları (kendi cümleleri)

- **"Öneri yapar, sen onaylarsın. Onaysız hiçbir şey değişmez."** AI hiçbir
  şeyi kendiliğinden uygulamaz.
- **"Basılı kod hiç değişmez; menüyü istediğin kadar güncelle."**
- **"Geri alma da bir yayındır: yeni sürüm numarası alır, QR aynı kalır."**
- **"İş başladığı anda liste dondurulur."** Toplu işlem çalışırken yüklenen
  yeni dosya o işe girmez.
- **"Herkes sadece işine yeteni görür."**
- **"Silinen dosya 30 gün burada bekler, sonra kalıcı silinir."**

## 4. Yenileme kuralı — benzetme değil değiştirme

1. Bir ekran kaynakta neyse ODUR. Var olan bileşen kaynağın düzenini
   taşıyamıyorsa **değiştirilir**, korunmaz.
2. Kaynakta olmayan bir bölüm üründe duruyorsa kaldırılır; kaldırılamıyorsa
   (veri/gizlilik/güvenlik) sebebi yazılır.
3. Kaynakta olan ve üründe VERİSİ OLMAYAN bir bölüm **uydurulmaz**: ya
   gerçek veriye bağlanır ya da hiç çizilmez ve burada sebebi yazılır.
4. Güvenlik değişmezleri kaynağın üstündedir ve sahibe SORULUR: bir önceki
   turda SVG, PDF, tarama anahtarı ve "aslını sakla" böyle karara bağlandı
   (`docs/108`). O kararlar geçerlidir.

## 5. Sayaç

0/11 tamamlandı, 0/11 aktif.

Bölümler: Home · Menüler · QR · Insights · Yayınlama · Şubeler · Takım ·
Medya/Toplu işlem · Medya/Yönetişim · Medya/Olgunluk · Medya/kalan farklar.
