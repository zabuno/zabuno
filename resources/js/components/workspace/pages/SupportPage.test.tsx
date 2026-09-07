import { describe, expect, it, vi, afterEach } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { SupportPage } from './SupportPage';
import supportSection from './SupportPage.section';
import { SECTION_DESCRIPTORS } from '../shell/WorkspaceSectionRegistry';
import type { SupportApi, SupportRequestRow } from './support/supportApi';

/**
 * FF-201 — panel destek ekranı (`docs/125` §4).
 *
 * Gereksinimler: SUPPORT-PAGE-LIST-01 (talepler referans/konu/durum/tarihle),
 * SUPPORT-PAGE-COMMITMENT-01 (taahhüt yalnız sunucu verirse),
 * SUPPORT-PAGE-SUBMIT-01 (form gönderir, liste yeniden okunur, cümle
 * sunucunun alındı hâlini söyler), SUPPORT-PAGE-API-01 (varsayılan
 * istemci doğru adreslere gider), SUPPORT-PAGE-SECTION-01 (bölüm kaydı).
 */
const rows: SupportRequestRow[] = [
    {
        id: 2,
        reference: 'ZB-3F7K2',
        subject: 'Menüm görünmüyor',
        status: 'received',
        received_at: '2026-09-06T09:12:00+03:00',
        first_response_at: null,
    },
    {
        id: 1,
        reference: 'ZB-Q2W9X',
        subject: 'Fatura adresi',
        status: 'answered',
        received_at: '2026-08-28T08:00:00+03:00',
        first_response_at: '2026-08-29T08:00:00+03:00',
    },
];

function fakeApi(
    overrides: Partial<SupportApi> = {},
    commitment: { hours: number; sentence: string } | null = null,
): SupportApi & { created: string[] } {
    const created: string[] = [];

    return {
        created,
        list: async () => ({ requests: rows, commitment }),
        create: async (subject: string) => {
            created.push(subject);

            return {
                id: 9,
                reference: 'ZB-NEW01',
                subject,
                status: 'received',
                received_at: '2026-09-06T10:00:00+03:00',
                first_response_at: null,
                acknowledgement: 'sent' as const,
            };
        },
        ...overrides,
    };
}

