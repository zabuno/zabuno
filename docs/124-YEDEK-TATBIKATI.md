# 124 — Yedek tatbikatı: koşucular, zamanlama, kanıt ve üretimde ilk gerçek tatbikat

> **Bu belge bir runbook'tur.** Ne yapıldığını ölçülmüş hâliyle, ne
> yapılmadığını açıkça söyler. "Yedek alınıyor" gibi bir cümle burada
> ancak kanıt tablosunda bir satır varsa yazılır — bugün üretimde o satır
> **yoktur**. Yol haritası satırı `docs/107` Faz 1.5; gap kaydı `docs/16`
> DR-01/DR-02; dağıtım yığını `docs/42`.

**Sahibin cümlesiyle:** *"Denenmemiş bir yedek, yedek değildir."*
Restoranın menüsünü kaybettik; geri getirebiliyor muyuz? Bu belge o
soruya bugün verilebilen dürüst cevabı ve cevabı "evet"e çevirmek için
kalan adımları taşır.

## 0. Ölçülmüş durum (2026-09-06)

| Ne | Durum | Nerede ölçüldü |
| --- | --- | --- |
| SQLite veritabanı tatbikatı (geliştirici makinesi) | Koşuyor, `passed` üretiyor | `tests/Unit/Infrastructure/Security/Execution/SqliteBackupRestoreDrillRunnerTest.php` |
| PostgreSQL veritabanı tatbikatı (üretim motoru) | Kod var; sunucusuz sözleşme yerelde ölçüldü; gerçek `pg_dump`/`pg_restore` turu **yalnız CI'da** ölçülür | `PostgresBackupRestoreDrillRunnerTest` (yerel), `tests/Feature/Security/PostgresBackupRestoreDrillTest.php` (CI, `DB_CONNECTION=pgsql`) |
| Medya tatbikatı (`storage/app` medya kökü) | Koşuyor; sahte dosyalarla eşleşme ve bozuk dosyada başarısızlık ölçüldü | `TarMediaBackupRestoreDrillRunnerTest` |
| Koşucu seçimi (bağlantıya göre `sqlite`/`pgsql`) | Ölçüldü | `BackupRestoreDrillRunnerFactoryTest` |
| Günlük zamanlama tanımı | Tanımlı ve testle kilitli; **çalıştığı iddia edilmiyor** | `BackupRestoreDrillIsScheduledTest` |
| Kanıt ucu (`GET /api/workspaces/{w}/security/evidence/backup-restore`) | Son kaydı, sürücüyü ve medya kaydını dönüyor | `BackupRestoreEvidenceApiTest` |
| **Üretim sunucusunda tatbikat** | **Yapılmadı.** Üretimde hiçbir kanıt satırı yok | — |
| `db-backups` hacmine düzenli yazan yedek işi | **Kod ve zamanlama var** (`zabuno:backup:database`, 03:30; `app` servisi hacmi `/backups` olarak bağlıyor). Üretimde koşumu **kanıtlanmadı**: sunucuda bugün hiçbir arşiv dosyası ölçülmedi | `DatabaseBackupCommandTest`, `DEPLOY-BACKUP-LANDS-16` |

Yerelde PostgreSQL ve `pg_dump` yoktur; PostgreSQL turu için bu makinede
sonuç **bilinmiyor**dur ("geçti" değil). CI işi (`.github/workflows/ci.yml`,
"Laravel test suite (PostgreSQL, deployment target)") istemciyi sunucuyla
aynı majöre (17) çeker ve `PostgresBackupRestoreDrillTest` orada `CI=true`
iken eksik aracı skip değil **hata** sayar.

## 1. Tatbikat ne yapar

Üç sonuç vardır ve üçü de kayda geçer:

- **`passed`** — yedek alındı, izole yere geri yüklendi, karşılaştırma tuttu.
- **`failed`** — tatbikat denendi ve bir adım kırıldı ya da karşılaştırma
  tutmadı.
