import { describe, expect, it, vi } from 'vitest';
import { render, screen, fireEvent, within } from '@testing-library/react';

import { PublicationStatusRegion, type CurrentPublication } from './PublicationStatusRegion';

/**
 * ALLERGEN-DUTY-01 — yayın şeridinde duran sorumluluk beyanı.
 *
 * ÖLÇÜLDÜ: "Yayınla" düğmesi, misafirin telefonunda gördüğü menüyü
 * değiştiren tek eylemdir. Şeritte bugün yalnız "yayın listesini gözden
 * geçirdim" onayı var — o onay İŞLETME İÇİ bir kontrol listesidir ve kimin
 * neyden sorumlu olduğunu söylemez.
 *
 * Restoran sahibinin yolculuğu: kebapçı akşam servisinden önce fiyatı
 * günceller, "Yayınla"ya basar. Ertesi gün bir misafir, karteste yazmayan
 * fıstık yüzünden alerjik tepki verir. O ana kadar ekran, alerjen
 * doğruluğunun KİME ait olduğunu hiç söylememiştir; sahip Zabuno'nun
 * menüyü "kontrol ettiğini" varsaymıştır. Fiyat ve bulunabilirlik için de
 * aynısı geçerli: tükenen ürün ekranda dururken misafir sipariş verir.
 *
 * Bu paket, tam da yayına basmadan önce duran tek cümlelik bir beyan kurar:
 * fiyat, alerjen ve bulunabilirlik doğruluğu işletmenindir, ayrıntısı
 * `/terms`tedir. Beyan bir KAPI değil, bir BİLDİRİMDİR — tek tıklık yayın
 * akışı aynen korunur; ikinci bir onay kutusu eklenmez.
 *
 * Ve beyan kapatılamaz. Kapatılabilir bir yükümlülük bildirimi, ilk gün
 * kapatıldıktan sonra hiç var olmamış gibi davranır; oysa yükümlülük her
 * yayında yeniden doğar.
 */
const CURRENT: CurrentPublication = {
    id: 12,
    workspaceId: 7,
    menuId: 42,
    locationId: 3,
    version: 4,
    state: 'published',
    publishedAt: '2026-08-28T18:00:00Z',
    snapshot: { categories: [] },
};

function renderStatus(overrides: Partial<Parameters<typeof PublicationStatusRegion>[0]> = {}) {
    render(
        <PublicationStatusRegion
            current={CURRENT}
            loading={false}
            loadError={false}
            onRetry={() => {}}
            checklistReady
            confirmed={false}
            onConfirmedChange={() => {}}
            onPublish={() => {}}
            publishing={false}
            errorMessage={null}
            {...overrides}
        />,
    );
}

/** Yayın şeridi: onay ve eylem aynı kutuda (`PublicationStatusRegion.aep.test.tsx`). */
function publishStrip(): HTMLElement {
    const strip = document.querySelector<HTMLElement>('[data-publish-commit]');

    expect(strip).not.toBeNull();

    return strip as HTMLElement;
}

/** Sorumluluk beyanı — şeridin kendi kancası, metne değil yapıya bağlanır. */
function dutyStatement(): HTMLElement {
    const statement = publishStrip().querySelector<HTMLElement>('[data-publish-duty]');

    expect(statement).not.toBeNull();

    return statement as HTMLElement;
}

function collapsed(text: string | null): string {
    return (text ?? '').replace(/\s+/g, ' ').trim();
}

