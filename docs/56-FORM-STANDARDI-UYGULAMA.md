# 56 — Form standardı: ekran şema değildir (formlar)

**Paket:** `FF-04a` — `docs/47` ve marka formu raporunun uygulanması.

## 1. Kök tespit

> Veri tabanı ve API alanları, olduğu gibi kullanıcı formuna çıkarılmış.

Kullanıcı marka sahibi olmak isterken sistem ondan `Europe/Istanbul`, `TRY` ve
`tr` gibi **geliştirici kodlarını bilmesini ve yazmasını** bekliyordu.

Sunucu bu değerleri doğru doğruluyordu (IANA saat dilimi, ISO-4217 para
birimi, desteklenen dil listesi). Yani `ISTANBUL` yazan kullanıcı haklı olarak
reddediliyor — ve **ne yazması gerektiğini hiçbir yerden öğrenemiyordu.**

## 2. Ne değişti

| Alan | Önce | Sonra |
| --- | --- | --- |
| Dil | Serbest metin, etiketi "Locale" | Seçenek listesi, etiketi **"Menu language"** |
| Saat dilimi | Serbest metin | Pazar seçimiyle daraltılmış liste, `İstanbul — UTC+03:00` |
| Para birimi | Serbest metin | `Turkish lira — TRY — ₺` |
| Slug | Salt-okunur, etiketi **"Slug"** | Salt-okunur, etiketi **"Menu web address"** + ne işe yaradığı |

Seçenekler **sunucudan** gelir (`/api/reference/markets`). Listeyi ön yüze
sabit yazmak, sunucunun kabul ettiği değerlerle ekranda sunulanların bir gün
ayrışması demekti.

`locales` uç noktaya bu pakette eklendi ve iki testle bağlandı: her sunulan
dil, `LocaleCode`'un **kabul ettiği** bir dildir. Listede olup doğrulamadan
geçmeyen bir seçenek, kullanıcıyı seçtiği şey yüzünden hataya sokardı.

## 3. Sessiz veri kaybı

Bir `<select>`, değerini seçeneklerinde bulamazsa **sessizce ilk seçeneğe
atlar**. Kullanıcı hiçbir şey yapmadan formu kaydettiğinde markasının dili
değişmiş olur ve bunun ekranda hiçbir belirtisi olmaz.

Uzak bir ihtimal değil: sunucunun listesi ICU sürümüne göre değişir. Mevcut
değer artık sunulmuyorsa listeye **eklenir**. Testi var ve önce kırmızıydı —
kendi yazdığım yorumu uygulamayı unutmuştum.

## 4. Arıza sınıfları

Önceden her başarısızlık aynı cümleye düşüyordu: *"We could not create your
brand. Please try again."* Kullanıcı bundan hiçbir şey öğrenemiyordu.

**"Tekrar deneyin" yalnız bir durumda doğru tavsiyedir.**

| Durum | Mesaj |
| --- | --- |
| 403/401 | İzniniz yok — kime sorulacağı yazar |
| 409 | Başkası değiştirdi — yeniden yükleyip tekrar uygula |
| 404 | Kayıt artık yok |
| 5xx | Şimdi kaydedilemedi; **hiçbir şey kaybolmadı** |
| Ağ | Ulaşılamadı; **yazdıklarınız duruyor** |

Destek kodu **uydurulmaz**: sunucu `X-Request-Id` gönderdiyse gösterilir,
göndermediyse hiç gösterilmez. Destek ekibinin arayamayacağı bir kod, hiç kod
olmamasından kötüdür.

## 5. Hata özeti

Alan altındaki hatalar tek başına yetmez: uzun bir formda kullanıcı gönder'e
bastığında hatalı alanlar ekranın dışında kalmış olabilir ve formun
gönderilmediğini bile fark etmeyebilir.

Özet üç işi birden yapar — kaç hata var, ne oldukları, ve her satırdan ilgili
alana **atlatır**. Odak özete taşınır: `role="alert"` mesajı duyurur ama
kullanıcıyı oraya götürmez.

Özet, alan hatalarından **türetilir**; ayrı bir liste tutulmaz. İki liste
olsaydı biri diğerini unutabilirdi.

## 6. Kontrol kenarlığı kontrastı

Rapor ölçtü: girdi kenarlığı koyu temada **1.43:1** — WCAG 2.2 AA metin dışı
kontrast için 3:1 gerekiyor. Bağımsız hesabım aynı sayıyı verdi.

Ayrı bir `--border-control` jetonu eklendi (açık **3.36:1**, koyu **3.29:1**).
`--border` toptan koyulaştırılmadı: aynı jeton kart ve ayraçlarda da
kullanılıyor ve kural **kontrol** sınırları içindir; hepsini kalınlaştırmak
arayüzü ağırlaştırır, hiyerarşiyi siler.

## 7. Kanıt

1003 PHP testi, 1022 ön yüz testi, pint / eslint / prettier temiz.
Form standardı için 7 yeni ön yüz testi, referans dilleri için 2 PHP testi.
i18n mührü 457 → 468.

## 8. Bu paketin KAPSAMADIĞI — ve neden

Raporun 1. maddesi **alan sahipliğinin taşınması**: saat dilimi lokasyona,
para birimi fiyat listesine, `locale` ise kullanıcı/marka/menü olarak üçe
ayrılmalı.

Bu paket onu yapmıyor ve sebebi ölçüldü: `locations` tablosunda `timezone`
sütunu **yok**. Taşıma bir migration, geri doldurma, alan sahipliği değişikliği
ve yayınlanmış menü anlık görüntülerinin gözden geçirilmesini gerektiriyor —
ayrı ve dikkatli bir paket. Bu paket, kullanıcının **yazamayacağı bir kodu
yazmaya zorlanmasını** bitiriyor; alanın hangi nesneye ait olduğu sorusu
açık kalıyor.
