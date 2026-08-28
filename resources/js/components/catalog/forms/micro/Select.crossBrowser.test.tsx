import { describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { Select } from './Select';

/**
 * Açılır listenin TARAYICIDAN BAĞIMSIZ olması — `docs/72`.
 *
 * `<select>`'in açılan paneli işletim sistemi tarafından çizilir: Chrome
 * macOS'ta kendi koyu panelini, Safari macOS'un yerli menüsünü gösterir.
 * İkisi farklı görünür ve CSS ile eşitlenemez, çünkü panel sayfanın DOM'unda
 * değildir.
 *
 * Bu testler panelin BİZİM DOM'umuzda olduğunu donduruyor.
 */
/**
 * Panel içinde ara.
 *
 * Seçenek metni İKİ yerde bulunur: yerli `<option>` ve bizim panelimiz.
 * Kapsamsız bir arama ikisini birden bulur ve testin hangisini ölçtüğü
 * belirsizleşir.
 */
function panel(): HTMLElement {
    const element = document.querySelector('ul[aria-hidden="true"]');

    if (element === null) {
        throw new Error('panel açık değil');
    }

    return element as HTMLElement;
}

function renderSelect(onChange = vi.fn(), value = 'tr') {
    render(
        <Select id="country" aria-label="Country" value={value} onChange={onChange}>
            <option value="tr">Türkiye</option>
            <option value="de">Germany</option>
            <option value="fr" disabled>
                France
            </option>
        </Select>,
    );

    return onChange;
}

describe('açılır liste tarayıcıya bırakılmaz', () => {
    it('yerli panelin açılmasını engeller', () => {
        renderSelect();

        const select = screen.getByLabelText('Country');
        const event = fireEvent.mouseDown(select);

        /*
            `fireEvent` engellenen olayda `false` döner. Yerli panel yalnız
            `mousedown` varsayılanı korunduğunda açılır; engellemek, panelin
            işletim sistemine gitmesini durdurmanın üç tarayıcıda da çalışan
            yoludur.
        */
        expect(event).toBe(false);
    });

    it('panel sayfanın kendi DOM ağacında çizilir', () => {
        renderSelect();

        fireEvent.mouseDown(screen.getByLabelText('Country'));

        const panel = document.querySelector('ul[aria-hidden="true"]');
        expect(panel).not.toBeNull();
        expect(within(panel as HTMLElement).getByText('Germany')).toBeInTheDocument();
    });

    /**
     * Erişilebilir kontrol `<select>`'in KENDİSİDİR: ekran okuyucu onun yerli
     * listesini duyar. Panelimiz aynı seçenekleri ikinci kez duyursaydı
     * kullanıcı iki listeyle baş başa kalırdı.
     */
    it('paneli ekran okuyucuya ikinci bir liste olarak sunmaz', () => {
        renderSelect();
        fireEvent.mouseDown(screen.getByLabelText('Country'));

        expect(screen.queryAllByRole('listbox')).toHaveLength(0);
        expect(document.querySelector('ul[aria-hidden="true"]')).not.toBeNull();
    });

    it('seçenek tıklanınca değer değişir ve onChange yayılır', async () => {
        const user = userEvent.setup();

        /*
            Değer ÇAĞRI ANINDA yakalanır.

            İlk hâli `onChange.mock.calls[0][0].target.value` okuyordu ve
            'tr' görüyordu: `target` canlı bir DOM düğümü ve kontrollü bir
            `select` tıklamadan sonra React tarafından kayıtlı değere geri
            çekiliyor. Test, olayın taşıdığı değeri değil, olaydan SONRAKİ
            durumu ölçüyordu.
        */
        const seen: string[] = [];
        const onChange = vi.fn((event: { target: HTMLSelectElement }) => {
            seen.push(event.target.value);
        });

        renderSelect(onChange);

        fireEvent.mouseDown(screen.getByLabelText('Country'));
        await user.click(within(panel()).getByText('Germany'));

        expect(seen).toEqual(['de']);
    });

    it('devre dışı seçenek seçilemez', () => {
        renderSelect();
        fireEvent.mouseDown(screen.getByLabelText('Country'));

        expect(within(panel()).getByText('France').closest('button')).toBeDisabled();
    });

    /** Seçili satır RENKLE değil işaretle ayrılır (WCAG 1.4.1). */
    it('seçili satırı renk dışı bir kanalda işaretler', () => {
        renderSelect(vi.fn(), 'de');
        fireEvent.mouseDown(screen.getByLabelText('Country'));

        const chosen = within(panel()).getByText('Germany').closest('button');
        const other = within(panel()).getByText('Türkiye').closest('button');

        expect(chosen?.textContent).not.toBe(other?.textContent);
    });

    it('Escape panelini kapatır', () => {
        renderSelect();
        fireEvent.mouseDown(screen.getByLabelText('Country'));
        expect(document.querySelector('ul[aria-hidden="true"]')).not.toBeNull();

        fireEvent.keyDown(document, { key: 'Escape' });
        expect(document.querySelector('ul[aria-hidden="true"]')).toBeNull();
    });

    /**
     * KLAVYE tarayıcıdan gelir ve taklit edilmez: ok tuşları, Home/End ve
     * harfle arama `<select>`'in kendi davranışıdır. Panel yalnız işaretçi
     * için açılır.
     */
    it('klavyeyle değer değiştirmek paneli açmaz', async () => {
        const user = userEvent.setup();
        renderSelect();

        await user.tab();
        await user.keyboard('{ArrowDown}');

        expect(document.querySelector('ul[aria-hidden="true"]')).toBeNull();
    });
});
