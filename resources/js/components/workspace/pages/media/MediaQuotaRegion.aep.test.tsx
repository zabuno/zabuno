import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';

import { MediaQuotaRegion } from './MediaQuotaRegion';

/**
 * KOTA ŞERİDİNİN AEP GRAMERİ — kanonik teslim paketi (`DESIGN_SPEC.md` §7):
 * "Başlık + kota şeridi: '{n} / 200 dosya · {x} MB / 2 GB · {m} / ∞ bu ay ·
 * Çöp 30 gün saklanır · {plan}'".
 *
 * Restoran sahibinin yolculuğu: telefondan on fotoğraf yükleyip on birincide
 * "yer kalmadı" duvarına toslamak istemiyor. Bu şerit onun tek bakışta
 * okuduğu göstergedir — bir kapı değil, bir gösterge. O yüzden şerit TEK bir
 * kutudur ve içindeki üç sayaç aynı kutunun içinde yan yana durur; üç ayrı
 * kart üç ayrı duyuru gibi okunurdu.
 *
 * Sayaçların üçü de RAKAMDIR ve yükleme ilerledikçe DEĞİŞİR. Orantılı
 * rakamlarda "48" ile "49" farklı genişlikte çizilir; her yükleme bittiğinde
 * şerit yatayda titrer. `tabular-nums` sabit genişlikli rakam verir.
 *
 * Ölçek disiplini: `text-meta` yalnız zaman damgası ve sayaç içindir. Kutunun
 * ETİKETLERİ ("Depolama", "Görseller") gövde metnidir ve `text-body` taşır —
 * `app.css` bunu token yorumunda açıkça söylüyor.
 */
const QUOTA = {
    planCode: 'starter',
    planLabel: 'Starter',
    originalBytesUsed: 15 * 1048576,
    originalBytesLimit: 200 * 1048576,
    assetsUsed: 3,
    assetsLimit: 100,
    monthlyUploadsUsed: 4,
    monthlyUploadsLimit: 100,
    trashRetentionDays: 30,
    blockedReason: null,
};

function mount() {
    vi.stubGlobal(
        'fetch',
        vi.fn(async () => ({
            ok: true,
            status: 200,
            json: async () => ({ quota: QUOTA }),
        })),
    );

    render(<MediaQuotaRegion workspaceId={4} />);
}

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('MediaQuotaRegion — şerit tek karttır, sayılar hizalanır', () => {
    it('şerit paketin kart yarıçapını ve yüzeyini taşır', async () => {
        mount();

        const strip = (await screen.findByText('Plan: Starter')).closest('section');

        expect(strip).toHaveClass('rounded-[var(--radius-lg)]');
        expect(strip).toHaveClass('border');
        expect(strip).toHaveClass('bg-surface');
    });

    it('sayaç değerleri tabular-nums ile yazılır', async () => {
        mount();

        const ratio = await screen.findByText('15 MB of 200 MB');

        expect(ratio).toHaveClass('tabular-nums');
        // Sayaç, `text-meta`nın MEŞRU kullanımıdır.
        expect(ratio).toHaveClass('text-meta');
    });

    it('sayaç etiketi gövde metnidir, meta değildir', async () => {
        /*
            "Originals" bir zaman damgası ya da sayaç değil, sayacın ADIDIR.
            Meta rolüne düşürülmüş bir etiket, ölçek bir gün 14px'e dönerse
            okunamaz hâle gelir; rol adı bu kararı bugünden doğru bağlar.
        */
        mount();

        const label = await screen.findByText('Originals');

        expect(label).toHaveClass('text-body');
        expect(label.className).not.toMatch(/text-meta/);
    });

    it('ağırlık merdiveni 400/500/700 ile sınırlıdır ve büyük harfe çevirme yoktur', async () => {
        /*
            600 (`font-semibold`) AEP merdiveninde YOKTUR; tarayıcı onu
            sentezler ve harf kenarları bulanıklaşır. `uppercase` ise Türkçede
            "i" harfini bozar — "İşletme" yerine "ISLETME".
        */
        mount();

        const strip = (await screen.findByText('Plan: Starter')).closest('section');
        const classLists: string[] = [strip?.className ?? ''];
        strip?.querySelectorAll<HTMLElement>('*').forEach((element) => {
            if (typeof element.className === 'string') classLists.push(element.className);
        });

        expect(classLists.filter((list) => /font-semibold/.test(list))).toEqual([]);
        expect(classLists.filter((list) => /uppercase/.test(list))).toEqual([]);
        expect(classLists.filter((list) => /rounded-full/.test(list))).toEqual([]);
    });
});