- **`unknown`** — tatbikat **hiç denenemedi**: `pg_dump`/`pg_restore`/`tar`
  yok, istemci sunucudan eski, kaynak okunamıyor. Bu "başarısız yedek"
  değildir; yedek hakkında hiçbir şey söylenememiştir. Sıfır çıkış koduyla
  "bilinmiyor" çelişkidir ve alan kaydı onu reddeder.

### 1.1 Veritabanı — PostgreSQL koşucusu (`PostgresBackupRestoreDrillRunner`)

1. Dondurulmuş manifest (`users`, `workspaces`, `workspace_memberships`,
   `menus`) için REPEATABLE READ bir işlem açar, `pg_export_snapshot()` ile
   anlık görüntüyü dışa aktarır; satır sayısını ve satır içeriği özetini o
   görüntüden okur.
2. `pg_dump --format=custom --snapshot=<aynı görüntü> --table=public.<…>`
   ile aynı görüntüyü arşivler. Sayım ile döküm aynı anı görür; canlı bir
   sunucuda araya giren bir INSERT tatbikatı yanlış yere "başarısız"
   göstermez.
3. Aynı sunucuda `zabuno_drill_<16 hex>` adlı **geçici** bir veritabanı
   açar, `pg_restore --single-transaction --section=pre-data --section=data`
   ile arşivi oraya yükler.
4. Geçici veritabanında tablo listesini, satır sayısını ve içerik özetini
   kaynakla karşılaştırır; sonra geçici veritabanını düşürür ve arşivi siler.
5. Kayda yazılan: `driver=pgsql`, satır sayıları, iki içerik özeti
   (`backup_sha256` = yedeklenen içerik, `restored_db_sha256` = geri
   yüklenen içerik), arşiv boyutu (`backup_bytes`), `backup_ms`, `restore_ms`.

**Neden yalnız pre-data + data.** Manifest bir alt kümedir: `menus.location_id`
manifest dışındaki `locations` tablosuna başvurur. Post-data bölümü (yabancı
anahtarlar, indeksler) boş bir geçici veritabanında o tabloyu bulamaz ve
geri yükleme kırılırdı. Tatbikatın ölçtüğü şey **satırların geri gelmesidir**;
kısıtların geri gelmesi tam veritabanı dökümünün (§5) işidir. İddia metni
bunu açıkça söyler.

**Kaynağa yazılmaz.** Kaynak veritabanına tek bir INSERT/UPDATE/DELETE yoktur;
geçici veritabanı yalnız koşucunun ürettiği adla ve yalnız
`zabuno_drill_[0-9a-f]{16}` desenine uyuyorsa düşürülür.

**Gereken yetki:** `CREATE DATABASE` (docker yığınında `DB_USERNAME`
`POSTGRES_USER`'dır ve süper kullanıcıdır; başka bir kurulumda `CREATEDB`
verilmelidir).

### 1.2 Veritabanı — SQLite koşucusu (`SqliteBackupRestoreDrillRunner`)

Davranış değişmedi (çevrimiçi yedek + izole dosya kopyası + `integrity_check`);
kayda artık `driver=sqlite`, `backup_bytes`, `backup_ms`, `restore_ms` de
yazılır ve okunamayan kaynak `failed` değil `unknown` olur.

### 1.3 Medya (`TarMediaBackupRestoreDrillRunner`)

Medya kökü, `config/filesystems.php`'deki `local` diskinin köküdür
(`storage/app/private`; medya modülü `quarantine/{workspace}/…` ve
`renditions/{workspace}/{asset}/…` altına yazar). Koşucu:

1. Kökü `tar -cf` ile arşivler, arşivi `storage/app/backup-restore-drill`
   altındaki izole bir dizine `tar -xf` ile açar.
2. Kaynak ve kopya için dosya sayısı, toplam bayt ve dosya başına SHA-256'dan
   türeyen manifest özetini karşılaştırır; tek bir bozuk bayt tatbikatı
   düşürür (testte ölçüldü).
