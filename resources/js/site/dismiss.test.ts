import { afterEach, describe, expect, it } from 'vitest';
import { bindDismiss } from './dismiss';

/**
 * AÇILIR MENÜNÜN KAPANMA YOLLARI — `SITE-DISMISS-01`.
 *
 * ── ÖLÇÜLEN KUSUR ──
 *
 * Sahibin sözü: *"menu, tıklayınca açılıyor, ama boşa tıklayınca
 * kapanmıyor, kapansın."* Tarayıcının kendi `<details>` davranışı menüyü
 * AÇAR ama dışarı tıklamayı hiç görmez; menü açık kalır, altındaki sayfayı
 * örter ve kullanıcı onu kapatmanın yolunu ARAMAK zorunda kalır.
 *
 * ── NE ÖLÇÜLÜYOR, NE ÖLÇÜLMÜYOR ──
 *
 * Burası jsdom: DÜZEN HESAPLAMAZ. "Panel gerçekten örtüyor mu", "320
 * pikselde taşıyor mu", "parmak hedefi tutuyor mu" soruları burada
 * SORULAMAZ — cevapları `scripts/mobile-ux-audit` içinde, gerçek Chrome'da.
 *
 * Burada ölçülen şey KARAR MANTIĞI: hangi olay kapatır, hangisi kapatmaz,
 * odak nereye gider ve KAPSAM nerede biter.
 */

/** Üstü örten açılır menü: kapanma yollarını isteyen tek işaret. */
function overlayMenu(): string {
    return `
        <details id="menu" data-dismiss-on-outside>
            <summary id="toggle">Menu</summary>
            <div id="panel"><a id="inside" href="/pricing">Pricing</a></div>
        </details>
    `;
}

/** Menünün DIŞINDA, odaklanabilir bir hedef. */
function outside(): string {
    return '<a id="outside" href="/">Home</a>';
}

function el<T extends HTMLElement>(id: string): T {
    const found = document.getElementById(id);

    if (found === null) {
        throw new Error(`Test kurulumu eksik: #${id} yok.`);
    }

    return found as T;
}

/**
 * `pointerdown` — TIKLAMA DEĞİL.
 *
 * jsdom `PointerEvent`i her sürümde taşımaz; olay adı ve kabarma davranışı
 * bizim için yeterli olduğundan `Event` ile gönderiliyor. Ölçülen şey
 * dinleyicinin hangi olayda ve hangi hedefte karar verdiğidir.
 */
function pointerDown(target: EventTarget): void {
    target.dispatchEvent(new Event('pointerdown', { bubbles: true, composed: true }));
}

function escape(target: EventTarget): void {
    target.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
}

/**
 * DİNLEYİCİLER TESTLER ARASINDA SIZMAZ.
 *
 * `document` bütün testlerde AYNI nesnedir. Bağlanan bir belge dinleyicisi
 * çözülmezse bir sonraki testte de dinlemeye devam eder ve o test, ölçmek
 * istediği şeyi değil bir öncekinin artığını ölçer.
 */
const bound: Array<() => void> = [];

function bind(): () => void {
    const release = bindDismiss(document);
    bound.push(release);

    return release;
}

afterEach(() => {
    while (bound.length > 0) {
        bound.pop()?.();
    }

    document.body.innerHTML = '';
});

