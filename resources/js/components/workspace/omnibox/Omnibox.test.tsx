import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { Omnibox, type OmniboxGroup } from './Omnibox';

/**
 * Omnibox GÖRSEL SÖZLEŞMESİ — AEP teslim paketi ("Restoran Paneli v2").
 *
 * Bu dosya davranışı değil, KOMUT ÇUBUĞUNUN DİLİNİ donduruyor. Sebep somut:
 * paneldeki her yüzey aynı sistemden çizilmediğinde kullanıcı iki farklı
 * ürün görür. Referans paket üç şeyi açıkça söylüyor ve üçü de burada
 * kapıya bağlanıyor:
 *
 *   1. Kısa hedefler HAP kontrol olur (`rounded-pill`), uzun kayıtlar İNCE
 *      AYRAÇLI satır olur — kart değil. Kart, her sonucu ayrı bir nesne gibi
 *      gösterip listeyi ikinci bir ekrana çevirir.
 *   2. Hiyerarşi büyük harfle değil AĞIRLIK ve RENKLE kurulur; ağırlık
 *      ölçeği 400/500/700'dür, 600 (`font-semibold`) yoktur.
 *   3. Ölçüler jetondan gelir: ham piksel, ham renk ve sabit değerli
 *      `rounded-full` yasaktır; kontrol yüksekliği `--control-height`.
 *
 * DÜRÜSTLÜK NOTU: bu kutuda AI grubu YOKTUR ve bu bir eksiklik değil bir
 * karardır — bağlı bir sağlayıcı yokken AI girişi hiç çizilmez (`docs/50`
 * §17). Aşağıdaki kapı, sahte bir "AI ile yap" satırının sessizce geri
 * gelmesini de engelliyor.
 */