describe('destek ekranı', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
        vi.restoreAllMocks();
    });

    it('SUPPORT-PAGE-LIST-01: talepleri referans, konu, durum ve tarihle listeler', async () => {
        render(<SupportPage workspaceId={7} email="mehmet@zeytinkebap.com" api={fakeApi()} />);

        const list = await screen.findByRole('region', { name: 'Your requests' });

        expect(within(list).getByText('ZB-3F7K2')).toBeInTheDocument();
        expect(within(list).getByText('Menüm görünmüyor')).toBeInTheDocument();
        expect(within(list).getByText('Received')).toBeInTheDocument();
        expect(within(list).getByText('Answered')).toBeInTheDocument();
        expect(within(list).getByText(/Received \d{2}[./]\d{2}[./]\d{4}/)).toBeInTheDocument();
        expect(within(list).getByText(/Answered \d{2}[./]\d{2}[./]\d{4}/)).toBeInTheDocument();

        // Yardım bağlantısı OTURUMSUZ sayfaya gider.
        expect(screen.getByRole('link', { name: 'Open help articles' })).toHaveAttribute(
            'href',
            '/help',
        );
    });

    it('SUPPORT-PAGE-COMMITMENT-01: taahhüt cümlesi yalnız sunucu verirse çizilir', async () => {
        const { unmount } = render(
            <SupportPage workspaceId={7} email="mehmet@zeytinkebap.com" api={fakeApi()} />,
        );

        await screen.findByRole('region', { name: 'Your requests' });
        expect(screen.queryByText(/within \d+ hours/i)).not.toBeInTheDocument();

        unmount();

        render(
            <SupportPage
                workspaceId={7}
                email="mehmet@zeytinkebap.com"
                api={fakeApi({}, { hours: 24, sentence: 'We reply within 24 hours.' })}
            />,
        );

        expect(await screen.findByText('We reply within 24 hours.')).toBeInTheDocument();
    });

    it('SUPPORT-PAGE-SUBMIT-01: form gönderir, listeyi yeniden okur ve alındı hâlini söyler', async () => {
        const user = userEvent.setup();
        const api = fakeApi();
        const listSpy = vi.spyOn(api, 'list');

        render(<SupportPage workspaceId={7} email="mehmet@zeytinkebap.com" api={api} />);
        await screen.findByRole('region', { name: 'Your requests' });

        const submit = screen.getByRole('button', { name: 'Send request' });
        expect(submit).toBeDisabled();

        await user.type(screen.getByLabelText(/Subject/), 'Karekod basılmıyor');
        await user.type(screen.getByLabelText(/What is happening\?/), 'PDF boş geliyor.');
        expect(submit).toBeEnabled();

        await user.click(submit);

        expect(
            await screen.findByText(
                'Your request ZB-NEW01 was received. We sent a confirmation to mehmet@zeytinkebap.com.',
            ),
        ).toBeInTheDocument();
        expect(api.created).toEqual(['Karekod basılmıyor']);
        // İlk yükleme + gönderim sonrası yeniden okuma.
        expect(listSpy).toHaveBeenCalledTimes(2);
        expect(screen.getByLabelText(/Subject/)).toHaveValue('');
    });

    it('SUPPORT-PAGE-SUBMIT-02: alındı e-postası çıkmadıysa "kopyasını gönderdik" DEMEZ', async () => {
        const user = userEvent.setup();
        const api = fakeApi({
            create: async (subject: string) => ({
                id: 9,
                reference: 'ZB-NEW01',
                subject,
                status: 'received',
                received_at: '2026-09-06T10:00:00+03:00',
                first_response_at: null,
                acknowledgement: 'failed' as const,
            }),
        });

        render(<SupportPage workspaceId={7} email="mehmet@zeytinkebap.com" api={api} />);
        await screen.findByRole('region', { name: 'Your requests' });

        await user.type(screen.getByLabelText(/Subject/), 'Karekod');
        await user.type(screen.getByLabelText(/What is happening\?/), 'Boş.');
        await user.click(screen.getByRole('button', { name: 'Send request' }));

        const notice = await screen.findByRole('status', { name: '' });
        expect(notice).toHaveTextContent('ZB-NEW01');
        expect(notice).toHaveTextContent('could not be sent');
        expect(notice).not.toHaveTextContent('We sent a confirmation');
    });

    it('SUPPORT-PAGE-SUBMIT-03: sunucu düşünce hata cümlesi yazar, girdiyi silmez', async () => {
        const user = userEvent.setup();
        const api = fakeApi({
            create: async () => {
                throw new Error('down');
            },
        });

        render(<SupportPage workspaceId={7} email="mehmet@zeytinkebap.com" api={api} />);
        await screen.findByRole('region', { name: 'Your requests' });

        await user.type(screen.getByLabelText(/Subject/), 'Karekod');
        await user.type(screen.getByLabelText(/What is happening\?/), 'Boş.');
        await user.click(screen.getByRole('button', { name: 'Send request' }));

        expect(await screen.findByRole('alert')).toHaveTextContent(
            'Your request could not be sent. Try again.',
        );
        expect(screen.getByLabelText(/Subject/)).toHaveValue('Karekod');
    });

    it('SUPPORT-PAGE-API-01: varsayılan istemci çalışma alanının destek uçlarına gider', async () => {
        const fetchMock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
            const url = String(input);

            if (url === '/sanctum/csrf-cookie') {
                return new Response(null, { status: 204 });
            }

            if (url === '/api/workspaces/7/support-requests' && (init?.method ?? 'GET') === 'GET') {
                return new Response(JSON.stringify({ requests: rows, commitment: null }), {
                    status: 200,
                    headers: { 'Content-Type': 'application/json' },
                });
            }

            if (url === '/api/workspaces/7/support-requests' && init?.method === 'POST') {
                return new Response(
                    JSON.stringify({
                        id: 9,
                        reference: 'ZB-NEW01',
                        subject: 'X',
                        status: 'received',
                        received_at: '2026-09-06T10:00:00+03:00',
                        first_response_at: null,
                        acknowledgement: 'sent',
                    }),
                    { status: 201, headers: { 'Content-Type': 'application/json' } },
                );
            }

            return new Response('', { status: 404 });
        });
        vi.stubGlobal('fetch', fetchMock);

        const user = userEvent.setup();
        render(<SupportPage workspaceId={7} email="mehmet@zeytinkebap.com" />);

        expect(await screen.findByText('ZB-3F7K2')).toBeInTheDocument();

        await user.type(screen.getByLabelText(/Subject/), 'X');
        await user.type(screen.getByLabelText(/What is happening\?/), 'Y');
        await user.click(screen.getByRole('button', { name: 'Send request' }));

        await waitFor(() => {
            expect(fetchMock).toHaveBeenCalledWith(
                '/api/workspaces/7/support-requests',
                expect.objectContaining({ method: 'POST' }),
            );
        });

        const postCall = fetchMock.mock.calls.find(([, init]) => init?.method === 'POST');
        expect(postCall).toBeDefined();
        expect(JSON.parse(String(postCall?.[1]?.body))).toEqual({ subject: 'X', message: 'Y' });
        // Ad ve e-posta GÖVDEDE YOK: hesaptan gelir.
        expect(String(postCall?.[1]?.body)).not.toContain('mehmet@');
    });

    /*
        Grup `management` — `utility` DEĞİL. FF-84 Ayarlar'ı kenar
        çubuğundan kaldırıp `utility` grubunu boşalttı; başlığının İngilizce
        karşılığı hâlâ "Settings". Destek oraya konunca sahibin kaldırdığı
        başlık tek maddeyle geri geliyordu. Destek zaten bir yönetim
        kanalıdır (`docs/125` §4) ve izni Şubeler/Ekip ile aynı.
    */
    it('SUPPORT-PAGE-SECTION-01: bölüm kaydı yönetim iznini ve yönetim grubunu taşır', () => {
        expect(supportSection.key).toBe('support');
        expect(supportSection.path).toBe('support');
        expect(supportSection.permission).toBe('workspace.manage');
        expect(supportSection.group).toBe('management');
        expect(supportSection.labelKey).toBe('workspace.support.title');
    });

    /*
        SUPPORT-PAGE-SECTION-02 — YERLEŞTİRME KARARI DONDURULUR.

        Kayıt defteri zaten çakışan `order` değerini gürültüyle reddediyor;
        bu test onun yerine geçmez, KARARI tutar: Destek her gün gidilen bir
        yer değil, tıkanınca gidilen yerdir; kayıtta ve Yönetim bloğunun
        içinde EN SONDA durur.

        Sayı yazılmaz (14 bugünün değeridir, yarın bölüm eklenir). Sınanan
        şey ilişkidir: kayıttaki her bölümün sırası Destek'inkinden küçük.
        Böylece biri Destek'i günlük ekranların arasına taşıdığında kapı
        kırılır ve karar yeniden konuşulur.
    */
    it('SUPPORT-PAGE-SECTION-02: destek kayıtta en sonda durur', () => {
        const others = SECTION_DESCRIPTORS.filter((descriptor) => descriptor.key !== 'support');

        expect(others.length).toBeGreaterThan(5);

        const misplaced = others
            .filter((descriptor) => descriptor.order >= supportSection.order)
            .map((descriptor) => `${descriptor.key}: ${descriptor.order}`);

        expect(
            misplaced,
            'Destek kayıtta en sonda durmalı: günlük operasyon değil, tıkanınca ' +
                'gidilen yer (docs/125 §4).',
        ).toEqual([]);
    });
});
