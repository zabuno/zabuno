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

    it('virüs taraması anahtar olarak görünür, açıktır ve KAPATILAMAZ', async () => {
        mount();

        const toggle = await screen.findByRole('switch', { name: /Virus scan/i });

        expect(toggle).toHaveAttribute('aria-checked', 'true');
        expect(toggle).toBeDisabled();

        const row = toggle.closest('li');
        expect(row).not.toBeNull();
        expect(within(row as HTMLElement).getByText('Cannot be switched off')).toBeInTheDocument();
    });

    it('tarayıcı bu ortamda yoksa "kapalı" değil "çalışmıyor" denir', async () => {
        mount({
            ...BODY,
            security: [
                { key: 'virusScan', state: 'unavailable', switchable: false },
                ...BODY.security.slice(1),
            ],
        });

        const toggle = await screen.findByRole('switch', { name: /Virus scan/i });

        expect(toggle).toHaveAttribute('aria-checked', 'false');
        expect(toggle).toBeDisabled();
        /*
            "Kapalı" bir KULLANICI KARARIDIR; burada olan bir ORTAM
            gerçeğidir. İkisini aynı kelimeyle söylemek, sahibin kapattığını
            sanmasına yol açardı.
        */
        expect(
            screen.getByText(/No scanner is connected in this environment/i),
        ).toBeInTheDocument();
    });

    it('yarım uygulanan önlem "tamamen açık" gibi gösterilmez', async () => {
        mount();

        const toggle = await screen.findByRole('switch', { name: /Strip embedded data/i });

        expect(toggle).toHaveAttribute('aria-checked', 'true');
        expect(
            screen.getByText(/original file is kept exactly as you uploaded it/i),
        ).toBeInTheDocument();
    });

    it('filigran için ANAHTAR ÇİZİLMEZ; "henüz yok" yazılır', async () => {
        mount();

        expect(await screen.findByText('Watermark')).toBeInTheDocument();
        expect(screen.getByText('Not built yet.')).toBeInTheDocument();
        expect(screen.queryByRole('switch', { name: /Watermark/i })).toBeNull();
    });

    it('hiçbir anahtar tıklanabilir değildir: hepsi salt okunur', async () => {
        mount();

        await screen.findByText('Watermark');

        const switches = screen.getAllByRole('switch');

        expect(switches).toHaveLength(4);
        switches.forEach((toggle) => {
            expect(toggle).toBeDisabled();
        });
    });

    it('uç okunamazsa bölüm sessizce çekilir', async () => {
        const { container } = mount({}, false);

        await waitFor(() => {
            expect(container.querySelector('section')).toBeNull();
        });
    });
});
