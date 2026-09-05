import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { QrCodesPage } from './QrCodesPage';

/**
 * QR KODLAR EKRANI — panel v3.1 kanonik kaynağı
 * (`docs/reference/panel-v3/panel-v3.1.dc.html`, QR bölümü).
 *
 * Sahibin kuralı (2026-09-05): *"eğer ben tasarım veriyorsam zaten asla eski
 * dökümanlara bağımlı kalmadan yapmalısın."*
 *
 * Önceki hâl bir KOD LİSTESİYDİ: kırk kareli bir ızgara, sahip birini seçer ve
 * seçtiği kodun paneli sağda belirirdi. Yani ilk soru "hangi kod"du ve baskı
 * ayarları o kodun içine gömülüydü — kırk masaya kart basmak isteyen sahip
 * önce bir masa seçmek zorundaydı.
 *
 * Kaynağın ekranı ise bir BASKI SİPARİŞİDİR ve üç soruyu bu sırayla sorar:
 * 1) ne basacaksın, 2) hangi masalar, 3) nasıl görünsün. Varsayılan kapsam
 * "tüm masalar"dır, çünkü sahibin buraya gelme sebebi çoğunlukla budur.
 *
 * AEP ağırlık ölçeği ÜÇ basamaklıdır: 400 gövde, 500 vurgulu satır, 700 başlık
 * ve birincil eylem. 600 (`font-semibold`) ölçekte YOKTUR: tarayıcı onu 500 ile
 * 700 arasından seçer ya da sentetik bir kalınlaştırma uydurur ve aynı ekran
 * iki makinede iki farklı ağırlıkta çizilir.
 */

function jsonResponse(status: number, body: unknown): Response {
    return {
        headers: new Headers(),
        ok: status >= 200 && status < 300,
        status,
        json: async () => body,
    } as Response;
}

const MENU_TREE = {
    id: 42,
    workspaceId: 7,
    locationId: 923,
    name: 'Ana menü',
    state: 'draft',
    categories: [],
};

function code(id: number, tableName: string, areaId: number, areaLabel: string, scanCount: number) {
    return {
        id,
        workspaceId: 7,
        locationId: 923,
        menuId: 42,
        token: String(id).repeat(30).slice(0, 30),
        resolverUrl: `https://zabuno.test/q/${String(id)}`,
        destinationType: 'published_menu',
        state: 'active',
        tableName,
        areaLabel,
        areaId,
        scanCount,
    };
}

const CODES = [
    code(11, 'Masa 1', 3, 'Bahçe', 31),
    code(12, 'Masa 2', 3, 'Bahçe', 0),
    code(13, 'Masa 3', 4, 'Salon', 12),
];

function routeFetch(url: string): Response {
    if (url.includes('/publications/current')) {
        return jsonResponse(200, { id: 1, version: 14, state: 'published' });
    }
    if (url.includes('/qr-codes')) {
        return jsonResponse(200, CODES);
    }

    return jsonResponse(200, []);
}

