# 154 — CI tarayıcı kapıları: arıza mı, kusur mu?

Bu depoda gerçek bir tarayıcıda ölçen iki kapı var. İkisi de jsdom'un
ölçemediği şeyi ölçtüğü için var: jsdom düzen hesaplamaz, her kutu sıfır
yüksekliktedir, hiçbir şey taşmaz ve hiçbir şey kaydırılmaz.

- `scripts/shell-scroll-gate` — kabuğun görünen alana oturması, belgenin
  kaymaması, ana alanın kayması, alt çubuğun dipte kalması.
- `scripts/mobile-ux-audit` — her Storybook hikâyesi 320×568'de ve ayrıca
  `dir=rtl` ile: yatay taşma, kırpılma, dokunma hedefi, hedefler arası
  ayrım, metin kırpılması, içerik yoğunluğu.

Kapıların kendisi doğru davranıyordu ve bu paket onlara dokunmadı. Sorun
kapılar değil, **altlarındaki tarayıcının güvenilmezliği** ve bir tarayıcı
açılmadığında ortaya çıkan **teşhis boşluğu**ydu.

---

## 1. Ölçülen düşme oranı

`gh run list --workflow=CI --limit 300` ile alınan pencere:
**2026-09-04 → 2026-09-08 arası tamamlanmış CI koşumları, 39 başarısızlık.**
Her başarısızlığın düşen ADIMI `gh run view <id> --json jobs` ile tek tek
okundu; tahmin edilmedi.

| Düşen adım                                        | Koşum |
| ------------------------------------------------- | ----: |
| Laravel test suite (PostgreSQL, deployment target) |    13 |
| Vitest suite                                       |    10 |
| JS/TS formatting check (Prettier)                  |     5 |
| JS/TS lint and typecheck                           |     3 |
| **Mobile UX audit (320px, real browser)**          |     2 |
| **Shell scroll gate (real browser)**               |     1 |
| Adaptive bundle gate self-test                     |     1 |
| PHP formatting check (Pint)                        |     1 |

Gerçek tarayıcı adımlarının düştüğü **3** koşumun logu okundu ve ikiye
ayrıldı:

| Koşum       | Tarih      | Adım             | Tür                                                      |
| ----------- | ---------- | ---------------- | -------------------------------------------------------- |
| 33997789409 | 2026-09-05 | Shell scroll gate | **ALTYAPI** — `Chrome hata ayıklama portu açılmadı.`     |
| 33986492223 | 2026-09-05 | Mobile UX audit   | KUSUR — `yeni ihlal: 4 · düzelen: 2` (ölçüm yapıldı)     |
| 33979995453 | 2026-09-05 | Mobile UX audit   | KUSUR — `yeni ihlal: 0 · düzelen: 67` (ölçüm yapıldı)    |

**Tarayıcı başlatılamadığı için düşen koşum sayısı: 39 başarısızlıkta 1
(%2,6).** Kalan 2 gerçek-tarayıcı düşüşü altyapı değil, ölçülmüş sonuçtu:
kapı çalıştı ve doğru cevabı verdi.

### 2026-09-08 gününün kendisi

Paketin gerekçesinde "aynı gün 25 koşumun 6'sı düştü, en az biri kesin
olarak bu" deniyordu. Ölçüldü ve **düzeltildi**: o gün (2026-09-08, ölçüm
anına kadar) **27 CI koşumu tamamlandı, 6'sı düştü** — sayı doğru — ama
**altısının hiçbiri tarayıcı yüzünden düşmedi**: üçü `Vitest suite`, üçü
`Laravel test suite (PostgreSQL)`. Tarayıcı kaynaklı tek düşüş üç gün
önceydi (2026-09-05).

Bu ayrımın kendisi paketin konusu: bugüne kadar iki kırmızı aynı görünüyordu
ve "hangisi makine, hangisi benim değişikliğim" sorusu logu satır satır
okumadan cevaplanamıyordu.

---

## 2. Kök neden

### 2.1 Ölçülebilen kısım

Düşen koşumun (33997789409) logu şunu söylüyor ve fazlasını söylemiyor:

```
23:09:06.60  shell-scroll-gate
23:09:21.69  Error: Chrome hata ayıklama portu açılmadı.
```

Aradaki süre **15,09 saniye** — `shell-scroll-gate`'in o günkü bekleme
bütçesinin tamamı (60 deneme × 250 ms). Yani Chrome ikilisi BULUNDU (aksi
hâlde farklı bir mesajla ve 2 koduyla çıkardı), ama hata ayıklama portu 15
saniye içinde açılmadı.

### 2.2 Ölçülemeyen kısım — ve bunun kendisi kusur

**Chrome'un neden açılmadığı ölçülemedi ve bu rapor onu uydurmuyor.**
Sebebi kodda yazılıydı: her iki kapı da tarayıcıyı `stdio: 'ignore'` ile
başlatıyordu. Chrome'un kendi söyledikleri — kum havuzu reddi, eksik
kütüphane, kilitli profil, `/dev/shm` baskısı — hepsi çöpe atılıyordu.
Süreç `error` ve `exit` olayları da hiç dinlenmiyordu: araç, tarayıcının
ilk yarım saniyede ölmüş olup olmadığını bile bilmiyordu; ölü bir sürecin
portunu 15 saniye beklemeye devam ediyordu.

