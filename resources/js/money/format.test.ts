import { describe, expect, it } from 'vitest';

import { formatMoney, formatMoneyOr } from './format';

/**
 * CORE-12 — frontend para biçimlendirmesi.
 *
 * Requirement ID'leri: MONEY-FE-DIGITS-06, MONEY-FE-UNKNOWN-07.
 */
describe('formatMoney', () => {
    // --- MONEY-FE-DIGITS-06 ----------------------------------------------
    it('does not divide a zero-decimal currency by a hundred', () => {
        // 1499 minor JPY = ¥1,499 — ¥14.99 değil.
        expect(formatMoney(1499, 'JPY', 'en')).toContain('1,499');
        expect(formatMoney(1499, 'JPY', 'en')).not.toContain('14.99');
    });

    it('divides a three-decimal currency by a thousand', () => {
        expect(formatMoney(1499, 'KWD', 'en')).toContain('1.499');
    });

    it('keeps the cents of a two-decimal currency', () => {
        expect(formatMoney(149900, 'USD', 'en')).toContain('1,499.00');
    });

    it('follows the reader locale for separators', () => {
        expect(formatMoney(149900, 'TRY', 'tr')).toContain('1.499,00');
        expect(formatMoney(149900, 'USD', 'en')).toContain('1,499.00');
    });

    // --- MONEY-FE-UNKNOWN-07 ---------------------------------------------
    it('returns null for an unknown currency instead of inventing an amount', () => {
        expect(formatMoney(1499, 'XYZ', 'en')).toBeNull();
    });

    it('returns null rather than rendering NaN', () => {
        expect(formatMoney(Number.NaN, 'TRY', 'tr')).toBeNull();
    });

    it('lets the caller supply its own words for a missing price', () => {
        expect(formatMoneyOr(1499, 'XYZ', 'Price unavailable', 'en')).toBe('Price unavailable');
    });
});