describe('QrCodesPage — panel v3.1 baskı siparişi', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchSpy = vi.fn((input: RequestInfo | URL) => Promise.resolve(routeFetch(String(input))));
        vi.stubGlobal('fetch', fetchSpy);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('kaynağın üç sorusunu kaynağın sırasıyla sorar', async () => {
        render(<QrCodesPage workspaceId={7} dashboardMenuTree={MENU_TREE} />);

        const steps = await screen.findAllByRole('region', { name: /^\d\. / });

        expect(steps.map((step) => step.getAttribute('aria-label'))).toEqual([
            '1. What are you printing?',
            '2. Which tables?',
            '3. How should it look?',
        ]);
    });

    it('ilk soru fiziksel nesnedir; kâğıt boyutu bunun sonucudur', async () => {
        render(<QrCodesPage workspaceId={7} dashboardMenuTree={MENU_TREE} />);

        const tableCard = await screen.findByRole('button', { name: /table card.*plexiglass/i });

        // Hazır çıktı BAŞTAN seçilidir: sahip hiçbir şeye dokunmadan masa
        // kartı basabilmeli.
        expect(tableCard).toHaveAttribute('aria-pressed', 'true');

        // Kâğıt boyu bir SONUÇTUR: sekiz kâğıt ve üç oran kapalı bölümde
        // duruyor, ilk bakışta değil.
        expect(screen.getByText('I need another size')).toBeInTheDocument();
    });

    it('varsayılan kapsam "tüm masalar"dır ve özet cümlesi bunu kelimeyle söyler', async () => {
        render(<QrCodesPage workspaceId={7} dashboardMenuTree={MENU_TREE} />);

        /*
            SEÇİLİ DURUM RENKLE ANLATILMAZ (WCAG 2.2 §1.4.1). Kaynağın alt
            çubuğu tam olarak bunun için var: üç adımın on kontrolünü tek
            cümleye indirir ve seçili olanı KELİMEYLE yazar.
        */
        expect(await screen.findByText('3 cards · A6 portrait · plain')).toBeInTheDocument();
        expect(screen.getByText('All tables · PDF · one zip file')).toBeInTheDocument();
    });

    it('birincil eylem gerçek toplu kart arşivi ucudur, uydurulmuş bir uç değil', async () => {
        render(<QrCodesPage workspaceId={7} dashboardMenuTree={MENU_TREE} />);

        const zip = await screen.findByRole('link', { name: /download 3 cards \(zip\)/i });
        const href = zip.getAttribute('href') ?? '';

        expect(href).toContain('/api/workspaces/7/brand/locations/923/qr-cards.zip');
        expect(href).toContain('cardTheme=classic');
        expect(href).toContain('size=A6');
        expect(href).toContain('orientation=portrait');
        expect(href).toContain('format=pdf');
    });

    it('bölge seçimi arşivi KİMLİKLE süzer — iki bölge aynı adı taşıyabilir', async () => {
        render(<QrCodesPage workspaceId={7} dashboardMenuTree={MENU_TREE} />);

        await userEvent.click(await screen.findByRole('button', { name: /one area/i }));

        const zip = await screen.findByRole('link', { name: /download 2 cards \(zip\)/i });
        expect(zip.getAttribute('href') ?? '').toContain('areaId=3');

        /*
            TEK KARTLIK BİR BÖLGE ARŞİV DEĞİLDİR. Bir dosyalık bir ZIP indirmek
            kullanıcıya açması gereken fazladan bir kabuk vermektir; sunucunun
            arşiv ucu da tek kart için ayrıca çalıştırılmaz.
        */
        await userEvent.click(screen.getByRole('button', { name: /salon/i }));

        /*
            Etiket artık kodun adını taşımıyor (sahibin 2026-09-05 kararı;
            ad zaten özet cümlesinde). Ölçülen şey değişmedi ve asıl önemli
            olan zaten oydu: DOĞRU KODUN ucu. Adres kimliği taşıyor.
        */
        const single = await screen.findByRole('link', { name: /^download$/i });
        expect(single.getAttribute('href') ?? '').toContain(
            '/api/workspaces/7/qr-codes/13/card.pdf',
        );
    });

    it('tek masa seçilince tek kartın ucu kullanılır ve yazdırma belirir', async () => {
        render(<QrCodesPage workspaceId={7} dashboardMenuTree={MENU_TREE} />);

        await userEvent.click(await screen.findByRole('button', { name: /a single table/i }));
        await userEvent.click(screen.getByRole('button', { name: 'Masa 2 · Bahçe' }));

        const download = await screen.findByRole('link', { name: /^download$/i });
        expect(download.getAttribute('href') ?? '').toContain(
            '/api/workspaces/7/qr-codes/12/card.pdf',
        );

        /*
            "Yazdır" YALNIZ TEK KARTTA çizilir: tarayıcıya yazdırılabilecek
            şey bir PDF'tir ve çok kartlı bir seçimin çıktısı ZIP arşividir.
        */
        expect(screen.getByRole('link', { name: /print/i })).toBeInTheDocument();
    });

    it('hiç taranmamış masa bunu KELİMEYLE söyler, renkle değil', async () => {
        render(<QrCodesPage workspaceId={7} dashboardMenuTree={MENU_TREE} />);

        await userEvent.click(await screen.findByRole('button', { name: /a single table/i }));
        await userEvent.click(screen.getByRole('button', { name: 'Masa 2 · Bahçe' }));

        expect(await screen.findByText('Masa 2 · Bahçe · never scanned yet')).toBeInTheDocument();
    });

    it('önizleme ÖLÇÜLMÜŞ bir milimetre yazar, temenni değil', async () => {
        render(<QrCodesPage workspaceId={7} dashboardMenuTree={MENU_TREE} />);

        const preview = await screen.findByRole('complementary', {
            name: /this is how it comes out/i,
        });

        // A6 dikey: kart 105 × 148 mm, kod 88 mm — sunucunun bestecisiyle
        // aynı hesap (`lib/qrCardGeometry`, iki taraflı test).
        expect(within(preview).getByText('A6 · 105 × 148 mm')).toBeInTheDocument();
        expect(
            within(preview).getByText(/code 88 mm — easy to read from the table/i),
        ).toBeInTheDocument();
    });

    it('önizleme sunucunun GERÇEK kartını çizer, elle çizilmiş bir maketi değil', async () => {
        render(<QrCodesPage workspaceId={7} dashboardMenuTree={MENU_TREE} />);

        const preview = await screen.findByRole('complementary', {
            name: /this is how it comes out/i,
        });
        const image = within(preview).getByRole('img');

        expect(image.getAttribute('src') ?? '').toContain('/api/workspaces/7/qr-codes/11/card.svg');
    });

    it('tasarım değişince önizleme ve indirme birlikte değişir — tek plan vardır', async () => {
        render(<QrCodesPage workspaceId={7} dashboardMenuTree={MENU_TREE} />);

        await userEvent.click(await screen.findByRole('button', { name: /^dark/i }));

        await waitFor(() => {
            expect(screen.getByText('3 cards · A6 portrait · dark')).toBeInTheDocument();
        });

        const preview = await screen.findByRole('complementary', {
            name: /this is how it comes out/i,
        });

        expect(within(preview).getByRole('img').getAttribute('src') ?? '').toContain(
            'cardTheme=dark',
        );
        expect(
            screen.getByRole('link', { name: /download 3 cards \(zip\)/i }).getAttribute('href') ??
                '',
        ).toContain('cardTheme=dark');
    });

    it('yeni masalar için toplu kod, ikinci adımın kendi içindedir', async () => {
        render(<QrCodesPage workspaceId={7} dashboardMenuTree={MENU_TREE} />);

        expect(await screen.findByRole('group', { name: /bulk qr wizard/i })).toBeInTheDocument();
    });

    it('kesilecek tabaka kendi adıyla sunulur: seçilen ölçüyü taşımaz', async () => {
        render(<QrCodesPage workspaceId={7} dashboardMenuTree={MENU_TREE} />);

        const sheet = await screen.findByRole('region', { name: /sheet to cut out/i });

        expect(
            within(sheet)
                .getByRole('link', { name: /download every card/i })
                .getAttribute('href'),
        ).toBe('/api/workspaces/7/brand/locations/923/qr-codes/print.pdf');
    });

    it('menü yokken çıkış eylemi ölçeğin 700 basamağını taşır', () => {
        render(<QrCodesPage workspaceId={7} dashboardMenuTree={null} />);

        const action = screen.getByRole('button', { name: 'Go to your menu' });

        expect(action).toHaveClass('font-bold');
        expect(action).not.toHaveClass('font-semibold');
    });
});
