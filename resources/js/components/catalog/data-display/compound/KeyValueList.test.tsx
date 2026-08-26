import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { KeyValueList } from './KeyValueList';

describe('KeyValueList', () => {
    it('renders each entry label and value', () => {
        render(
            <KeyValueList
                entries={[
                    { key: 'name', label: 'Name', value: 'Ada Lovelace' },
                    { key: 'role', label: 'Role', value: 'Owner' },
                ]}
            />,
        );
        expect(screen.getByText('Name')).toBeInTheDocument();
        expect(screen.getByText('Ada Lovelace')).toBeInTheDocument();
        expect(screen.getByText('Role')).toBeInTheDocument();
        expect(screen.getByText('Owner')).toBeInTheDocument();
    });

    // Niyet değişmedi: girişler arasında görsel ayrım var, ilkinden ÖNCE yok.
    // Değişen, ayrımın nasıl yapıldığı. Eskiden araya `role="separator"` taşıyan
    // bir Divider konuyordu; axe bunu `definition-list` ihlali olarak bildirdi:
    // `<dl>` rol taşıyan hiçbir doğrudan çocuğu kabul etmez. Ayrım artık grup
    // `div`'inin üst kenarlığıyla yapılır, bu yüzden test kenarlığı doğrular.
    it('separates entries with a top border on every row after the first', () => {
        const { container } = render(
            <KeyValueList
                entries={[
                    { key: 'a', label: 'A', value: '1' },
                    { key: 'b', label: 'B', value: '2' },
                    { key: 'c', label: 'C', value: '3' },
                ]}
            />,
        );

        const rows = Array.from(container.querySelectorAll('dl > div'));

        expect(rows).toHaveLength(3);
        expect(rows[0].className).not.toMatch(/border-t/);
        expect(rows[1].className).toMatch(/border-t/);
        expect(rows[2].className).toMatch(/border-t/);
    });

    it('renders no separation for a single entry', () => {
        const { container } = render(
            <KeyValueList entries={[{ key: 'a', label: 'A', value: '1' }]} />,
        );

        const rows = Array.from(container.querySelectorAll('dl > div'));

        expect(rows).toHaveLength(1);
        expect(rows[0].className).not.toMatch(/border-t/);
    });

    // Kusurun kendisini dondurur: `<dl>` yalnız `dt`/`dd` veya bunları
    // doğrudan saran `div` içerebilir. Rol taşıyan bir çocuk geri gelirse
    // bu test, axe kapısından önce ve daha net bir mesajla kırılır.
    it('keeps the dl child grammar valid — no role-bearing direct children', () => {
        const { container } = render(
            <KeyValueList
                entries={[
                    { key: 'a', label: 'A', value: '1' },
                    { key: 'b', label: 'B', value: '2' },
                ]}
            />,
        );

        const invalid = Array.from(container.querySelectorAll('dl > *')).filter(
            (child) => child.hasAttribute('role') || child.tagName.toLowerCase() !== 'div',
        );

        expect(invalid.map((el) => el.tagName.toLowerCase())).toEqual([]);
    });
});
