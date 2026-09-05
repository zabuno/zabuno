import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';

import { MediaMaturityRegion } from './MediaMaturityRegion';

/**
 * OLGUNLUK — kanonik kaynak `docs/reference/panel-v3/MedyaModulu.dc.html`
 * (ekran etiketi "Olgunluk"), seviye sözlüğü `docs/108` §6.7.
 *
 * ═══ BU DOSYANIN KORUDUĞU TEK ŞEY: EKRAN KENDİNİ ÖVEMEZ ═══
 *
 * Olgunluk ekranı, ürünün kendisi hakkında konuştuğu tek ekrandır. Bir
 * restoran sahibi burada "Toplu işlem · L4" okuduğunda, bin sekiz yüz
 * fotoğrafını tek seferde dönüştürmeye güvenmeye başlar. O rozetin
 * arkasında bu depoda gerçekten duran bir şey yoksa, ekran ona olmayan
 * bir yeteneği satmıştır.
 *
 * O yüzden burada üç şey korunur:
 *
 *   1. Her satırın yanında KANITI yazar — hangi uç, hangi gereksinim,
 *      hangi test. Rozet tek başına asla görünmez.
 *   2. Kanıtı olmayan basamak "kanıt yok" der ve puana girmez.
 *   3. Ekranın kendisi "bu bir öz değerlendirmedir" der. Söylemeyen bir
 *      ekran, sahibin gözünde bağımsız bir denetim raporuna dönüşür.
 *
 * DURUM RENKLE ANLATILMAZ: her kanıtın yanında "found" / "not found" /
 * "could not be checked" KELİMESİ durur. Yalnız yeşil bir noktayla
 * anlatılan bir durum, renk göremeyen kullanıcı için hiç anlatılmamıştır.
 */
const BODY = {
    selfAssessed: true,
    score: { achieved: 6, possible: 12 },
    capabilities: [
        {
            key: 'intake',
            level: 4,
            rungs: [
                {
                    level: 1,
                    state: 'met',
                    evidence: [
                        {
                            kind: 'endpoint',
                            ref: 'POST api/workspaces/{workspace}/media',
                            state: 'found',
                        },
                    ],
                },
                {
                    level: 2,
                    state: 'met',
                    evidence: [
                        {
                            kind: 'requirement',
                            ref: 'MEDIA-INTAKE-SIZE-REJECT-01',
                            state: 'found',
                        },
                    ],
                },
                {
                    level: 3,
                    state: 'met',
                    evidence: [
                        { kind: 'requirement', ref: 'MEDIA-AUDIT-WRITE-01', state: 'found' },
                    ],
                },
                {
                    level: 4,
                    state: 'met',
                    evidence: [
                        { kind: 'requirement', ref: 'MEDIA-FAILURE-VISIBLE-01', state: 'found' },
                    ],
                },
            ],
        },
        {
            key: 'scan',
            level: 2,
            rungs: [
                {
                    level: 1,
                    state: 'met',
                    evidence: [
                        { kind: 'requirement', ref: 'MEDIA-ACCEPT-CLEAN-01', state: 'found' },
                    ],
                },
                {
                    level: 2,
                    state: 'met',
                    evidence: [
                        { kind: 'requirement', ref: 'MEDIA-ACCEPT-INFECTED-01', state: 'found' },
                    ],
                },
                { level: 3, state: 'unmet', evidence: [] },
                { level: 4, state: 'unmet', evidence: [] },
            ],
        },
        {
            key: 'viewer',
            level: 1,
            rungs: [
                {
                    level: 1,
                    state: 'met',
                    evidence: [
                        {
                            kind: 'endpoint',
                            ref: 'GET api/workspaces/{workspace}/media/{media}/viewer',
                            state: 'found',
                        },
                    ],
                },
                {
                    level: 2,
                    state: 'unverifiable',
                    evidence: [
                        {
                            kind: 'test',
                            ref: 'MediaViewerTest::test_a_type_the_panel_cannot_open_is_said_so_instead_of_being_served',
                            state: 'unverifiable',
                        },
                    ],
                },
                { level: 3, state: 'unmet', evidence: [] },
                { level: 4, state: 'unmet', evidence: [] },
            ],
        },
    ],
};

