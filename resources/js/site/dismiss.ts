/**
 * AÇILIR MENÜYÜ KAPATMANIN YOLLARI — `SITE-DISMISS-01`.
 *
 * ── ÖLÇÜLEN KUSUR ──
 *
 * Sahibin sözü: *"menu, tıklayınca açılıyor, ama boşa tıklayınca
 * kapanmıyor, kapansın."*
 *
 * `<details>` bir açılır bölme için doğru tabandır — betiksiz açılır,
 * klavyeyle çalışır, ekran okuyucuya durumunu kendisi söyler. Ama
 * tarayıcının bu davranışı bir tek şeyi HİÇ görmez: menünün DIŞINA yapılan
 * hareketi. Kapanmanın tek yolu `<summary>`ye ikinci kez basmaktır; onu
 * bilmeyen (ya da parmağıyla panelin dışına basıp bir şey olmadığını gören)
 * kullanıcı için menü, altındaki sayfayı örten ve kapatılamayan bir kapak
 * hâline gelir.
 *
 * ── NEDEN İLERLEYİCİ ZENGİNLEŞTİRME ──
 *
 * Bu dosya menüyü AÇMAZ ve menünün açılmasını üstlenmez. Açma-kapama hâlâ
 * tarayıcının; buraya eklenen şey yalnız KAPANMA YOLLARIDIR. Betik hiç
 * yüklenmezse — ağ hatası, kapatılmış JavaScript, eski tarayıcı — menü
 * açılmaya ve `<summary>` ile kapanmaya devam eder. Bir `click` dinleyicisi
 * üstünden yeniden yazılmış bir menü, betiği gelmeyen ziyaretçide HİÇ
 * açılmazdı.
 *
 * Aynı gerekçeyle burada React yok: kurumsal sayfalar React paketini hiç
 * yüklemiyor (`HOME-NO-REACT-05`) ve bir öznitelik silmek için bir bileşen
 * ağacı taşımak, hiçbir şey karşılığında ödenen bir bedel olurdu.
 *
 * ── NEDEN HAREKET KAPISININ DIŞINDA ──
 *
 * Bu dosya `mountScene`in içinde DEĞİL, yanında çağrılır. Sahne motoru
 * `prefers-reduced-motion` isteyen ziyaretçide hiç başlamaz; kapanma ise
 * bir süs değil, bir ÇIKIŞ YOLUDUR. Hareketi istemeyen birinin menüyü
 * kapatamaması, kuralı doğru uygulamış olmanın yarattığı bir arıza olurdu.
 */

/**
 * KAPSAM — ÜSTÜ ÖRTEN AÇILIR MENÜLER, HEPSİ BU.
 *
 * Sitede başka `<details>`ler var: altbilgideki katlanabilir bağlantı
 * grupları ve içerik sayfalarındaki SSS akordeonları. Onları kullanıcı
 * BİLEREK açar, akışın içinde okur ve okurken sayfanın başka bir yerine
 * tıklaması olağandır — dışarı tıklamada kapanmaları, okuduğu şeyin elinden
 * alınması olurdu.
 *
 * Ayrım bir VERİ ÖZNİTELİĞİYLE çiziliyor, sınıf adıyla değil: sınıf adı
 * görünüşe aittir ve bir gün yeniden adlandırılır; o gün bu davranış ya
 * sessizce kaybolur ya da hiç istenmediği bir yere sızar. Öznitelik ise tek
 * bir şey söyler ve o şeyi söylemek için durur.
 */
const SCOPE = 'details[data-dismiss-on-outside]';

