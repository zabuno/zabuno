# 42 — Dağıtım: netcup VPS + Docker + PostgreSQL

Owner kararı (2026-08-27): Faz 2'den önce deploy edilecek. Birincil hedef
**netcup VPS, AMD EPYC, linux/amd64**, veritabanı **PostgreSQL**, yığın
**Docker Compose** ile.

## Ne kuruldu

| Parça | Dosya | Ne yapar |
| --- | --- | --- |
| Üretim imajı | `docker/Dockerfile` | Üç aşama: varlık derleme, bağımlılık çözümü, çalışma zamanı. Derleme araçları son imaja girmez |
| Web sunucusu | `docker/nginx.conf` | php-fpm önünde; varlıklara uzun önbellek, gizli dosyalara ret |
| Süreç yöneticisi | `docker/supervisord.conf` | nginx + php-fpm tek konteynerde, ikisi de yeniden başlar |
| Açılış | `docker/entrypoint.sh` | DB bekler → migrasyon → önbellek ısıtma → servis |
| Yığın | `docker-compose.yml` | `db` + `app` + `clamav` + `proxy`, üç ağ kuralı |
| Virüs tarayıcı | `docker/clamd.conf`, `docker/clamd.client.conf`, `docker/zabuno-scan` | Daemon ayrı `clamav` servisinde (imzalar bellekte, ~1,2 GB); uygulama imajında yalnız ince istemci; dosya soketten akıtılır. Düz `clamscan` her çağrıda veritabanını yükleyip zaman aşımına takılacağı için seçilmedi. Kapı: `MalwareScanTransportTest` |
| Topoloji katmanları | `docker-compose.local.yml`, `docker-compose.edge-proxy.yml` | Aynı yığın, farklı ortam: geliştirici makinesi ve hazır vekil arkası |
| HTTPS | `docker/Caddyfile` | Sertifikayı Caddy alır ve yeniler |
| Yayın akışı | `.github/workflows/deploy.yml` | CI geçince tetiklenir; imajı derler, SSH ile aktarır, yayına alır, sağlık kontrolü yapar |

## Neden bu kararlar

**Veritabanı internete açılmıyor.** `db` servisinin yayımlanmış portu yok ve
`internal: true` işaretli bir ağda. Bir portu açmak, parolayı tek savunma
hattı yapar.

**Deploy otomatiktir ama kapılıdır** (owner kararı, 2026-08-27). `main`'e
birleşme yayına döner — sahibi güncellemeyi GitHub'dan izler ve kimsenin
sunucuya bağlanması gerekmez.

Kapı şudur: akış `push` yerine `workflow_run` ile bağlıdır ve CI'ın
`conclusion` değerini kontrol eder. Doğrudan push'a bağlansaydı deploy
testlerle YARIŞIR ve kırık bir sürüm yayına çıkabilirdi. Yayına çıkan
commit, CI'ın geçtiği commit'tir — dalın o anki ucu değil.

**SSH host anahtarı sabitlenir.** Deploy kanalı üretim sunucusuna kök
erişimdir; ilk bağlantıda host anahtarını körlemesine kabul etmek o kanalı
ortadaki adama açar. `DEPLOY_KNOWN_HOSTS` bu yüzden zorunlu.

**`opcache.validate_timestamps=0`.** İmaj değişmezdir; dosya değişikliği
yalnız yeni imajla gelir. Aynı ayarın FTP tabanlı iş akışını sessizce
öldürdüğü `docs/13` §7'de kayıtlı — orada tehlike, burada doğru davranış.

**Sağlık kontrolü uygulamadan geçer.** Konteynerin ayakta olması uygulamanın
çalıştığı anlamına gelmez; yeşil bir deploy işi düşmüş bir siteyi
gizlememeli.

## Sunucuda bir kerelik hazırlık

VPS'te bir kullanıcı ve şu dizin gerekir: `/opt/zabuno/` (`install.sh` ile aynı). İçine `.env` konur:

```
APP_KEY=base64:...          # `php artisan key:generate --show` çıktısı
APP_URL=https://birinci-alanadi.com
ZABUNO_DOMAINS=birinci-alanadi.com, www.birinci-alanadi.com, ikinci-alanadi.net
URL_TRUSTED_HOSTS=birinci-alanadi.com,www.birinci-alanadi.com,ikinci-alanadi.net
URL_ENFORCE_HOST=false
DB_DATABASE=zabuno
DB_USERNAME=zabuno
DB_PASSWORD=...             # uzun ve rastgele
```

