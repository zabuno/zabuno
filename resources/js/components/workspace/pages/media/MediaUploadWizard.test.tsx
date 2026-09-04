import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { MediaUploadRegion } from './MediaUploadRegion';

/**
 * YÜKLEME SİHİRBAZI — dört adım (kanonik kaynak:
 * `docs/reference/media-manager/Medya Yonetimi v2.dc.html`, "Yükle" ekranı).
 *
 * Öncesinde bu ekran TEK bir uzun formdu: bırakma alanı, kırpma aracı, alt
 * metin, yer seçimi ve yükle düğmesi alt alta. Telefonda bu, sahibin
 * kaydırarak aradığı beş ayrı karar demekti ve hangi sırayla yapılacağı
 * hiçbir yerde yazmıyordu.
 *
 * Kaynak aynı işi dört adıma bölüyor ve her adımda TEK bir soru soruyor:
 * hangi dosya, ne kadar küçülsün, hangi kare, gönderelim mi. Adım göstergesi
 * bir süs değil: kullanıcı nerede olduğunu ve kaç adım kaldığını görür.
 */
function stubPolicies(slot: { minWidth: number; minHeight: number; aspect: string | null }): void {
    vi.stubGlobal(
        'fetch',
        vi.fn(async () => ({
            ok: true,
            status: 200,
            json: async () => ({
                slots: [{ key: 'itemImage', formats: ['jpeg'], altRequired: true, ...slot }],
                limits: { maxBytes: 31457280, maxMegapixels: 40 },
            }),
        })),
    );
}

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('yükleme sihirbazı — dört adım', () => {
    it('adım göstergesi dört adımı sırayla gösterir ve ilki etkindir', async () => {
        stubPolicies({ minWidth: 1, minHeight: 1, aspect: null });
        render(<MediaUploadRegion onSubmit={vi.fn(async () => {})} />);

        const steps = await screen.findByRole('list', { name: /upload steps/i });
        const labels = within(steps)
            .getAllByRole('button')
            .map((button) => button.textContent?.replace(/^\d+/, '').trim());

        expect(labels).toEqual(['Choose', 'Shrink', 'Frame', 'Send']);
        expect(within(steps).getByRole('button', { name: /choose/i })).toHaveAttribute(
            'aria-current',
            'step',
        );
    });

    it('dosya seçilmeden sonraki adımlara geçilemez', async () => {
        /*
            Sıra keyfî değil: küçültme, kırpma ve gönderme hepsi SEÇİLMİŞ bir
            dosyaya bağlı. Erişilebilir görünüp boş çıkan bir adım, kullanıcıya
            "bir şeyi kaçırdım" dedirtir.
        */
        stubPolicies({ minWidth: 1, minHeight: 1, aspect: null });
        render(<MediaUploadRegion onSubmit={vi.fn(async () => {})} />);

        const steps = await screen.findByRole('list', { name: /upload steps/i });

        expect(within(steps).getByRole('button', { name: /shrink/i })).toBeDisabled();
        expect(within(steps).getByRole('button', { name: /^send$/i })).toBeDisabled();
    });

    it('dosya seçilince adımlar açılır ve sihirbaz küçültme adımına geçer', async () => {
        stubPolicies({ minWidth: 1, minHeight: 1, aspect: null });
        const user = userEvent.setup();
        render(<MediaUploadRegion onSubmit={vi.fn(async () => {})} />);

        const region = screen.getByRole('region', { name: /media upload/i });
        await user.upload(
            within(region).getByLabelText(/choose a file/i) as HTMLInputElement,
            new File(['a'], 'lahmacun.jpg', { type: 'image/jpeg' }),
        );

        const steps = screen.getByRole('list', { name: /upload steps/i });

        await waitFor(() =>
            expect(within(steps).getByRole('button', { name: /shrink/i })).toHaveAttribute(
                'aria-current',
                'step',
            ),
        );
        expect(within(steps).getByRole('button', { name: /frame/i })).toBeEnabled();
    });

    it('Geri ve Devam adımlar arasında gezdirir', async () => {
        stubPolicies({ minWidth: 1, minHeight: 1, aspect: null });
        const user = userEvent.setup();
        render(<MediaUploadRegion onSubmit={vi.fn(async () => {})} />);

        const region = screen.getByRole('region', { name: /media upload/i });
        await user.upload(
            within(region).getByLabelText(/choose a file/i) as HTMLInputElement,
            new File(['a'], 'lahmacun.jpg', { type: 'image/jpeg' }),
        );

        await screen.findByRole('button', { name: /^back$/i });
        await user.click(screen.getByRole('button', { name: /^back$/i }));
        expect(screen.getByRole('list', { name: /upload steps/i })).toBeInTheDocument();
        expect(
            within(screen.getByRole('list', { name: /upload steps/i })).getByRole('button', {
                name: /choose/i,
            }),
        ).toHaveAttribute('aria-current', 'step');

        await user.click(screen.getByRole('button', { name: /^continue$/i }));
        await waitFor(() =>
            expect(
                within(screen.getByRole('list', { name: /upload steps/i })).getByRole('button', {
                    name: /shrink/i,
                }),
            ).toHaveAttribute('aria-current', 'step'),
        );
    });

    it('eksik alan varsa o alanın ADIMINA döner', async () => {
        /*
            Sihirbazın en sinsi arızası budur: "Upload" son adımda, hata ise
            başka bir adımda. Kullanıcı düğmeye basar, hiçbir şey olmaz ve
            neyin eksik olduğunu göremez — çünkü eksik alan ekranda DEĞİLDİR.
        */
        stubPolicies({ minWidth: 1, minHeight: 1, aspect: null });
        const user = userEvent.setup();
        render(<MediaUploadRegion onSubmit={vi.fn(async () => {})} />);

        const region = screen.getByRole('region', { name: /media upload/i });
        await user.upload(
            within(region).getByLabelText(/choose a file/i) as HTMLInputElement,
            new File(['a'], 'lahmacun.jpg', { type: 'image/jpeg' }),
        );

        // Küçültme → çerçeve → gönder, hiçbir alanı doldurmadan.
        await screen.findByRole('button', { name: /^continue$/i });
        await user.click(screen.getByRole('button', { name: /^continue$/i }));
        await user.click(screen.getByRole('button', { name: /^continue$/i }));
        await user.click(screen.getByRole('button', { name: /^upload$/i }));

        // Yer seçimi 3. adımdadır; sihirbaz oraya döner ve hatayı gösterir.
        await waitFor(() =>
            expect(
                within(screen.getByRole('list', { name: /upload steps/i })).getByRole('button', {
                    name: /frame/i,
                }),
            ).toHaveAttribute('aria-current', 'step'),
        );
        expect(screen.getByText(/choose where this image will be used/i)).toBeInTheDocument();
    });
});