Elde kalan tek cümle "port açılmadı" idi ve bu cümle bakan kişiye hiçbir
şey söylemiyor.

### 2.3 Ölçülen yapısal kusur: iki kopya, iki sözleşme

Tarayıcıyı başlatan kod **tek yerde değildi**. İki kapı da kendi
kopyasını taşıyordu ve kopyalar çoktan ayrışmıştı:

|                    | `shell-scroll-gate` (eski) | `mobile-ux-audit` (eski) |
| ------------------ | -------------------------- | ------------------------ |
| Port               | sabit `9333`               | `0` (Chrome seçer)       |
| Portu bulma        | `127.0.0.1:9333/json/list` | `DevToolsActivePort`     |
| Bekleme bütçesi    | 15 sn                      | 30 sn                    |
| stderr             | atılıyor                   | atılıyor                 |
| Süreç ölümü        | fark edilmiyor             | fark edilmiyor           |
| Yeniden deneme     | yok                        | yok                      |
| `--user-data-dir`  | tek, sabit                 | tek, sabit               |

Düşen taraf, ikisinden **kısa bütçeli ve sabit portlu** olandı. Sabit port
tek başına da bir risktir: aynı makinede ikinci bir çalışma ağacı ya da
yarım kalmış bir Chrome 9333'ü tutuyorsa kapı ya bağlanamaz ya da BAŞKA
birinin tarayıcısını ölçer. Bu ikisi, "düzeltme bir yerde kalır, öteki
düşmeye devam eder" tarifinin birebir kendisiydi.

---

## 3. Ne sağlamlaştırıldı

Başlatma tek dosyaya taşındı: **`scripts/browser-session.mjs`**. İki kapı
da artık oradan geçiyor ve kendi kopyalarını taşımıyor.

1. **Koşul beklenir, sabit uyku yoktur.** İki koşul birden aranır: Chrome
   portu profil dizinindeki `DevToolsActivePort` dosyasına yazmış olmalı VE
   o portta `/json/list` gerçekten cevap vermeli.
2. **Ölü sürecin portu beklenmez.** `error` ve `exit` olayları dinlenir;
   süreç ölürse zaman aşımı doldurulmadan o deneme biter. Ölçüldü: çöken
   bir ikilide eski davranış 3 × 8 saniye beklerken yeni davranış 1 saniyede
   sonuç veriyor (`scripts/browser-session.test.sh`).
3. **Yeniden deneme sınırlıdır: 3.** Sonsuz değil. Her deneme **kendi
   profil dizinini** alır — kilitli ya da yarım kalmış bir profil, Chrome'un
   açılmamasının bilinen sebeplerindendir ve aynı dizinle yeniden denemek
   aynı sonucu verirdi.
4. **Sabit port kalktı.** Her iki kapı da `--remote-debugging-port=0`
   kullanıyor; port çakışması ve yabancı bir tarayıcıya bağlanma olasılığı
   sıfırlandı.
