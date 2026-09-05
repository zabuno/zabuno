import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ProfilePage } from './ProfilePage';
import type { BrandProfile } from '../BrandEditForm';

/**
 * FF-88 — sahibin isteği (2026-09-04): profil ekranı kişisel bilgileri,
 * fotoğrafı, temayı ve markanın renklerini tek yerde toplar.
 *
 * Gereksinim: PROFILE-SCREEN-01 (bölümler), PROFILE-BRAND-PERMISSION-02
 * (renk bölümü yalnız yetkiliye), PROFILE-BRAND-SAVE-03 (renk kaydı).
 */
const brand: BrandProfile = {
    id: 1,
    workspace_id: 7,
    name: 'Zeytin Kebap',
    slug: 'zeytin-kebap',
    locale: 'tr',
    timezone: 'Europe/Istanbul',
    currency: 'TRY',
    description: null,
    contact_email: null,
    contact_phone: null,
    primary_color: null,
    secondary_color: null,
};

function renderPage(overrides: Partial<Parameters<typeof ProfilePage>[0]> = {}) {
    const onBrandSaved = vi.fn();

    render(
        <ProfilePage
            workspaceId={7}
            email="mehmet@zeytinkebap.com"
            userName="Mehmet Usta"
            avatarMediaAssetId={null}
            avatarUrl={null}
            brand={brand}
            onBrandSaved={onBrandSaved}
            canManageBrand
            {...overrides}
        />,
    );

    return { onBrandSaved };
}

describe('profil ekranı', () => {
    beforeEach(() => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => new Response('{}', { status: 200 })),
        );
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        vi.restoreAllMocks();
    });

    // --- PROFILE-SCREEN-01 -------------------------------------------------
    it('fotoğraf, kişisel bilgi ve marka rengi bölümlerini taşır', () => {
        renderPage();

        expect(screen.getByRole('heading', { name: 'Profile' })).toBeTruthy();
        expect(screen.getByRole('heading', { name: 'Profile photo' })).toBeTruthy();
        expect(screen.getByRole('heading', { name: 'Brand colours' })).toBeTruthy();
    });

    /*
        KİŞİSEL OLAN HER ŞEY BURADA (docs/109, kaynağın "Profil" ekranı).
        Ad, e-posta ve şifre Ayarlar'dan buraya taşındı; Ayarlar artık
        çalışma alanına aittir. Bu ölçü olmadan taşıma "Ayarlar'dan silindi"
        ile "Profil'e kondu" arasında ayrım yapamazdı — biri düzeltme, diğeri
        yetenek kaybı olurdu.
    */
    it('ad, e-posta ve şifre değiştirme bu ekrandadır', () => {
        renderPage();

        expect(screen.getByLabelText('Your name')).toBeTruthy();
        expect(screen.getByLabelText('Email')).toBeTruthy();
        expect(screen.getByText('Change password')).toBeTruthy();
    });

    // --- PROFILE-BRAND-PERMISSION-02 ---------------------------------------
    it('marka rengi bölümü, yönetme izni olmayana ÇİZİLMEZ', () => {
        renderPage({ canManageBrand: false });

        expect(screen.queryByRole('heading', { name: 'Brand colours' })).toBeNull();
        // Kişisel bölümler yerinde kalır: izin markaya aittir, kişiye değil.
        expect(screen.getByRole('heading', { name: 'Profile photo' })).toBeTruthy();
    });

    // --- PROFILE-BRAND-SAVE-03 ---------------------------------------------
    it('renk kodu yazılınca markayı zorunlu alanlarıyla birlikte kaydeder', async () => {
        const user = userEvent.setup();
        const saved = { ...brand, primary_color: '#C8102E' };
        const fetchMock = vi.fn(async (...args: [input: RequestInfo | URL, init?: RequestInit]) =>
            String(args[0]).includes('/brand')
                ? new Response(JSON.stringify(saved), { status: 200 })
                : new Response('{}', { status: 200 }),
        );
        vi.stubGlobal('fetch', fetchMock);

        const { onBrandSaved } = renderPage();

        await user.type(
            screen.getByLabelText('Primary colour', { selector: 'input[type="text"]' }),
            '#C8102E',
        );
        await user.click(screen.getByRole('button', { name: 'Save colours' }));

        await waitFor(() => expect(onBrandSaved).toHaveBeenCalledWith(saved));

        const brandCall = fetchMock.mock.calls.find((call) => String(call[0]).includes('/brand'));
        expect(brandCall).toBeTruthy();
        const body = JSON.parse(
            String((brandCall?.[1] as RequestInit | undefined)?.body),
        ) as Record<string, unknown>;
        /*
            Ad, saat dilimi ve para birimi sunucuda zorunlu. Gönderilmezlerse
            kullanıcı, hiç dokunmadığı bir alan yüzünden rengini kaydedemezdi.
        */
        expect(body.primary_color).toBe('#C8102E');
        expect(body.name).toBe('Zeytin Kebap');
        expect(body.timezone).toBe('Europe/Istanbul');
        expect(body.currency).toBe('TRY');
    });
});