export function bindDismiss(root: Document): () => void {
    /*
        HİÇ MENÜ YOKSA HİÇ DİNLEYİCİ YOK.

        Yasal metinler ve yardım makaleleri bu betiği de indirir. Orada üç
        belge dinleyicisi açık bırakmak, hiç kimsenin görmediği bir iş için
        her basışta ve her sekme hareketinde iş yapmaktır.
    */
    if (root.querySelector(SCOPE) === null) {
        return () => {};
    }

    const opened = () => root.querySelectorAll<HTMLDetailsElement>(`${SCOPE}[open]`);

    /*
        `pointerdown` — `click` DEĞİL, `mousedown` DEĞİL.

        `click` yalnız parmak ya da düğme KALKINCA doğar: kullanıcı bastığı
        hâlde menü bir an daha açık kalır ve o an, "bastım ama olmadı"
        duygusunun doğduğu andır. `mousedown` ise dokunmada gecikmeli ve
        güvenilmez bir taklittir — dokunmalı bir cihazda `hover` da yoktur
        (`TOUCH-FIRST-INTERFACE` §2), yani imleç olaylarına bağlanan bir
        kapanma yolu orada HİÇ var olmaz. `pointerdown` iki giriş kipinde de,
        basıldığı anda doğar.

        Yakalama evresinde dinleniyor: panelin içindeki bir bileşen olayın
        kabarmasını durdursa bile kapanma yolu ayakta kalır. `passive`, çünkü
        burada hiçbir varsayılan davranış engellenmiyor — menüyü kapatmak,
        altındaki bağlantıya yapılan basışı yutmaz.
    */
    const onPointerDown = (event: Event) => {
        const target = event.target as Node | null;

        for (const menu of opened()) {
            if (target === null || !menu.contains(target)) {
                menu.open = false;
            }
        }
    };

    /*
        ESCAPE — VE ODAK GERİ DÖNER.

        Kapatmak yetmez: odak, kaybolan panelin içindeyse hiçbir yere düşmemeli.
        `<body>`ye düşen odak, klavye kullanan birini sayfanın BAŞINA atar ve
        menüye kadar olan bütün yolu yeniden yürütür. Odak, menüyü açan
        düğmeye — `<summary>`ye — döner: kullanıcı bıraktığı yerde kalır.

        `:scope >` ile YALNIZ menünün kendi düğmesi seçiliyor; içeride başka
        bir `<details>` varsa onun `<summary>`si bu menünün düğmesi değildir.
    */
    const onKeyDown = (event: KeyboardEvent) => {
        if (event.key !== 'Escape') {
            return;
        }

        for (const menu of opened()) {
            menu.open = false;
            menu.querySelector<HTMLElement>(':scope > summary')?.focus();
        }
    };

    /*
        ODAK MENÜDEN ÇIKTIYSA MENÜ DE ÇIKAR.

        Sekmeyle gezen biri menünün son bağlantısını geçtiğinde odak sayfanın
        gövdesine ilerler; menü arkasında açık kalırsa, artık içinde olmadığı
        bir katmanı örtüyor olur.

        `relatedTarget` boşsa KAPANMIYOR: odak o zaman sayfadan değil,
        PENCEREDEN çıkmıştır (başka bir sekmeye ya da uygulamaya geçiş).
        Orada kapatmak, kullanıcı geri döndüğünde menüsünü kaybetmiş olması
        demekti — ve bunu yapan hiçbir şeye dokunmamıştır.
    */
    const onFocusOut = (event: FocusEvent) => {
        const next = event.relatedTarget as Node | null;

        if (next === null) {
            return;
        }

        const menu = (event.target as Element).closest<HTMLDetailsElement>(SCOPE);

        if (menu !== null && !menu.contains(next)) {
            menu.open = false;
        }
    };

    /*
        AYNI ANDA TEK MENÜ.

        Bugün üst çubukta bir tane var; yarın ikincisi eklendiğinde ikisinin
        birden açık durması, birinin ötekini örtmesi demek olurdu. Kural
        şimdiden burada, çünkü ikinci menüyü ekleyen kişinin bu dosyayı
        hatırlaması gerekmemeli.

        `toggle` KABARMAZ; bu yüzden yakalama evresinde dinleniyor — olay
        hedefe İNERKEN belge onu görür. Böylece tek bir el, sonradan eklenen
        menüleri de kapsar; her menüye ayrı dinleyici bağlamak gerekmez.
    */
    const onToggle = (event: Event) => {
        const menu = event.target as HTMLDetailsElement;

        if (!menu.open || !menu.matches(SCOPE)) {
            return;
        }

        for (const other of opened()) {
            if (other !== menu) {
                other.open = false;
            }
        }
    };

    root.addEventListener('pointerdown', onPointerDown, { capture: true, passive: true });
    root.addEventListener('keydown', onKeyDown);
    root.addEventListener('focusout', onFocusOut);
    root.addEventListener('toggle', onToggle, true);

    return () => {
        root.removeEventListener('pointerdown', onPointerDown, true);
        root.removeEventListener('keydown', onKeyDown);
        root.removeEventListener('focusout', onFocusOut);
        root.removeEventListener('toggle', onToggle, true);
    };
}