describe('PublicationStatusRegion — sorumluluk beyanı yayın düğmesinden önce durur', () => {
    it('beyan yayın şeridinde, Publish düğmesinden ÖNCE ve /terms bağlantısıyla görünür', () => {
        renderStatus();

        const statement = dutyStatement();
        const publishButton = screen.getByRole('button', { name: /publish/i });

        // Aynı şerit: karar, sorumluluk ve eylem tek kutuda okunur.
        expect(publishStrip().contains(publishButton)).toBe(true);

        /*
            SIRA ANLAMLIDIR. Beyan düğmeden SONRA gelseydi, sahip zaten
            basmış olurdu — bir yükümlülük bildirimi, yükümlülüğü doğuran
            eylemden sonra okunduğunda bildirim değil mazerettir.
        */
        expect(
            statement.compareDocumentPosition(publishButton) & Node.DOCUMENT_POSITION_FOLLOWING,
        ).toBeTruthy();

        // Cümle üç sorumluluğu da ADIYLA sayar; "menü doğruluğu" gibi
        // kapsayıcı bir söz, alerjeni sahibin gözünde görünmez bırakır.
        const sentence = collapsed(statement.textContent);
        expect(sentence).toMatch(/price/i);
        expect(sentence).toMatch(/allerg/i);
        expect(sentence).toMatch(/availab/i);

        // Ayrıntı metni beyanın İÇİNDEDİR: sahibin "nerede yazıyor" diye
        // aramak için ekranı terk etmesi gerekmez.
        const termsLink = within(statement).getByRole('link');
        expect(termsLink).toHaveAttribute('href', '/terms');
    });

    it('her yayın durumunda görünür kalır ve kapatma/gizleme kontrolü taşımaz', () => {
        const states: Partial<Parameters<typeof PublicationStatusRegion>[0]>[] = [
            // Kontrol listesi tamamlanmamışken de: yükümlülük, yayına hazır
            // olmakla değil, yayınlayacak olmakla doğar.
            { checklistReady: false, confirmed: false },
            { loading: true, current: null },
            { current: null, loadError: true },
            { current: null },
            { publishing: true, confirmed: true },
        ];

        for (const state of states) {
            const view = render(
                <PublicationStatusRegion
                    current={CURRENT}
                    loading={false}
                    loadError={false}
                    onRetry={() => {}}
                    checklistReady
                    confirmed={false}
                    onConfirmedChange={() => {}}
                    onPublish={() => {}}
                    publishing={false}
                    errorMessage={null}
                    {...state}
                />,
            );

            const statement = view.container.querySelector<HTMLElement>(
                '[data-publish-commit] [data-publish-duty]',
            );

            expect(statement).not.toBeNull();
            expect(statement).toBeVisible();
            // Ekran okuyucuya bırakılmış bir beyan, gözle yayınlayan sahip
            // için yok demektir.
            expect(statement?.className ?? '').not.toMatch(/\bsr-only\b|\bhidden\b/);

            /*
                Kapatılamaz olmak bir stil tercihi değil: kapatılabilir bir
                yükümlülük bildirimi ilk gün kapatılır ve sonraki her yayında
                hiç var olmamış gibi davranır.
            */
            expect(statement?.closest('details')).toBeNull();
            expect(within(statement as HTMLElement).queryAllByRole('button')).toEqual([]);
            expect(
                statement?.querySelectorAll(
                    '[data-dismiss], [aria-expanded], [data-collapsible], [aria-controls]',
                ).length,
            ).toBe(0);
            expect(collapsed(statement?.textContent ?? '')).not.toMatch(
                /dismiss|don'?t show|do not show|hide this|got it/i,
            );

            view.unmount();
        }
    });

    it('onaylıyken tek tıklık yayın akışı bozulmaz: onPublish tam bir kez çağrılır', () => {
        /*
            Beyan bir metindir, bir kapı değil. Sahip onay kutusunu
            işaretlediyse "Yayınla" HÂLÂ tek dokunuşla çalışır — beyanın
            eklenmesi, akşam servisi başlarken fiyatı düzelten kebapçıya
            fazladan bir adım çıkarmaz.
        */
        const onPublish = vi.fn();

        renderStatus({ confirmed: true, onPublish });

        const publishButton = screen.getByRole('button', { name: /publish/i });
        expect(publishButton).toBeEnabled();

        fireEvent.click(publishButton);

        expect(onPublish).toHaveBeenCalledTimes(1);
    });

    it('beyan tek cümledir ve ikinci bir onay kapısı eklemez', () => {
        renderStatus({ confirmed: true });

        const statement = dutyStatement();
        const sentence = collapsed(statement.textContent);

        // Tek cümle: sahip yayın anında paragraf okumaz, okumadığı bir
        // paragraf da hiç gösterilmemiş sayılır.
        expect(sentence).toMatch(/^[^.!?]+[.!?]$/);

        // Beyanın içinde form kontrolü YOKTUR — ne onay kutusu ne başka bir
        // giriş; yükümlülük bildirimi onaylanmaz, bildirilir.
        expect(statement.querySelectorAll('input, select, textarea').length).toBe(0);

        /*
            Ve düğmenin kilidi eskisi gibi YALNIZ kontrol listesi + onay +
            yayın hâline bağlıdır: beyan bu zincire yeni bir halka eklemez.
        */
        expect(screen.getByRole('button', { name: /publish/i })).toBeEnabled();

        screen.getByRole('checkbox');
        expect(publishStrip().querySelectorAll('input[type="checkbox"]').length).toBe(1);
    });
});
