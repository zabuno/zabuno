import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';

import { PublicationHistoryRegion } from './PublicationHistoryRegion';

/**
 * SÜRÜM GEÇMİŞİNİN AEP RİTMİ — kanonik teslim paketi (`DESIGN_SPEC.md` §9
 * "Sürümler" ve "Kart grameri": TEK kart, içinde İNCE AYRAÇLI satırlar).
 *
 * Restoran sahibinin yolculuğu: yanlış fiyat listesi yayında, misafir masada
 * ve sahibin cevaplaması gereken soru tek bir cümle — "hangi sürüme
 * döneceğim?". Bu soru bir KARŞILAŞTIRMA sorusudur: dört sürüm alt alta,
 * aynı hizada, aynı ritimde okunmalıdır.
 *
 * Önceki hâl bunu yapamıyordu: satırlar `gap` ile birbirinden ayrılmış
 * bağımsız kutulardı, aralarında çizgi yoktu, yükseklikleri içeriğe göre
 * oynuyordu. Göz her satırda "bu neydi?" diye yeniden başlıyor; dört sürüm
 * dört ayrı duyuru gibi okunuyordu.
 *
 * Paketin düzeni bir LİSTEDİR ve ritmin kaynağı YOĞUNLUK jetonlarıdır
 * (`--density-row-height`, `--density-padding-inline`). Sahip Ayarlar'dan
 * "Sıkı / Standart / Ferah" seçtiğinde bu liste de onunla değişir; elle
 * yazılmış bir `py-2`, yoğunluk anahtarını sağır bırakır.
 *
 * Uygulanmış örnek: `team/TeamMemberList.tsx`.
 */
function jsonResponse(status: number, body: unknown): Response {
    return {
        ok: status >= 200 && status < 300,
        status,
        headers: new Headers(),
        json: async () => body,
    } as Response;
}

const HISTORY = [
    { id: 92, version: 2, state: 'published', publishedAt: '2026-08-28T18:00:00Z', isLive: true },
    { id: 91, version: 1, state: 'published', publishedAt: '2026-08-27T18:00:00Z', isLive: false },
];

function mount() {
    vi.stubGlobal(
        'fetch',
        vi.fn(async (url: string) => {
            if (String(url) === '/sanctum/csrf-cookie') return jsonResponse(204, {});

            return jsonResponse(200, { data: HISTORY });
        }),
    );

    render(
        <PublicationHistoryRegion
            workspaceId={7}
            menuId={42}
            refreshToken={0}
            onRestored={() => {}}
        />,
    );
}

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('PublicationHistoryRegion — sürüm satırı kart değildir', () => {
    it('satırlar ÜSTTEN ayraçlıdır ve ilk satırda ayraç yoktur', async () => {
        /*
            Ayraç ÜSTE konur, alta değil. Alt ayraçlı bir listede son satırın
            ayracını ayrıca susturmak gerekir; o susturma unutulduğunda
            listenin altında kartın kendi kenarlığıyla çakışan ikinci bir
            çizgi belirir. Üstten ayraç, listeye eklenen HER yeni sürümü
            kendiliğinden doğru çizer — düzeltilecek bir istisna kalmaz.
        */
        mount();

        const row = (await screen.findByText('Version 2')).closest('li');

        expect(row).toHaveClass('border-t');
        expect(row).toHaveClass('first:border-t-0');
        expect(row).not.toHaveClass('border-b');
    });

    it('satır yüksekliği ve yatay dolgusu yoğunluk jetonundan gelir', async () => {
        mount();

        const row = (await screen.findByText('Version 2')).closest('li');

        expect(row).toHaveClass('min-h-[var(--density-row-height)]');
        expect(row).toHaveClass('px-[var(--density-padding-inline)]');
    });

    it('satırlar arasında boşluk YOKTUR: liste tek bir kartın içidir', async () => {
        /*
            `gap` ile ayrılmış satırlar, aralarındaki boşluk yüzünden ayrı
            kutular gibi okunur. Kart grameri tam tersini söyler: dış çerçeve
            bir kez çizilir, içerideki ayrım 1 piksellik çizgidir.
        */
        mount();

        const list = (await screen.findByText('Version 2')).closest('ul');

        expect(list?.className ?? '').not.toMatch(/(^|\s)gap-/);
    });

    it('liste tek bir kartın içindedir', async () => {
        mount();

        const card = (await screen.findByText('Version 2')).closest('[data-publication-history]');

        expect(card).not.toBeNull();
        expect(card).toHaveClass('border');
        expect(card).toHaveClass('bg-surface');
        expect(card).toHaveClass('rounded-[var(--radius-lg)]');
    });
});

describe('PublicationHistoryRegion — sayılar hizalanır, rozet hap biçimlidir', () => {
    it('zaman damgası tabular-nums taşır', async () => {
        /*
            Zaman damgaları ALT ALTA okunur ve orantılı rakamlarda haneler
            kayar: "27" ile "28" farklı genişlikte çizilir, sütun titrer.
            `tabular-nums` sabit genişlikli rakam verir; göz tek bir dikey
            çizgiyi takip eder.
        */
        mount();

        const stamp = await screen.findByText('2026-08-28T18:00:00Z');

        expect(stamp).toHaveClass('tabular-nums');
        // Zaman damgası, `text-meta`nın MEŞRU tek kullanımıdır.
        expect(stamp).toHaveClass('text-meta');
    });

    it('sürüm numarası tabular-nums ile ve 700 ağırlıkta yazılır', async () => {
        mount();

        const version = await screen.findByText('Version 2');

        expect(version).toHaveClass('tabular-nums');
        // AEP ağırlık merdiveni 400/500/700. 600 (`font-semibold`) o
        // merdivende YOK ve tarayıcı tarafından sentezleniyordu.
        expect(version).toHaveClass('font-bold');
        expect(version.className).not.toMatch(/font-semibold/);
    });

    it('"Yayında" rozeti hap biçimlidir ve renkten başka bir kanal da taşır', async () => {
        /*
            Rozet, `rounded-full` değil `rounded-pill` kullanır: hap bir
            YARIÇAP değil bir BİÇİM kararıdır ve külliyat onu kendi jetonuyla
            yayınlar (`--radius-pill`).

            Rozet ayrıca yalnız renkle konuşmaz (WCAG 1.4.1): "Live" kelimesi
            metin olarak orada durur ve zemin dolgusu ikinci kanaldır.
        */
        mount();

        const badge = await screen.findByText('Live');

        expect(badge).toHaveClass('rounded-pill');
        expect(badge.className).not.toMatch(/rounded-full/);
        expect(badge).toHaveClass('bg-surface-success');
    });

    it('hiçbir yerde büyük harfe çevirme yoktur', async () => {
        mount();

        const card = (await screen.findByText('Version 2')).closest('[data-publication-history]');
        const classLists: string[] = [];
        card?.querySelectorAll<HTMLElement>('*').forEach((el) => {
            if (typeof el.className === 'string') classLists.push(el.className);
        });

        // Cümle düzeni: paket başlıkları da rozetleri de büyük harfe
        // çevirmez. Türkçede `uppercase` ayrıca "i" harfini bozar.
        expect(classLists.filter((list) => /uppercase/.test(list))).toEqual([]);
    });
});