function mount(body: unknown = BODY, ok = true) {
    vi.stubGlobal(
        'fetch',
        vi.fn(async () => ({ ok, status: ok ? 200 : 500, json: async () => body })),
    );

    return render(<MediaMaturityRegion workspaceId={7} />);
}

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('MediaMaturityRegion — kanıtsız puan yasak', () => {
    it('puanı gösterir ve bunun bir ÖZ DEĞERLENDİRME olduğunu yazar', async () => {
        mount();

        expect(await screen.findByText('6 / 12')).toBeInTheDocument();

        /*
            "Öz değerlendirme" cümlesi mühendislik sözcüğü taşıyamaz
            (`workspace-vocabulary.guard`), o yüzden sahibin diliyle
            yazılır: ürün kendi ödevini kendisi işaretliyor.
        */
        expect(screen.getByText(/marking its own homework/i)).toBeInTheDocument();
        expect(screen.getByText(/never counts/i)).toBeInTheDocument();
    });

    it('yetenek adını SUNUCUNUN anahtarıyla değil sahibin diliyle yazar', async () => {
        mount();

        // Sunucu `intake` gönderir; sahip "Uploading" okur. Anahtarın kendisi
        // ekranda hiç görünmez — bir ürün ekranı, iç adlandırmasını sızdırmaz.
        expect(await screen.findByRole('group', { name: 'Uploading' })).toBeInTheDocument();
        expect(screen.queryByText('intake')).toBeNull();
    });

    /*
        Rozet, seviye açıklamalarında da aynı biçimde kullanılır; o yüzden
        iddia YETENEK KARTININ İÇİNDE kurulur. "L4" kısaltması ekranda
        görülür, ekran okuyucuda ise tam cümle duyulur.
    */
    it('seviye rozeti ekran okuyucuya "4 üzerinden 4" diye okunur', async () => {
        mount();

        const intake = await screen.findByRole('group', { name: 'Uploading' });

        expect(within(intake).getByLabelText('Level 4 of 4')).toBeInTheDocument();
        expect(within(intake).getByText('L4')).toBeInTheDocument();
    });

    it('her basamağın KANITINI ekranda gösterir — rozet tek başına durmaz', async () => {
        mount();

        expect(
            await screen.findByText('POST api/workspaces/{workspace}/media'),
        ).toBeInTheDocument();
        expect(screen.getByText('MEDIA-INTAKE-SIZE-REJECT-01')).toBeInTheDocument();
        expect(
            screen.getByText(
                'MediaViewerTest::test_a_type_the_panel_cannot_open_is_said_so_instead_of_being_served',
            ),
        ).toBeInTheDocument();
    });

    it('kanıtı OLMAYAN basamak "kanıt yok" der ve puana girmez', async () => {
        mount();

        const scan = await screen.findByRole('group', { name: 'Virus scan' });

        // Taramanın sayacı yok: L3 ve L4 boş. İkisi de aynı cümleyi taşır.
        expect(within(scan).getAllByText(/no evidence/i).length).toBe(2);
        expect(within(scan).getByLabelText('Level 2 of 4')).toBeInTheDocument();
    });

    it('denetlenemeyen kanıt RENKLE değil KELİMEYLE anlatılır', async () => {
        mount();

        const viewer = await screen.findByRole('group', { name: 'Viewer' });

        expect(within(viewer).getByText(/could not be checked/i)).toBeInTheDocument();
    });

    it('uç okunamazsa hiç çizilmez — boş bir puan tablosu uydurulmaz', () => {
        const { container } = mount(BODY, false);

        expect(container).toBeEmptyDOMElement();
    });
});
