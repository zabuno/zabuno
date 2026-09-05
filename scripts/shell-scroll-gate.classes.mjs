/*
    KABUĞUN SINIF SÖZLEŞMESİ — tek kaynak, iki tüketici.

    `scripts/shell-scroll-gate` gerçek Chrome'da DAVRANIŞI ölçüyor ve bunu
    elle kurulmuş bir düzenek üzerinde yapıyor: bileşenleri gerçek tarayıcıda
    render etmek bir derleme adımı ve bir sunucu gerektirirdi.

    Bu kolaylığın bir bedeli var ve o bedel sessizdir: bileşenin sınıfı
    değişir, düzenek eski sınıfla ölçmeye devam eder, kapı yeşil kalır ve
    ölçtüğü şey artık ekranda olan şey değildir. Bu deponun tekrar eden
    kusuru tam olarak budur (`docs/109` §8.7): çalışan ama söylediğini
    ölçmeyen bir kapı.

    Bu dosya o boşluğu kapatıyor: sınıflar BURADA yaşıyor, kapı buradan
    okuyor ve `AdminShell.contract.test.tsx` gerçek bileşenleri render edip
    her sınıfın hâlâ orada olduğunu ölçüyor. Bileşen değişirse jsdom testi
    kırılır ve düzeneğin eskidiği aynı gün görülür.
*/

export const FRAME = 'admin-shell-frame flex flex-col overflow-hidden';
export const LAYOUT = 'admin-shell-layout flex min-h-0 min-w-0 flex-1 flex-wrap';
export const MAIN = 'admin-shell-main min-w-0 flex-1 basis-[32rem] overflow-y-auto';
export const RAIL =
    'admin-shell-sidebar flex shrink-0 grow-0 basis-[16.5rem] flex-col border-e min-h-0';
export const SCROLLER = 'flex min-h-0 flex-1 flex-col overflow-y-auto';
export const ACCOUNT = 'mt-auto border-t';
export const BOTTOM_NAV = 'z-10 grid grid-cols-5 items-stretch border-t';