describe('dışarı tıklama (SITE-DISMISS-01)', () => {
    it('menünün DIŞINA basınca menü kapanır', () => {
        document.body.innerHTML = overlayMenu() + outside();
        bind();

        const menu = el<HTMLDetailsElement>('menu');
        menu.open = true;

        pointerDown(el('outside'));

        expect(menu.open).toBe(false);
    });

    /*
        BASMAK YETER, BIRAKMAK GEREKMEZ.

        `click` yalnız parmak/düğme KALKINCA doğar. Kapanmayı ona bağlamak,
        kullanıcı bastığı hâlde menünün bir an daha açık kalması demekti.
    */
    it('kapanma `pointerdown` ile olur, `click` beklenmez', () => {
        document.body.innerHTML = overlayMenu() + outside();
        bind();

        const menu = el<HTMLDetailsElement>('menu');
        menu.open = true;

        el('outside').dispatchEvent(new Event('click', { bubbles: true }));
        expect(menu.open).toBe(true);

        pointerDown(el('outside'));
        expect(menu.open).toBe(false);
    });

    it('menünün İÇİNE basmak kapatmaz', () => {
        document.body.innerHTML = overlayMenu() + outside();
        bind();

        const menu = el<HTMLDetailsElement>('menu');
        menu.open = true;

        pointerDown(el('inside'));

        expect(menu.open).toBe(true);
    });

    /*
        DOKUNMADA `hover` YOKTUR (`TOUCH-FIRST-INTERFACE` §2).

        Kapatma yolu bir imleç olayına (`mouseleave`, `mouseout`) bağlansaydı
        dokunmalı cihazda HİÇ var olmazdı. `pointerdown` her iki giriş kipinde
        de doğar; bu test onu bir dokunma olarak gönderir.
    */
    it('dokunmayla (pointerType: touch) da kapanır', () => {
        document.body.innerHTML = overlayMenu() + outside();
        bind();

        const menu = el<HTMLDetailsElement>('menu');
        menu.open = true;

        const touch = new Event('pointerdown', { bubbles: true, composed: true });
        Object.defineProperty(touch, 'pointerType', { value: 'touch' });
        el('outside').dispatchEvent(touch);

        expect(menu.open).toBe(false);
    });
});

describe('Escape (SITE-DISMISS-01)', () => {
    it('Escape menüyü kapatır ve odağı `<summary>`ye geri verir', () => {
        document.body.innerHTML = overlayMenu() + outside();
        bind();

        const menu = el<HTMLDetailsElement>('menu');
        menu.open = true;
        el('inside').focus();

        escape(el('inside'));

        expect(menu.open).toBe(false);
        /*
            ODAK HİÇBİR YERE DÜŞMEMELİ.

            Odak `<body>`ye düşerse klavye kullanıcısı sayfanın BAŞINA atılır
            ve menüye kadar olan bütün yolu yeniden yürür. Kapatmanın bedeli
            bu olamaz.
        */
        expect(document.activeElement).toBe(el('toggle'));
    });

    it('kapalı menüde Escape odağı ÇALMAZ', () => {
        document.body.innerHTML = overlayMenu() + outside();
        bind();

        el<HTMLAnchorElement>('outside').focus();
        escape(el('outside'));

        expect(document.activeElement).toBe(el('outside'));
    });
});

describe('odak menüden çıkınca (SITE-DISMISS-01)', () => {
    it('sekmeyle menüden çıkan kullanıcı menüyü arkasında açık bırakmaz', () => {
        document.body.innerHTML = overlayMenu() + outside();
        bind();

        const menu = el<HTMLDetailsElement>('menu');
        menu.open = true;

        el('inside').dispatchEvent(
            new FocusEvent('focusout', { bubbles: true, relatedTarget: el('outside') }),
        );

        expect(menu.open).toBe(false);
    });

    it('odak menünün İÇİNDE gezerken kapanmaz', () => {
        document.body.innerHTML = overlayMenu() + outside();
        bind();

        const menu = el<HTMLDetailsElement>('menu');
        menu.open = true;

        el('toggle').dispatchEvent(
            new FocusEvent('focusout', { bubbles: true, relatedTarget: el('inside') }),
        );

        expect(menu.open).toBe(true);
    });

    /*
        PENCEREDEN ÇIKMAK MENÜDEN ÇIKMAK DEĞİLDİR.

        Kullanıcı başka bir sekmeye geçtiğinde `relatedTarget` boştur. Orada
        kapatmak, geri döndüğünde menüsünü kaybetmiş olması demekti.
    */
    it('odak pencereden çıkarsa (relatedTarget yok) kapanmaz', () => {
        document.body.innerHTML = overlayMenu() + outside();
        bind();

        const menu = el<HTMLDetailsElement>('menu');
        menu.open = true;

        el('inside').dispatchEvent(new FocusEvent('focusout', { bubbles: true }));

        expect(menu.open).toBe(true);
    });
});