5. **stderr artık yakalanıyor** (son 12 satır) ve arıza raporunda basılıyor.
6. **Gerekçeli bayraklar eklendi:** `--disable-dev-shm-usage` (küçük
   `/dev/shm`, başsız Chrome'un bilinen açılmama sebeplerinden),
   `--disable-background-networking` (açılışta bileşen/güncelleme isteği
   yok), `--no-default-browser-check`. Kum havuzu **kapatılmadı**;
   `ZABUNO_BROWSER_NO_SANDBOX=1` ile açıkça istenirse kapanır ve o zaman
   teşhis çıktısında görünür.
7. **Teşhis edilebilir arıza çıktısı.** Artık şunlar basılıyor: hangi ikili,
   hangi sürüm, her denemenin sonucu ve süresi, sürecin çıkış kodu ve
   sinyali, `DevToolsActivePort` yazıldı mı, kullanılan tam bayrak listesi,
   stderr'in son satırları.

### Kapı GEVŞEMEDİ

- Tarayıcı açılamazsa çıkış hâlâ sıfırdan farklıdır; adım hâlâ kırmızıdır.
- `continue-on-error`, `|| true`, `if: always()` **yok** — CI dosyasında
  hiçbiri geçmiyor.
- Yeniden deneme yalnız **başlatmayı** kurtarır. Ölçüm başladıktan sonra bu
  modülün işi biter: ihlal eden bir ölçüm yeniden denenmez.
- "Tarayıcı yok, atlıyorum" hâlâ yok. Ölçüm yapılmadıysa sonuç "geçti"
  değil "bilinmiyor"dur ve bilinmeyen bir sonuç yeşil gösterilmez (küresel
  `TOUCH-FIRST-INTERFACE` madde 4).

---

## 4. Altyapı arızası ile gerçek kusur nasıl ayrıldı

**Çıkış koduyla ve çıktının kendisiyle.** İkisi de kırmızıdır; ayrı
okunabilirler.

| Durum                                | Çıkış | Çıktı                                   |
| ------------------------------------ | ----: | --------------------------------------- |
| Tarayıcı başlatılamadı (ALTYAPI)     | **3** | `═══ ALTYAPI ARIZASI — TARAYICI BAŞLATILAMADI ═══` + tam teşhis |
| Ölçüm yapıldı, kural ihlal edildi    | **1** | `N ölçüm başarısız` / `yeni ihlal: N`   |
| Kullanım hatası (eksik argüman)      | **2** | `kullanım: …`                           |

Altyapı çıktısı bunu açıkça söylüyor:

```
═══ ALTYAPI ARIZASI — TARAYICI BAŞLATILAMADI ═══

Bu bir ölçüm sonucu DEĞİLDİR: ölçüm hiç yapılamadı, yani düzenin
kurala uyup uymadığı BİLİNMİYOR. Bilinmeyen bir sonuç yeşil
gösterilmez; bu yüzden kapı kırmızıdır.

sebep         : debugger-port-never-opened
kapı          : shell-scroll-gate
ikili         : /usr/bin/google-chrome
sürüm         : Google Chrome 152.0.7977.77
deneme başına : 20000 ms

── deneme 1 ──
  sonuç              : process-exited (100 ms sonra)
  süreç çıkış kodu   : 127
  DevToolsActivePort : YAZILMADI
  bayraklar          : --headless=new --remote-debugging-port=0 …
  stderr (son 1 satır):
      Failed to move to new namespace: Operation not permitted
```

Bu blok logda göründüğünde cevap bellidir: kırmızı, paketin değil makinenin.
Blok yoksa ve `N ölçüm başarısız` varsa cevap da bellidir: ölçüm yapıldı ve
düzen kuralı ihlal etti.

---

## 5. Tarayıcı kurulu ve sürümü belli

CI'ya iki adım eklendi ve ikisi de pahalı derlemelerden **önce** koşuyor:

```yaml
- name: Browser preflight (version on record, one real launch)
  run: node scripts/browser-session.mjs --preflight

- name: Browser session self-test
  run: ./scripts/browser-session.test.sh
```

Ön kontrol ikiliyi çözer, **sürümü loga basar** ve tarayıcıyı bir kez
gerçekten açıp kapatır. İki kazanç:

1. Altyapı arızası, dört dakikalık bir Storybook derlemesinin sonunda değil
   başında görünür.
2. **Sürüm kayda geçer.** Runner imajının Chrome'u sessizce değişirse
   ölçülen değerler de sessizce değişir. Bu depoda birebir bu yaşandı — o
   sefer yazı tipi yüzünden (`scripts/mobile-ux-audit`, `document.fonts.ready`
   gerekçesi). Sürüm iki koşumun logunda durursa "ölçüm mü değişti, motor
   mu" sorusu cevaplanabilir. Aynı künye artık kapıların kendi çıktısında da
   var (`shell-scroll-gate` ilk satır, `mobile-ux-audit` stderr ilk satır).

Runner imajının Chrome'u sabitlenmedi: sabitlemek, sürüm kayması riskini
üçüncü taraf bir eyleme ve ağ indirmesine takas ederdi. Seçilen yol, kaymayı
ENGELLEMEK değil GÖRÜNÜR kılmak.

---

## 6. Kendi kendini test

`scripts/browser-session.test.sh` — 27 belirlenimci ölçüm. Gerçek Chrome'a
karşı koşmuyor; sahte "tarayıcı" ikilileriyle her arıza biçimi kuruluyor
(hemen çöken, yaşayan ama portu hiç açmayan, portu geç açan, ilk denemede
çöküp ikincide açılan, hiç var olmayan). Gerçek Chrome'a karşı koşan bir
test, tam olarak düzeltmeye çalıştığımız kararsızlığı taşırdı.

Ölçülenler arasında kapının gevşemediği de var: yeniden deneme sayısının
sınırlı olduğu, başarısızlığın hâlâ sıfırdan farklı bir kodla bittiği ve
"geçti" kelimesinin ölçüm yapılmadığında hiç geçmediği.

---

## 7. Bu paketin ölçmediği

- iOS Safari davranışı. Her iki kapı da Chrome'da koşuyor ve başkasını
  ölçtüğünü iddia etmiyor.
- 33997789409'da Chrome'un **neden** açılmadığı. O koşumun stderr'i o gün
  atıldı ve geri getirilemez. Aynı arıza tekrarlarsa artık cevaplanabilir
  olacak — bu paketin yaptığı şey tam olarak budur.
- Diğer 36 CI düşüşünün sebepleri (13 Laravel, 10 Vitest, 5 Prettier, 3
  lint, 1 bundle self-test, 1 Pint). Bu paketin konusu değil; sayıları
  yukarıda, karşılaştırma yapılabilsin diye duruyor.