describe('yükleme sihirbazı — 1. adım', () => {
    it('fotoğraf çekme ve galeriden seçme ayrı ayrı sunulur', async () => {
        /*
            Kaynağın iki düğmesi ("Fotoğraf çek" / "Galeriden seç") bir görsel
            tercih değil: telefonda `capture` niteliği doğrudan kamerayı açar.
            Tek bir "dosya seç" düğmesi, mutfakta duran sahibi önce galeriye,
            oradan kameraya götürür.
        */
        stubPolicies({ minWidth: 1, minHeight: 1, aspect: null });
        render(<MediaUploadRegion onSubmit={vi.fn(async () => {})} />);

        const camera = (await screen.findByLabelText(/take a photo/i)) as HTMLInputElement;

        expect(camera).toHaveAttribute('capture', 'environment');
        expect(camera).toHaveAttribute('accept', 'image/*');
        expect(screen.getByLabelText(/choose a file/i)).toBeInTheDocument();
    });

    it('desteklenen türler tablosu tür, azami boyut, uzantı ve notu birlikte verir', async () => {
        stubPolicies({ minWidth: 1, minHeight: 1, aspect: null });
        render(<MediaUploadRegion onSubmit={vi.fn(async () => {})} />);

        const table = await screen.findByRole('table', { name: /supported types/i });
        const headers = within(table)
            .getAllByRole('columnheader')
            .map((cell) => cell.textContent);

        expect(headers).toEqual(['Type', 'Largest size', 'Extensions', 'Note']);
        expect(within(table).getByText('Images')).toBeInTheDocument();
        expect(within(table).getByText('.heic')).toBeInTheDocument();
    });

    it('azami boyut SUNUCUNUN sınırıdır, broşürden kopyalanmış bir sayı değil', async () => {
        /*
            Kaynakta "25 MB" yazıyor; bu depoda sunucu 30 MB kabul ediyor.
            Sabit yazılsaydı iki sayı bir gün ayrışırdı ve kullanıcı hangisine
            güveneceğini bilemezdi — üstelik yanlış olan, ekranda duran olurdu.
        */
        stubPolicies({ minWidth: 1, minHeight: 1, aspect: null });
        render(<MediaUploadRegion onSubmit={vi.fn(async () => {})} />);

        const table = await screen.findByRole('table', { name: /supported types/i });

        expect(within(table).getByText('30 MB')).toBeInTheDocument();
    });

    it('YALNIZ gerçekten kabul edilen türler listelenir', async () => {
        /*
            Kaynak dört grup gösteriyor (görsel, video, belge, ses) ama bu
            depoda yükleyici yalnız görsel kabul ediyor. Kabul edilmeyen bir
            türü "desteklenen" diye listelemek, sahibi mutfakta bir MP4 ile
            baş başa bırakır.
        */
        stubPolicies({ minWidth: 1, minHeight: 1, aspect: null });
        render(<MediaUploadRegion onSubmit={vi.fn(async () => {})} />);

        const table = await screen.findByRole('table', { name: /supported types/i });

        expect(within(table).queryByText('Video')).toBeNull();
        expect(within(table).queryByText('Audio')).toBeNull();
    });
});