3. Arşivi ve kopyayı siler. Kayda: `archive_sha256`, `archive_bytes`,
   dosya sayıları, bayt toplamları, iki manifest özeti, süre.

Boş medya kökü `passed` (0 dosya) olur — kaybedilecek bir şey yoktur; **yok**
medya kökü `unknown` olur. Çalışma kökü medya kökünün içindeyse tatbikat
kendini yutmamak için `unknown` der. Tatbikat sırasında disk, medya kökünün
boyutu kadar ek yer ister (arşiv + kopya); §6'da bekleyen iştir.

## 2. Komut

```text
php artisan security:evidence:backup-restore            # veritabanı + medya
php artisan security:evidence:backup-restore --database # yalnız veritabanı
php artisan security:evidence:backup-restore --media    # yalnız medya
php artisan security:evidence:backup-restore --json     # eklenen kayıtları JSON basar
```

- Koşucu bağlantıya göre seçilir (`config('database.default')`: `sqlite`
  ya da `pgsql`; başka bir bağlantı açık bir hatayla reddedilir).
- Her tatbikat kendi tablosuna **bir** satır ekler: `backup_restore_evidence`
  ve `media_backup_restore_evidence`. Kayıtlar yalnız-eklemelidir; güncelleme
  ve silme yolu yoktur.
- Çıkış kodu yalnız seçilen her tatbikat `passed` ise 0'dır. `unknown` ve
  `failed` sıfır olmayan kodla döner; zamanlayıcı ve operatör bunu görür.
- Tanımsız bir seçenek (`--status`, `--table`, `--path`, …) komutu düşürür;
  sonuç hiçbir girdiden etkilenmez.

`--json` çıktısı `{ "passed": bool, "database": {...}|null, "media": {...}|null }`
biçimindedir; kayıt alanlarını olduğu gibi taşır, ham çıktı ve yol içermez.

### 2.1 Duran yedek — `zabuno:backup:database` (BACKUP-PRODUCE-01)

**Tatbikat edilen yedek ile duran yedek aynı şey değildir.** Yukarıdaki
komut "geri gelebiliyor mu" sorusunu sorar ve kendi dökümünü işi bitince
*siler* — doğrusu da budur. Bu komut öbür soruyu cevaplar: "bugün sunucu
gitse, geri dönülecek bir dosya **var mı**?" Ölçülen boşluk şuydu:
`db-backups` adlı kalıcı hacim 2026'nın başından beri tanımlıydı, yalnız
`db` servisine bağlıydı ve içi boştu.

```text
php artisan zabuno:backup:database         # arşivi üretir, insan okunur rapor basar
php artisan zabuno:backup:database --json   # aynı koşu, makine okunur rapor
```

Sıra bilinçlidir; **her ön kontrol döküm başlamadan biter**:

1. Bağlantı sürücüsü PostgreSQL değilse koşu "uygulanamadı" der, "yapıldı"
   demez — başka bir motorda üretilen dosya üretime geri yüklenemez.
2. Hedef klasör yazılabilir bir dizin değilse durur. Hedef doğrulanmadan
   `pg_dump` çalıştırılsaydı üretim veritabanı gigabaytlarca okunur ve
   sonuç hiçbir yere yazılamazdı.
3. Boş disk emniyet payının altındaysa durur. Yarım yazılan bir dosya iki
   kez zarar verir: yedek alınmamış olur ve aynı diskteki veritabanı da
   yazamaz hâle gelir — yani yedekleme işinin kendisi siteyi düşürür.
