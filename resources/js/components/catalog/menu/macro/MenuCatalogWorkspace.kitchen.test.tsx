import type React from 'react';
import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * MUTFAK MENÜ EKRANINDA — "başka bir şey görmez" satırın İÇİNDE de geçerli.
 *
 * Kenar çubuğu zaten süzülüyordu (`WorkspaceSectionRegistry.kitchen.test`):
 * aşçı Hasan yalnız Menüler'i görüyor. Ama Menüler'i AÇTIĞINDA her satırda
 * fiyat düğmesi, görünürlük anahtarı, "Kaldır" ve "Yeniden adlandır"
 * duruyordu. Sunucu bunların hepsini 403 ile reddediyor — yani ekran, hiçbir
 * zaman işe yaramayacak sekiz kontrol çiziyordu.
 *
 * ÇİZİLMEYEN ŞEY, KAPATILMIŞ ŞEYDEN İYİDİR. Bu deponun kuralı `docs/98`
 * FF-74'te kondu ve orada bir cümleyle özetlendi: "sunucu 'yapamaz' dedi,
 * ekran onu hiç çizmedi". Devre dışı bir düğme hâlâ bir söz verir — "bir
 * gün, bir şekilde" der; oysa aşçının rolü değişmedikçe o gün gelmeyecek.
 *
 * BİLGİ İSE KALIR. Fiyat düğme olmaktan çıkar ama METİN olarak durur:
 * Hasan'ın gördüğü satır hâlâ tam satırdır ve doğru ürüne baktığını fiyattan
 * da doğrulayabilir. Yetkiyi kaldırmak, bilgiyi karartmak değildir.
 *
 * MÜŞTERİ YOLCULUĞU. Akşam servisi: levrek bitti, tatlıda fıstık var. Hasan
 * telefonu açar, Menüler'de levreğin yanındaki "Tükendi"ye basar, tatlının
 * alerjenine fıstığı ekler. Ekranda başka hiçbir düğme yoktur — yanlışlıkla
 * fiyata dokunma ihtimali de yoktur.
 */
const WORKSPACE_ID = 7;
const LOCATION_ID = 3;
const MENU_ID = 42;

/** `App\Domain\Authorization\RolePermissions::for(MembershipRole::Kitchen)`. */
const KITCHEN = ['workspace.view', 'menu.view', 'menu.allergens.manage', 'menu.stock.manage'];

/** Kontrol grubu: bugünkü editör, aynı ekranda her şeyi görmeye devam eder. */
const EDITOR = [...KITCHEN, 'menu.manage'];

function jsonResponse(status: number, body: unknown): Response {
    return {
        ok: status >= 200 && status < 300,
        status,
        headers: new Headers(),
        json: async () => body,
    } as Response;
}

function tree() {
    return {
        id: MENU_ID,
        workspaceId: WORKSPACE_ID,
        locationId: LOCATION_ID,
        name: 'Ana Menü',
        state: 'draft',
        categories: [
            {
                id: 5,
                menuId: MENU_ID,
                name: 'Ana Yemekler',
                position: 1,
                menuItems: [
                    {
                        id: 11,
                        categoryId: 5,
                        productId: 9,
                        productName: 'Levrek',
                        priceMinorAmount: 48000,
                        currencyCode: 'TRY',
                        position: 1,
                        isVisible: true,
                        allergens: [],
                    },
                ],
            },
        ],
    };
}

async function renderAs(permissions: string[]) {
    const fetchMock = vi.fn(async (url: string, init?: RequestInit) => {
        const method = (init?.method ?? 'GET').toUpperCase();

        if (String(url) === '/sanctum/csrf-cookie') return jsonResponse(204, {});
        if (String(url).endsWith('/brand') && method === 'GET') {
            return jsonResponse(200, { id: 1, workspaceId: WORKSPACE_ID, currency: 'TRY' });
        }
        if (String(url).endsWith(`/locations/${LOCATION_ID}/menu`) && method === 'GET') {
            return jsonResponse(200, tree());
        }

        return jsonResponse(200, { ok: true });
    });

    vi.stubGlobal('fetch', fetchMock);

    const { MenuCatalogWorkspace } = (await import('./MenuCatalogWorkspace')) as unknown as {
        MenuCatalogWorkspace: React.ComponentType<{
            workspaceId: number;
            locationId: number;
            can?: (permission: string) => boolean;
        }>;
    };

    render(
        <MenuCatalogWorkspace
            workspaceId={WORKSPACE_ID}
            locationId={LOCATION_ID}
            can={(permission) => permissions.includes(permission)}
        />,
    );

    await screen.findByRole('heading', { name: 'Ana Yemekler' });
}