**Bu dosyayı `install.sh` sizin yerinize üretir** (`docs/43`). Elle yazmak
yalnız var olan bir kuruluma dokunurken gerekir.

`ZABUNO_DOMAINS` çoğuldur ve bilinçlidir: bu bir SaaS, aynı yazılım birden
çok alan adında çalışır. `URL_ENFORCE_HOST` kapalı kalmalı — açılırsa ikinci
alan adı birincinin adreslerine yönlendirilir ve kendi kimliğini kaybeder.

`.env` sunucuda kalır ve depoya **girmez**. Compose dosyaları her deploy'da
akış tarafından gönderilir.

## Ortam matrisi — hangi dosya nerede

Taban `docker-compose.yml` **topolojiden bağımsızdır**: servisleri, ağı ve
hacimleri tanımlar, hiçbir kuruluma ait değer taşımaz. Ortam farkı iki
yerde yaşar ve ikisinin ayrımı bilinçlidir:

| Katman | Ne taşır | Nerede | Takipli mi |
| --- | --- | --- | --- |
| `docker-compose.yml` | Servis, ağ, hacim | Depo | Evet |
| `docker-compose.local.yml` | Kaynaktan derleme, hata ayıklama, vekil kapalı | Depo | Evet |
| `docker-compose.edge-proxy.yml` | Loopback port yayını, vekil kapalı, oturum güvenliği | Depo | Evet |
| `.env` | Alan adı, port, parola, anahtar | Yalnız o makine | **Hayır** |

Topolojinin depoda yaşaması bir tercih değil. Bu ayrım, zabuno.com yayına
alınırken sunucuda hazır bir Caddy bulunduğu için doğdu: yığının kendi
vekili başlatılmamalı ve uygulama portu ana makineye yayımlanmalıydı. O
fark ilk gün yalnız sunucuda duran, hiçbir yerde kayıtlı olmayan bir
dosyaya yazılmıştı. Dosya kaybolsaydı `up -d` yığının Caddy'sini başlatıp
80 ve 443'ü isteyecek, o portları tutan sistem vekilini devirecekti —
yalnız bu uygulamayı değil, aynı sunucudaki her siteyi.

Sırrı gizlemek doğrudur; topolojiyi gizlemek kurulumu unutmaktır.

### Hangi topolojinin geçerli olduğunu sunucu söyler

Override elle `-f` ile eklenmez. O makinenin `.env`'i seçer:

```
COMPOSE_FILE=docker-compose.yml:docker-compose.edge-proxy.yml
ZABUNO_APP_PORT=54213
ZABUNO_HEALTH_HOST=alanadiniz.com
```

Böylece `docker compose up -d` yeter ve override'ı unutmak imkânsızlaşır.
Deploy akışı da topolojiyi bilmez: `COMPOSE_FILE`'ı sunucunun `.env`'inden
okur. Akışa gömülseydi, ikinci bir kurulum akışı çatallamak zorunda
kalırdı.

Vekil tarafında karşılığı `reverse_proxy 127.0.0.1:54213` ve isteğin
gerçek şemasını taşıyan `X-Forwarded-Proto` / `X-Forwarded-Host`
başlıklarıdır. Onlar olmadan uygulama ürettiği adreslerde `http` yazar.

### Sağlık probu kendini gerçek site olarak tanıtır

Dockerfile'daki `HEALTHCHECK` uygulamaya `Host: 127.0.0.1` ile gelir.
`URL_TRUSTED_HOSTS` beyan edilen her kurulumda uygulama bunu **haklı
olarak** 400'ler ve konteyner kalıcı `unhealthy` görünür. Çözüm güvenilir
host listesini gevşetmek değil — o liste imzalı adreslerin başka bir alan
adına kaymasını önleyen sınırdır — probun `Host` başlığını taşımasıdır;
override'lar bunu yapar.

Aynı tuzak `install.sh`'ın son doğrulama adımında da vardı: kurulum,
uygulama ayaktayken bile "cevap vermedi" diyip başarısız olurdu.

## GitHub'a eklenecek secret'lar

`Settings → Secrets and variables → Actions`:

| Ad | Değer |
| --- | --- |
| `DEPLOY_HOST` | VPS'in IP veya alan adı |
| `DEPLOY_USER` | Deploy kullanıcısı |
| `DEPLOY_SSH_KEY` | O kullanıcının özel anahtarı (parolasız) |
| `DEPLOY_KNOWN_HOSTS` | `ssh-keyscan -H <host>` çıktısı (port taşınmışsa `-p <port>` ile; satırlar `[host]:port` biçiminde olur) |
| `DEPLOY_HEALTH_URL` | `https://alanadin.com/up` |
| `DEPLOY_DIR` | İsteğe bağlı; kurulum `/opt/zabuno` dışındaysa |
| `DEPLOY_PORT` | İsteğe bağlı; sshd 22 dışında bir portta dinliyorsa |

Bunları **sahibi ekler.** Anahtar ve parolalar bende görünmez ve benim
tarafımdan girilmez.

## Deploy nasıl yapılır

`main`'e birleşme, CI geçtiyse deploy'u kendiliğinden başlatır. Elle
tetikleme de durur: GitHub → `Actions` → `Deploy` → `Run workflow` → kutuya
`DEPLOY`.

Akış sırayla: imajı `linux/amd64` için derler, **bir kayıt defterine
uğramadan** SSH üzerinden sunucuya aktarır (`gzip | ssh | docker load`),
`up -d` çalıştırır, sonra siteye HTTP isteği atarak gerçekten cevap
verdiğini doğrular.

### İmaj neden GHCR'dan geçmiyor

İlk hâl imajı GHCR'a itiyordu ve ilk gerçek deploy orada durdu: sunucu
`unauthorized` aldı. Private bir paketi çekebilmek için sunucuda uzun ömürlü
bir registry kimlik bilgisi durması gerekiyordu — sabitlenmiş SSH kanalının
yanında, aynı işi yapan ikinci bir güven ilişkisi. Buna ek olarak private
paketin depolaması ve sunucunun her çekimi hesabın paket kotasına yazılıyor;
her commit ayrı bir SHA etiketi ürettiği için bu birikiyor.

Deploy'un zaten bir kanalı var. İmaj oradan akıyor: runner'da `gzip -1` ile
sıkıştırılıp sunucuda doğrudan `docker load`'a veriliyor, diske yazılmadan.
Saklanacak token, dolacak süre ve ödenecek kota kalmıyor.

## Geri alma

Her imaj commit SHA'sı ile etiketlenir ve etiketler **sunucuda kalır**
(`docker image prune -f` yalnız sahipsiz katmanları siler). Registry artık
geri alma kaynağı olmadığı için bu yerel etiketler tek geriye dönüş yoludur;
toptan silen bir temizlik yazılmamalı. Geri almak, sunucuda `.image.env`
içindeki `ZABUNO_IMAGE` değerini önceki SHA ile değiştirip
`docker compose up -d` çalıştırmaktır. Migrasyonlar geri alınmaz; şema
değişikliği içeren bir sürümden dönüş, ayrı bir karardır.

## Yerelde doğrulandı

İmaj derlendi ve yığın çalıştırıldı: migrasyonlar PostgreSQL 17'de koştu,
`/up` 200 verdi, açılış sayfası 9.276 bayt gerçek içerik döndürdü, CSP ve
`X-Frame-Options` başlıkları yerindeydi, opcache üretim ayarlarıyla açıktı.

İki kusur bu doğrulama sırasında bulundu ve düzeltildi: `composer` imajında
`gd` yoktu (yeni bildirilen `ext-*` sözleşmesi derlemeyi durdurdu — doğru
davranış), ve geliştirme makinesinin `bootstrap/cache` dizini imaja
kopyalanıp `--no-dev` ile var olmayan bir sağlayıcıyı yüklemeye çalıştı.
İkisi de `DeploymentContractTest` ile kilitlendi.

## Henüz yapılmadı

- **Gerçek sunucuda koşum.** Yığın yerelde çalıştı; netcup'ta çalıştığı
  kanıtlanmadı. Exit Gate bu kanıtı bekliyor (`docs/18`).
- **Yedekleme otomasyonu.** `db-backups` hacmi ayrıldı, içine yazan bir iş
  yok.
- **Kuyruk işçisi.** `QUEUE_CONNECTION=database` ayarlı ama kuyruk tüketen
  bir süreç yığında yok; kuyruğa iş bırakan bir özellik eklendiği gün
  gerekir.
