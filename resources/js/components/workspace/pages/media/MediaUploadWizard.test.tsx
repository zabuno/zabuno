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
function stubPolicies(slot: {
    minWidth: number;
    minHeight: number;
    aspect: string | null;
    formats?: string[];
}): void {
    const { formats = ['jpeg'], ...geometry } = slot;

    vi.stubGlobal(
        'fetch',
        vi.fn(async () => ({
            ok: true,
            status: 200,
            json: async () => ({
                slots: [{ key: 'itemImage', formats, altRequired: true, ...geometry }],
                /*
                    FF-158: sunucu tek düz bir sayı değil, TÜRE göre sınır
                    bildirir. `maxBytes` mutlak tavandır ve yalnız türü
                    tanınmayan bir dosya için kullanılır.
                */
                limits: {
                    maxBytes: 47185920,
                    maxBytesByKind: { image: 26214400, vector: 2097152, document: 47185920 },
                    maxMegapixels: 40,
                },
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

    it('azami boyut SUNUCUNUN sınırıdır ve TÜRE göredir', async () => {
        /*
            İki ayrı arıza tek testte duruyor.

            Birincisi eskiydi: kaynak broşüründe "25 MB" yazıyor, sunucu
            başka bir sayı uyguluyor. Sabit yazılsaydı yanlış olan, ekranda
            duran olurdu.

            İkincisi FF-158: sunucu artık türe göre sınır uyguluyor. Tek bir
            sayının bütün satırlara dağıtılması, satırların en az birini
            yalan yapardı — ve o satır tam olarak SVG'ninkiydi: temizleyici
            gövdenin tamamını ayrıştırdığı için oradaki sınır bir kolaylık
            değil güvenlik kısıtıdır ve fotoğrafınkinden çok daha dardır.
        */
        stubPolicies({ minWidth: 1, minHeight: 1, aspect: null });
        render(<MediaUploadRegion onSubmit={vi.fn(async () => {})} />);

        const table = await screen.findByRole('table', { name: /supported types/i });

        const sizeOf = (rowName: string): string | null =>
            within(table)
                .getByRole('rowheader', { name: rowName })
                .parentElement!.querySelectorAll('td')[0]!.textContent;

        expect(sizeOf('Images')).toBe('25 MB');
        expect(sizeOf('Vector (SVG)')).toBe('2.0 MB');
    });

    it('ret, HANGİ türün sınırına takıldığını söyler', async () => {
        /*
            FF-158. "Dosya çok büyük" kullanıcıya ne yapacağını söylemez.
            Buradaki SVG 3 MB — fotoğraf sınırının (25 MB) çok altında, ama
            vektörünkinin (2 MB) üstünde. Yalnız "sınır 25 MB" diyen bir ret,
            sahibi dosyasının neden geri geldiğini hiç anlamadan bırakırdı.
        */
        stubPolicies({ minWidth: 1, minHeight: 1, aspect: null, formats: ['svg', 'png'] });
        const user = userEvent.setup();
        render(<MediaUploadRegion onSubmit={vi.fn(async () => {})} />);

        const region = screen.getByRole('region', { name: /media upload/i });
        await user.upload(
            within(region).getByLabelText(/choose a file/i) as HTMLInputElement,
            new File([new Uint8Array(3 * 1024 * 1024)], 'logo.svg', { type: 'image/svg+xml' }),
        );

        await screen.findByRole('button', { name: /^continue$/i });
        await user.click(screen.getByRole('button', { name: /^continue$/i }));
        await user.click(screen.getByRole('button', { name: /^continue$/i }));
        await user.click(screen.getByRole('button', { name: /^upload$/i }));

        expect(
            await screen.findByText(/this file is 3 MB; the limit for SVG files is 2 MB/i),
        ).toBeInTheDocument();
    });

    it('seçilen yerin boyut sınırı, gönderilmeden ÖNCE yazılır', async () => {
        /*
            Gereksinimler listesi en küçük ölçüyü, oranı ve biçimleri
            söylüyordu; boyutu SÖYLEMİYORDU. Bir slot birden çok tür kabul
            edebilir (`logo`: svg + png) ve onların sınırları aynı değildir —
            bu yüzden tek bir sayı değil, her türün kendi sayısı yazılır.
        */
        stubPolicies({ minWidth: 1, minHeight: 1, aspect: null, formats: ['svg', 'png'] });
        const user = userEvent.setup();
        render(<MediaUploadRegion onSubmit={vi.fn(async () => {})} />);

        const region = screen.getByRole('region', { name: /media upload/i });
        await user.upload(
            within(region).getByLabelText(/choose a file/i) as HTMLInputElement,
            new File(['a'], 'logo.png', { type: 'image/png' }),
        );

        await screen.findByRole('button', { name: /^continue$/i });
        await user.click(screen.getByRole('button', { name: /^continue$/i }));
        await user.selectOptions(await screen.findByLabelText(/where will/i), 'itemImage');

        expect(
            await screen.findByText(/largest file size: SVG files 2\.0 MB · images 25 MB/i),
        ).toBeInTheDocument();
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