/*
    Kapı YORUMLARI okumaz. Bir kuralın NEDEN var olduğunu anlatmak için
    yasaklı sınıfın adını yazmak gerekir ("`rounded-full` yerine
    `rounded-pill`"); yorumu da tarayan bir kapı, kendi gerekçesini ihlal
    sayardı ve gerekçeyi silmeye zorlardı.
*/
const SOURCE = readFileSync(
    path.resolve(path.dirname(fileURLToPath(import.meta.url)), 'Omnibox.tsx'),
    'utf8',
)
    .replace(/\/\*[\s\S]*?\*\//g, '')
    .replace(/^\s*\/\/.*$/gm, '');

const GROUPS: OmniboxGroup[] = [
    {
        key: 'goto',
        label: 'Git',
        entries: [
            { key: 'goto-menu', label: 'Menüler', onSelect: vi.fn() },
            { key: 'goto-team', label: 'Ekip', onSelect: vi.fn() },
        ],
    },
    {
        key: 'create',
        label: 'Oluştur',
        entries: [{ key: 'create-location', label: 'Şube', onSelect: vi.fn() }],
    },
    {
        key: 'records',
        label: 'Bu çalışma alanında',
        entries: [
            {
                key: 'item-900',
                label: 'Menemen',
                detail: 'Kahvaltı · Menü',
                onSelect: vi.fn(),
            },
            {
                key: 'item-901',
                label: 'Mercimek çorbası',
                detail: 'Çorbalar · Menü',
                onSelect: vi.fn(),
            },
        ],
    },
];

function renderOmnibox() {
    return render(
        <Omnibox
            open
            onClose={vi.fn()}
            workspaceName="Paşa Döner"
            locationDisplayName="Kadıköy"
            groups={GROUPS}
        />,
    );
}

describe('Omnibox — AEP komut çubuğu dili', () => {
    /**
     * KAPSAM TEK SATIRDIR. Referans paket kapsamı "Paşa Döner · Kadıköy"
     * biçiminde tek bir soluk satırda yazıyor; iki ayrı paragraf, panelin
     * en üstünde iki başlık gibi okunup asıl işi (yazmayı) aşağı itiyordu.
     *
     * Çalışma alanı ve şube AYRI ögelerde kalır: kapsamı okuyan başka bir
     * kapı (`WorkspaceApp.omnibox.test`) ikisini ayrı ayrı arıyor ve tek bir
     * birleşik metin o kapıyı sessizce kırardı.
     */
    it('kapsamı tek satırda, ayraçla ayrılmış iki ögede gösterir', () => {
        renderOmnibox();

        const scope = screen.getByTestId('omnibox-scope');

        expect(within(scope).getByText('Paşa Döner')).toBeInTheDocument();
        expect(within(scope).getByText('Kadıköy')).toBeInTheDocument();
        // Ayraç okunacak bir bilgi değil, görsel bir işaret: ekran okuyucu
        // "orta nokta" diye seslendirmemeli.
        expect(scope.querySelector('[aria-hidden="true"]')?.textContent).toBe('·');
    });

    /**
     * Şube seçilmemişken ayraç da olmaz: tek başına duran bir "·" işareti
     * eksik bir bilgi vaat eder.
     */
    it('şube yokken ayraç çizilmez', () => {
        render(
            <Omnibox
                open
                onClose={vi.fn()}
                workspaceName="Paşa Döner"
                locationDisplayName={null}
                groups={GROUPS}
            />,
        );

        const scope = screen.getByTestId('omnibox-scope');

        expect(scope.querySelector('[aria-hidden="true"]')).toBeNull();
    });

    /**
     * Arama alanı BÖLÜMLÜ bir kontroldür: büyeç alanın içinde durur.
     * Referanstaki komut çubuğunun ayırt edici işareti bu — kutunun ne işe
     * yaradığı, yazmadan önce görülür.
     */
    it('arama alanı kendi içinde büyeç taşır', () => {
        renderOmnibox();

        // Etiket i18n kataloğundan gelir; testlerde varsayılan dil İngilizce.
        const input = screen.getByLabelText('Search');

        expect(input.parentElement?.querySelector('svg')).not.toBeNull();
    });

    /**
     * KISA HEDEFLER HAP. "Git" ve "Oluştur" maddeleri bir iki sözcüktür;
     * tam genişlikte satır olarak çizildiklerinde panelin yarısı boş kalır
     * ve göz her madde için baştan sona tarar.
     */
    it('git ve oluştur maddelerini hap kontrol olarak çizer', async () => {
        const user = userEvent.setup();
        renderOmnibox();

        for (const name of ['Menüler', 'Şube']) {
            const chip = screen.getByRole('button', { name });

            expect(chip.className).toContain('rounded-pill');
            // Yükseklik jetondan: yoğunluk modu değişince dokunma hedefi de
            // birlikte değişir, elle yazılmış bir piksel takılı kalmaz.
            expect(chip.className).toContain('min-h-[var(--control-height)]');
        }

        // Hap da olsa seçilebilir kalır: biçim, işi değiştirmez.
        await user.click(screen.getByRole('button', { name: 'Menüler' }));
        expect(GROUPS[0].entries[0].onSelect).toHaveBeenCalled();
    });

    /**
     * KAYITLAR İNCE AYRAÇLI SATIR, KART DEĞİL. Her sonucu kenarlıklı bir
     * kutuya koymak, arama sonucunu bir nesne koleksiyonu gibi gösterir;
     * oysa bu liste okunmak için değil, İÇİNDEN BİRİ SEÇİLMEK için var.
     */
    it('kayıt sonuçlarını ince ayraçlı satır olarak çizer', async () => {
        const user = userEvent.setup();
        renderOmnibox();

        // Kayıtlar yalnız ARANDIĞINDA çizilir; boş sorgu ikinci bir liste
        // ekranı üretirdi.
        await user.type(screen.getByLabelText('Search'), 'menemen');

        const row = screen.getByRole('button', { name: /Menemen/ });
        const list = row.closest('ul');

        expect(list?.className).toContain('divide-y');
        expect(row.className).not.toContain('rounded-pill');
        // Satırın kendi kenarlığı yoktur; ayrımı listenin ayracı taşır.
        expect(row.className).not.toMatch(/\bborder\b/);
    });

    /**
     * İkincil satır bir ZAMAN DAMGASI ya da SAYAÇ değildir; kaydın nerede
     * durduğunu söyleyen bir etikettir. Etiket gövde tabanında yazılır,
     * ayrımı RENK taşır (`--text-meta` yalnız gerçekten yardımcı sayısal
     * bilgi içindir).
     */
    it('kayıt ayrıntısını gövde tabanında ve renkle ayırır', async () => {
        const user = userEvent.setup();
        renderOmnibox();

        await user.type(screen.getByLabelText('Search'), 'menemen');

        const detail = screen.getByText('Kahvaltı · Menü');

        expect(detail.className).toContain('text-body');
        expect(detail.className).toContain('text-fg-secondary');
        expect(detail.className).not.toContain('text-meta');
    });

    /**
     * Grup başlığı bir bölüm etiketidir: hiyerarşiyi ağırlık ve renk kurar.
     * Büyük harf Türkçede i/İ eşlemesini tarayıcının dil tahminine bırakır
     * (DS-NO-UPPERCASE-12) ve zaten burada da yasaktır.
     */
    it('grup başlığını 700 ağırlıkla ve gövde tabanında yazar', () => {
        renderOmnibox();

        const heading = screen.getByRole('heading', { name: 'Git' });

        expect(heading.className).toContain('font-bold');
        expect(heading.className).toContain('text-body');
        expect(heading.className).not.toContain('font-semibold');
    });

    /**
     * Kaynak metnin kendisi de bir kapıdır: aşağıdaki yazım biçimleri
     * ekranda gözle ayırt edilemeyecek kadar küçük görünür ama tasarım
     * sisteminin dışına çıkar.
     */
    it('ham değer, 600 ağırlık ve sabit hap sınıfı yazmaz', () => {
        expect(SOURCE).not.toMatch(/\bfont-semibold\b/);
        expect(SOURCE).not.toMatch(/\brounded-full\b/);
        expect(SOURCE).not.toMatch(/\buppercase\b/);
        // Ham hex renk.
        expect(SOURCE).not.toMatch(/#[0-9a-fA-F]{3,8}\b/);
        /*
            `p-[13px]`, `gap-[6px]`, `rounded-[8px]` gibi ham piksel GEOMETRİSİ.

            Odak halkasının `outline-offset-[-2px]`'i bilerek kapsam dışı:
            o bir yerleşim ölçüsü değil, halkanın kendi kalınlığına bağlı
            bir düzeltme ve depoda her yerde aynı yazılıyor.
        */
        expect(SOURCE).not.toMatch(
            /\b(?:p|px|py|pt|pb|ps|pe|m|mx|my|mt|mb|w|h|min-h|max-w|gap|rounded|size)-\[[^\]]*\d+px/,
        );
    });

    /**
     * Bağlı bir sağlayıcı yokken AI girişi HİÇ ÇİZİLMEZ. Sahte bir öneri
     * satırı ya da devre dışı bir "AI ile yap" düğmesi, olmayan bir yeteneği
     * varmış gibi gösterir.
     */
    it('bağlı AI sağlayıcısı yokken AI girişi sunmaz', () => {
        const { container } = renderOmnibox();

        expect(screen.queryByRole('heading', { name: /ai/i })).toBeNull();
        expect(container.querySelectorAll('button[disabled]')).toHaveLength(0);
    });
});