describe('mutfak rolü menü ekranında (docs/109 §6.4)', () => {
    it('mutfağa fiyat, görünürlük ve satır menüsü ÇİZİLMEZ; tükendi ve alerjen kalır', async () => {
        await renderAs(KITCHEN);

        /*
            YAPABİLDİĞİ İKİ ŞEY. Bunlar rolün tanımıdır; biri kaybolursa rol
            anlamsız kalır — "alerjen ve bugün bitti" cümlesinden geriye bir
            salt-okunur üyelik kalırdı.
        */
        expect(
            screen.getByRole('button', { name: 'Mark Levrek sold out for today' }),
        ).toBeInTheDocument();

        /*
            YAPAMADIKLARI. Devre dışı da değil — HİÇ YOK. `queryBy`
            kullanılıyor çünkü aranan şeyin yokluğu iddianın kendisidir.
        */
        expect(screen.queryByRole('switch')).toBeNull();
        expect(screen.queryByRole('button', { name: /^Rename/i })).toBeNull();
        expect(screen.queryByRole('button', { name: /^More actions/i })).toBeNull();
        expect(screen.queryByRole('button', { name: /Edit price/i })).toBeNull();
        /*
            ŞERİT: menü hapları KALIR (hangi menüye bakıldığını seçmek
            okumaktır), menüyü değiştiren dört eylem gider.
        */
        expect(screen.queryByRole('button', { name: /Add menu|New menu/i })).toBeNull();
        expect(screen.queryByRole('button', { name: /Edit menu/i })).toBeNull();
        expect(screen.queryByRole('button', { name: /CSV/i })).toBeNull();
        expect(screen.queryByRole('button', { name: /Add product|Add item/i })).toBeNull();
        expect(screen.queryByRole('button', { name: /Add category|New category/i })).toBeNull();
        expect(screen.queryByRole('button', { name: /Preview and publish/i })).toBeNull();

        /*
            FİYAT METİN OLARAK DURUR: yetki gitti, bilgi gitmedi. Dize
            kanonik biçimlendiriciden gelir (`money/format`) — test dilinde
            "TRY 480.00", Türkçe'de "₺480,00"; ikisi de aynı üründür.
        */
        expect(screen.getByText('TRY 480.00')).toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    it('mutfak alerjen düzenleyicisini GERÇEKTEN açar; çekmecede fiyat/sunum alanı yoktur', async () => {
        /*
            DÜĞMENİN VAR OLMASI YETMEZ. Rolün tanımı "alerjen ve bugün
            bitti"yse, alerjen yolunun ucuna kadar çalışması gerekir —
            aksi hâlde daraltma, yeteneği de birlikte götürmüş olurdu.

            Aynı kontrolde çekmecenin İÇİ de sınanır: fiyat ve sunum
            düzenleyicileri yalnız kendi girişlerinden açılıyor ve o
            girişler mutfağa çizilmiyor. Bu iddia olmadan, girişleri
            kaldırmak çekmeceyi güvenli sanmak olurdu.
        */
        const user = userEvent.setup();
        await renderAs(KITCHEN);

        await user.click(screen.getByRole('button', { name: 'Edit allergens for Levrek' }));

        expect(await screen.findByRole('textbox', { name: /allergens/i })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Save allergens' })).toBeInTheDocument();

        // Çekmece açık: fiyat ve açıklama alanları YİNE yok.
        expect(screen.queryByLabelText(/price/i)).toBeNull();
        expect(screen.queryByLabelText(/description/i)).toBeNull();

        vi.unstubAllGlobals();
    });

    it('editörün gördüğü ekran değişmez — daraltma yalnız mutfağa uygulanır', async () => {
        await renderAs(EDITOR);

        expect(screen.getByRole('switch')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /Edit price/i })).toBeInTheDocument();
        // Kategori satırının ve ürün satırının kendi taşma menüleri var.
        expect(screen.getAllByRole('button', { name: /^More actions/i }).length).toBeGreaterThan(0);

        vi.unstubAllGlobals();
    });

    it('izin listesi VERİLMEZSE hiçbir şey daraltılmaz (eski gövde)', async () => {
        /*
            `can` isteğe bağlıdır ve tanımsızsa "evet" sayılır — WorkspaceApp
            ile aynı kural (`docs/98` FF-74). Bu, ekranı izin listesi
            gelmeden çizen her yolun (Storybook, eski gövde, doğrudan
            gömme) sessizce boşalmasını engeller.
        */
        const fetchMock = vi.fn(async (url: string, init?: RequestInit) => {
            const method = (init?.method ?? 'GET').toUpperCase();
            if (String(url) === '/sanctum/csrf-cookie') return jsonResponse(204, {});
            if (String(url).endsWith('/brand') && method === 'GET') {
                return jsonResponse(200, { id: 1, workspaceId: WORKSPACE_ID, currency: 'TRY' });
            }
            if (String(url).endsWith(`/locations/${LOCATION_ID}/menu`) && method === 'GET') {
                return jsonResponse(200, tree());
            }
            return jsonResponse(200, { ok: true });
        });
        vi.stubGlobal('fetch', fetchMock);

        const { MenuCatalogWorkspace } = (await import('./MenuCatalogWorkspace')) as unknown as {
            MenuCatalogWorkspace: React.ComponentType<{ workspaceId: number; locationId: number }>;
        };

        render(<MenuCatalogWorkspace workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);
        await screen.findByRole('heading', { name: 'Ana Yemekler' });

        expect(screen.getByRole('switch')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /Edit price/i })).toBeInTheDocument();

        vi.unstubAllGlobals();
    });
});
