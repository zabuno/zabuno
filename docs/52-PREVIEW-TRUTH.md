# 52 — Preview Truth: ekranda gördüğün, çalıştırdığın kod mu?

**Paket:** `FF-00` — `FRONTEND-FOUNDATION-v1` programının ilk paketi.
**Durum:** uygulandı.

## 1. Neden ilk paket bu

Frontend değerlendirme raporu Faz 1 için `NO-GO` verdi ve ilk önceliği görsel
tasarım DEĞİL, bu paket olarak belirledi. Gerekçe kanıtlanabilir bir olaydı:

> Geliştirme checkout'u `1fea9b6` iken `127.0.0.1:8787` sunucusu
> `6a923a3` sunuyordu.

Bu, bir hata değildi — hiçbir şey çökmedi, hiçbir kayıt düşmedi, arayüz gayet
normal göründü. Yalnızca **yanlış sürümü** gösterdi. Sonucu şu: o tur boyunca
yapılan her UI değerlendirmesi, her "bu düzeldi mi" kontrolü, her ekran
görüntüsü geçersizdi ve bunu kimse fark edemezdi.

Bu yüzden sıralama tartışmasızdır: **güvenilmeyen bir önizleme üzerinde
yapılan her sonraki paket de boşa gider.** Önce ölçüm aleti onarılır.

## 2. Raporun tanımından farkım

Rapor `FF-00`'ı "görünür build kimliği" olarak tanımlıyor. Bu tek başına
sorunu **çözmez** ve nedeni önemlidir:

Sorun, sürüm bilgisinin bulunamaması değildi. Sorun, sahibin **bakması
gerektiğini bilmemesiydi.** Arayüz normal görünüyordu. Gidip bakılması gereken
bir sürüm rozeti, bakılması gerektiği bilinmediği sürece hiçbir şeyi
engellemez — ve bir kez göz ona alıştıktan sonra hiç bakılmaz.

Bu yüzden mekanizma **çekme değil İTMEdir**: ayrışmayı uygulama fark eder ve
kendisi söyler. Her şey yolundayken **hiçbir şey çizilmez** — bu aynı zamanda
"kabukta ölü kontrol bulunmayacak" kuralına da uyar.

## 3. İki AYRI bayatlık — biri diğerinin yerine geçmez

| # | Tür | Nasıl oluşur | Sürüm karşılaştırması görür mü |
| --- | --- | --- | --- |
| 1 | **Sürüm uyuşmazlığı** | Sayfayı sunan kaynak ile JS paketi farklı commit'ten. Localhost'un başka bir worktree'den sunulması. | **Evet** |
| 2 | **Bayat derleme** | Kaynak düzenlendi, `npm run build` çalışmadı. | **HAYIR** |

İkincisi kritik: commit oluşmadığı için **iki taraf da aynı SHA'yı söyler.**
Sürüm kontrolü "temiz" der, oysa ekrandaki JavaScript diskteki kaynak
değildir. Geliştirmede sık olan da budur.

Bu yüzden ikinci sinyal sürüme değil **zamana** bakar: derleme anı
(`public/build/manifest.json` mtime) ile en son değişen ön yüz kaynağının anı
karşılaştırılır. Yalnız üretim DIŞINDA — üretimde kaynak derlemeden sonra
değişmez ve her istekte dosya taramak saf israftır.

## 4. Yapının kararları

**Sunucu tarafı `git` ÇAĞIRMAZ** (`app/Support/Build/GitHead.php`).
Her istekte süreç doğurmak üretimde kabul edilemez; üretim sunucusunda `git`
çoğu zaman yoktur; `shell_exec` genellikle kapalıdır. `.git` **dosya olarak**
okunur. Derleme zamanında (`vite.config.ts`) `git` çağırmak serbesttir —
derleme başına bir kez çalışır. Ayrım kasıtlıdır.

**`.git` bir dizin VARSAYILMAZ.** Worktree'de `.git` bir **dosyadır** ve asıl
depoyu gösterir. Yanlış sürümü sunan ortam tam olarak budur; bu dal
desteklenmezse dedektör kurulduğu tek hedefi ıskalar. Desteklenen üç yerleşim:
normal depo, worktree işaretçisi, ayrık HEAD. Ayrıca `packed-refs` de okunur —
`git gc` referansları oraya taşır ve yalnız gevşek dosyalara bakan bir okuyucu
deponun bakım görmesiyle **sessizce** çalışmayı bırakırdı.

**Çözülemediğinde `null` döner ve bunu söyler.** Uydurulmuş bir sürüm,
karşılaştırmayı her zaman "eşit" yapar ve dedektörü sessizce işlevsiz kılar.
Aynı sebeple: taraflardan biri bilinmiyorsa **karşılaştırma yapılmaz.**
Bilinmeyeni "farklı" saymak her kurulumda alarm verirdi; "aynı" saymak
dedektörü öldürürdü. İkisi de yanlış — doğrusu iddiada bulunmamaktır.

