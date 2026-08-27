/**
 * Derleme zamanında Vite tarafından yerine konan sabit
 * (vite.config.ts → `define`).
 *
 * Sabit olması önemlidir: bu değer çalışma zamanında öğrenilemez. Tarayıcıya
 * inen paketin HANGİ kaynaktan üretildiğini yalnız paketin kendisi taşıyabilir;
 * sonradan sorulabilecek bir yer yoktur.
 */
declare const __ZABUNO_BUILD_REVISION__: string;