4. `pg_dump`/`pg_restore` yoksa ya da büyük sürümü sunucununkinden eskiyse
   durur (`config/backup.php#minimum_client_major`, bugün 17; kaynağı
   compose'daki `postgres:17-alpine` ve DEPLOY-PG-CLIENT-PARITY-14). Eski
   bir istemcinin ürettiği arşiv geri yüklenemez; üretilip duruyor olması
   onu yedek yapmaz, yalnız yedeği olduğu **yanılsamasını** üretir.

Sonra: veritabanının **tamamı** `pg_dump --format=custom` ile geçici bir
dosyaya dökülür, arşiv `pg_restore --list` ile **okunarak** doğrulanır,
SHA-256 özeti hesaplanıp arşivin yanındaki `.sha256` kardeş dosyasına
yazılır ve dosya tek bir `rename()` ile final adına (`*.dump`) taşınır.
Yarım kalan bir döküm final adını **almaz**: geri yükleme günü kimse
dosyanın içine bakmaz, en yeni `.dump` dosyasına bakar.

**Üretim verisine tek bir yazma yoktur.** Doğrulama `pg_restore --list`
iledir — arşivin içindekiler listelenir, hiçbir şey geri *yüklenmez*.
Komutun canlı veritabanına `pg_restore` çalıştıran bir yolu yoktur.

**Hiçbir dosya silinmez.** Retention kuralı yazılmadan önce silme
*yeteneği* de olmamalı; yeni arşiv eskisinin yanına gelir. Temizlenen tek
şey koşunun kendi geçici dosyalarıdır.

**Parola argümanda ve günlükte geçmez.** İstemciye `--no-password` verilir,
değer yalnız alt sürecin ortamına (`PGPASSWORD`) konur ve hata metinleri
maskelenir — bir yedekleme arızası, veritabanı parolasını günlüğe düşüren
yer olmamalıdır.

`--json` çıktısı `{ "ok": bool, "reason": string, "message": string,
"path": string|null, "bytes": int|null, "sha256": string|null }`
biçimindedir. Özet iki yerde yaşar: koşunun raporunda (izlemeye akan yer)
ve arşivin yanındaki kardeş dosyada (arşivle birlikte taşınan yer). İkisi
ayrışırsa kanıtın kendisi şüphelidir.

Sözleşme `tests/Feature/Backup/DatabaseBackupCommandTest.php` ile
kilitlidir (BACKUP-DB-DESTINATION-01 … BACKUP-DB-RESTORABLE-07). Canlı
PostgreSQL isteyen iki madde, PostgreSQL yokken "geçti" demez: atlanır ve
sebebini söyler; ölçüm CI'ın `DB_CONNECTION=pgsql` işinde yapılır.

## 3. Zamanlama

`routes/console.php`: her gün 03:40'ta (çöp boşaltımından sonra),
`withoutOverlapping` ile. Bu tanım testle kilitlidir.

Duran yedek (`zabuno:backup:database`, §2.1) aynı dosyada **03:30**'dadır:
çöp boşaltımından (03:20) *sonra* — silinen dosya yedeğe girmesin —, bu
tatbikattan (03:40) *önce* — tatbikat o gecenin yedeği alınmış hâli
ölçsün. Sıralama ve `withoutOverlapping` DEPLOY-BACKUP-LANDS-16 ile
kilitlidir; aynı kapı, dökümün ineceği kalıcı hacmin `app` servisine
bağlı olduğunu da ölçer.

**Bu tanım "çalışıyor" demez** — ikisi için de. Bir zamanlayıcı girdisi,
o işin üretimde koştuğunun kanıtı değildir; çalışıp çalışmadığı yalnız
kanıt kaydından (§4) ve `/backups` altında duran gerçek dosyadan okunur.

Uygulama imajı `postgresql-client-17` taşır (`docker/Dockerfile`, PGDG
deposundan; DEPLOY-PG-CLIENT-PARITY-14 bunu sunucunun majörüyle birlikte
kilitler), yani araç eksikliğinden doğan `unknown` beklentisi artık
geçerli değil. Ama **canlı koşum hâlâ yapılmadı**: üretimde ne bir kanıt
satırı ne de bir arşiv dosyası ölçüldü. Araç var olmak, iş koşmuş olmak
değildir; §7 bunun kapanışıdır.

## 4. Kanıt nerede görünür

`GET /api/workspaces/{workspace}/security/evidence/backup-restore` — çalışma
alanı sahibi (`SecurityEvidenceView` izni), son kayıtlar:

```json
{
  "data":  { "key": "backup_restore", "status": "passed|failed|unknown", "driver": "sqlite|pgsql",
             "source_row_count": 0, "restored_row_count": 0, "backup_bytes": 0,
             "backup_ms": 0, "restore_ms": 0, "claim": "…", "integrity_sha256": "…" },
  "media": { "key": "media_backup_restore", "status": "…", "source_file_count": 0,
             "restored_file_count": 0, "source_bytes": 0, "archive_bytes": 0, "claim": "…" }
}
```

- `media` hiç kayıt yoksa `null`dır. Veritabanı kaydı yoksa uç 404 döner.
- Kurcalanmış bir satır (özet tutmuyor) 500 döner; hiçbir şey sunulmaz.
- **Eski satırlar.** 2026-09-06 migrasyonundan önce yazılmış satırların
  özeti yeni alanları kapsamaz ve doğrulanmaz; ilk yeni koşu "son kayıt"
  olur. Aradaki pencerede uç 500 döner — doğrulanamayan kaydın "geçti" diye
  sunulmamasıdır.
- Süperadmin ekranı ve panel yüzeyi bu paketin dışındadır; okuma ucu
  değişmemiş, alanları genişlemiştir.

## 5. Üretim sunucusunda ilk gerçek tatbikat — adım adım

Sunucuda, `docker-compose.yml`'in bulunduğu dizinde ve `.env` ile. Aşağıdaki
adımlar **kaynağa yazmaz**; §5.6 dışında hiçbiri geri döndürülemez bir iş
yapmaz. Komutlar `db` konteynerinin **kendi** `pg_dump`'ını kullanır; sürüm
sunucuyla aynıdır (postgres:17).

### 5.1 Ön kontrol

```bash
docker compose --env-file .env ps
docker compose --env-file .env exec db pg_isready -U "$DB_USERNAME"
docker compose --env-file .env exec db df -h /backups
```

`/backups`, `db-backups` hacmidir; konteynerle birlikte silinmez.

### 5.2 Tam veritabanı yedeği (manifest değil, TÜM veritabanı)

```bash
docker compose --env-file .env exec db sh -c \
  'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" --format=custom \
     --file=/backups/zabuno-$(date +%Y%m%d-%H%M%S).dump'
docker compose --env-file .env exec db sh -c 'ls -l /backups && sha256sum /backups/*.dump'
```

Boyutu ve SHA-256'yı not alın; kanıtın parçasıdır. Uygulama içi tatbikat
dört tabloyu ölçer; gerçek yedek **tüm** veritabanıdır ve kısıtları da
taşır.

### 5.3 İzole geri yükleme (geçici veritabanı)

```bash
docker compose --env-file .env exec db sh -c \
  'createdb -U "$POSTGRES_USER" -T template0 zabuno_drill_manual && \
   time pg_restore -U "$POSTGRES_USER" --dbname=zabuno_drill_manual \
     --no-owner --no-privileges --exit-on-error /backups/<5.2-deki dosya>'
```

`time` çıktısı **ölçülmüş** geri yükleme süresidir — `docs/16` DR-01'in
RTO hedefi (4 saat) ilk kez bir sayıyla karşılaştırılır. Sonucu §6'ya
yazın.

### 5.4 Satır sayılarını karşılaştır

```bash
docker compose --env-file .env exec db sh -c '
for t in users workspaces workspace_memberships menus locations brands media_assets; do
  src=$(psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -tAc "select count(*) from $t")
  dst=$(psql -U "$POSTGRES_USER" -d zabuno_drill_manual -tAc "select count(*) from $t")
  echo "$t: $src / $dst"
done'
```

Her satırda iki sayı eşit olmalıdır. Eşit değilse tatbikat **başarısızdır**;
sebep bulunmadan §5.6'ya geçilmez.

### 5.5 Geçici veritabanını düşür

```bash
docker compose --env-file .env exec db sh -c 'dropdb -U "$POSTGRES_USER" zabuno_drill_manual'
docker compose --env-file .env exec db sh -c \
  'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -tAc "select datname from pg_database where datname like '"'"'zabuno_drill_%'"'"'"'
```

İkinci komut boş dönmelidir. Uygulama içi tatbikatın düşüremediği bir
`zabuno_drill_<hex>` kalmışsa aynı `dropdb` ile temizlenir.

### 5.6 Medya yedeği ve izole geri açma

```bash
docker compose --env-file .env exec app sh -c \
  'tar -cf /tmp/zabuno-media-$(date +%Y%m%d).tar -C /var/www/html/storage/app/private . && ls -l /tmp/zabuno-media-*.tar'
docker compose --env-file .env exec app sh -c \
  'mkdir -p /tmp/media-restore && tar -xf /tmp/zabuno-media-*.tar -C /tmp/media-restore && \
   echo "kaynak: $(find /var/www/html/storage/app/private -type f | wc -l) dosya" && \
   echo "kopya:  $(find /tmp/media-restore -type f | wc -l) dosya"'
docker compose --env-file .env cp app:/tmp/zabuno-media-$(date +%Y%m%d).tar ./  # sunucu dışına çıkarmak için ilk adım
docker compose --env-file .env exec app sh -c 'rm -rf /tmp/media-restore /tmp/zabuno-media-*.tar'
```

Bu, uygulama içi medya tatbikatının elle karşılığıdır; `storage/app` hacmi
`app-storage`'dır ve imajla birlikte silinmez.

### 5.7 Uygulama içi tatbikat ve kanıt satırı

```bash
docker compose --env-file .env exec app php artisan security:evidence:backup-restore --json
```

- Medya kaydı gerçek bir ölçümdür.
- Veritabanı kaydı da gerçek bir ölçüm **olmalıdır**: imaj
  `postgresql-client-17` taşır, yani `pg_dump` bulunur ve eski araca
  dayanan `"status": "unknown"`/`"exit_code": 127` beklentisi geçerli
  değildir. Yine de bu komut üretimde **henüz bir kez bile koşmadı**;
  çıkan `status` ne olursa olsun buraya ölçüldüğü gibi yazılır ve
  ölçülmeden "passed" yazılmaz.
- Aynı koşuda duran yedeği de görün (§2.1): `ls -la /backups` ve
  `php artisan zabuno:backup:database --json`. Zamanlama tanımlı olsa da
  ilk canlı arşiv bu adımda ölçülür.

Kanıt: `GET /api/workspaces/{w}/security/evidence/backup-restore` (sahip
oturumuyla) ya da doğrudan tablo:

```bash
docker compose --env-file .env exec db sh -c \
  'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -c "select id, status, driver, source_row_count, restored_row_count, backup_bytes, ran_at from backup_restore_evidence order by id desc limit 3" \
   -c "select id, status, source_file_count, restored_file_count, source_bytes, ran_at from media_backup_restore_evidence order by id desc limit 3"'
```

### 5.8 Tatbikat raporu

Tek bir yere yazılır (`docs/16` DR-02 satırına tarihli bir güncelleme):
tarih, `pg_dump` dosya boyutu ve SHA-256, `pg_restore` süresi, satır
sayısı tablosu, medya dosya sayısı ve toplam bayt, `--json` çıktısındaki
iki `status`. Ölçülmeyen bir alan boş bırakılmaz; "ölçülmedi" yazılır.

## 6. Geri yükleme kararı kimde

- **Tatbikat hiçbir koşulda üretimin üstüne geri yüklemez.** Geçici
  veritabanı ve izole dizin dışına çıkmaz.
- **Üretimin üstüne geri yükleme** (bir kaybı gerçekten geri almak) geri
  döndürülemez bir iştir: karar **sahibindir**, uygulama **DevOps'undur**
  (Hüseyin). Karar verilmeden `pg_restore --dbname=$POSTGRES_DB` hiçbir
  koşulda çalıştırılmaz; önce §5.2 ile o anın yedeği alınır.
- RPO/RTO hedefleri (`docs/16` DR-01: 24 sa / 4 sa) **hedeftir**; §5.3'teki
  `time` ilk ölçümdür ve hedefi doğrulayana kadar taahhüt olarak yazılmaz.

## 7. Hüseyin (DevOps) için bekleyen işler

Bağlantı: `docs/42` "Henüz yapılmadı" (yedekleme otomasyonu), `docs/87`
(üretimde görünen kusurlar), `docs/61` P0-07 (canlı dağıtım kanıtı).

1. **§5'i bir kez koşmak** ve §5.8 raporunu `docs/16` DR-02'ye yazmak. Bu
   satır kapanmadan `docs/107` Faz 1.5 "bitti" olmaz.
2. ~~**Uygulama imajına `postgresql-client-17`**~~ — **kodda tamamlandı.**
   `docker/Dockerfile` istemciyi PGDG deposundan kurar ve
   DEPLOY-PG-CLIENT-PARITY-14 onu compose'daki sunucu majörüyle birlikte
   kilitler. Bekleyen iş artık paketin kurulması değil, **canlıda ilk
   koşum**: tatbikatın veritabanı ayağının gerçekten `unknown` yerine bir
   ölçüm yazdığı üretimde görülmedi.
3. ~~**`db-backups` hacmine düzenli yazan bir yedek işi**~~ — **kodda
   tamamlandı** (BACKUP-PRODUCE-01): `zabuno:backup:database` her gece
   03:30'da tam bir döküm üretir, `app` servisi hacmi `/backups` olarak
   bağlar, sözleşme `DatabaseBackupCommandTest` ve DEPLOY-BACKUP-LANDS-16
   ile kilitlidir. Bekleyen iş **canlıda ilk koşum ve ilk gerçek arşiv
   dosyası**: üretimde `/backups` altında bugün ölçülmüş bir dosya yoktur,
   dolayısıyla "yedeğimiz var" denemez.
4. **Sunucu dışı kopya.** `/backups` ve medya arşivi sunucuyla birlikte
   kaybolur; sunucu dışına kopya olmadan RPO sonsuzdur (`docs/98` düzeltmesi).
5. **Disk payı.** Günlük medya tatbikatı, medya kökü kadar ek yer ister
   (arşiv + kopya). `df -h` ile pay ölçülür; yetmiyorsa tatbikat `failed`
   yazar, sessizce geçmez.
6. **Saklama süresi.** `/backups` altındaki dökümlerin kaç gün tutulacağı
   sahip kararıdır (dış maliyet: disk); karar verilene kadar dosyalar
   silinmez.

## 8. Sınırlar ve bilinmeyenler

- Tatbikat aynı sunucudadır; sunucunun kendisi kaybolduğunda geri
  dönülebileceğini **göstermez** (madde 7.4).
- Veritabanı manifesti dört tablodur; medya-satır ilişkisi (`media_assets`),
  QR hedefleri, abonelikler manifestte değildir. Tam yedek §5.2'dir.
- Nokta-zaman kurtarma (WAL arşivi) yoktur; RPO en iyi hâlde son döküm
  anıdır.
- **Duran yedeğin kodu ve zamanlaması hazır** (§2.1) ama **üretim
  artefaktı henüz kanıtlanmadı**: sunucuda `/backups` altında ölçülmüş tek
  bir arşiv yoktur. Bir komutun var olması, bir dosyanın durması değildir.
- Üretilecek arşiv de *aynı sunucuda* duracaktır: offsite kopya, retention
  (eski yedeğin silinmesi), nokta-zaman kurtarma ve medya yedeğinin
  arşivlenmesi bu paketin dışındadır ve hiçbiri yazılmadı. Disk dolarsa
  koşu durur ve söyler; kimse eski dosyayı silmez.
- PostgreSQL turu için bu makinede sonuç bilinmiyor; CI'daki ilk koşu bu
  paketin PR'ında ölçülür. Kırılırsa kayıt burada güncellenir, "geçti"
  yazılmaz.
