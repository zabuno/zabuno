/*
    SHELL-SCROLL-GATE'İN BAŞLATMA SÖZLEŞMESİ.

    ── BU DOSYA 2026-09-11'DE KÜÇÜLDÜ, KAPSAMI KÜÇÜLMEDİ ──

    Burada bir zamanlar dört madde vardı ve dördü de kapının KENDİ iç
    fonksiyonlarını (`connect`, `startupDiagnostics`, `stopChrome`) kaynak
    metinden kesip `vm` ile çalıştırarak ölçüyordu. O fonksiyonlar artık
    burada yaşamıyor: başlatma `browser-session.mjs`e taşındı (`docs/154`),
    çünkü aynı otuz satır iki kapıda ayrı ayrı duruyor ve ayrışıyordu.

    Ölçüm kaybolmadı, YERİ DEĞİŞTİ ve genişledi: portun geç açılması, ölen
    sürecin beklenmemesi, stderr'in sınırlanması, her denemenin kendi profil
    dizini ve deneme sayısının sınırı artık `scripts/browser-session.test.sh`
    içinde — sahte tarayıcı ikilileriyle, gerçek Chrome'a hiç ihtiyaç
    duymadan ölçülüyor.

    Geriye BU kapıya özgü olan iki şey kaldı ve ikisi de burada:

      1. Kapı, başlatma yolunu KENDİ kopyalamıyor olmalı. Üçüncü bir kopya,
         düzeltilen kusurun ta kendisidir.
      2. Tarayıcı açılmadığında kapı doğru kodla çıkmalı VE ardında kendi
         geçici dizinini bırakmamalı.
*/

import assert from 'node:assert/strict';
import { readFileSync, mkdtempSync, writeFileSync, rmSync, readdirSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';
import { test } from 'node:test';

const gateSource = readFileSync(new URL('./shell-scroll-gate', import.meta.url), 'utf8');
const launcherSource = readFileSync(new URL('./browser-session.mjs', import.meta.url), 'utf8');

test('the gate does not carry its own browser launcher', () => {
    /*
        ÜÇÜNCÜ KOPYA YASAK.

        `shell-scroll-gate` ve `mobile-ux-audit` Chrome'u kendi kopyaladıkları
        kodla başlatıyordu ve o iki kopya ayrışmıştı: biri sabit port ve 15
        saniye, öteki seçilen port ve 30 saniye kullanıyordu. Birinde
        düzeltilen bir şey ötekinde düzelmiyordu (`docs/154`).

        Bu madde kapının geri kaymasını engeller: başlatma ortak katmandan
        gelir, burada `spawn` edilmez.
    */
    assert.match(
        gateSource,
        /import \{[^}]*launchBrowser[^}]*\} from '\.\/browser-session\.mjs'/,
        'Kapı ortak başlatıcıyı kullanmıyor.',
    );
    assert.doesNotMatch(
        gateSource,
        /\bspawn\(/,
        'Kapı Chrome’u yine kendisi başlatıyor — üçüncü kopya doğuyor.',
    );
    assert.doesNotMatch(
        gateSource,
        /CHROME_CANDIDATES/,
        'Kapı kendi tarayıcı aday listesini taşıyor; liste tek yerde kalmalı.',
    );
});

test('nothing in the launch path names a fixed debugging port', () => {
    /*
        SABİT PORT SESSİZCE YANLIŞ ÇALIŞIR.

        Sabit bir hata ayıklama portu (eskiden 9333), aynı makinede koşan
        BAŞKA bir Chrome'a bağlanmayı mümkün kılar: kapı o zaman kendi
        açtığı sayfayı değil, bambaşka bir pencereyi ölçer ve yeşil yanar.
        Yerelde iki çalışma ağacı bunu sık sık aynı anda yapar.

        Garanti yapısaldır — portu Chrome seçer (`--remote-debugging-port=0`)
        ve numarayı YALNIZ kendi profil dizinindeki `DevToolsActivePort`
        dosyasından okuruz — ama yapısal bir garanti, testsiz kaldığı gün
        bir sonraki düzenlemede kaybolur.
    */
    assert.match(launcherSource, /--remote-debugging-port=0/);
    assert.match(launcherSource, /DevToolsActivePort/);

    for (const [name, source] of [
        ['browser-session.mjs', launcherSource],
        ['shell-scroll-gate', gateSource],
    ]) {
        assert.doesNotMatch(
            source,
            /--remote-debugging-port=(?!0\b)\d+/,
            `${name} sabit bir hata ayıklama portu adlandırıyor.`,
        );
    }
});

test('startup failure exits with the infrastructure code and leaves no temporary directory', () => {
    const dir = mkdtempSync(join(tmpdir(), 'shell-startup-cli-test-'));
    writeFileSync(join(dir, 'unrelated'), 'keep');

    try {
        /*
            `/usr/bin/false` VARDIR ama hemen çıkar: "ikili yok" ile
            "ikili açılmıyor" ayrı arızalardır ve bu madde ikincisini kurar.
        */
        const run = spawnSync(
            process.execPath,
            [fileURLToPath(new URL('./shell-scroll-gate', import.meta.url)), 'file:///unused.css'],
            {
                env: { ...process.env, CHROME_PATH: '/usr/bin/false', TMPDIR: dir },
                encoding: 'utf8',
                timeout: 20000,
            },
        );

        assert.equal(run.error, undefined);

        /*
            ALTYAPI ARIZASININ KENDİ KODU VAR — VE BU DEĞİŞİKLİK BİLİNÇLİ.

            Bu madde eskiden 1 bekliyordu: tarayıcı açılamadığında kapı, bir
            ÖLÇÜM İHLALİYLE aynı kodla çıkıyordu. Bakan kişinin ilk sorusu
            "benim değişikliğim mi bozdu, makine mi?" olur ve tek kod o
            soruyu cevapsız bırakıyordu (`docs/154`).

              3 = tarayıcı açılamadı, ölçüm HİÇ YAPILMADI.
              1 = ölçüm yapıldı ve bir kural çiğnendi.
        */
        assert.equal(run.status, 3, `beklenen 3, gelen ${run.status}. stderr: ${run.stderr}`);

        /*
            TEŞHİS GERÇEKTEN TEŞHİS OLMALI. Eski çıktı tek satırdı; yenisi
            hangi ikiliyi denediğini, kaç kez denediğini ve sürecin ne
            söylediğini yazar. Üçü de burada kilitli.
        */
        assert.match(run.stderr, /ALTYAPI ARIZASI — TARAYICI BAŞLATILAMADI/);
        assert.match(run.stderr, /ikili\s+:\s+\/usr\/bin\/false/);
        assert.match(run.stderr, /deneme 3/);
        assert.match(run.stderr, /süreç çıkış kodu/);

        /*
            KENDİ ÇÖPÜNÜ TOPLAR. `exitBrowserUnavailable` süreci bitirir ve
            kapının `finally` bloğu hiç çalışmaz; temizlik çıkıştan ÖNCE
            yapılmalı. Bu madde onu ölçer: dizinde yalnız testin koyduğu
            dosya kalmalı.
        */
        assert.deepEqual(readdirSync(dir), ['unrelated']);
    } finally {
        rmSync(dir, { recursive: true, force: true });
    }
});
