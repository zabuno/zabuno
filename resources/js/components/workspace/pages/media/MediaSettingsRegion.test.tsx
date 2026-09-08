import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';

import { MediaSettingsRegion } from './MediaSettingsRegion';

/**
 * MEDYA AYARLARI — kanonik kaynak `docs/reference/media-manager/
 * Medya Yonetimi v2.dc.html` (ekran etiketi "Ayarlar"), somut listeler
 * `docs/108` §6.5 ve §6.6.
 *
 * SAHİBİN KARARI (2026-09-05), bu dosyanın koruduğu şey:
 *
 *   - Virüs taraması GÖSTERİLİR ama KAPATILAMAZ. Kapatılabilir bir güvenlik
 *     anahtarı, kapatıldığı gün bir güvenlik açığıdır.
 *   - Uygulanmayan bir anahtarı çalışıyormuş gibi göstermek YASAK. Bağlı
 *     olmayan bir anahtar ya çizilmez ya "henüz yok" der.
 *
 * SAHİBİN İKİNCİ KARARI (2026-09-08): "switch butonlar saçma, UI hatası."
 *
 * Kapatılamayan önlemler ANAHTAR biçiminde çiziliyordu ve altlarında
 * "Cannot be switched off" yazıyordu. Ekran aynı anda iki şey söylüyordu:
 * anahtarın kendisi "değiştirebilirsin", cümle "değiştiremezsin" diyordu.
 * Kullanıcı dokunuyor, hiçbir şey olmuyor — ve dokunmanın işe yaramadığını
 * ancak DENEYEREK öğreniyordu.
 *
 * Çözüm anahtarı ÇALIŞTIRMAK DEĞİL: bu dört şey kapatılamaz ve kapatılabilir
 * olmamalı. Çözüm onları doğru anlatmak — ayar değil OLGU olduklarını. Aynı
 * sayfanın üst yarısı bunu zaten doğru yapıyor ("Nothing here is a choice, so
 * there is nothing to save"); desen oradan alındı.
 *
 * Bir ayar ekranındaki her kontrol bir SÖZDÜR: kullanıcı onu çevirdiğinde
 * bir şeyin değişeceğini söyler. Bu depoda desenler değiştirilemez ve
 * güvenlik önlemleri kapatılamaz; o yüzden burada KAYDETME KUTUSU yoktur.
 */
const BODY = {
    patterns: [
        { key: 'directory', value: 'workspaceFolder', changeable: false },
        { key: 'fileName', value: 'opaqueKey', changeable: false },
        { key: 'date', value: 'deviceLocale', changeable: false },
    ],
    security: [
        { key: 'virusScan', state: 'on', switchable: false },
        { key: 'contentSignature', state: 'on', switchable: false },
        { key: 'metadataStrip', state: 'partial', switchable: false },
        { key: 'signedLink', state: 'on', switchable: false },
        { key: 'watermark', state: 'missing', switchable: false },
    ],
};

