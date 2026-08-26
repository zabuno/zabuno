/**
 * Yerel (native) form öğeleri için tek stil sahibi — CORE/UI.
 *
 * `TextInput`/`Select` bileşenleri Flowbite tabanlıdır ve kendi sarmalayıcı
 * işaretlemesini getirir. Bazı yüzeyler ise `<input>`/`<select>` öğesini
 * doğrudan kullanmak zorundadır (var olan testler DOM yapısını donduruyor,
 * ya da öğe bir `<label>` içinde kontrolsüz çalışıyor). O yüzeyler eskiden
 * kendi ham palet sınıflarını yazıyordu ve her biri kendi kararını
 * veriyordu.
 *
 * Bu dosya o kararı tek yere toplar. Flowbite teması token köküne
 * bağlandığında bu sabitler kaldırılır ve her yüzey bileşeni kullanır
 * (bkz. `PlainButton` docblock'undaki aynı not).
 */

/** Metin/sayı girdisi ve seçim kutusu. */
export const NATIVE_FIELD_CLASS =
    'w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-fg ' +
    'min-h-[var(--density-hit-area-min)] ' +
    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus ' +
    'disabled:cursor-not-allowed disabled:text-fg-muted disabled:opacity-60';

/** Alanın üstündeki küçük etiket. */
export const NATIVE_LABEL_CLASS = 'flex flex-col gap-1 text-xs font-medium text-fg-secondary';