**Sıcak sunucu tespiti sunucudan gelir**, istemcide `import.meta.hot`
bakılmaz. O sabit test koşucusunda da tanımlıdır: ona bağlanan her kontrol
testte sessizce "sıcak" sanılır ve **hiçbir zaman sınanmaz.** Sınanamayan bir
kontrol güvenilemeyen bir kontroldür. Bu, testler yazılırken bulundu.

## 5. Hata sınırı — boş ekranın sonu

Bu pakete kadar depoda **tek bir hata sınırı yoktu.** Sonucu teorik değil,
gözlenmişti: `i.map is not a function` hatası bütün paneli bomboş bir sayfaya
çeviriyordu.

Boş sayfa, kullanıcı için en kötü arıza biçimidir: ne olduğunu, kimin suçu
olduğunu, ne yapacağını söylemez. Çoğu kullanıcı bunu "internetim gitti" diye
yorumlar ve **hiç bildirmez** — yani kusur hem kullanıcıyı engeller hem
görünmez kalır.

Sınır iki düzeyde:

- **Kök** (`scope="app"`) — üç giriş noktasında da (`workspace`, `platform`,
  `auth`).
- **Rota** (`scope="route"`) — `WorkspaceApp` ve `PlatformApp` içinde. Ayrı
  olması esas: yalnız kök sınır olsaydı tek bir ekranın çökmesi kenar
  çubuğunu ve başlığı da götürürdü, kullanıcının başka ekrana geçme imkânı
  kalmazdı.

`resetKey` kurtarmayı **gerçek** yapar: React bir hata yakaladıktan sonra o
ağacı kalıcı olarak bozuk sayar. Anahtar olmadan kullanıcı başka bölüme geçse
bile hata ekranı kalırdı ve tek çıkış sayfayı yenilemek olurdu — o da aynı
bozuk ekrana dönmek demektir.

**Çökme ölçüme gider.** Sahibin kilit kuralı her şeyin tenant bazında
gözlenebilmesi (`docs/46`); ön yüz çökmesi bugüne kadar bunun tamamen
dışındaydı, çünkü hata tarayıcıda olur ve sunucu kaydına hiçbir şey düşmez.
Yalnız hata **sınıfı** gönderilir — metni değil: mesajlar sıklıkla veri taşır
(`Cannot read 'email' of undefined`) ve dataLayer'a giren veri geri alınamaz.

## 6. Kapı

```bash
scripts/preview-truth check --url http://127.0.0.1:8787/login
```

Bir tur UI değerlendirmesine başlamadan **önce** çalıştırılır. Son satır tek
bir karar belirtecidir: `PASS`, `REVISION_MISMATCH`, `BUILD_STALE`,
`NO_BUILD_IDENTITY`, `UNREACHABLE`. Giriş ekranı bilerek seçildi: kimlik
doğrulaması gerektirmeyen tek uygulama yüzeyi orasıdır.

Yeni bir HTTP uç noktası **eklenmedi** — meta etiketi zaten okunabiliyordu;
ek yüzey açmanın gerekçesi yoktu.

## 7. Kanıt

| Kontrol | Sonuç |
| --- | --- |
| `BuildIdentity` + `GitHead` birim testleri (3 git yerleşimi + packed-refs + çözümsüzlük) | 9 test |
| Kimliğin gerçekten sayfaya bastığı | 2 test |
| Her React görünümü kimlik taşıyor (regresyon muhafızı) | 1 test, kusur sokularak düştüğü doğrulandı |
| Ayrışma mantığı | 7 test |
| Hata sınırı: yakalama, sıfırlama, ölçüm, metin sızdırmama | 5 test |
| Giriş noktası muhafızı | 3 test, **üç kural da** kusur sokularak düştüğü doğrulandı |
| Kapı betiği senaryoları | 5 senaryo |
| Canlı uçtan uca | `PASS` → kaynağa dokun → `BUILD_STALE` → `npm run build` → `PASS` |
| 320×480 tarayıcı | Şerit okunur, yatay kaydırma yok; sorun çözülünce **kaybolur** |

## 8. Bu paketin KAPSAMADIĞI

Rapordaki `FF-01`…`FF-07` paketleri açık: shell kontratları, sayfa şablonları,
Context Workbench, form motoru, ECA aksiyon modeli, bağlamsal AI ve golden
journey. Bu paket yalnız **ölçüm aletini** onarır; ölçülen şeyi düzeltmez.

Yan bulgu olarak giderildi: `flowbite-react` Vite eklentisi test kipinde
dosya izleyicisi kuruyor ve macOS'ta `EMFILE` ile **bütün ön yüz test paketini
yerelde çalışamaz** hâle getiriyordu. Test kipinde devre dışı bırakıldı —
eklenti yalnız CSS sınıf listesi üretir, jsdom testlerinde işlevi yoktur.
Yerel test döngüsünün hiç çalışmaması, Preview Truth'un aynı ailesinden bir
sorundur: doğrulayamadığın şeye güvenemezsin.
