/*
    `shell-scroll-gate.classes.mjs` düz JavaScript, çünkü onu Node doğrudan
    çalıştırıyor (kapı bir derleme adımından geçmiyor — geçseydi, kapının
    kendisi derlemeye bağımlı olurdu ve derlemeyi doğrulayan bir kapı olarak
    değerini kaybederdi).

    Bu bildirim yalnız TypeScript tarafı için: sözleşme testi aynı sabitleri
    içe aktarıyor ve `tsc` tür bilgisi olmadan `any` görüp şikâyet ediyordu.
*/
export const FRAME: string;
export const LAYOUT: string;
export const MAIN: string;
export const RAIL: string;
export const SCROLLER: string;
export const ACCOUNT: string;
export const BOTTOM_NAV: string;