describe('tek seferde tek menü (SITE-DISMISS-01)', () => {
    it('biri açılınca öteki kapanır', () => {
        document.body.innerHTML = `
            <details id="a" data-dismiss-on-outside><summary>A</summary><p>a</p></details>
            <details id="b" data-dismiss-on-outside><summary>B</summary><p>b</p></details>
        `;
        bind();

        const a = el<HTMLDetailsElement>('a');
        const b = el<HTMLDetailsElement>('b');

        a.open = true;
        a.dispatchEvent(new Event('toggle'));

        b.open = true;
        b.dispatchEvent(new Event('toggle'));

        expect(a.open).toBe(false);
        expect(b.open).toBe(true);
    });
});

describe('KAPSAM — işaretlenmemiş `<details>` dokunulmaz', () => {
    /*
        ALTBİLGİDEKİ KATLANABİLİR GRUPLAR VE İÇERİK AKORDEONLARI.

        Bunları kullanıcı BİLEREK açar ve okurken sayfanın başka bir yerine
        tıklaması olağandır. Dışarı tıklamada kapanmaları, okuduğu şeyin
        elinden alınması olurdu. Kapsam bir VERİ ÖZNİTELİĞİYLE çizilir; sınıf
        adına güvenmek, bir sınıf yeniden adlandırıldığında kapsamı sessizce
        kaydırırdı.
    */
    it('altbilgi `<details>`i dışarı tıklamayla KAPANMAZ', () => {
        document.body.innerHTML =
            '<details id="fold" class="site-footer-fold" open><summary>Legal</summary>' +
            '<a href="/terms">Terms</a></details>' +
            outside();
        bind();

        pointerDown(el('outside'));

        expect(el<HTMLDetailsElement>('fold').open).toBe(true);
    });

    it('işaretlenmemiş `<details>` Escape ile de kapanmaz', () => {
        document.body.innerHTML =
            '<details id="faq" class="site-doc-faq-item" open><summary>Soru</summary>' +
            '<p id="answer">Cevap</p></details>';
        bind();

        escape(el('answer'));

        expect(el<HTMLDetailsElement>('faq').open).toBe(true);
    });
});

describe('İLERLEYİCİ ZENGİNLEŞTİRME — taban betiksiz durur', () => {
    /*
        BETİK YOKSA MENÜ YİNE ÇALIŞIR.

        `<details>` TABANDIR; bu dosya yalnız KAPANMA YOLLARI ekler. Betik
        hiç yüklenmezse (ağ hatası, eski tarayıcı, kapatılmış JavaScript)
        menü açılır ve `<summary>` ile kapanır. Bu test `bindDismiss`i
        BİLEREK çağırmaz.
    */
    it('`bindDismiss` çağrılmadan menü `<summary>` ile açılıp kapanır', () => {
        document.body.innerHTML = overlayMenu();

        const menu = el<HTMLDetailsElement>('menu');
        const toggle = el('toggle');

        expect(menu.open).toBe(false);

        toggle.click();
        expect(menu.open).toBe(true);

        toggle.click();
        expect(menu.open).toBe(false);
    });

    it('menü yoksa hiçbir dinleyici bağlanmaz ve çözme güvenlidir', () => {
        document.body.innerHTML = outside();

        const release = bind();

        expect(() => release()).not.toThrow();
    });

    /*
        ÇÖZME GERÇEKTEN ÇÖZER.

        Bırakılan bir belge dinleyicisi, sayfa yaşadıkça her basışta iş yapan
        ölü bir eldir.
    */
    it('çözdükten sonra dışarı basmak artık kapatmaz', () => {
        document.body.innerHTML = overlayMenu() + outside();

        const release = bind();
        const menu = el<HTMLDetailsElement>('menu');

        menu.open = true;
        release();
        pointerDown(el('outside'));

        expect(menu.open).toBe(true);
    });
});