function mount(body: unknown = BODY, ok = true) {
    vi.stubGlobal(
        'fetch',
        vi.fn(async () => ({ ok, status: ok ? 200 : 500, json: async () => body })),
    );

    return render(<MediaSettingsRegion workspaceId={7} />);
}

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('MediaSettingsRegion — her kontrol bir sözdür', () => {
    it('desen alanları okunur ama seçilemez; kaydetme kutusu YOKTUR', async () => {
        mount();

        expect(await screen.findByText('Folder structure')).toBeInTheDocument();
        expect(screen.getByText('One folder per workspace')).toBeInTheDocument();
        expect(screen.getByText('File name')).toBeInTheDocument();
        expect(screen.getByText('Date format')).toBeInTheDocument();

        // Seçenek çipi, açılır kutu ve kaydetme düğmesi olmamalı: hiçbiri
        // bu depoda bir şeyi değiştirmiyor.
        expect(screen.queryByRole('button', { name: /save/i })).toBeNull();
        expect(screen.queryByRole('combobox')).toBeNull();
        expect(screen.queryByRole('radio')).toBeNull();
    });

    it('değiştirilemeyen desenin NEDEN değiştirilemediği yazılır', async () => {
        mount();

        // "Yapamazsın" tek başına bir cevap değildir; sebebi de söylenir.
        expect(await screen.findByText(/storage address is never rewritten/i)).toBeInTheDocument();
    });

    it('güvenlik bölümünde HİÇBİR anahtar ya da onay kutusu YOKTUR', async () => {
        mount();

        await screen.findByText('Watermark');

        /*
            Kusurun kendisi buydu: dört anahtar çizilip dördü de devre dışı
            bırakılmıştı. Devre dışı bir anahtar hâlâ bir anahtardır —
            "değiştirilebilir" diye çizilmiş, "değiştirilemez" diye
            davranan bir şey.
        */
        expect(screen.queryAllByRole('switch')).toHaveLength(0);
        expect(screen.queryAllByRole('checkbox')).toHaveLength(0);
    });

    it('güvenlik bölümünde tıklanacak ya da odaklanacak hiçbir şey yoktur', async () => {
        mount();

        const heading = await screen.findByText('Security and privacy');
        const block = heading.parentElement as HTMLElement;

        // Odak alan bir öğe, klavye kullanıcısına "burada bir iş var" der.
        expect(
            block.querySelectorAll('button, input, select, textarea, a[href], [tabindex]'),
        ).toHaveLength(0);
    });

    it('virüs taraması AÇIK okunur ve kapatılamayacağı yazılır', async () => {
        mount();

        const label = await screen.findByText('Virus scan');
        const row = label.closest('li') as HTMLElement;

        expect(within(row).getByText('On')).toBeInTheDocument();
        expect(within(row).getByText('Cannot be switched off')).toBeInTheDocument();
        expect(
            within(row).getByText(/Every file is scanned before it enters the library/i),
        ).toBeInTheDocument();
    });

    it('tarayıcı bu ortamda yoksa "kapalı" değil "çalışmıyor" denir', async () => {
        mount({
            ...BODY,
            security: [
                { key: 'virusScan', state: 'unavailable', switchable: false },
                ...BODY.security.slice(1),
            ],
        });

        const label = await screen.findByText('Virus scan');
        const row = label.closest('li') as HTMLElement;

        /*
            "Kapalı" bir KULLANICI KARARIDIR; burada olan bir ORTAM
            gerçeğidir. İkisini aynı kelimeyle söylemek, sahibin kapattığını
            sanmasına yol açardı.
        */
        expect(within(row).getByText('Not running here')).toBeInTheDocument();
        expect(
            screen.getByText(/No scanner is connected in this environment/i),
        ).toBeInTheDocument();

        /*
            Çalışmayan bir önlemin altına "kapatılamaz" yazmak, açıklamanın
            söylediğiyle çelişir: zaten kapalı ve açılamıyor. O satır yalnız
            GERÇEKTEN yürüyen önlemin altında durur.
        */
        expect(within(row).queryByText('Cannot be switched off')).toBeNull();
    });

    it('yarım uygulanan önlem "tamamen açık" gibi gösterilmez', async () => {
        mount();

        const label = await screen.findByText('Strip embedded data');
        const row = label.closest('li') as HTMLElement;

        expect(within(row).getByText('Partly on')).toBeInTheDocument();
        expect(
            screen.getByText(/original file is kept exactly as you uploaded it/i),
        ).toBeInTheDocument();
    });

    it('filigran için DURUM da yazılmaz; "henüz yok" tek cümledir', async () => {
        mount();

        const label = await screen.findByText('Watermark');
        const row = label.closest('li') as HTMLElement;

        expect(within(row).getByText('Not built yet.')).toBeInTheDocument();
        // Olmayan bir şeyin "durumu" olmaz; ikinci bir hâl kelimesi
        // aynı yokluğu iki kez söylerdi.
        expect(within(row).queryByText('Cannot be switched off')).toBeNull();
        expect(within(row).queryByText('On')).toBeNull();
    });

    it('uç okunamazsa bölüm sessizce çekilir', async () => {
        const { container } = mount({}, false);

        await waitFor(() => {
            expect(container.querySelector('section')).toBeNull();
        });
    });
});
