import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { cleanup, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { FirstRunHint } from './FirstRunHint';
import { HELP_ARTICLE_ANCHORS, firstRunHintStorageKey } from './firstRunHints';
import { resetAnalyticsContext, setAnalyticsContext } from '../../../../lib/analytics';

/**
 * İLK KEZ İPUCU — `docs/107` 1.7 ("ürün içi rehberli kurulum yok"),
 * `docs/101` §3 A1/A2/A8.
 *
 * Ölçüm (2026-09-06, ÖNCE): kurulum adımlarının hedef ekranlarında "burada
 * ne yapılacak" diyen tek bir cümle yoktu; Home'daki büyük düğme kullanıcıyı
 * ekrana bırakıyor ve ekran susuyordu. Yardım makalesi vardı ama panelden ona
 * giden bağlantı sayısı SIFIRDI.
 *
 * Bu kutunun dört kuralı var ve dördü de burada dondurulur:
 *   1. Adım bitmişse kutu YOK — biten bir işi öğretmek gürültüdür (A8).
 *   2. Ekranın kendi adımını anlatırken İKİNCİ bir büyük düğme çizmez (A1):
 *      birincil eylem ekranın formudur.
 *   3. Yardım bağlantısı yalnız makalede O BÖLÜM VARSA çizilir — uydurma
 *      çapa yok; test makale dosyasını okuyarak doğrular.
 *   4. Kapatma CİHAZDA hatırlanır ve bir sürtünme olayı basar.
 */
const ARTICLE = path.join(
    path.dirname(fileURLToPath(import.meta.url)),
    '../../../../../help/en/first-15-minutes.blade.php',
);

function dataLayer(): Array<Record<string, unknown>> {
    return (window as unknown as { dataLayer: Array<Record<string, unknown>> }).dataLayer;
}

describe('FirstRunHint (ILK15-HINT-01)', () => {
    beforeEach(() => {
        localStorage.clear();
        (window as unknown as { dataLayer?: unknown[] }).dataLayer = [];
        setAnalyticsContext({ tenantId: '7', tenantSlug: 'adana-ocakbasi' });
    });

    afterEach(() => {
        cleanup();
        resetAnalyticsContext();
        delete (window as unknown as { dataLayer?: unknown[] }).dataLayer;
    });

    it('bitmemiş adımda tek cümle söyler ve ikinci bir büyük düğme çizmez (A1)', () => {
        render(<FirstRunHint step="publication" workspaceId={7} done={false} />);

        const tip = screen.getByRole('complementary', { name: 'First-time tip' });
        expect(tip).toHaveTextContent(/press Publish/);

        // Tek düğme: kapatma. Birincil eylem ekranın kendi formudur.
        const buttons = within(tip).getAllByRole('button');
        expect(buttons).toHaveLength(1);
        expect(buttons[0]).toHaveAccessibleName('Hide this tip');
    });

    it('biten adımda hiç çizilmez (A8)', () => {
        render(<FirstRunHint step="menu" workspaceId={7} done />);

        expect(screen.queryByRole('complementary')).toBeNull();
    });

    it('kapatınca gizlenir, cihazda hatırlanır ve sürtünme olayı basar', async () => {
        const user = userEvent.setup();
        const { unmount } = render(<FirstRunHint step="menu" workspaceId={7} done={false} />);

        await user.click(screen.getByRole('button', { name: 'Hide this tip' }));

        expect(screen.queryByRole('complementary')).toBeNull();
        expect(localStorage.getItem(firstRunHintStorageKey(7, 'menu'))).toBe('1');
        expect(dataLayer()).toContainEqual(
            expect.objectContaining({ event: 'setup_hint_dismissed', step: 'menu' }),
        );

        // Yeniden bağlanınca (sayfa yenilendi) kutu geri gelmez.
        unmount();
        render(<FirstRunHint step="menu" workspaceId={7} done={false} />);
        expect(screen.queryByRole('complementary')).toBeNull();

        // Başka bir çalışma alanının kapatması bu alanı etkilemez.
        cleanup();
        render(<FirstRunHint step="menu" workspaceId={8} done={false} />);
        expect(screen.getByRole('complementary')).toBeInTheDocument();
    });

    it('yardım bağlantısı yalnız makalede o bölüm varsa çizilir; çapalar gerçek', () => {
        const article = readFileSync(ARTICLE, 'utf8');

        for (const [step, href] of Object.entries(HELP_ARTICLE_ANCHORS)) {
            const anchor = href.split('#')[1];
            expect(article, `${step} → ${href}`).toContain(`id="${anchor}"`);

            cleanup();
            render(<FirstRunHint step={step as 'menu'} workspaceId={7} done={false} />);
            const link = screen.getByRole('link');
            expect(link).toHaveAttribute('href', href);
            expect(link).toHaveAttribute('target', '_blank');
        }

        // Makalede marka, şube ve yayın bölümü YOK: bağlantı uydurulmaz.
        for (const step of ['brand', 'location', 'publication'] as const) {
            cleanup();
            render(<FirstRunHint step={step} workspaceId={7} done={false} />);
            expect(screen.queryByRole('link')).toBeNull();
        }
    });

    it('yardıma tıklamak hangi adımda takılındığını ölçer', async () => {
        const user = userEvent.setup();
        render(<FirstRunHint step="qr" workspaceId={7} done={false} />);

        const link = screen.getByRole('link', { name: /How to print QR codes/ });
        // jsdom gezinmez; tıklamanın varsayılanı engellenir ki test sekme açmaya çalışmasın.
        link.addEventListener('click', (event) => event.preventDefault());
        await user.click(link);

        expect(dataLayer()).toContainEqual(
            expect.objectContaining({ event: 'setup_help_opened', step: 'qr' }),
        );
    });

    it('"devam" kipinde önceki adımın ekranında sıradaki adımı Home ile aynı fiille söyler', async () => {
        const user = userEvent.setup();
        const onContinue = vi.fn();
        render(
            <FirstRunHint step="location" workspaceId={7} done={false} onContinue={onContinue} />,
        );

        const next = screen.getByRole('complementary', { name: 'Next step' });
        expect(next).toHaveTextContent(/Your restaurant has a name/);

        // Fiil Home'daki "şimdi" düğmesiyle AYNI: kullanıcı aynı kelimeyi iki
        // ekranda görür ve bunun aynı iş olduğunu anlar.
        const go = within(next).getByRole('button', { name: 'Add your location' });
        await user.click(go);
        expect(onContinue).toHaveBeenCalledTimes(1);

        // Kapatma anahtarı ekran-içi kipten AYRIDIR: biri kapanınca diğeri kalır.
        expect(firstRunHintStorageKey(7, 'location', 'next')).not.toBe(
            firstRunHintStorageKey(7, 'location'),
        );
    });
});
