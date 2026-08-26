/**
 * `po.mjs` düz JavaScript'tir çünkü Node onu derlemesiz çalıştırır — bir
 * script'in çalışması için build adımına ihtiyaç duyması, boru hattının
 * kendisini kırılgan yapardı. Tip bilgisi burada yaşar, böylece testler ve
 * editör yine de tip kontrolünden geçer.
 */
export type PoEntry = {
    msgstr: string;
    fuzzy: boolean;
    references: string[];
};

export function parsePo(source: string): { header: string; entries: Map<string, PoEntry> };

export function formatPo(input: {
    headerFields: Record<string, string>;
    entries: Map<string, PoEntry>;
}): string;

export function compileMo(translations: Record<string, string>): Buffer;
