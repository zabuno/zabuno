import { TextInput } from '../../forms/micro/TextInput';
import { Textarea } from '../../forms/micro/Textarea';
import { Select } from '../../forms/micro/Select';
import clsx from 'clsx';
import {
    Image as ImageIcon,
    ImageSquare,
    PencilSimple,
    Prohibit,
    Warning,
} from '@phosphor-icons/react';
import { useEffect, useState, type FormEvent } from 'react';
import { RowActions } from '../compound/RowActions';
import { CategoryRail } from '../compound/CategoryRail';
import {
    MenuScreenActions,
    type MenuPill,
    type PhotoImportState,
} from '../compound/MenuScreenActions';
import { DrawerPanel } from '../../overlays/compound/DrawerPanel';
import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../../../lib/csrfHeader';
import { formatMoneyOr } from '../../../../money/format';
import { focusFirstInvalidField, readValidationFailure } from '../../../../lib/validationErrors';
import { t } from '../../../../i18n/menu';
import { FileDropzone } from '../../forms/compound/FileDropzone';
import { InlineRename } from '../micro/InlineRename';
import { ConfirmDialog } from '../../overlays/compound/ConfirmDialog';
import { FieldError } from '../micro/FieldError';
import { OrderBadge } from '../micro/OrderBadge';

export type MenuCatalogWorkspaceProps = {
    workspaceId: number;
    locationId: number;
    onTreeChange?: (tree: MenuTree) => void;
    /**
     * Yayın ekranına geçiş — `docs/101` Y3.
     *
     * Kebapçının UNUTTUĞU adım fiyatı değiştirdikten sonra YAYINLAMAKTIR;
     * düzenleme taslağı değiştirir, masadaki misafir son yayınlanan sürümü
     * görmeye devam eder. Kaydettikten sonra hatırlatma burada doğar.
     */
    onNavigateToSection?: (section: string) => void;
    /**
     * Bu kullanıcı bu izne sahip mi? — `WorkspaceApp`'in `can`'i ile aynı
     * sözleşme (`docs/98` FF-74).
     *
     * İSTEĞE BAĞLI ve tanımsızsa "evet" sayılır. Sebebi bir güvenlik boşluğu
     * değil, bir görünüm kuralı: karar her zaman sunucudadır (403/404), bu
     * işlev yalnız ekranın hangi kontrolü ÇİZECEĞİNİ söyler. Tanımsızken
     * daraltsaydı, izin listesini hiç taşımayan yollar (Storybook, doğrudan
     * gömme) sessizce boş bir menü ekranı gösterirdi.
     */
    can?: (permission: string) => boolean;
};

type Brand = {
    id: number;
    workspaceId: number;
    name: string;
    slug: string;
    locale: string;
    timezone: string;
    currency: string;
    description: string | null;
    contactEmail?: string | null;
    contactPhone?: string | null;
};

type MenuItemRow = {
    id: number;
    categoryId: number;
    productId: number;
    productName?: string;
    priceMinorAmount: number;
    currencyCode: string;
    position: number;
    allergens: string[];
    isVisible: boolean;
    description?: string | null;
    imageMediaAssetId?: number | null;
    /** "Bugün tükendi" — GÖRÜNÜRLÜKTEN ayrı bir eksen (`docs/82`). */
    outOfStock?: boolean;
};

/** Panelde seçilebilecek, İŞLENMESİ BİTMİŞ bir görsel. */
type ReadyMediaRow = {
    id: number;
    altText: string;
    slot: string;
    status: string;
};

/**
 * Yinelenen ürün ADAYI — `docs/96`/`docs/97` Yolculuk C.
 *
 * SALT OKUNUR bir öneridir, bir eylem değil: hiçbir alan bir düğmeye
 * bağlanmaz, sahip isterse mevcut "adı değiştir"/"sil" akışlarına
 * kendisi geçer.
 */
type DuplicateCandidate = {
    productAId: number;
    productAName: string;
    productBId: number;
    productBName: string;
    similarity: number;
};

/**
 * BİR YETENEĞİN ŞU ANKİ DURUMU — `docs/97` R9 / AIV-07.
 *
 * `reason` bir hata kodu DEĞİL: "kapalı", "bütçe bitti" ve "sağlayıcı yok"
 * farklı durumlardır ve farklı çözümleri vardır (`docs/47` Kural 5'in AI
 * karşılığı). Ekran bu yüzden tek bir "kullanılamıyor" metni değil, sebebe
 * göre değişen tek satırlık bir açıklama gösterir.
 */
type AiCapabilityState = {
    capability: string;
    available: boolean;
    reason: string;
};

/** AI'nın fotoğraftan okuduğu TEK satır — inceleme ekranının ham verisi. */
type AiImportRow = {
    /*
        Satır adı (`row.1`) yalnız KENDİ taslağı içinde eşsizdir. Toplu
        okumada dört fotoğrafın dördünde de `row.1` vardır; hangi taslaktan
        geldiğini taşımazsa React aynı anahtarı dört kez görür ve listeyi
        yanlış eşler.
    */
    artifactId: number;
    name: string;
    category: string;
    product: string;
    priceMinorAmount: number | null;
    currencyCode: string;
    confidence: number;
    uncertain: boolean;
};

/**
 * `ApplyMenuArtifact`'in cevabı — `rejectedRows[].row` bir SATIR NUMARASI
 * DEĞİL, alan adıdır ("row.3"); CSV içe aktarmanın `{line, reason}`
 * şeklinden farklı, bu yüzden `importReport` yeniden kullanılmaz.
 */
type AiImportApplyReport = {
    importedItems: number;
    importedCategories: number;
    rejectedRows: { row: string; reason: string; artifactId?: number }[];
};

type CategoryRow = {
    id: number;
    menuId: number;
    name: string;
    position: number;
    menuItems: MenuItemRow[];
};

export type MenuTree = {
    id: number;
    workspaceId: number;
    locationId: number;
    name: string;
    state: string;
    categories: CategoryRow[];
};

/**
 * ŞUBENİN BİR MENÜSÜ — menü hapının ham verisi (`docs/109` §7.1).
 *
 * `startsAt`/`endsAt` menünün kendi sütunlarından gelmez; şubenin geçiş
 * anlarından HESAPLANIR ve bir menünün birden çok yayı olabilir: "Kahvaltı
 * 07–11" dendiğinde ana menü 00:00–07:00 ve 11:00–00:00 diye ikiye
 * ayrılır.
 */
type MenuRow = {
    id: number;
    name: string;
    /** `draft` | `active` | `disabled` */
    state: string;
    sortOrder: number;
    startsAt: string | null;
    endsAt: string | null;
    windows: { startsAt: string; endsAt: string }[];
    isServingNow: boolean;
    isAddressAnchor: boolean;
};

function brandUrl(workspaceId: number): string {
    return `/api/workspaces/${workspaceId}/brand`;
}

function menuUrl(workspaceId: number, locationId: number): string {
    return `/api/workspaces/${workspaceId}/brand/locations/${locationId}/menu`;
}

/*
    ÇOKLU MENÜ (sahibin 2026-09-05 kararı, `docs/109` §7.1).

    Şubenin menü listesi ayrı bir yoldur: `.../menu` şubenin ŞU AN servis
    ettiği menüyü verir (sahip misafirle aynı şeyi görsün diye), `.../menus`
    ise hapların tamamını.
*/
function menusUrl(workspaceId: number, locationId: number): string {
    return `/api/workspaces/${workspaceId}/brand/locations/${locationId}/menus`;
}

function menuByIdUrl(workspaceId: number, menuId: number): string {
    return `/api/workspaces/${workspaceId}/menu/${menuId}`;
}

function menuServiceWindowUrl(workspaceId: number, menuId: number): string {
    return `/api/workspaces/${workspaceId}/menu/${menuId}/service-window`;
}

function categoriesUrl(workspaceId: number, menuId: number): string {
    return `/api/workspaces/${workspaceId}/menu/${menuId}/categories`;
}

/** Ürün + menü satırı + alerjenler; tek istek, tek işlem. */
function menuEntriesUrl(workspaceId: number, categoryId: number): string {
    return `/api/workspaces/${workspaceId}/menu-categories/${categoryId}/menu-entries`;
}

function allergensUrl(workspaceId: number, menuItemId: number): string {
    return `/api/workspaces/${workspaceId}/menu-items/${menuItemId}/allergens`;
}

function priceUrl(workspaceId: number, menuItemId: number): string {
    return `/api/workspaces/${workspaceId}/menu-items/${menuItemId}/price`;
}

function visibilityUrl(workspaceId: number, menuItemId: number): string {
    return `/api/workspaces/${workspaceId}/menu-items/${menuItemId}/visibility`;
}

/*
    MENÜYÜ İŞLETMEK — `docs/73` (P0-01).

    Ürün bu dört yol olmadan bir menüyü yayımlayabiliyor ama işletemiyordu:
    yanlış yazılan bir ürünü düzeltmenin yolu yoktu, sezonluk bir kategoriyi
    kaldırmanın yolu yoktu, sıra veri modelinde vardı ama yüzeyi yoktu.
*/
function categoryUrl(workspaceId: number, categoryId: number): string {
    return `/api/workspaces/${workspaceId}/menu-categories/${categoryId}`;
}

function menuItemImageUrl(workspaceId: number, menuItemId: number): string {
    return `/api/workspaces/${workspaceId}/menu-items/${menuItemId}/image`;
}

function stockUrl(workspaceId: number, menuItemId: number): string {
    return `/api/workspaces/${workspaceId}/menu-items/${menuItemId}/stock`;
}

/**
 * Tek istekte çoklu işaretleme — `docs/82` kriter 3, ekranı `docs/98` FF-64.
 * "Balıklar bitti" altı ürünü altı istekle işaretlemek değildir.
 */
function menuStockUrl(workspaceId: number, menuId: number): string {
    return `/api/workspaces/${workspaceId}/menu/${menuId}/stock`;
}

function exportUrl(workspaceId: number, menuId: number): string {
    return `/api/workspaces/${workspaceId}/menu/${menuId}/export.csv`;
}

function importUrl(workspaceId: number, menuId: number): string {
    return `/api/workspaces/${workspaceId}/menu/${menuId}/import`;
}

function mediaUrl(workspaceId: number): string {
    return `/api/workspaces/${workspaceId}/media`;
}

function menuItemUrl(workspaceId: number, menuItemId: number): string {
    return `/api/workspaces/${workspaceId}/menu-items/${menuItemId}`;
}

/** AI açıklama taslağı üretir — `docs/96` Faz 2 (`opt-23`). */
function descriptionDraftUrl(workspaceId: number, menuItemId: number): string {
    return `/api/workspaces/${workspaceId}/menu-items/${menuItemId}/description-drafts`;
}

/** Taslağı (düzenlenmiş ya da olduğu gibi) ürüne yazar. */
function applyDescriptionDraftUrl(workspaceId: number, artifactId: number): string {
    return `/api/workspaces/${workspaceId}/description-drafts/${artifactId}/apply`;
}

/** Yinelenen ürün adayları — salt okunur öneri (`docs/96` Faz 2). */
function duplicateCandidatesUrl(workspaceId: number): string {
    return `/api/workspaces/${workspaceId}/menu/duplicate-candidates`;
}

/**
 * AI şu an ne yapabilir — TIKLAMADAN ÖNCE (`docs/97` R9).
 *
 * Bu olmadan ekran ancak düğmeye basıp 503 alarak öğrenebiliyordu: kullanıcı,
 * var olmayan bir işi denemek zorunda kalırdı.
 */
function aiAvailabilityUrl(workspaceId: number): string {
    return `/api/workspaces/${workspaceId}/ai/availability`;
}

/**
 * Fotoğraf/PDF'ten menü taslağı okur — `docs/96` Faz 3.
 *
 * Tekil `.../ai-imports` ucu API'de DURUYOR (yayınlanmış yüzey, kendi
 * testleri var) ama ekran artık onu kullanmıyor: bir restoranın menüsü tek
 * fotoğrafa sığmaz ve tek seçim, sahibi aynı işi sayfa sayısı kadar
 * tekrarlamaya zorluyordu. Kullanılmayan bir istemci yardımcısını
 * "ileride lazım olur" diye bırakmak, iki yol arasında hangisinin geçerli
 * olduğunu belirsiz kılardı.
 *
 * Bir restoranın menüsü tek fotoğrafa sığmaz; dört sayfayı tek tek
 * okutmak, sahibin aynı işi dört kez yapması demekti.
 */
/** Toplu orkestra (FF-75): 10'dan çok sayfa kuyruğa gider, parti izlenir. */
function aiBatchesUrl(workspaceId: number, menuId: number): string {
    return `/api/workspaces/${workspaceId}/menu/${menuId}/ai-batches`;
}

function showAiBatchUrl(workspaceId: number, batchId: number): string {
    return `/api/workspaces/${workspaceId}/ai-batches/${batchId}`;
}

const INTERACTIVE_IMPORT_MAX_PHOTOS = 10;
const BATCH_POLL_MS = 2000;

function bulkAiImportsUrl(workspaceId: number, menuId: number): string {
    return `/api/workspaces/${workspaceId}/menu/${menuId}/ai-imports/batch`;
}

/** Okunan taslakların tamamını tek onayla taslağa yazar. */
function bulkApplyAiImportUrl(workspaceId: number): string {
    return `/api/workspaces/${workspaceId}/ai-imports/batch/apply`;
}

/** Okunan taslağın alanları — capability-agnostic, generic. */
function showAiImportUrl(workspaceId: number, artifactId: number): string {
    return `/api/workspaces/${workspaceId}/ai-imports/${artifactId}`;
}

function itemOrderUrl(workspaceId: number, categoryId: number): string {
    return `/api/workspaces/${workspaceId}/menu-categories/${categoryId}/item-order`;
}

function categoryOrderUrl(workspaceId: number, menuId: number): string {
    return `/api/workspaces/${workspaceId}/menu/${menuId}/category-order`;
}

function currencyFractionDigits(currencyCode: string): number {
    try {
        return (
            new Intl.NumberFormat(undefined, {
                style: 'currency',
                currency: currencyCode,
            }).resolvedOptions().maximumFractionDigits ?? 2
        );
    } catch {
        return 2;
    }
}

function minorAmountToDecimalString(minorAmount: number, currencyCode: string): string {
    const digits = currencyFractionDigits(currencyCode);
    const negative = minorAmount < 0;
    const absDigits = Math.abs(minorAmount)
        .toString()
        .padStart(digits + 1, '0');
    if (digits === 0) {
        return `${negative ? '-' : ''}${absDigits}`;
    }
    const wholePart = absDigits.slice(0, absDigits.length - digits);
    const fractionPart = absDigits.slice(absDigits.length - digits);
    return `${negative ? '-' : ''}${wholePart}.${fractionPart}`;
}

function parseAllergens(raw: string): string[] {
    const seen = new Set<string>();
    const result: string[] = [];
    for (const part of raw.split(',')) {
        const trimmed = part.trim();
        if (!trimmed) continue;
        const key = trimmed.toLocaleLowerCase();
        if (seen.has(key)) continue;
        seen.add(key);
        result.push(trimmed);
    }
    return result;
}

async function postJson(
    url: string,
    body: unknown,
    method: 'POST' | 'PUT' | 'DELETE' = 'POST',
): Promise<Response> {
    await bootstrapCsrfCookie();
    return fetch(
        url,
        buildAuthRequestInit({
            method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        }),
    );
}

/**
 * Sunucunun yanıtından gösterilecek metni çıkarır.
 *
 * Önceki hâli yalnız `message` alanını okuyup `errors` nesnesini atıyordu —
 * yani "hangi alan" bilgisi yine kayboluyordu. Ortak okuyucu ikisini de
 * getirir; burada alan hatası varsa o gösterilir, çünkü kullanıcıya
 * düzeltmesi gereken şeyi söylemek özetten daha yararlıdır.
 */
/**
 * Şubenin menü haplarını getirir; arıza hâlinde `null`.
 *
 * Hata YUTULUR ve bu bilinçlidir: haplar ikincil bir bilgidir ve
 * getirilememeleri menü ekranını kapatmak için bir sebep değil. Sahip
 * menüsünü görmeye ve düzenlemeye devam eder; yalnız liste bir tur eski
 * kalır.
 */
async function fetchMenuRows(workspaceId: number, locationId: number): Promise<MenuRow[] | null> {
    try {
        const response = await fetch(menusUrl(workspaceId, locationId));

        if (!response?.ok) return null;

        const body = (await response.json()) as { data?: MenuRow[] };

        return Array.isArray(body?.data) ? body.data : null;
    } catch {
        return null;
    }
}

async function parseErrorMessage(response: Response, fallback: string): Promise<string> {
    const failure = await readValidationFailure(response, fallback);
    const firstField = Object.values(failure.fields)[0];

    return firstField ?? failure.message ?? fallback;
}

// Bu yüzey menü kataloğunun kalbidir ve restoran sahibinin en çok gördüğü
// ekrandır; bu yüzden kendi rengini seçmemesi özellikle önemlidir.
const labelClass = 'block text-body font-medium text-fg-secondary';

const buttonClass = clsx(
    'inline-flex min-h-[var(--density-hit-area-min)] items-center justify-center rounded-md px-4 py-2 text-body font-bold',
    'border border-action bg-action text-action-fg hover:brightness-95',
    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus',
    'disabled:cursor-not-allowed disabled:opacity-50',
    'forced-colors:border forced-colors:border-[ButtonText]',
);

/**
 * Satır içi, İKİNCİL eylem.
 *
 * Daha önce dosyadaki her buton `buttonClass` kullanıyordu; yani "alerjen
 * düzenle" ile "menü oluştur" aynı görsel ağırlıktaydı ve dikey yığın
 * içinde marka sarısı tam genişlik bantlara dönüşüyordu. Bir ekranda her
 * şey birincilse hiçbir şey birincil değildir — ve marka rengi vurgudan
 * çıkıp gürültü olur (docs/37 §1: affordance, kimlik "Flat 2.0 + tonal").
 */
const inlineActionClass = clsx(
    'inline-flex min-h-[var(--density-hit-area-min)] shrink-0 items-center rounded-md px-2 py-1',
    'text-meta font-medium text-fg-secondary',
    'border border-border bg-transparent hover:bg-surface-hover hover:text-fg',
    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus',
    'disabled:cursor-not-allowed disabled:opacity-50',
);

// Kategori/bölüm kutusu — panel kart grameriyle aynı (`docs/102` §1).
const sectionClass = clsx(
    'flex flex-col gap-[var(--space-4)] rounded-[var(--radius-lg)] border border-border bg-[var(--color-surface)] p-[var(--space-5)]',
    'forced-colors:border-[CanvasText]',
);

/*
    ÜRÜN SATIRININ SÜTUN RİTMİ — kanonik teslim paketi (`DESIGN_SPEC` §3):

        48px görsel · ad + meta · 110px fiyat · 44px bitti · 48px görünürlük ·
        eylemler

    NEDEN IZGARA, ESNEK SARMA DEĞİL: sarmalı bir satırda her ürünün fiyatı
    kendi adının uzunluğu kadar sağa kayar. Akşam servisinde on beş fiyata
    bakan bir sahibin gözü, hiçbir ikisi aynı hizada olmayan bir sütunu
    tarayamaz. Izgara, sayıyı her satırda AYNI x'e koyar; karşılaştırma
    ancak böyle gözle yapılır.

    320px'de ızgara KAPANIR ve satır tekrar sarar: dar ekranda altı sütunu
    zorlamak, her sütunu okunamayacak kadar daraltmaktı. Hiçbir bilgi
    kaybolmaz, yalnız iki satıra iner (`DESIGN_SPEC` §12).

    Satır yüksekliği yoğunluk jetonundan gelir; dokunma hedefi hiçbir modda
    44px'in altına inmez (`--density-hit-area-min`).
*/
const itemRowGridClass = clsx(
    'flex flex-wrap items-center gap-x-[var(--space-3)] gap-y-[var(--space-1)]',
    'min-h-[var(--density-row-height)]',
    'sm:grid sm:grid-cols-[48px_minmax(0,1fr)_minmax(110px,auto)_44px_48px_auto] sm:gap-y-0',
);

/*
    48px görsel karesi. Fotoğrafı olmayan üründe de durur ve boş kalır —
    boşluğun kendisi bilgidir: sahip listeye baktığında hangi ürünün
    fotoğrafı yok, saymadan görür.
*/
const itemThumbClass = clsx(
    'flex h-[48px] w-[48px] shrink-0 items-center justify-center',
    'rounded-[var(--radius-md)] border border-border bg-surface-subtle text-fg-secondary',
    'hover:bg-surface-active hover:text-fg',
    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus',
);

/*
    FİYAT — sağa yaslı, eşit genişlikte rakam, soluk dolgu.

    `justify-end` + `tabular-nums`: "₺420,00" ile "₺38,00" ancak böyle aynı
    yerde biter. `bg-surface-subtle`: "buraya tıklanır" bilgisini renkle
    değil dolguyla verir — marka sarısı bir listede on beş kez tekrar
    ederse vurgu olmaktan çıkar, gürültü olur.
*/
/*
    FİYAT KUTUSUNUN ORTAK BİÇİMİ. Düzenlenebilir hâli de salt okunur hâli de
    aynı ızgarayı taşır: iki farklı rol aynı listeye baktığında sütunlar
    aynı yerde durur (`docs/109` §8.4).
*/
const itemPriceBaseClass = clsx(
    'flex min-h-[var(--density-hit-area-min)] min-w-[110px] shrink-0 items-center justify-end gap-[var(--space-2)]',
    'rounded-[var(--radius-md)] bg-surface-subtle px-[var(--space-3)]',
    'text-body font-bold tabular-nums text-fg',
);

/* Üzerine gelme ve odak YALNIZ düzenlenebilir hâlde: biçim, verilen sözdür. */
const itemPriceClass = clsx(
    itemPriceBaseClass,
    'hover:bg-surface-active',
    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus',
);

/*
    "BUGÜN BİTTİ" satırın en sık kullanılan düğmesidir ve artık ikon taşır:
    metin ("Sold out" / "Back in stock") satırın genişliğini ürünün o anki
    durumuna göre değiştiriyordu, yani sütunlar satırdan satıra kayıyordu.
    Erişilebilir isim tam cümledir ve `aria-pressed` durumu taşır —
    bilgi kaybolmaz, yalnız genişlik sabitlenir.
*/
const itemStockButtonClass = clsx(
    'flex min-h-[var(--density-hit-area-min)] min-w-[var(--density-hit-area-min)] shrink-0 items-center justify-center',
    'rounded-[var(--radius-md)] text-fg-secondary',
    'hover:bg-surface-hover hover:text-fg',
    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus',
    'disabled:cursor-not-allowed disabled:opacity-50',
);

export function MenuCatalogWorkspace({
    workspaceId,
    locationId,
    onTreeChange,
    onNavigateToSection,
    can,
}: MenuCatalogWorkspaceProps) {
    /*
        MENÜYÜ İŞLETME YETKİSİ — tek bir soru, tek bir yerde sorulur.

        Bu ekranda iki tür iş var: menüyü DEĞİŞTİRMEK (fiyat, görünürlük,
        ad, sıra, silme, ekleme, içe aktarma) ve GÜNÜ İŞLETMEK (tükendi,
        alerjen). Mutfak rolü ikincisini yapar, birincisini yapamaz
        (`docs/109` §6.4) — ve sunucu bunu zaten 403 ile durduruyor.

        Ekranın işi o sınırı TEKRAR ETMEK değil, ona uymaktır: yapılamayan
        şey çizilmez. Devre dışı bir düğme "bir gün olabilir" der; aşçının
        rolü değişmedikçe o gün gelmeyecek.
    */
    const canManageMenu = can === undefined || can('menu.manage');

    const [initialLoading, setInitialLoading] = useState(true);
    const [initialError, setInitialError] = useState(false);
    const [retryToken, setRetryToken] = useState(0);
    const [brand, setBrand] = useState<Brand | null>(null);
    const [tree, setTree] = useState<MenuTree | null>(null);
    /*
        İŞLETME hatalarının tek yeri. Her satıra ayrı bir hata durumu koymak,
        aynı cümleyi beş yerde tutmak olurdu; bu işlemlerin hepsi aynı sonucu
        verir: olmadı ve sebebi sunucudan geliyor.
    */
    const [operationError, setOperationError] = useState<string | null>(null);

    /*
        SİLME ONAYI ürünün kendi diyaloğuyla sorulur (FF-101).

        Önceki hâl `window.confirm` idi. Tarayıcının kendi kutusu ürünün
        dışında çizilir, sayfayı dondurur, hangi satırın silineceğini
        gösteremez ve kullanıcı bir kez "bu sayfa başka diyalog açmasın"
        derse SESSİZCE "iptal" döndürür — yani silme düğmesi çalışır görünüp
        hiçbir şey yapmaz.

        Beklemekte olan silme burada tutulur: hangi satır, hangi tür. `null`
        ise diyalog kapalıdır.
    */
    const [pendingDelete, setPendingDelete] = useState<
        { kind: 'item'; item: MenuItemRow } | { kind: 'category'; category: CategoryRow } | null
    >(null);
    const [deleting, setDeleting] = useState(false);

    const [menuName, setMenuName] = useState('');
    const [menuNameError, setMenuNameError] = useState<string | null>(null);
    const [menuSubmitError, setMenuSubmitError] = useState<string | null>(null);
    const [creatingMenu, setCreatingMenu] = useState(false);

    /*
        ÇOKLU MENÜ — sahibin 2026-09-05 kararı (`docs/109` §7.1).

        `menus` hapların kaynağıdır; `tree` ise şu an EKRANDA AÇIK olan
        menünün içeriği. İkisi ayrı tutulur çünkü hapa basmak yalnız
        içeriği değiştirir: liste yerinde kalır, yoksa her tıklamada
        haplar yeniden çizilip zıplardı.
    */
    const [menus, setMenus] = useState<MenuRow[]>([]);
    const [switchingMenu, setSwitchingMenu] = useState(false);
    const [menuPanel, setMenuPanel] = useState<
        { mode: 'create' } | { mode: 'edit'; menuId: number } | null
    >(null);
    const [menuFormName, setMenuFormName] = useState('');
    const [menuFormStartsAt, setMenuFormStartsAt] = useState('');
    const [menuFormEndsAt, setMenuFormEndsAt] = useState('');
    const [menuFormError, setMenuFormError] = useState<string | null>(null);
    const [menuFormBusy, setMenuFormBusy] = useState(false);
    const [menuPendingDelete, setMenuPendingDelete] = useState<number | null>(null);

    const [categoryName, setCategoryName] = useState('');
    const [categoryNameError, setCategoryNameError] = useState<string | null>(null);
    const [categorySubmitError, setCategorySubmitError] = useState<string | null>(null);
    const [creatingCategory, setCreatingCategory] = useState(false);
    const [currentCategoryId, setCurrentCategoryId] = useState<number | null>(null);

    /*
        RAYDA SEÇİLİ KATEGORİ — kanonik kaynak `panel.dc.html` satır
        30256-30263 (`s.cat`).

        `currentCategoryId` ile KARIŞTIRILMAMALI: o, ürün ekleme formunun
        hangi kategoride açık olduğunu tutar. Bu ise ekranın sağ yarısında
        HANGİ kategorinin ürünlerinin çizildiğini söyler.

        `null` "hiçbiri" değil, "henüz seçilmedi" demektir: ilk kategori
        varsayılan olarak seçilir (aşağıda), çünkü boş bir sağ yarı
        kullanıcıya yapacak iş vermez.
    */
    const [selectedCategoryId, setSelectedCategoryId] = useState<number | null>(null);

    /*
        CSV kutusu ve fotoğraf sihirbazı artık kapalı bir `<details>`
        içinde değil, üst şeritteki düğmelerin açtığı panellerdir.
    */
    const [csvPanelOpen, setCsvPanelOpen] = useState(false);

    // Kategori ekleme, ürün ekleme formunun İÇİNDE değil; kendi eylemidir ve
    // kapalı durur. Öncesinde ikisi yan yana iki formdu ve kullanıcı hangi
    // alanın hangi işe ait olduğunu ayırt edemiyordu: "kategori adı" ile
    // "ürün adı" aynı görsel ağırlıkta, art arda iki kutuydu.
    const [categoryFormOpen, setCategoryFormOpen] = useState(false);

    // Menüye ürün eklemenin TEK durumu. Bunlar daha önce üç ayrı formun üç
    // ayrı durum kümesiydi (ürün / fiyat / alerjen) ve her biri bir öncekinin
    // gönderilmesini bekliyordu.
    const [productName, setProductName] = useState('');
    const [productNameError, setProductNameError] = useState<string | null>(null);
    const [price, setPrice] = useState('');
    const [priceError, setPriceError] = useState<string | null>(null);
    const [entryAllergens, setEntryAllergens] = useState('');
    const [entrySubmitError, setEntrySubmitError] = useState<string | null>(null);
    const [creatingEntry, setCreatingEntry] = useState(false);
    const [lastAddedEntry, setLastAddedEntry] = useState<string | null>(null);

    // Alerjen düzenleme artık SATIRIN İÇİNDE açılır — fiyat düzenleme gibi.
    // Öncesinde sayfanın en altında tek bir form vardı: listenin başındaki
    // bir ürünün alerjenini düzenlemek için kullanıcı ekranın dibine iniyor,
    // hangi ürünü düzenlediğini oradan göremiyordu.
    const [allergenEditItemId, setAllergenEditItemId] = useState<number | null>(null);
    const [allergensInput, setAllergensInput] = useState('');
    const [allergensSubmitError, setAllergensSubmitError] = useState<string | null>(null);
    const [savingAllergens, setSavingAllergens] = useState(false);

    const [priceEditItemId, setPriceEditItemId] = useState<number | null>(null);
    const [priceEditValue, setPriceEditValue] = useState('');
    const [priceEditSubmitError, setPriceEditSubmitError] = useState<string | null>(null);
    const [savingPriceEdit, setSavingPriceEdit] = useState(false);

    /*
        SUNUM düzenleyicisi: açıklama ve fotoğraf bir arada.

        İkisi ayrı düğme olsaydı sahip aynı satır için iki kez form açardı;
        oysa yaptığı tek bir iş: "bu ürünü misafire nasıl göstereceğim".
    */
    const [presentationItemId, setPresentationItemId] = useState<number | null>(null);
    const [descriptionInput, setDescriptionInput] = useState('');
    const [imageChoice, setImageChoice] = useState('');
    const [presentationError, setPresentationError] = useState<string | null>(null);
    const [savingPresentation, setSavingPresentation] = useState(false);
    const [readyMedia, setReadyMedia] = useState<ReadyMediaRow[]>([]);
    /*
        AI ÖNERİSİ — `docs/97` R4-R5. `aiDraftArtifactId` dolu olduğu sürece
        "Kaydet" düz PUT yerine onay uç noktasına gider (`docs/96` `opt-23`);
        boşsa bugüne kadarki elle-düzenleme yolu değişmeden çalışır. Form her
        açılışta sıfırlanır — bir ürünün taslağı başka bir ürüne sızmamalı.
    */
    const [aiDraftArtifactId, setAiDraftArtifactId] = useState<number | null>(null);
    const [aiSuggestionLoading, setAiSuggestionLoading] = useState(false);
    const [aiSuggestionError, setAiSuggestionError] = useState<string | null>(null);
    const [aiSuggestionUncertain, setAiSuggestionUncertain] = useState(false);
    const [aiSuggestionUsedFallback, setAiSuggestionUsedFallback] = useState(false);
    /*
        YİNELENEN ÜRÜN ADAYLARI — `docs/97` Yolculuk C. Aday yoksa (ya da
        istek başarısız olursa) bölüm HİÇ görünmez — bu ikincil, salt
        okunur bir öneridir; sessizce gizlenmesi kritik bir hata değildir
        (birincil "AI ile öner" eylemi gibi ayrı hata mesajı gerektirmez).
    */
    const [duplicateCandidates, setDuplicateCandidates] = useState<DuplicateCandidate[]>([]);
    /*
        AI KULLANILABİLİRLİĞİ — `docs/97` R9.

        `null` = HENÜZ BİLİNMİYOR (istek sürüyor ya da cevap okunamadı) ve
        bu bilinçli olarak "kullanılabilir" gibi davranır: bilinmeyeni
        "kapalı" saymak, ağ yavaşken çalışan bir özelliği gizler ve sahip
        onu bir daha aramaz. Yalnız AÇIKÇA "kullanılamaz" cevabı geldiğinde
        eylem kaldırılır ve yerine sebebi yazılır.
    */
    const [aiCapabilities, setAiCapabilities] = useState<AiCapabilityState[] | null>(null);
    /*
        FOTOĞRAFTAN İÇE AKTARMA (AI) — `docs/97` Yolculuk A. Yükleme Media
        sayfasında olur; burası yalnız HAZIR bir görseli seçip okutur ve
        sonucu incelettirir. `ApplyMenuArtifact` toplu/otomatik uygular —
        okunamayan satır ATLANIR, kullanıcı tek tek düzenlemez (bu, satır
        satır düzenleme öngören ilk taslaktan bilinçli bir sapmadır: geri
        döndürülebilir bir teknik/kapsam kararı, `docs/97`'de not edildi).
    */
    const [aiImportOpen, setAiImportOpen] = useState(false);
    const [importSourceMedia, setImportSourceMedia] = useState<ReadyMediaRow[]>([]);
    const [importMediaChoices, setImportMediaChoices] = useState<number[]>([]);
    /** Taslak değişti, yayınlanmadı — fiyat kaydından sonra görünür (`docs/101` Y3). */
    const [pendingPublishNotice, setPendingPublishNotice] = useState(false);
    const [aiBatchProgress, setAiBatchProgress] = useState<{
        done: number;
        total: number;
    } | null>(null);
    const [aiBatchSummary, setAiBatchSummary] = useState<{
        rows: number;
        pages: number;
        dupes: number;
    } | null>(null);
    const [aiImportArtifactIds, setAiImportArtifactIds] = useState<number[]>([]);
    /** Okunamayan fotoğraflar: medya kimliği → sebep (`docs/96` Faz 3). */
    const [aiImportFailures, setAiImportFailures] = useState<
        { mediaAssetId: number; error: string }[]
    >([]);
    const [aiImportRows, setAiImportRows] = useState<AiImportRow[]>([]);
    const [aiImportUsedFallback, setAiImportUsedFallback] = useState(false);
    const [aiImportReviewLoading, setAiImportReviewLoading] = useState(false);
    const [aiImportReviewError, setAiImportReviewError] = useState<string | null>(null);
    const [aiImportApplying, setAiImportApplying] = useState(false);
    const [aiImportApplyReport, setAiImportApplyReport] = useState<AiImportApplyReport | null>(
        null,
    );

    /* Menüyü almak ve geri koymak (`docs/80`). */
    const [importing, setImporting] = useState(false);
    const [importReport, setImportReport] = useState<{
        importedItems: number;
        importedCategories: number;
        rejectedRows: { line: number; reason: string }[];
    } | null>(null);
    const [importError, setImportError] = useState<string | null>(null);

    const [stockPending, setStockPending] = useState<Record<number, boolean>>({});
    const [categoryStockPending, setCategoryStockPending] = useState<Record<number, boolean>>({});

    const [visibilityPending, setVisibilityPending] = useState<Record<number, boolean>>({});
    const [visibilityErrors, setVisibilityErrors] = useState<Record<number, string | null>>({});

    // Tek bildirim mekanizması. Daha önce onTreeChange yalnız ilk yüklemede ve
    // görünürlük değişiminde elle çağrılıyordu; menü, kategori, ürün, kalem,
    // alerjen ve fiyat mutasyonları çağırmayı "unutuyordu". Bu yüzden Publication
    // sayfası paylaşılan menü ağacını hiç göremiyor, menuId null kalıyor ve Publish
    // düğmesi sessizce hiçbir istek atmıyordu. Her mutasyon noktasında çağrıyı
    // hatırlamak yerine ağacın kendisini kaynak alıyoruz.
    useEffect(() => {
        if (tree) {
            onTreeChange?.(tree);
        }
    }, [tree, onTreeChange]);

    const locationKey = `${workspaceId}:${locationId}`;
    const [previousLocationKey, setPreviousLocationKey] = useState(locationKey);
    if (locationKey !== previousLocationKey) {
        setPreviousLocationKey(locationKey);

        setBrand(null);
        setTree(null);

        setMenuName('');
        setMenuNameError(null);
        setMenuSubmitError(null);
        setCreatingMenu(false);

        // Şube değişti: eski şubenin menü hapları yeni şubede hiçbir şeye
        // karşılık gelmez.
        setMenus([]);
        setMenuPanel(null);
        setMenuFormError(null);
        setMenuFormBusy(false);
        setMenuPendingDelete(null);
        setSwitchingMenu(false);

        setCategoryName('');
        setCategoryNameError(null);
        setCategorySubmitError(null);
        setCreatingCategory(false);
        setCurrentCategoryId(null);
        // Konum değişti: eski şubenin kategori seçimi yeni şubede hiçbir
        // şeye karşılık gelmez.
        setSelectedCategoryId(null);

        setCategoryFormOpen(false);

        setProductName('');
        setProductNameError(null);
        setPrice('');
        setPriceError(null);
        setEntryAllergens('');
        setEntrySubmitError(null);
        setCreatingEntry(false);
        setLastAddedEntry(null);

        setAllergenEditItemId(null);
        setAllergensInput('');
        setAllergensSubmitError(null);
        setSavingAllergens(false);

        setPriceEditItemId(null);
        setPriceEditValue('');
        setPriceEditSubmitError(null);
        setSavingPriceEdit(false);

        setVisibilityPending({});
        setVisibilityErrors({});

        setDuplicateCandidates([]);
        setAiCapabilities(null);

        setAiImportOpen(false);
        setImportSourceMedia([]);
        setImportMediaChoices([]);
        setAiImportArtifactIds([]);
        setAiImportFailures([]);
        setAiImportRows([]);
        setAiImportUsedFallback(false);
        setAiImportReviewLoading(false);
        setAiImportReviewError(null);
        setAiImportApplying(false);
        setAiImportApplyReport(null);
    }

    useEffect(() => {
        let cancelled = false;

        async function loadInitial() {
            setInitialLoading(true);
            setInitialError(false);

            try {
                const [brandResponse, menuResponse] = await Promise.all([
                    fetch(brandUrl(workspaceId)),
                    fetch(menuUrl(workspaceId, locationId)),
                ]);
                if (cancelled) return;

                if (!brandResponse.ok) {
                    setInitialError(true);
                    return;
                }

                if (menuResponse.status !== 404 && !menuResponse.ok) {
                    setInitialError(true);
                    return;
                }

                setBrand((await brandResponse.json()) as Brand);

                if (menuResponse.status === 404) {
                    setTree(null);
                } else {
                    const loaded = asMenuTree(await menuResponse.json());

                    if (loaded === null) {
                        setInitialError(true);

                        return;
                    }

                    setTree(loaded);

                    // İlk kategori SEÇİLİ AÇILMAZ.
                    //
                    // Seçim, ürün formunun hangi kategoride açılacağını
                    // belirliyor; yüklemede seçmek, kullanıcı istemeden
                    // birinci kategorinin altında bir form açardı. Menüyü
                    // GÖRMEK için gelen kişiye, sormadığı bir soruyu
                    // gösterirdi.
                }
            } catch {
                if (!cancelled) {
                    setInitialError(true);
                }
            } finally {
                if (!cancelled) {
                    setInitialLoading(false);
                }
            }
        }

        void loadInitial();

        return () => {
            cancelled = true;
        };
    }, [workspaceId, locationId, retryToken]);

    /*
        MENÜ HAPLARI AYRI BİR TURDA GELİR ve bu bilinçlidir.

        Menünün kendisiyle aynı `Promise.all` içinde olsaydı, hap listesini
        getiren istek arızalandığında ekran BÜTÜNÜYLE hata verirdi: sahip
        menüsünü göremez, oysa menü sunucudan sorunsuz gelmiştir. Haplar
        ikincil bir bilgidir; yokluğunda ekran açık menüyü tek hap olarak
        çizmeye devam eder (`menuPills`).
    */
    useEffect(() => {
        let cancelled = false;

        void (async () => {
            const rows = await fetchMenuRows(workspaceId, locationId);

            if (!cancelled && rows !== null) {
                setMenus(rows);
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [workspaceId, locationId, retryToken]);

    /*
        Yinelenen ürün adaylarını menü yüklenince BİR KEZ çeker — her
        satır mutasyonunda yeniden sorgulamaz. Bu ikincil, bilgilendirici
        bir öneridir; sahip menüyü yeniden açtığında tazelenir, o kadarı
        yeterli (`docs/97` Yolculuk C kapsam kararı). Bağımlılık dizisi
        `tree` NESNESİNE değil, sahip olup olmamasına bakar — aksi hâlde
        her satır düzenlemesi (ad, fiyat, sıra...) yeni bir sorgu tetikler.
    */
    /*
        AI kullanılabilirliğini menüden BAĞIMSIZ sorar: menü henüz yokken de
        (ilk kurulum) doğru cevabı bilmek gerekir, çünkü fotoğraftan içe
        aktarma tam o anda en çok işe yarayan eylemdir. Hiçbir sağlayıcı
        çağrısı yapmaz — yalnız yapılandırma/bütçe okur, bedava.
    */
    useEffect(() => {
        let cancelled = false;

        void (async () => {
            try {
                const response = await fetch(aiAvailabilityUrl(workspaceId));
                if (cancelled || !response.ok) return;

                const body = (await response.json()) as { capabilities?: AiCapabilityState[] };
                if (Array.isArray(body.capabilities)) {
                    setAiCapabilities(body.capabilities);
                }
            } catch {
                // Bilinmiyor kalır — iyimser davranır (bkz. state yorumu).
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [workspaceId]);

    /**
     * Bu yetenek AÇIKÇA kullanılamaz mı? Bilinmiyorsa `null` döner ve eylem
     * olduğu gibi gösterilir.
     */
    function aiBlockedReason(capability: string): string | null {
        if (aiCapabilities === null) return null;

        const row = aiCapabilities.find((entry) => entry.capability === capability);
        if (row === undefined || row.available) return null;

        return row.reason;
    }

    /**
     * "Fotoğraftan aktar" düğmesinin üst şeritteki HÂLİ.
     *
     * AI kapalı/bütçesiz/rotasızsa düğme hiç çizilmez ve yerine sebebi
     * yazılır (`docs/97` R9 / AIV-07): üç sebep üç ayrı çözüme gider
     * (yönetici açar / bütçe artar / sağlayıcı anahtarı girilir). Tek bir
     * "kullanılamıyor" cümlesi sahibi kime gideceğini bilmeden bırakırdı.
     */
    function photoImportState(): PhotoImportState {
        const reason = aiBlockedReason('menu.extract');

        if (reason !== null) {
            return {
                kind: 'blocked',
                reason: t(`menu.ai.unavailable.${reason}` as never),
            };
        }

        return {
            kind: 'available',
            label: aiImportOpen
                ? t('menu.item.ai.import.cancel')
                : t('menu.item.ai.import.disclose'),
            onClick: () => void handleToggleAiImport(),
        };
    }

    const hasTree = tree !== null;
    useEffect(() => {
        if (!hasTree) return;

        let cancelled = false;

        void (async () => {
            try {
                const response = await fetch(duplicateCandidatesUrl(workspaceId));
                if (cancelled || !response.ok) return;

                const body = (await response.json()) as { candidates?: DuplicateCandidate[] };
                setDuplicateCandidates(body.candidates ?? []);
            } catch {
                // Sessizce boş kalır — bkz. yukarıdaki state yorumu.
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [workspaceId, hasTree]);

    const busy =
        creatingMenu || creatingCategory || creatingEntry || savingAllergens || savingPriceEdit;

    function handleEditAllergens(item: MenuItemRow) {
        // Aynı satıra ikinci kez basmak kapatır: açılan bir düzenleyiciyi
        // kapatmanın yolu olmalı, yoksa kullanıcı vazgeçtiğinde ekranda
        // asılı kalır.
        if (allergenEditItemId === item.id) {
            setAllergenEditItemId(null);

            return;
        }

        setAllergenEditItemId(item.id);
        setAllergensInput(item.allergens.join(', '));
        setAllergensSubmitError(null);
    }

    /**
     * Sunum düzenleyicisini açar/kapatır ve o anda hazır olan görselleri
     * çeker.
     *
     * Liste form AÇILINCA çekilir, sayfa yüklenince değil: menü ekranını
     * açan herkesin medya listesini indirmesi için sebep yok ve sahip
     * arada yeni bir fotoğraf yüklemiş olabilir.
     */
    async function handleEditPresentation(item: MenuItemRow) {
        if (presentationItemId === item.id) {
            setPresentationItemId(null);

            return;
        }

        setPresentationItemId(item.id);
        setDescriptionInput(item.description ?? '');
        setImageChoice(
            item.imageMediaAssetId === null || item.imageMediaAssetId === undefined
                ? ''
                : String(item.imageMediaAssetId),
        );
        setPresentationError(null);
        setAiDraftArtifactId(null);
        setAiSuggestionLoading(false);
        setAiSuggestionError(null);
        setAiSuggestionUncertain(false);
        setAiSuggestionUsedFallback(false);

        try {
            const response = await fetch(mediaUrl(workspaceId), buildAuthRequestInit());

            if (!response.ok) {
                return;
            }

            const body = (await response.json()) as { data?: ReadyMediaRow[] };

            // Yalnız İŞLENMESİ BİTMİŞ ve bu slota ait görseller seçilebilir:
            // hazır olmayan bir görseli seçtirmek, menüye kırık bir kutu
            // koymaya davet etmektir.
            setReadyMedia(
                (body.data ?? []).filter(
                    (row) => row.status === 'ready' && row.slot === 'itemImage',
                ),
            );
        } catch {
            setReadyMedia([]);
        }
    }

    /**
     * AI'dan açıklama taslağı ister — `docs/97` Yolculuk B.
     *
     * AI kapalıysa/bütçe yoksa (503) bu bir HATA değildir; sebep kısa bir
     * mesajla gösterilir, kutu boş kalır, elle yazma yolu bozulmaz
     * (`docs/96` `ai-no-credit-degradation`). Sağlayıcı yanıt vermezse
     * (502) ayrı bir mesaj — ikisi aynı cümle olursa sahip "AI hiç yok"
     * ile "şimdilik cevap vermedi"yi ayıramaz.
     */
    async function handleRequestAiDescription() {
        if (presentationItemId === null) return;

        setAiSuggestionLoading(true);
        setAiSuggestionError(null);

        try {
            const response = await postJson(
                descriptionDraftUrl(workspaceId, presentationItemId),
                {},
            );

            if (response.status === 503) {
                setAiSuggestionError(t('menu.item.ai.description.unavailable'));

                return;
            }

            if (!response.ok) {
                setAiSuggestionError(
                    await parseErrorMessage(response, t('menu.item.ai.description.error')),
                );

                return;
            }

            const body = (await response.json()) as {
                id: number;
                description: string;
                confidence: number;
                uncertainFieldCount: number;
                usedFallback: boolean;
            };

            setDescriptionInput(body.description);
            setAiDraftArtifactId(body.id);
            setAiSuggestionUncertain(body.uncertainFieldCount > 0);
            setAiSuggestionUsedFallback(body.usedFallback);
        } catch {
            setAiSuggestionError(t('menu.item.ai.description.error'));
        } finally {
            setAiSuggestionLoading(false);
        }
    }

    async function handleSavePresentation(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (presentationItemId === null || tree === null) return;

        const item = tree.categories
            .flatMap((category) => category.menuItems)
            .find((row) => row.id === presentationItemId);

        if (!item) return;

        setPresentationError(null);
        setSavingPresentation(true);

        try {
            const description = descriptionInput.trim();

            /*
                Bir AI taslağı varsa "Kaydet" ONAY uç noktasına gider —
                düzenlenmiş metni taşır ve taslağı `applied_at` ile
                imzalar (`docs/97` R4, `ApplyProductDescriptionDraft`).
                Yoksa bugüne kadarki düz yazma yolu değişmeden çalışır.
            */
            const detailsResponse =
                aiDraftArtifactId !== null
                    ? await postJson(applyDescriptionDraftUrl(workspaceId, aiDraftArtifactId), {
                          description,
                      })
                    : await postJson(
                          menuItemUrl(workspaceId, presentationItemId),
                          {
                              productName: item.productName ?? '',
                              description: description === '' ? null : description,
                          },
                          'PUT',
                      );

            if (!detailsResponse.ok) {
                setPresentationError(await parseErrorMessage(detailsResponse, t('menu.ops.error')));

                return;
            }

            const imageResponse = await postJson(
                menuItemImageUrl(workspaceId, presentationItemId),
                { mediaAssetId: imageChoice === '' ? null : Number(imageChoice) },
                'PUT',
            );

            if (!imageResponse.ok) {
                // Açıklama kaydedildi, fotoğraf kaydedilemedi. Bunu tek bir
                // "kaydedilemedi" ile anlatmak yalan olurdu; sahip neyin
                // olduğunu ve neyin olmadığını bilmeli.
                setPresentationError(
                    await parseErrorMessage(imageResponse, t('menu.item.presentation.error.image')),
                );

                const partial = await fetch(menuUrl(workspaceId, locationId));

                if (partial.ok) {
                    // Okunamayan bir gövde ekrandaki ağacı EZMEZ (bkz. `asMenuTree`).
                    const partialTree = asMenuTree(await partial.json());

                    if (partialTree !== null) {
                        setTree(partialTree);
                    }
                }

                return;
            }

            const refreshed = await fetch(menuUrl(workspaceId, locationId));

            if (refreshed.ok) {
                // Okunamayan bir gövde ekrandaki ağacı EZMEZ (bkz. `asMenuTree`).
                const refreshedTree = asMenuTree(await refreshed.json());

                if (refreshedTree !== null) {
                    setTree(refreshedTree);
                }
            }

            setPresentationItemId(null);
            setAiDraftArtifactId(null);
        } catch {
            setPresentationError(t('menu.ops.error'));
        } finally {
            setSavingPresentation(false);
        }
    }

    /**
     * Fotoğraftan içe aktarma bölümünü açar/kapatır — `docs/97` Yolculuk A.1.
     *
     * Açılınca HAZIR olan `menuImportSource` görsellerini çeker; sunum
     * düzenleyicideki `handleEditPresentation`'la aynı gecikmeli-yükleme
     * disiplini (form kapalıyken kimsenin listeyi indirmesine sebep yok).
     */
    async function handleToggleAiImport() {
        if (aiImportOpen) {
            setAiImportOpen(false);

            return;
        }

        setAiImportOpen(true);
        setAiImportReviewError(null);

        try {
            const response = await fetch(mediaUrl(workspaceId), buildAuthRequestInit());

            if (!response.ok) return;

            const body = (await response.json()) as { data?: ReadyMediaRow[] };

            setImportSourceMedia(
                (body.data ?? []).filter(
                    (row) => row.status === 'ready' && row.slot === 'menuImportSource',
                ),
            );
        } catch {
            setImportSourceMedia([]);
        }
    }

    /**
     * Seçilen fotoğrafı okur ve taslağı İNCELEMEYE açar — `docs/97`
     * Yolculuk A.3-6. Ham `fields`'i inceleme satırına çevirir; fiyatı
     * okunamayan/eksik satır da GÖSTERİLİR (AI-15) — kullanıcı "Ekle"ye
     * basmadan önce ne atlanacağını görür.
     */
    async function handleReadAiImport() {
        // Form henüz ekranda değilse söylenecek bir şey yok (`docs/47`
        // Kural 5 kapsamı dışı). Seçim boşluğu ayrıca sınanmaz: "Oku"
        // düğmesi zaten seçim yokken devre dışıdır — aynı kontrolü burada
        // sessizce tekrarlamak, kapının aradığı desenin ta kendisi olurdu.
        if (tree === null) return;

        setAiImportReviewLoading(true);
        setAiImportReviewError(null);
        setAiImportRows([]);
        setAiImportArtifactIds([]);
        setAiImportFailures([]);
        setAiImportApplyReport(null);
        setAiBatchProgress(null);
        setAiBatchSummary(null);

        /*
            TOPLU ORKESTRA (`docs/98` FF-75, `docs/adr/ADR-L11`): 10 sayfaya
            kadar tek istekte eşzamanlı; ötesi kuyruğa gider, parti kalıcı
            hafızada izlenir, toplayıcı yinelenenleri ayıklayıp TEK liste
            döner. Uygulama iki yolda da aynı insan-onaylı düğmedir.
        */
        if (importMediaChoices.length > INTERACTIVE_IMPORT_MAX_PHOTOS) {
            await handleReadAiBatch();
            return;
        }

        try {
            const storeResponse = await postJson(bulkAiImportsUrl(workspaceId, tree.id), {
                mediaAssetIds: importMediaChoices,
            });

            if (storeResponse.status === 503) {
                setAiImportReviewError(t('menu.item.ai.import.unavailable'));

                return;
            }

            if (!storeResponse.ok) {
                setAiImportReviewError(
                    await parseErrorMessage(storeResponse, t('menu.item.ai.import.error')),
                );

                return;
            }

            const stored = (await storeResponse.json()) as {
                results: {
                    mediaAssetId: number;
                    id?: number;
                    usedFallback?: boolean;
                    error?: string;
                }[];
            };

            /*
                KISMİ BAŞARISIZLIK: bir fotoğraf okunamazsa diğerlerinin
                sonucu ÇÖPE GİTMEZ — o satır sebebiyle listelenir, kalanlar
                incelemeye girer. Tümünü reddetmek, sahibi hiçbir şey
                kazanmadan baştan başlatırdı.
            */
            const failures = stored.results
                .filter((result) => result.id === undefined)
                .map((result) => ({
                    mediaAssetId: result.mediaAssetId,
                    error: result.error ?? 'unknown',
                }));

            const readable = stored.results.filter(
                (result): result is { mediaAssetId: number; id: number; usedFallback?: boolean } =>
                    typeof result.id === 'number',
            );

            setAiImportFailures(failures);
            setAiImportUsedFallback(readable.some((result) => result.usedFallback === true));

            const rows: AiImportRow[] = [];
            const artifactIds: number[] = [];

            for (const result of readable) {
                const showResponse = await fetch(
                    showAiImportUrl(workspaceId, result.id),
                    buildAuthRequestInit(),
                );

                if (!showResponse.ok) {
                    failures.push({ mediaAssetId: result.mediaAssetId, error: 'unreadable' });

                    continue;
                }

                const artifact = (await showResponse.json()) as {
                    fields: {
                        name: string;
                        value: {
                            category?: string;
                            product?: string;
                            priceMinorAmount?: number | null;
                            currencyCode?: string;
                        };
                        confidence: number;
                        uncertain: boolean;
                    }[];
                };

                artifactIds.push(result.id);

                for (const field of artifact.fields) {
                    rows.push({
                        artifactId: result.id,
                        name: field.name,
                        category: field.value.category ?? '',
                        product: field.value.product ?? '',
                        priceMinorAmount: field.value.priceMinorAmount ?? null,
                        currencyCode: field.value.currencyCode ?? '',
                        confidence: field.confidence,
                        uncertain: field.uncertain,
                    });
                }
            }

            setAiImportFailures([...failures]);
            setAiImportArtifactIds(artifactIds);
            setAiImportRows(rows);
        } catch {
            setAiImportReviewError(t('menu.item.ai.import.error'));
        } finally {
            setAiImportReviewLoading(false);
        }
    }

    async function handleReadAiBatch() {
        if (tree === null) return;
        try {
            const started = await postJson(aiBatchesUrl(workspaceId, tree.id), {
                mediaAssetIds: importMediaChoices,
            });
            if (started.status === 503) {
                setAiImportReviewError(t('menu.item.ai.import.unavailable'));
                return;
            }
            if (!started.ok) {
                setAiImportReviewError(
                    await parseErrorMessage(started, t('menu.item.ai.import.error')),
                );
                return;
            }
            const { id: batchId, totalPages } = (await started.json()) as {
                id: number;
                totalPages: number;
            };
            setAiBatchProgress({ done: 0, total: totalPages });

            // Parti kapanana kadar izle: ilerleme sayfa sayısıyla okunur.
            for (;;) {
                const shown = await fetch(
                    showAiBatchUrl(workspaceId, batchId),
                    buildAuthRequestInit(),
                );
                if (!shown.ok) {
                    setAiImportReviewError(t('menu.item.ai.import.error'));
                    return;
                }
                const batch = (await shown.json()) as {
                    state: string;
                    donePages: number;
                    failedPages: number;
                    totalPages: number;
                    summary: {
                        rows: AiImportRow[];
                        artifactIds: number[];
                        duplicatesSkipped: number;
                        failedPages: { mediaAssetId: number; reason: string }[];
                    } | null;
                };
                setAiBatchProgress({
                    done: batch.donePages + batch.failedPages,
                    total: batch.totalPages,
                });

                if (batch.state === 'collected' && batch.summary) {
                    setAiImportFailures(
                        batch.summary.failedPages.map((page) => ({
                            mediaAssetId: page.mediaAssetId,
                            error: page.reason,
                        })),
                    );
                    setAiImportArtifactIds(batch.summary.artifactIds);
                    setAiImportRows(batch.summary.rows);
                    setAiBatchSummary({
                        rows: batch.summary.rows.length,
                        pages: batch.donePages,
                        dupes: batch.summary.duplicatesSkipped,
                    });
                    return;
                }
                if (batch.state === 'failed') {
                    setAiImportReviewError(t('menu.item.ai.import.batch.failed'));
                    return;
                }
                await new Promise((resolve) => setTimeout(resolve, BATCH_POLL_MS));
            }
        } catch {
            setAiImportReviewError(t('menu.item.ai.import.error'));
        } finally {
            setAiImportReviewLoading(false);
            setAiBatchProgress(null);
        }
    }

    /**
     * İnsan onayı — TASLAĞA yazar, yayına dokunmaz (`docs/97` Yolculuk
     * A.9-11). `ApplyMenuArtifact` fiyatı/kategori-ürün adı okunamayan
     * satırı otomatik atlar ve sebebini döner; burada tek yapılan bu
     * sonucu göstermek.
     */
    async function handleApplyAiImport() {
        /*
            Boş liste kontrolü BİLEREK yok (`docs/47` Kural 5): "Ekle"
            düğmesi yalnız okunan satır varken çizilir, satır varsa taslak
            da vardır. Aynı koşulu burada sessizce tekrarlamak, kapının
            aradığı "basıldı ama hiçbir şey olmadı" desenini yaratırdı —
            ve olmayacak bir durum için sessiz kalmaktansa, sunucudan
            gelecek hatayı göstermek dürüst olan.
        */
        setAiImportApplying(true);
        setAiImportReviewError(null);

        try {
            const response = await postJson(bulkApplyAiImportUrl(workspaceId), {
                artifactIds: aiImportArtifactIds,
            });

            if (!response.ok) {
                setAiImportReviewError(
                    await parseErrorMessage(response, t('menu.item.ai.import.error')),
                );

                return;
            }

            const result = (await response.json()) as AiImportApplyReport;
            setAiImportApplyReport(result);
            // Uygulandı — taslak artık geçmiş; satırlar tekrar
            // uygulanabilir görünmemeli.
            setAiImportRows([]);
            setAiImportArtifactIds([]);

            const refreshed = await fetch(menuUrl(workspaceId, locationId));

            if (refreshed.ok) {
                // Okunamayan bir gövde ekrandaki ağacı EZMEZ (bkz. `asMenuTree`).
                const refreshedTree = asMenuTree(await refreshed.json());

                if (refreshedTree !== null) {
                    setTree(refreshedTree);
                }
            }
        } catch {
            setAiImportReviewError(t('menu.item.ai.import.error'));
        } finally {
            setAiImportApplying(false);
        }
    }

    /**
     * CSV aktarımı.
     *
     * `postJson` kullanılmaz: bu bir dosya yüklemesidir ve `Content-Type`
     * sınırını tarayıcının kendisi üretmeli. Elle `multipart/form-data`
     * yazmak, sınır dizesini kaybettirir ve sunucu boş bir gövde görür.
     */
    async function handleImport(file: File) {
        setImporting(true);
        setImportError(null);
        setImportReport(null);

        try {
            await bootstrapCsrfCookie();

            const form = new FormData();
            form.append('file', file);

            const init = buildAuthRequestInit();
            const headers = new Headers(init.headers);
            headers.delete('Content-Type');

            const response = await fetch(importUrl(workspaceId, tree?.id ?? 0), {
                ...init,
                method: 'POST',
                headers,
                body: form,
            });

            const body = (await response.json()) as {
                message?: string;
                importedItems?: number;
                importedCategories?: number;
                rejectedRows?: { line: number; reason: string }[];
            };

            if (!response.ok) {
                setImportError(body.message ?? t('menu.import.error'));

                return;
            }

            setImportReport({
                importedItems: body.importedItems ?? 0,
                importedCategories: body.importedCategories ?? 0,
                rejectedRows: body.rejectedRows ?? [],
            });

            const refreshed = await fetch(menuUrl(workspaceId, locationId));

            if (refreshed.ok) {
                // Okunamayan bir gövde ekrandaki ağacı EZMEZ (bkz. `asMenuTree`).
                const refreshedTree = asMenuTree(await refreshed.json());

                if (refreshedTree !== null) {
                    setTree(refreshedTree);
                }
            }
        } catch {
            setImportError(t('menu.import.error'));
        } finally {
            setImporting(false);
        }
    }

    /**
     * "Bugün tükendi" işaretler.
     *
     * YAYIN GEREKTİRMEZ (`docs/82`): balık servis sırasında biter ve yayın
     * beklemek hem yavaş hem tehlikelidir — taslakta yarım kalmış bir fiyat
     * düzenlemesi de canlıya giderdi.
     */
    /**
     * Bir kategorinin TAMAMINI tükendi/mevcut işaretle — `docs/82` kriter 3.
     * Arka uç (`PUT .../menu/{menu}/stock`) 2026-08-30'dan beri vardı; ekran
     * yalnız tek ürünü işaretliyordu. Akşam servisinde "balıklar bitti"
     * demek altı ayrı tıklama olmamalı.
     */
    async function handleCategoryStock(category: CategoryRow, outOfStock: boolean) {
        if (tree === null) return;

        const ids = category.menuItems.map((item) => item.id);
        setOperationError(null);
        setCategoryStockPending((current) => ({ ...current, [category.id]: true }));

        try {
            const response = await postJson(
                menuStockUrl(workspaceId, tree.id),
                outOfStock ? { outOfStock: ids, inStock: [] } : { outOfStock: [], inStock: ids },
                'PUT',
            );

            if (!response.ok) {
                setOperationError(await parseErrorMessage(response, t('menu.ops.error')));

                return;
            }

            setTree((current) =>
                current === null
                    ? current
                    : {
                          ...current,
                          categories: current.categories.map((row) =>
                              row.id === category.id
                                  ? {
                                        ...row,
                                        menuItems: row.menuItems.map((item) => ({
                                            ...item,
                                            outOfStock,
                                        })),
                                    }
                                  : row,
                          ),
                      },
            );
        } catch {
            setOperationError(t('menu.ops.error'));
        } finally {
            setCategoryStockPending((current) => ({ ...current, [category.id]: false }));
        }
    }

    async function handleToggleStock(item: MenuItemRow) {
        setStockPending((current) => ({ ...current, [item.id]: true }));
        setOperationError(null);

        const next = item.outOfStock !== true;

        try {
            const response = await postJson(
                stockUrl(workspaceId, item.id),
                { outOfStock: next },
                'PUT',
            );

            if (!response.ok) {
                setOperationError(await parseErrorMessage(response, t('menu.ops.error')));

                return;
            }

            setTree((current) =>
                current === null
                    ? current
                    : {
                          ...current,
                          categories: current.categories.map((category) => ({
                              ...category,
                              menuItems: category.menuItems.map((row) =>
                                  row.id === item.id ? { ...row, outOfStock: next } : row,
                              ),
                          })),
                      },
            );
        } catch {
            setOperationError(t('menu.ops.error'));
        } finally {
            setStockPending((current) => ({ ...current, [item.id]: false }));
        }
    }

    function handleEditPrice(item: MenuItemRow) {
        setPriceEditItemId(item.id);
        setPriceEditValue(minorAmountToDecimalString(item.priceMinorAmount, item.currencyCode));
        setPriceEditSubmitError(null);
    }

    /**
     * Bir kategorinin ürün ekleme formunu açar veya kapatır.
     *
     * Kategori artık bir ALAN değil, TIKLADIĞIN YER. Sahibinin tespiti
     * ("kategori ekleme bilgileri ile ürün ekleme bilgileri aynı formda
     * olmaz") bir adım öteye taşındı: Kebaplar'a bakarken Kebaplar'ı
     * listeden seçmek zorunda kalmak, sorulmayan bir soruya cevap
     * vermektir.
     */
    function toggleEntryForm(categoryId: number) {
        if (currentCategoryId === categoryId) {
            setCurrentCategoryId(null);

            return;
        }

        setCurrentCategoryId(categoryId);
        setEntrySubmitError(null);
        setProductNameError(null);
        setPriceError(null);
        setLastAddedEntry(null);
    }

    async function handleCreateMenu(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const trimmed = menuName.trim();
        if (!trimmed) {
            setMenuNameError(t('menu.name.error.required'));
            return;
        }
        setMenuNameError(null);
        setMenuSubmitError(null);
        setCreatingMenu(true);
        try {
            const response = await postJson(menuUrl(workspaceId, locationId), { name: trimmed });
            if (!response.ok) {
                setMenuSubmitError(
                    await parseErrorMessage(response, t('menu.create.error.submit')),
                );
                return;
            }
            const created = (await response.json()) as Omit<MenuTree, 'categories'>;
            setTree({ ...created, categories: [] });
            await refreshMenus();
        } catch {
            setMenuSubmitError(t('menu.create.error.submit'));
        } finally {
            setCreatingMenu(false);
        }
    }

    /* ---------------------------------------------------------------
       ÇOKLU MENÜ — sahibin 2026-09-05 kararı (`docs/109` §7.1).
       --------------------------------------------------------------- */

    /** Hapları sunucudan tazeler. Sessizdir: liste ikincil bir bilgidir. */
    async function refreshMenus(): Promise<void> {
        const rows = await fetchMenuRows(workspaceId, locationId);

        if (rows !== null) {
            setMenus(rows);
        }
    }

    /**
     * Hapa basıldığında O MENÜNÜN içeriğini getirir.
     *
     * Kaynağın kendi hapları burada yalnız bir bildirim gösteriyor,
     * kategori ve ürün listesini değiştirmiyordu. Bu çağrı olmasaydı sahip
     * "Kahvaltı"ya basar, ekranda hâlâ akşam menüsünü görür ve eklediği
     * ürünün hangi menüye gittiğini bir daha asla bilemezdi.
     */
    /**
     * Gövde GERÇEKTEN bir menü ağacı mı?
     *
     * Bu kontrol bir tip süslemesi değil, yaşanmış bir çökmenin cevabı: menü
     * uçlarından biri `categories` taşımayan bir 200 döndüğünde ekran
     * `tree.categories.some(...)` satırında patlıyor ve sahibin gördüğü şey boş
     * beyaz bir sayfa oluyordu — hatanın kendisi bile yazılmıyordu.
     *
     * `as MenuTree` derleyiciye bir SÖZ verir, sunucuya değil. Söz tutulmadığında
     * ekranın yapması gereken şey uydurmak (`categories: []`) değil — o, boş bir
     * menü göstermek olurdu ve sahip ürünlerinin silindiğini sanırdı — okunamayan
     * cevabı REDDEDİP kendi yükleme hatasını göstermektir.
     */
    function asMenuTree(body: unknown): MenuTree | null {
        if (typeof body !== 'object' || body === null) return null;

        const candidate = body as { id?: unknown; categories?: unknown };

        if (typeof candidate.id !== 'number' || !Array.isArray(candidate.categories)) {
            return null;
        }

        return body as MenuTree;
    }

    async function openMenu(menuId: number): Promise<void> {
        if (tree !== null && tree.id === menuId) return;

        setSwitchingMenu(true);
        setOperationError(null);

        try {
            const response = await fetch(menuByIdUrl(workspaceId, menuId));

            if (!response.ok) {
                setOperationError(t('menu.initial.error.load'));

                return;
            }

            const loaded = asMenuTree(await response.json());

            if (loaded === null) {
                setOperationError(t('menu.initial.error.load'));

                return;
            }

            setTree(loaded);
            // Menü değişti: eski menünün kategori seçimi yeni menüde
            // hiçbir şeye karşılık gelmez.
            setSelectedCategoryId(null);
            setCurrentCategoryId(null);
        } catch {
            setOperationError(t('menu.initial.error.load'));
        } finally {
            setSwitchingMenu(false);
        }
    }

    function openMenuCreatePanel(): void {
        setMenuPanel({ mode: 'create' });
        setMenuFormName('');
        setMenuFormStartsAt('');
        setMenuFormEndsAt('');
        setMenuFormError(null);
        setMenuPendingDelete(null);
    }

    function openMenuEditPanel(menuId: number): void {
        const menu = menus.find((row) => row.id === menuId) ?? null;

        setMenuPanel({ mode: 'edit', menuId });
        setMenuFormName(menu?.name ?? tree?.name ?? '');
        setMenuFormStartsAt(menu?.startsAt ?? '');
        setMenuFormEndsAt(menu?.endsAt ?? '');
        setMenuFormError(null);
        setMenuPendingDelete(null);
    }

    function closeMenuPanel(): void {
        setMenuPanel(null);
        setMenuFormError(null);
        setMenuPendingDelete(null);
    }

    /**
     * Menü formunu kaydeder.
     *
     * Ad ve saat AYNI GÖNDERİMDE gider ama iki ayrı yola: ad menünün
     * kendi kaydıdır, saat ise ŞUBENİN gününü böler. İkisini tek bir
     * uç noktaya sıkıştırmak, "adı düzelttim, saatler neden değişti"
     * sorusunu doğururdu.
     */
    async function submitMenuPanel(event: FormEvent<HTMLFormElement>): Promise<void> {
        event.preventDefault();

        if (menuPanel === null) return;

        const name = menuFormName.trim();

        if (!name) {
            setMenuFormError(t('menu.name.error.required'));

            return;
        }

        const startsAt = menuFormStartsAt.trim();
        const endsAt = menuFormEndsAt.trim();

        // Saat İSTEĞE BAĞLIDIR: menü önce kurulur, saatini sahip sonra
        // verir. Ama yarısı verilemez — "07:00'de başlar, hiç bitmez"
        // cümlesi bir aralık değildir.
        if ((startsAt === '') !== (endsAt === '')) {
            setMenuFormError(t('menu.window.error.incomplete'));

            return;
        }

        setMenuFormBusy(true);
        setMenuFormError(null);

        try {
            let menuId = menuPanel.mode === 'edit' ? menuPanel.menuId : null;

            if (menuId === null) {
                const created = await postJson(menuUrl(workspaceId, locationId), { name });

                if (!created.ok) {
                    setMenuFormError(
                        await parseErrorMessage(created, t('menu.create.error.submit')),
                    );

                    return;
                }

                menuId = ((await created.json()) as { id: number }).id;
            } else {
                const renamed = await postJson(menuByIdUrl(workspaceId, menuId), { name }, 'PUT');

                if (!renamed.ok) {
                    setMenuFormError(await parseErrorMessage(renamed, t('menu.save.error.submit')));

                    return;
                }
            }

            if (startsAt !== '' && endsAt !== '') {
                const scheduled = await postJson(
                    menuServiceWindowUrl(workspaceId, menuId),
                    { startsAt, endsAt },
                    'PUT',
                );

                if (!scheduled.ok) {
                    setMenuFormError(
                        await parseErrorMessage(scheduled, t('menu.window.error.submit')),
                    );

                    return;
                }
            }

            await refreshMenus();
            await openMenu(menuId);
            closeMenuPanel();
        } catch {
            setMenuFormError(t('menu.save.error.submit'));
        } finally {
            setMenuFormBusy(false);
        }
    }

    /** "Ramazan kapalı": menü silinmez, yalnız rotasyondan çıkar. */
    async function disableMenu(menuId: number): Promise<void> {
        setMenuFormBusy(true);
        setMenuFormError(null);

        try {
            const response = await postJson(
                menuServiceWindowUrl(workspaceId, menuId),
                {},
                'DELETE',
            );

            if (!response.ok) {
                setMenuFormError(await parseErrorMessage(response, t('menu.save.error.submit')));

                return;
            }

            await refreshMenus();
        } catch {
            setMenuFormError(t('menu.save.error.submit'));
        } finally {
            setMenuFormBusy(false);
        }
    }

    /**
     * Menüyü siler.
     *
     * Sunucu şubenin SON menüsünü silmeyi reddeder (409) ve o cümle
     * burada olduğu gibi gösterilir: sebebi sunucu bilir, ekran uydurmaz.
     */
    async function deleteMenu(menuId: number): Promise<void> {
        setMenuFormBusy(true);
        setMenuFormError(null);

        try {
            const response = await postJson(menuByIdUrl(workspaceId, menuId), {}, 'DELETE');

            if (!response.ok) {
                setMenuFormError(await parseErrorMessage(response, t('menu.delete.error.submit')));

                return;
            }

            const remaining = menus.filter((row) => row.id !== menuId);

            await refreshMenus();
            closeMenuPanel();

            if (remaining.length > 0) {
                await openMenu(remaining[0].id);
            }
        } catch {
            setMenuFormError(t('menu.delete.error.submit'));
        } finally {
            setMenuFormBusy(false);
            setMenuPendingDelete(null);
        }
    }

    /**
     * Hapın tek satırlık saat ipucu.
     *
     * Uydurulmaz: her dal sunucudan gelen gerçek bir hâle karşılık gelir.
     * `startsAt === endsAt` "tüm gün" demektir — sıfır uzunlukta bir
     * aralık değil, günün tamamı (`docs/109` §7.1).
     */
    function menuHint(menu: MenuRow): string {
        if (menu.state === 'disabled') return t('menu.pill.disabled');
        if (menu.startsAt === null || menu.endsAt === null) return t('menu.pill.draft');
        if (menu.startsAt === menu.endsAt) return t('menu.pill.allDay');

        const range = `${menu.startsAt}–${menu.endsAt}`;

        return menu.windows.length > 1
            ? t('menu.pill.moreWindows', {
                  range,
                  count: String(menu.windows.length - 1),
              })
            : range;
    }

    /**
     * Ekrandaki haplar.
     *
     * Liste sunucudan henüz gelmediyse AÇIK OLAN MENÜNÜN kendisi tek hap
     * olarak çizilir: ekranda bir menü varken hap sırasının boş durması,
     * sahibe "menün yok" der.
     *
     * SIRALAMA BURADA YAPILMAZ. Sunucu menüleri günün akışına göre gönderir
     * (en erken servis penceresi önce, saati olmayanlar sonda) ve ekran o
     * sırayı aynen çizer. Bir de burada sıralasaydık iki gerçek olurdu ve
     * bir gün ayrışırlardı — aynı liste yarın başka bir tüketiciye
     * gittiğinde sıra orada başka türlü çıkardı.
     */
    function menuPills(): MenuPill[] {
        const rows: MenuRow[] = [...menus];

        /*
            AÇIK OLAN MENÜ HER ZAMAN BİR HAPTIR.

            Liste sunucudan henüz gelmediyse ya da yeni açılmış bir menüyü
            henüz içermiyorsa, ekranda duran menünün hapı olmazdı: sahip
            "Gece" menüsünü kurar, ürünlerini ekler, ama hangi menüde
            olduğunu ekranda göremezdi. Seçili hap aynı zamanda ekranın
            başlığıdır; kaybolması başlığı da götürürdü.

            SONA eklenmesi sunucunun kuralıyla aynıdır: yeni menü henüz
            rotasyona girmemiştir (`draft`, saatsiz) ve saati olmayan menü
            günün bir yerine değil, saatlilerin ardına düşer.
        */
        if (tree !== null && !rows.some((row) => row.id === tree.id)) {
            rows.push({
                id: tree.id,
                name: tree.name,
                state: tree.state,
                sortOrder: rows.length,
                startsAt: null,
                endsAt: null,
                windows: [],
                isServingNow: rows.length === 0,
                isAddressAnchor: rows.length === 0,
            });
        }

        return rows.map((menu) => ({
            id: menu.id,
            name: menu.name,
            hint: menuHint(menu),
            isSelected: tree !== null && tree.id === menu.id,
            isServingNow: menu.isServingNow,
        }));
    }

    async function handleCreateCategory(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!tree) return;
        const trimmed = categoryName.trim();
        if (!trimmed) {
            setCategoryNameError(t('menu.category.name.error.required'));
            return;
        }
        setCategoryNameError(null);
        setCategorySubmitError(null);
        setCreatingCategory(true);
        try {
            const response = await postJson(categoriesUrl(workspaceId, tree.id), { name: trimmed });
            if (!response.ok) {
                setCategorySubmitError(
                    await parseErrorMessage(response, t('menu.category.create.error.submit')),
                );
                return;
            }
            const created = (await response.json()) as Omit<CategoryRow, 'menuItems'>;

            // Yeni kategori HEMEN seçili olur ve form kapanır: kategoriyi
            // eklemenin sebebi neredeyse her zaman içine ürün koymaktır.
            //
            // RAY de o kategoriye geçer. Geçmeseydi sahip "Çorbalar"ı
            // ekler, ekranda hâlâ "Kebaplar"ı görür ve az önce yarattığı
            // kategoriyi rayda aramak zorunda kalırdı.
            setCurrentCategoryId(created.id);
            setSelectedCategoryId(created.id);
            setCategoryName('');
            setCategoryFormOpen(false);
            setTree((previous) =>
                previous
                    ? {
                          ...previous,
                          categories: [...previous.categories, { ...created, menuItems: [] }],
                      }
                    : previous,
            );
        } catch {
            setCategorySubmitError(t('menu.category.create.error.submit'));
        } finally {
            setCreatingCategory(false);
        }
    }

    /**
     * Menüye bir ürün ekler — TEK gönderim.
     *
     * Öncesinde bu üç ayrı formdu ve sırayla doldurulurdu: ürün adı kaydet,
     * sonra beliren fiyat formunu kaydet, sonra beliren alerjen formunu
     * kaydet. Kullanıcı için hepsi tek bir iştir: "menüye kebap ekle".
     *
     * Sunucu tarafında da tek işlemdir; ikinci adım düşerse hiçbir menüde
     * görünmeyen öksüz bir ürün geride kalmaz.
     */
    async function handleAddMenuEntry(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (currentCategoryId === null) return;

        const trimmedName = productName.trim();
        const trimmedPrice = price.trim();

        // İki alan da AYNI ANDA doğrulanır. Tek tek doğrulamak, kullanıcıyı
        // aynı formu iki kez göndermeye zorlar: önce adı düzeltir, sonra
        // fiyat hatasını görür.
        const nameError = trimmedName ? '' : t('menu.product.name.error.required');
        const priceFieldError = trimmedPrice ? '' : t('menu.item.price.error.required');

        setProductNameError(nameError || null);
        setPriceError(priceFieldError || null);

        if (nameError || priceFieldError) {
            focusFirstInvalidField({ 'product-name': nameError, 'item-price': priceFieldError }, [
                'product-name',
                'item-price',
            ]);

            return;
        }

        setEntrySubmitError(null);
        setLastAddedEntry(null);
        setCreatingEntry(true);

        try {
            const currency = brand?.currency ?? 'TRY';
            const response = await postJson(menuEntriesUrl(workspaceId, currentCategoryId), {
                productName: trimmedName,
                price: trimmedPrice,
                currency,
                allergens: parseAllergens(entryAllergens),
            });

            if (!response.ok) {
                setEntrySubmitError(
                    await parseErrorMessage(response, t('menu.entry.error.submit')),
                );

                return;
            }

            const created = (await response.json()) as MenuItemRow & { categoryId: number };

            setTree((previous) => {
                if (!previous) return previous;

                return {
                    ...previous,
                    categories: previous.categories.map((category) =>
                        category.id === created.categoryId
                            ? { ...category, menuItems: [...category.menuItems, created] }
                            : category,
                    ),
                };
            });

            // Form temizlenir ki sıradaki ürün hemen yazılabilsin: menü
            // doldurmak tek seferlik değil, ARDA ARDA yapılan bir iştir.
            setProductName('');
            setPrice('');
            setEntryAllergens('');
            setLastAddedEntry(
                t('menu.entry.success', {
                    name: created.productName ?? trimmedName,
                    category:
                        tree?.categories.find((category) => category.id === currentCategoryId)
                            ?.name ?? '',
                }),
            );
        } catch {
            setEntrySubmitError(t('menu.entry.error.submit'));
        } finally {
            setCreatingEntry(false);
        }
    }

    async function handleSaveAllergens(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (allergenEditItemId === null) return;
        setAllergensSubmitError(null);
        setSavingAllergens(true);
        try {
            const allergens = parseAllergens(allergensInput);
            const response = await postJson(
                allergensUrl(workspaceId, allergenEditItemId),
                { allergens },
                'PUT',
            );
            if (!response.ok) {
                setAllergensSubmitError(
                    await parseErrorMessage(response, t('menu.item.allergens.error.submit')),
                );
                return;
            }
            const refreshed = await fetch(menuUrl(workspaceId, locationId));
            if (refreshed.ok) {
                // Okunamayan bir gövde ekrandaki ağacı EZMEZ (bkz. `asMenuTree`).
                const refreshedTree = asMenuTree(await refreshed.json());

                if (refreshedTree !== null) {
                    setTree(refreshedTree);
                }
            }
            // Kaydedince düzenleyici kapanır: açık kalması, işin bitmediği
            // izlenimi verirdi.
            setAllergenEditItemId(null);
        } catch {
            setAllergensSubmitError(t('menu.item.allergens.error.submit'));
        } finally {
            setSavingAllergens(false);
        }
    }

    async function handleSavePrice(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (priceEditItemId === null || !tree) return;
        const item = tree.categories
            .flatMap((category) => category.menuItems)
            .find((row) => row.id === priceEditItemId);
        if (!item) return;
        const trimmed = priceEditValue.trim();
        setPriceEditSubmitError(null);
        setSavingPriceEdit(true);
        try {
            const response = await postJson(
                priceUrl(workspaceId, priceEditItemId),
                { price: trimmed, currency: item.currencyCode },
                'PUT',
            );
            if (!response.ok) {
                setPriceEditSubmitError(
                    await parseErrorMessage(response, t('menu.item.price.edit.error.submit')),
                );
                return;
            }
            const updated = (await response.json()) as {
                priceMinorAmount: number;
                currencyCode: string;
            };
            setTree((previous) => {
                if (!previous) return previous;
                return {
                    ...previous,
                    categories: previous.categories.map((category) => ({
                        ...category,
                        menuItems: category.menuItems.map((row) =>
                            row.id === priceEditItemId
                                ? {
                                      ...row,
                                      priceMinorAmount: updated.priceMinorAmount,
                                      currencyCode: updated.currencyCode,
                                  }
                                : row,
                        ),
                    })),
                };
            });
            setPriceEditItemId(null);
            setPriceEditValue('');
            // Kaydedildi ≠ masada göründü (`docs/101` Y3/A2).
            setPendingPublishNotice(true);
        } catch {
            setPriceEditSubmitError(t('menu.item.price.edit.error.submit'));
        } finally {
            setSavingPriceEdit(false);
        }
    }

    /**
     * Ürünü menüden çıkarır.
     *
     * Onay istenir ve GERİ ALINAMAZ olduğu söylenir: yayınlanmış sürüm
     * etkilenmez ama taslaktaki satır geri gelmez.
     */
    async function handleDeleteItem(item: MenuItemRow) {
        setOperationError(null);

        try {
            const response = await postJson(menuItemUrl(workspaceId, item.id), {}, 'DELETE');

            if (!response.ok) {
                setOperationError(await parseErrorMessage(response, t('menu.ops.error')));

                return;
            }

            setTree((current) =>
                current === null
                    ? current
                    : {
                          ...current,
                          categories: current.categories.map((category) => ({
                              ...category,
                              menuItems: category.menuItems.filter((row) => row.id !== item.id),
                          })),
                      },
            );
        } catch {
            setOperationError(t('menu.ops.error'));
        }
    }

    async function handleDeleteCategory(category: CategoryRow) {
        setOperationError(null);

        try {
            const response = await postJson(categoryUrl(workspaceId, category.id), {}, 'DELETE');

            if (!response.ok) {
                setOperationError(await parseErrorMessage(response, t('menu.ops.error')));

                return;
            }

            setTree((current) =>
                current === null
                    ? current
                    : {
                          ...current,
                          categories: current.categories.filter((row) => row.id !== category.id),
                      },
            );
        } catch {
            setOperationError(t('menu.ops.error'));
        }
    }

    /**
     * Adı düzeltir — SATIR İÇİNDE, tarayıcı diyaloguyla değil.
     *
     * Önceki hâl `window.prompt` idi ve o bir ara adımdı: sorun "düzeltmenin
     * YOLU YOK"tu, yol açıldı. Ama tarayıcı diyalogu ürünün dışında çizilir,
     * düzenlenen satırı gizler, doğrulama gösteremez ve kullanıcı bir kez
     * "bu sayfa başka diyalog açmasın" derse SESSİZCE ölür — düğme çalışır
     * görünüp hiçbir şey yapmaz (sahibin bildirimi, 2026-09-04).
     *
     * Artık ad, durduğu yerde bir alana dönüşür. Bu fonksiyon yalnız
     * KAYDETMEYİ bilir: hata mesajını döndürür, `null` başarıdır — mesajı
     * kimin nereye çizeceği çağıranın işidir ve o da satırın altıdır.
     */
    async function handleRename(
        kind: 'category' | 'item',
        id: number,
        currentName: string,
        next: string,
    ): Promise<string | null> {
        const trimmed = next.trim();

        if (trimmed === '') {
            return t('menu.rename.error.empty');
        }

        if (trimmed === currentName) {
            return null;
        }

        setOperationError(null);

        try {
            const response = await postJson(
                kind === 'category' ? categoryUrl(workspaceId, id) : menuItemUrl(workspaceId, id),
                kind === 'category' ? { name: trimmed } : { productName: trimmed },
                'PUT',
            );

            if (!response.ok) {
                return await parseErrorMessage(response, t('menu.ops.error'));
            }

            setTree((current) =>
                current === null
                    ? current
                    : {
                          ...current,
                          categories: current.categories.map((category) =>
                              kind === 'category'
                                  ? category.id === id
                                      ? { ...category, name: trimmed }
                                      : category
                                  : {
                                        ...category,
                                        menuItems: category.menuItems.map((row) =>
                                            row.id === id ? { ...row, productName: trimmed } : row,
                                        ),
                                    },
                          ),
                      },
            );

            return null;
        } catch {
            return t('menu.ops.error');
        }
    }

    /**
     * Bir satırı bir basamak yukarı/aşağı taşır.
     *
     * Sürükle-bırak değil: sürükleme dokunmatik ekranda ve klavyeyle
     * güvenilir değildir ve ayrı bir erişilebilirlik sözleşmesi ister.
     * Yukarı/aşağı düğmesi her girdi yöntemiyle çalışır.
     *
     * İstek TOPLU gider: `unique(position)` kısıtı yüzünden iki satırı tek
     * tek güncellemek yolun ortasında çakışır.
     */
    async function moveItem(category: CategoryRow, index: number, direction: -1 | 1) {
        const target = index + direction;

        if (target < 0 || target >= category.menuItems.length) {
            return;
        }

        const ids = category.menuItems.map((row) => row.id);
        [ids[index], ids[target]] = [ids[target], ids[index]];

        await applyOrder(
            itemOrderUrl(workspaceId, category.id),
            { menuItemIds: ids },
            (current) => ({
                ...current,
                categories: current.categories.map((row) =>
                    row.id === category.id
                        ? {
                              ...row,
                              menuItems: ids
                                  .map((id) => row.menuItems.find((item) => item.id === id))
                                  .filter((item): item is MenuItemRow => item !== undefined),
                          }
                        : row,
                ),
            }),
        );
    }

    async function moveCategory(index: number, direction: -1 | 1) {
        if (tree === null) {
            return;
        }

        const target = index + direction;

        if (target < 0 || target >= tree.categories.length) {
            return;
        }

        const ids = tree.categories.map((category) => category.id);
        [ids[index], ids[target]] = [ids[target], ids[index]];

        await applyOrder(
            categoryOrderUrl(workspaceId, tree.id),
            { categoryIds: ids },
            (current) => ({
                ...current,
                categories: ids
                    .map((id) => current.categories.find((category) => category.id === id))
                    .filter((category): category is CategoryRow => category !== undefined),
            }),
        );
    }

    /**
     * Kategoriyi SÜRÜKLEYEREK taşır — kanonik teslim paketi (`DESIGN_SPEC`
     * §3: "Satır: sürükleme tutamacı + ad + sayı").
     *
     * `moveCategory` iki komşuyu TAKAS eder; sürükleme ise sırayı bozmadan
     * ARAYA SOKAR. Fark ekranda görülür: altı kategorilik bir menüde
     * "Tatlılar"ı en üste çekmek takasla beş ayrı hamle, araya sokmayla tek
     * hamledir — ve aradaki dördünün göreli sırası korunur.
     *
     * Yukarı/aşağı düğmeleri KALDI: sürükleme dokunmatikte ve klavyeyle
     * güvenilir değildir, o yüzden ikinci bir yol değil, TEK GÜVENİLİR
     * yoldur; tutamaç onun fare kısayoludur.
     */
    async function reorderCategoryByDrag(sourceId: number, targetId: number) {
        if (tree === null || sourceId === targetId) {
            return;
        }

        const ids = tree.categories.map((category) => category.id);
        const from = ids.indexOf(sourceId);
        const to = ids.indexOf(targetId);

        if (from < 0 || to < 0) {
            return;
        }

        ids.splice(to, 0, ids.splice(from, 1)[0] as number);

        await applyOrder(
            categoryOrderUrl(workspaceId, tree.id),
            { categoryIds: ids },
            (current) => ({
                ...current,
                categories: ids
                    .map((id) => current.categories.find((category) => category.id === id))
                    .filter((category): category is CategoryRow => category !== undefined),
            }),
        );
    }

    /**
     * Ürün ayrıntı çekmecesi AÇIK MI?
     *
     * Üç düzenleyici (sunum, alerjen, fiyat) daha önce satırın ALTINDA üç
     * ayrı kutu olarak açılıyordu; aşağıdaki bütün ürünler kayıyor ve
     * sahibin baktığı satır ekrandan çıkıyordu. Üçü de aynı sorunun
     * parçasıdır — "bu ürünü misafire nasıl göstereceğim" — ve referansta
     * tek bir panelde durur.
     */
    function inspectorOpenFor(itemId: number): boolean {
        return (
            presentationItemId === itemId ||
            allergenEditItemId === itemId ||
            priceEditItemId === itemId
        );
    }

    /** Paneli kapatır: hangi bölüm açıksa hepsi birlikte kapanır. */
    function closeInspector() {
        setPresentationItemId(null);
        setAllergenEditItemId(null);
        setPriceEditItemId(null);
    }

    async function applyOrder(
        url: string,
        body: Record<string, number[]>,
        reorder: (current: MenuTree) => MenuTree,
    ) {
        setOperationError(null);

        try {
            const response = await postJson(url, body, 'PUT');

            if (!response.ok) {
                setOperationError(await parseErrorMessage(response, t('menu.ops.error')));

                return;
            }

            setTree((current) => (current === null ? current : reorder(current)));
        } catch {
            setOperationError(t('menu.ops.error'));
        }
    }

    async function handleToggleVisibility(item: MenuItemRow) {
        const nextVisible = !item.isVisible;
        setVisibilityErrors((previous) => ({ ...previous, [item.id]: null }));
        setVisibilityPending((previous) => ({ ...previous, [item.id]: true }));
        try {
            const response = await postJson(
                visibilityUrl(workspaceId, item.id),
                { isVisible: nextVisible },
                'PUT',
            );
            if (!response.ok) {
                const message = await parseErrorMessage(
                    response,
                    t('menu.item.visibility.error.submit'),
                );
                setVisibilityErrors((previous) => ({ ...previous, [item.id]: message }));
                return;
            }
            const updated = (await response.json()) as { id: number; isVisible: boolean };
            if (tree) {
                const nextTree: MenuTree = {
                    ...tree,
                    categories: tree.categories.map((category) => ({
                        ...category,
                        menuItems: category.menuItems.map((row) =>
                            row.id === updated.id ? { ...row, isVisible: updated.isVisible } : row,
                        ),
                    })),
                };
                setTree(nextTree);
            }
        } catch {
            setVisibilityErrors((previous) => ({
                ...previous,
                [item.id]: t('menu.item.visibility.error.submit'),
            }));
        } finally {
            setVisibilityPending((previous) => ({ ...previous, [item.id]: false }));
        }
    }

    if (initialLoading) {
        return (
            <div role="status" aria-label="Loading">
                {t('menu.loading')}
            </div>
        );
    }

    if (initialError) {
        return (
            <div className={sectionClass}>
                <FieldError message={t('menu.initial.error.load')} />
                <button
                    type="button"
                    className={buttonClass}
                    onClick={() => setRetryToken((token) => token + 1)}
                >
                    {t('menu.initial.error.retry')}
                </button>
            </div>
        );
    }

    const menuHasItems =
        tree !== null && tree.categories.some((category) => category.menuItems.length > 0);

    /*
        SEÇİLİ KATEGORİ, HER ZAMAN GERÇEK BİR KATEGORİ.

        Seçim `useEffect` ile değil, çizim anında düzeltilir. Sebebi
        somut: sahip "Tatlılar"a bakarken o kategoriyi siliyor. Efektle
        düzeltseydik, silme ile efektin çalışması arasındaki bir kare
        boyunca ekranda var olmayan bir kategorinin başlığı durur, sonra
        yerinden zıplardı. Burada düşen kategori hiç çizilmez.
    */
    const visibleCategory =
        tree === null
            ? null
            : (tree.categories.find((category) => category.id === selectedCategoryId) ??
              tree.categories[0] ??
              null);

    return (
        <div className={clsx('flex flex-col gap-6', 'text-fg')}>
            {busy ? <p role="status">{t('menu.status.saving')}</p> : null}

            {/*
                İşletme hatalarının TEK yeri (`docs/73`). Her satıra ayrı bir
                hata koymak aynı cümleyi beş yerde tutmak olurdu.
            */}
            {operationError !== null ? <FieldError message={operationError} /> : null}

            {!tree ? (
                <form className={sectionClass} onSubmit={handleCreateMenu} noValidate>
                    <label className={labelClass} htmlFor="menu-name">
                        {t('menu.name.label')}
                    </label>
                    <TextInput
                        id="menu-name"
                        type="text"
                        value={menuName}
                        onChange={(event) => setMenuName(event.target.value)}
                    />
                    {menuNameError ? <FieldError message={menuNameError} /> : null}
                    {menuSubmitError ? <FieldError message={menuSubmitError} /> : null}
                    <button type="submit" className={buttonClass} disabled={creatingMenu}>
                        {t('menu.create.submit')}
                    </button>
                </form>
            ) : (
                <>
                    {/*
                        EKRANIN EYLEM ŞERİDİ — kanonik kaynak `panel.dc.html`
                        satır 30199-30209. Menü kimliği solda, dört eylem
                        sağda; ikisi de başlığın hizasında.

                        Buradaki "Ürün ekle", seçili kategorinin ürün
                        formunu açar. Kaynakta da bu düğme tek ve
                        ekranın üstündedir; her kategorinin kendi ekleme
                        düğmesi olmasına gerek kalmadı, çünkü ekranda
                        artık aynı anda tek kategori duruyor — "hangi
                        kategoriye ekliyorum" sorusu doğmuyor.
                    */}
                    <MenuScreenActions
                        label={t('menu.actions.label')}
                        /*
                            MENÜ HAPLARI — sahibin 2026-09-05 kararı
                            (`docs/109` §7.1). Bir hapa basmak GERÇEKTEN
                            o menünün kategorilerini ve ürünlerini getirir;
                            "+" yeni menü açar, "Menüyü düzenle" seçili
                            menünün adını ve saatini değiştirir.
                        */
                        menusLabel={t('menu.pills.label')}
                        menus={menuPills()}
                        onSelectMenu={(menuId) => void openMenu(menuId)}
                        addMenuLabel={t('menu.pills.add')}
                        /*
                            MUTFAK ŞERİTTE YALNIZ HAPLARI GÖRÜR. Hangi menüye
                            baktığını seçmek gerekir — akşam menüsündeki
                            levreği işaretlemek için önce o menüye geçilir —
                            ama menü açmak, adını değiştirmek, ürün eklemek ve
                            içe aktarmak `menu.manage` işidir.
                        */
                        onAddMenu={canManageMenu ? openMenuCreatePanel : null}
                        editMenuLabel={t('menu.pills.edit')}
                        onEditMenu={canManageMenu ? openMenuEditPanel : null}
                        servingNowLabel={t('menu.pill.servingNow')}
                        photoImport={canManageMenu ? photoImportState() : null}
                        csvLabel={t('menu.actions.csv')}
                        onCsv={canManageMenu ? () => setCsvPanelOpen((open) => !open) : null}
                        previewAndPublishLabel={t('menu.actions.previewAndPublish')}
                        /*
                            YAYINLAMA BU ŞERİTTE DEĞİL, BİR SATIR YUKARIDA.

                            Kaynak onu bu sıraya koyuyor (satır 30207) ve
                            konum olarak orası zaten karşılanıyor: sayfa
                            başlığının yanındaki eylem yuvası (`MenuPage`).
                            Buraya da konsaydı aynı işi yapan iki düğme
                            olurdu; yalnız buraya konsaydı, menü sunucudan
                            gelene kadar yayınlama yolu ekranda hiç
                            görünmezdi. Gerekçenin tamamı `MenuPage.tsx`'te.
                        */
                        onPreviewAndPublish={null}
                        addProductLabel={t('menu.entry.open')}
                        onAddProduct={
                            canManageMenu
                                ? () => {
                                      if (visibleCategory !== null) {
                                          toggleEntryForm(visibleCategory.id);
                                      }
                                  }
                                : null
                        }
                    />

                    {switchingMenu ? (
                        <p role="status" className="text-meta text-fg-secondary">
                            {t('menu.pill.switching')}
                        </p>
                    ) : null}

                    {/*
                        MENÜ FORMU — ekleme ve düzenleme AYNI form.

                        İki ayrı form olsaydı sahip "yeni menü"de saat
                        alanı görür, "düzenle"de görmezdi ya da tersi;
                        aynı kavramın iki farklı yüzü olurdu. Saat isteğe
                        bağlıdır: menü önce kurulur, saatini sahip sonra
                        verir.
                    */}
                    {menuPanel !== null ? (
                        <form
                            className={sectionClass}
                            onSubmit={(event) => void submitMenuPanel(event)}
                            noValidate
                            aria-label={
                                menuPanel.mode === 'create'
                                    ? t('menu.pills.add')
                                    : t('menu.pills.edit')
                            }
                        >
                            <div className="flex flex-col gap-[var(--space-1)]">
                                <label className={labelClass} htmlFor="menu-panel-name">
                                    {t('menu.name.label')}
                                </label>
                                <TextInput
                                    id="menu-panel-name"
                                    type="text"
                                    value={menuFormName}
                                    onChange={(event) => setMenuFormName(event.target.value)}
                                />
                            </div>

                            {/*
                                320px'de TEK SÜTUN: iki saat alanını dar
                                ekranda yan yana sıkıştırmak, ikisini de
                                okunamayacak kadar daraltırdı.
                            */}
                            <div className="grid grid-cols-1 gap-[var(--space-4)] sm:grid-cols-2">
                                <div className="flex flex-col gap-[var(--space-1)]">
                                    <label className={labelClass} htmlFor="menu-panel-starts-at">
                                        {t('menu.window.startsAt.label')}
                                    </label>
                                    <TextInput
                                        id="menu-panel-starts-at"
                                        type="time"
                                        value={menuFormStartsAt}
                                        onChange={(event) =>
                                            setMenuFormStartsAt(event.target.value)
                                        }
                                    />
                                </div>
                                <div className="flex flex-col gap-[var(--space-1)]">
                                    <label className={labelClass} htmlFor="menu-panel-ends-at">
                                        {t('menu.window.endsAt.label')}
                                    </label>
                                    <TextInput
                                        id="menu-panel-ends-at"
                                        type="time"
                                        value={menuFormEndsAt}
                                        onChange={(event) => setMenuFormEndsAt(event.target.value)}
                                    />
                                </div>
                            </div>

                            {/*
                                Kuralın kendisi EKRANDA yazılıdır. Sahip
                                "kahvaltı 07–11" der ve 11:00'de ne
                                olacağını bilmek zorundadır; bilmezse
                                akşam menüsünün geri gelip gelmeyeceğini
                                denemek için misafiri bekler.
                            */}
                            <p className="text-meta text-fg-secondary">{t('menu.window.help')}</p>

                            {menuFormError !== null ? <FieldError message={menuFormError} /> : null}

                            <div className="flex flex-wrap items-center gap-[var(--space-2)]">
                                <button
                                    type="submit"
                                    className={buttonClass}
                                    disabled={menuFormBusy}
                                >
                                    {t('menu.save.submit')}
                                </button>
                                <button
                                    type="button"
                                    className={inlineActionClass}
                                    onClick={closeMenuPanel}
                                    disabled={menuFormBusy}
                                >
                                    {t('menu.entry.cancel')}
                                </button>

                                {menuPanel.mode === 'edit' ? (
                                    <>
                                        <button
                                            type="button"
                                            className={inlineActionClass}
                                            onClick={() => void disableMenu(menuPanel.menuId)}
                                            disabled={menuFormBusy}
                                        >
                                            {t('menu.window.disable')}
                                        </button>

                                        {/*
                                            SİLME İKİ ADIMDIR. Menü
                                            silmek altmış ürünü birden
                                            götürür ve geri alınamaz;
                                            tek tıklamaya bırakılamaz.
                                        */}
                                        {menuPendingDelete === menuPanel.menuId ? (
                                            <>
                                                <span className="text-meta text-fg-secondary">
                                                    {t('menu.delete.confirm')}
                                                </span>
                                                <button
                                                    type="button"
                                                    className={inlineActionClass}
                                                    onClick={() =>
                                                        void deleteMenu(menuPanel.menuId)
                                                    }
                                                    disabled={menuFormBusy}
                                                >
                                                    {t('menu.delete.confirm.yes')}
                                                </button>
                                                <button
                                                    type="button"
                                                    className={inlineActionClass}
                                                    onClick={() => setMenuPendingDelete(null)}
                                                    disabled={menuFormBusy}
                                                >
                                                    {t('menu.entry.cancel')}
                                                </button>
                                            </>
                                        ) : (
                                            <button
                                                type="button"
                                                className={inlineActionClass}
                                                onClick={() =>
                                                    setMenuPendingDelete(menuPanel.menuId)
                                                }
                                                disabled={menuFormBusy}
                                            >
                                                {t('menu.delete.submit')}
                                            </button>
                                        )}
                                    </>
                                ) : null}
                            </div>
                        </form>
                    ) : null}

                    {/*
                        OLASI TEKRARLAR — `docs/97` Yolculuk C. Aday yoksa
                        bölüm HİÇ yer kaplamaz; bir eylem sunmaz, yalnız
                        bilgi verir.
                    */}
                    {duplicateCandidates.length > 0 ? (
                        <section
                            className={sectionClass}
                            aria-labelledby="duplicate-candidates-heading"
                        >
                            <h3 id="duplicate-candidates-heading" className="text-body font-bold">
                                {t('menu.duplicates.heading', {
                                    count: String(duplicateCandidates.length),
                                })}
                            </h3>
                            <p className="text-meta text-fg-secondary">
                                {t('menu.duplicates.help')}
                            </p>
                            <ul className="flex flex-col gap-1">
                                {duplicateCandidates.map((pair) => (
                                    <li
                                        key={`${pair.productAId}-${pair.productBId}`}
                                        className="text-body"
                                    >
                                        {t('menu.duplicates.pair', {
                                            a: pair.productAName,
                                            b: pair.productBName,
                                        })}
                                    </li>
                                ))}
                            </ul>
                        </section>
                    ) : null}

                    {/*
                        AÇILIP KAPANAN KUTU KALKTI — kanonik kaynak
                        `panel.dc.html` satır 30205-30206.

                        Fotoğraftan ve CSV'den içe aktarma, "Bring in a whole
                        menu" başlıklı bir `<details>` içinde duruyordu ve
                        menüde ürün varken KAPALI geliyordu. Gerekçesi
                        makuldü (ilk ekran kalabalıklaşmasın) ama bedeli
                        ağırdı: kapalı bir kutu orada bir yol olduğunu
                        SÖYLEMEZ. Basılı menüsünü fotoğraftan
                        aktarabileceğini bilmeyen sahip, altmış ürünü tek
                        tek yazmaya başlıyordu.

                        Kaynak o tercihi geri alıyor: iki eylem de üst
                        şeritte, her zaman görünür. Kalabalık sorununa
                        cevap, eylemi saklamak değil — PANELİ saklamaktır.
                        Panel yalnız düğmesine basılınca açılır.
                    */}
                    {menuHasItems ? null : (
                        <p className="text-body text-fg-secondary">{t('menu.empty.guide')}</p>
                    )}
                    {aiImportOpen || csvPanelOpen ? (
                        <div
                            role="group"
                            aria-label={t('menu.tools.summary')}
                            className="flex flex-col gap-4"
                        >
                            {/*
                        FOTOĞRAFTAN İÇE AKTARMA (AI) — `docs/92`/`docs/97`
                        Yolculuk A. Yükleme Media sayfasında olur; burası
                        yalnız hazır bir görseli okutur ve inceletir.
                    */}
                            <div className={clsx(sectionClass, aiImportOpen ? '' : 'hidden')}>
                                {aiImportOpen ? (
                                    <>
                                        {/*
                                    Artık tek bir alan değil, bir GRUP: bu
                                    yüzden `label`/`for` yerine `fieldset`/
                                    `legend`. Hedefi olmayan bir `for`,
                                    ekran okuyucuya var olmayan bir kontrol
                                    vaat ederdi.
                                */}
                                        <fieldset>
                                            <legend className={labelClass}>
                                                {t('menu.item.ai.import.media.label')}
                                            </legend>
                                            {importSourceMedia.length === 0 ? (
                                                <p className="text-meta text-fg-secondary">
                                                    {t('menu.item.ai.import.media.empty')}
                                                </p>
                                            ) : (
                                                /*
                                        ÇOK SEÇİM — `docs/96` Faz 3. Bir
                                        restoranın menüsü tek fotoğrafa
                                        sığmaz; dört sayfayı tek tek
                                        okutmak, aynı işi dört kez yapmaktı.
                                        Onay kutusu listesi, açılır listeye
                                        yeğlendi: seçilenlerin hepsi aynı
                                        anda görünür kalmalı.
                                    */
                                                <ul className="flex flex-col gap-1">
                                                    {importSourceMedia.map((media) => (
                                                        <li key={media.id}>
                                                            <label className="flex items-center gap-2">
                                                                <TextInput
                                                                    type="checkbox"
                                                                    name={`ai-import-media-${media.id}`}
                                                                    checked={importMediaChoices.includes(
                                                                        media.id,
                                                                    )}
                                                                    onChange={(event) =>
                                                                        setImportMediaChoices(
                                                                            (previous) =>
                                                                                event.target.checked
                                                                                    ? [
                                                                                          ...previous,
                                                                                          media.id,
                                                                                      ]
                                                                                    : previous.filter(
                                                                                          (id) =>
                                                                                              id !==
                                                                                              media.id,
                                                                                      ),
                                                                        )
                                                                    }
                                                                />
                                                                <span className="text-body">
                                                                    {media.altText ||
                                                                        `#${media.id}`}
                                                                </span>
                                                            </label>
                                                        </li>
                                                    ))}
                                                </ul>
                                            )}
                                        </fieldset>

                                        <button
                                            type="button"
                                            className={buttonClass}
                                            disabled={
                                                importMediaChoices.length === 0 ||
                                                aiImportReviewLoading ||
                                                aiImportApplying
                                            }
                                            onClick={() => void handleReadAiImport()}
                                        >
                                            {aiImportReviewLoading
                                                ? t('menu.item.ai.import.reading')
                                                : t('menu.item.ai.import.read')}
                                        </button>

                                        {aiBatchProgress ? (
                                            <p
                                                role="status"
                                                className="text-meta text-fg-secondary"
                                            >
                                                {t('menu.item.ai.import.batch.progress', {
                                                    done: String(aiBatchProgress.done),
                                                    total: String(aiBatchProgress.total),
                                                })}
                                            </p>
                                        ) : null}
                                        {aiBatchSummary ? (
                                            <p
                                                role="status"
                                                className="text-meta text-fg-secondary"
                                            >
                                                {t('menu.item.ai.import.batch.collected', {
                                                    rows: String(aiBatchSummary.rows),
                                                    pages: String(aiBatchSummary.pages),
                                                    dupes: String(aiBatchSummary.dupes),
                                                })}
                                            </p>
                                        ) : null}
                                        {aiImportReviewError ? (
                                            <FieldError message={aiImportReviewError} />
                                        ) : null}

                                        {/*
                                    Okunamayan fotoğraflar AYRI listelenir ve
                                    okunabilenlerin sonucunu gölgelemez.
                                */}
                                        {aiImportFailures.length > 0 ? (
                                            <ul className="flex flex-col gap-0.5">
                                                {aiImportFailures.map((failure) => (
                                                    <li
                                                        key={failure.mediaAssetId}
                                                        className="text-meta text-fg-warning"
                                                    >
                                                        {t('menu.item.ai.import.photo.failed', {
                                                            name:
                                                                importSourceMedia.find(
                                                                    (media) =>
                                                                        media.id ===
                                                                        failure.mediaAssetId,
                                                                )?.altText ||
                                                                `#${failure.mediaAssetId}`,
                                                        })}
                                                    </li>
                                                ))}
                                            </ul>
                                        ) : null}

                                        {aiImportRows.length > 0 ? (
                                            <div className="flex flex-col gap-2">
                                                <h3 className="text-body font-bold">
                                                    {t('menu.item.ai.import.preview.heading')}
                                                </h3>
                                                {aiImportUsedFallback ? (
                                                    <p className="text-meta text-fg-secondary">
                                                        {t('menu.item.ai.import.fallback')}
                                                    </p>
                                                ) : null}
                                                <ul className="flex flex-col gap-1">
                                                    {aiImportRows.map((row) => (
                                                        <li
                                                            key={`${row.artifactId}-${row.name}`}
                                                            className="text-body"
                                                        >
                                                            {row.category} — {row.product}
                                                            {row.priceMinorAmount === null ? (
                                                                <span className="ms-2 text-fg-warning">
                                                                    {t(
                                                                        'menu.item.ai.import.row.price.missing',
                                                                    )}
                                                                </span>
                                                            ) : (
                                                                <span className="ms-2 text-fg-secondary">
                                                                    {minorAmountToDecimalString(
                                                                        row.priceMinorAmount,
                                                                        row.currencyCode || 'TRY',
                                                                    )}{' '}
                                                                    {row.currencyCode}
                                                                </span>
                                                            )}
                                                            {row.uncertain &&
                                                            row.priceMinorAmount !== null ? (
                                                                <span className="ms-2 text-fg-warning">
                                                                    {t(
                                                                        'menu.item.ai.import.row.uncertain',
                                                                    )}
                                                                </span>
                                                            ) : null}
                                                        </li>
                                                    ))}
                                                </ul>
                                                <button
                                                    type="button"
                                                    className={buttonClass}
                                                    disabled={aiImportApplying}
                                                    onClick={() => void handleApplyAiImport()}
                                                >
                                                    {aiImportApplying
                                                        ? t('menu.item.ai.import.applying')
                                                        : t('menu.item.ai.import.apply')}
                                                </button>
                                            </div>
                                        ) : null}

                                        {aiImportApplyReport ? (
                                            <div role="status" className="flex flex-col gap-1">
                                                <p className="text-body">
                                                    {t('menu.import.done', {
                                                        items: String(
                                                            aiImportApplyReport.importedItems,
                                                        ),
                                                        categories: String(
                                                            aiImportApplyReport.importedCategories,
                                                        ),
                                                    })}
                                                </p>
                                                {aiImportApplyReport.rejectedRows.length > 0 ? (
                                                    <>
                                                        <p className="text-body">
                                                            {t('menu.import.rejected', {
                                                                count: String(
                                                                    aiImportApplyReport.rejectedRows
                                                                        .length,
                                                                ),
                                                            })}
                                                        </p>
                                                        <ul className="flex flex-col gap-0.5">
                                                            {aiImportApplyReport.rejectedRows.map(
                                                                (row) => (
                                                                    <li
                                                                        key={row.row}
                                                                        className="text-meta text-fg-secondary"
                                                                    >
                                                                        {t(
                                                                            'menu.item.ai.import.rejected.row',
                                                                            {
                                                                                row: row.row.replace(
                                                                                    'row.',
                                                                                    '',
                                                                                ),
                                                                                reason: row.reason,
                                                                            },
                                                                        )}
                                                                    </li>
                                                                ),
                                                            )}
                                                        </ul>
                                                    </>
                                                ) : null}
                                            </div>
                                        ) : null}
                                    </>
                                ) : null}
                            </div>

                            {/*
                        Menüyü ALMAK ve GERİ KOYMAK (`docs/80`).

                        İndirme düz bir bağlantıdır: tarayıcının kendi indirme
                        yolu, bizim yeniden ürettiğimiz herhangi bir yoldan
                        güvenilirdir.
                    */}
                            <div className={clsx(sectionClass, csvPanelOpen ? '' : 'hidden')}>
                                <p className="text-meta text-fg-secondary">
                                    {t('menu.tools.help')}
                                </p>
                                <a
                                    className={inlineActionClass}
                                    href={exportUrl(workspaceId, tree.id)}
                                    download
                                >
                                    {t('menu.export.download')}
                                </a>
                                {/*
                                    DOSYA SEÇME YÜZEYİ, görsel yüklemeyle AYNI
                                    bileşendir (sahibin isteği, 2026-09-04).

                                    Burada ham bir `<input type="file">` vardı
                                    ve tarayıcı onu işletim sisteminin dilinde
                                    çiziyordu: uygulama Türkçe iken düğmede
                                    "Dosya Seç · Dosya seçilmedi" yazıyordu ve
                                    dosyayı sürükleyip bırakmak mümkün değildi.
                                */}
                                <span className={labelClass}>{t('menu.import.label')}</span>
                                <FileDropzone
                                    name="menu-import-file"
                                    accept=".csv,text/csv"
                                    disabled={importing}
                                    label={t('menu.import.dropzone.label')}
                                    activeLabel={t('menu.import.dropzone.active')}
                                    hint={t('menu.import.dropzone.hint')}
                                    chooseLabel={t('menu.import.dropzone.choose')}
                                    onSelect={(files) => {
                                        const file = files[0];

                                        if (file) {
                                            void handleImport(file);
                                        }
                                    }}
                                />
                                <p className="text-meta text-fg-secondary">
                                    {t('menu.import.help')}
                                </p>

                                {importError ? <FieldError message={importError} /> : null}

                                {importReport ? (
                                    <div role="status" className="flex flex-col gap-1">
                                        <p className="text-body">
                                            {t('menu.import.done', {
                                                items: String(importReport.importedItems),
                                                categories: String(importReport.importedCategories),
                                            })}
                                        </p>
                                        {importReport.rejectedRows.length > 0 ? (
                                            <>
                                                {/*
                                            Reddedilen satırlar SATIR NUMARASIYLA
                                            listelenir: sahip hatayı kendi
                                            dosyasında bulabilmeli, yoksa 60
                                            satırı gözle taramak zorunda kalır.
                                        */}
                                                <p className="text-body">
                                                    {t('menu.import.rejected', {
                                                        count: String(
                                                            importReport.rejectedRows.length,
                                                        ),
                                                    })}
                                                </p>
                                                <ul className="flex flex-col gap-0.5">
                                                    {importReport.rejectedRows.map((row) => (
                                                        <li
                                                            key={row.line}
                                                            className="text-meta text-fg-secondary"
                                                        >
                                                            {t('menu.import.rejected.row', {
                                                                line: String(row.line),
                                                                reason: row.reason,
                                                            })}
                                                        </li>
                                                    ))}
                                                </ul>
                                            </>
                                        ) : null}
                                    </div>
                                ) : null}
                            </div>
                        </div>
                    ) : null}
                    {pendingPublishNotice ? (
                        <div
                            role="status"
                            className="flex flex-wrap items-center justify-between gap-[var(--space-3)] rounded-[var(--radius-md)] border border-s-4 border-border border-s-brand bg-[var(--color-surface-subtle)] px-[var(--space-4)] py-[var(--space-3)]"
                        >
                            <span className="text-body text-fg">
                                {t('menu.publishReminder.text')}
                            </span>
                            <span className="flex items-center gap-[var(--space-2)]">
                                {onNavigateToSection ? (
                                    <button
                                        type="button"
                                        className={buttonClass}
                                        onClick={() => onNavigateToSection('publication')}
                                    >
                                        {t('menu.publishReminder.action')}
                                    </button>
                                ) : null}
                                <button
                                    type="button"
                                    className={inlineActionClass}
                                    onClick={() => setPendingPublishNotice(false)}
                                >
                                    {t('menu.publishReminder.dismiss')}
                                </button>
                            </span>
                        </div>
                    ) : null}

                    {/*
                        RAY + TEK KATEGORİ — kanonik kaynak `panel.dc.html`
                        satır 30255-30282.

                        Önceki hâl: her kategori kendi kartıyla, alt alta.
                        Altı kategorili bir dönercide "Tatlılar"daki
                        künefenin fiyatını düzeltmek isteyen sahip,
                        önündeki beş kategorinin bütün ürünlerinin
                        arasından kaydırarak geçiyordu — ve aradığı
                        kategorinin ekrandaki yeri SABİT DEĞİLDİ, çünkü
                        konumu kendinden öncekilerin ürün sayısına bağlıydı.

                        Şimdi ray solda sabit durur, sağda yalnız seçilen
                        kategori vardır. 240px'lik sütun 320px'te kalkar ve
                        ray yatay bir şeride döner: dar ekranda iki sütunu
                        zorlamak ikisini de okunmaz yapardı.
                    */}
                    <div className="grid items-start gap-[var(--space-5)] md:grid-cols-[240px_minmax(0,1fr)]">
                        <CategoryRail
                            categories={tree.categories.map((category) => ({
                                id: category.id,
                                name: category.name,
                                count: category.menuItems.length,
                            }))}
                            activeCategoryId={visibleCategory?.id ?? null}
                            onSelect={setSelectedCategoryId}
                            onAddCategory={canManageMenu ? () => setCategoryFormOpen(true) : null}
                            listLabel={t('menu.categories.list.label')}
                            addLabel={t('menu.category.add.short')}
                            countLabel={(count) =>
                                t('menu.category.count', { count: String(count) })
                            }
                            /*
                                TUTAMAÇ RAYA TAŞINDI ve bu zorunluydu:
                                sürüklemenin bir HEDEFE ihtiyacı var. Kategori
                                başlığındayken hedef, ekranda duran öteki
                                kategori kartıydı; artık ekranda tek kategori
                                olduğu için orada bırakılacak bir yer yok.
                                Rayda bütün kategoriler duruyor, dolayısıyla
                                sıralama ancak orada yapılabilir — kaynak da
                                tutamacı zaten rayda gösteriyor
                                (`panel.dc.html` satır 30258).
                            */
                            /* Sıralamak da menüyü değiştirmektir. */
                            onReorder={
                                canManageMenu
                                    ? (source, target) => void reorderCategoryByDrag(source, target)
                                    : undefined
                            }
                            reorderHandleLabel={(name) =>
                                t('menu.category.reorder.handle', { name })
                            }
                        />
                        <ol
                            aria-label={t('menu.category.panel.label')}
                            className="flex flex-col gap-4"
                        >
                            {(visibleCategory === null ? [] : [visibleCategory]).map((category) => (
                                <li key={category.id} className={sectionClass}>
                                    <div className="flex flex-wrap items-center gap-2">
                                        {/*
                                        SIRA → BAŞLIK → SAYI → TOPLU STOK →
                                        EYLEMLER. Ürün satırıyla AYNI dizilim:
                                        iki farklı sıralama, gözü her
                                        kategoride yeniden eğitmek olurdu
                                        (`docs/103` Döngü 5).

                                        SÜRÜKLEME TUTAMACI ARTIK BURADA DEĞİL,
                                        RAYDA. Sürüklemenin bir HEDEFE ihtiyacı
                                        var; ekranda tek kategori durduğu için
                                        burada bırakılacak bir yer kalmadı.
                                        Rayda bütün kategoriler duruyor
                                        (`panel.dc.html` satır 30258).
                                    */}
                                        <OrderBadge
                                            position={category.position}
                                            label={t('menu.category.order.label', {
                                                name: category.name,
                                            })}
                                        />
                                        {/*
                                        Başlığın KENDİSİ düzenlenir: adı
                                        değiştirmek için ayrı bir kalem
                                        simgesi aramak gerekmez ve düzeltme,
                                        adın durduğu yerde olur.
                                    */}
                                        <h3 className="min-w-0 text-section font-bold tracking-tight text-fg">
                                            <InlineRename
                                                readOnly={!canManageMenu}
                                                value={category.name}
                                                label={t('menu.rename.label', {
                                                    name: category.name,
                                                })}
                                                emptyMessage={t('menu.rename.error.empty')}
                                                saveLabel={t('menu.rename.save')}
                                                cancelLabel={t('menu.rename.cancel')}
                                                onSubmit={(next) =>
                                                    handleRename(
                                                        'category',
                                                        category.id,
                                                        category.name,
                                                        next,
                                                    )
                                                }
                                            />
                                        </h3>
                                        {/*
                                        ÜRÜN SAYISI — referans rayının ikinci
                                        yarısı ("ad + sayı"). Boş kalmış bir
                                        kategoriyi açmadan görmenin tek yolu
                                        budur; `tabular-nums`, sayılar alt alta
                                        dizildiğinde hizayı korur.
                                    */}
                                        <span className="shrink-0 text-meta tabular-nums text-fg-secondary">
                                            {t('menu.category.count', {
                                                count: String(category.menuItems.length),
                                            })}
                                        </span>
                                        {/*
                                        KATEGORİ GENELİ STOK — `docs/82` kriter 3.
                                        Yalnız ürün varken çizilir; boş bir kategoride
                                        "hepsi tükendi" demenin anlamı yok. Tümü tükenmişse
                                        yalnız geri getirme sunulur, tersi de öyle.
                                    */}
                                        {category.menuItems.length > 0 ? (
                                            <button
                                                type="button"
                                                /*
                                                DURUM, ÜRÜN SATIRIYLA AYNI
                                                YERDE: sağda, eylemlerden hemen
                                                önce. Başlığa yapışık durduğunda
                                                kategori adı sıkışık okunuyor ve
                                                aynı bilgi iki satırda iki ayrı
                                                yerde aranıyordu.
                                            */
                                                className={clsx(inlineActionClass, 'ms-auto')}
                                                disabled={
                                                    categoryStockPending[category.id] === true
                                                }
                                                onClick={() =>
                                                    void handleCategoryStock(
                                                        category,
                                                        !category.menuItems.every(
                                                            (item) => item.outOfStock === true,
                                                        ),
                                                    )
                                                }
                                                aria-label={t(
                                                    category.menuItems.every(
                                                        (item) => item.outOfStock === true,
                                                    )
                                                        ? 'menu.category.stock.back.button'
                                                        : 'menu.category.stock.out.button',
                                                    { name: category.name },
                                                )}
                                            >
                                                {t(
                                                    category.menuItems.every(
                                                        (item) => item.outOfStock === true,
                                                    )
                                                        ? 'menu.category.stock.back.short'
                                                        : 'menu.category.stock.out.short',
                                                )}
                                            </button>
                                        ) : null}
                                        {/*
                                        Kategoriyi İŞLETMEK — `docs/73`.
                                        Yukarı/aşağı, sürükle-bırak değil:
                                        sürükleme dokunmatikte ve klavyeyle
                                        güvenilir değildir.
                                    */}
                                        {canManageMenu ? (
                                            <RowActions
                                                onDelete={() =>
                                                    setPendingDelete({ kind: 'category', category })
                                                }
                                                onMoveUp={() =>
                                                    void moveCategory(
                                                        tree.categories.indexOf(category),
                                                        -1,
                                                    )
                                                }
                                                onMoveDown={() =>
                                                    void moveCategory(
                                                        tree.categories.indexOf(category),
                                                        1,
                                                    )
                                                }
                                                deleteLabel={t('menu.category.delete.label', {
                                                    name: category.name,
                                                })}
                                                deleteText={t('menu.row.delete')}
                                                moreLabel={t('menu.row.more', {
                                                    name: category.name,
                                                })}
                                                upLabel={t('menu.move.up', { name: category.name })}
                                                downLabel={t('menu.move.down', {
                                                    name: category.name,
                                                })}
                                            />
                                        ) : null}
                                    </div>
                                    <ul
                                        aria-label={t('menu.category.items.label', {
                                            name: category.name,
                                        })}
                                        className="flex flex-col gap-2"
                                    >
                                        {category.menuItems.map((item) => (
                                            // Satır, KARŞILAŞTIRMA için hizalanır: tablo
                                            // tasarımının temel ölçütü budur
                                            // (`design-corpus/saas-panel-tasarim-sistemi.md`).
                                            // Dikey yığında iki ürünün fiyatını yan yana
                                            // görmek mümkün değildi.
                                            <li
                                                key={item.id}
                                                className={clsx(
                                                    'flex flex-col gap-[var(--space-1)] border-b border-border py-[var(--space-2)] last:border-b-0',
                                                    /*
                                                    GİZLİ SATIR SOLUK ÇİZİLİR.

                                                    Gizli bir ürün SİLİNMİŞ
                                                    değildir; yalnız misafirde
                                                    yoktur. Opaklık bu farkı
                                                    satırın yerini değiştirmeden
                                                    anlatır — satır listedeki
                                                    sırasını korur, sahip onu
                                                    aramak zorunda kalmaz.

                                                    Tek başına yeterli DEĞİL:
                                                    aynı bilgiyi anahtarın
                                                    `aria-checked` durumu da
                                                    taşır (`DESIGN_SPEC` §12).
                                                */
                                                    item.isVisible ? null : 'opacity-55',
                                                )}
                                            >
                                                <div className={itemRowGridClass}>
                                                    {/*
                                                    GÖRSEL → AD+META → FİYAT →
                                                    BİTTİ → GÖRÜNÜRLÜK → EYLEMLER.

                                                    Önceki dizilim eylemleri satırın
                                                    ORTASINA koyuyordu: göz addan fiyata
                                                    giderken üç düğmenin üstünden atlamak
                                                    zorundaydı. İçerik solda, sayı sağda,
                                                    eylemler en sağda — liste ancak böyle
                                                    okunur ve iki ürünün fiyatı ancak böyle
                                                    karşılaştırılır (`docs/103` Döngü 5).
                                                */}
                                                    <button
                                                        type="button"
                                                        aria-label={t('menu.item.open.button', {
                                                            name: item.productName ?? '',
                                                        })}
                                                        className={itemThumbClass}
                                                        onClick={() =>
                                                            void handleEditPresentation(item)
                                                        }
                                                    >
                                                        {item.imageMediaAssetId === null ||
                                                        item.imageMediaAssetId === undefined ? (
                                                            <ImageIcon
                                                                size={20}
                                                                aria-hidden="true"
                                                            />
                                                        ) : (
                                                            <ImageSquare
                                                                size={20}
                                                                weight="fill"
                                                                aria-hidden="true"
                                                            />
                                                        )}
                                                    </button>
                                                    <span className="flex min-w-0 flex-1 flex-col gap-[var(--space-1)] sm:flex-auto">
                                                        <InlineRename
                                                            readOnly={!canManageMenu}
                                                            value={
                                                                item.productName ??
                                                                `#${item.productId}`
                                                            }
                                                            label={t('menu.rename.label', {
                                                                name: item.productName ?? '',
                                                            })}
                                                            emptyMessage={t(
                                                                'menu.rename.error.empty',
                                                            )}
                                                            saveLabel={t('menu.rename.save')}
                                                            cancelLabel={t('menu.rename.cancel')}
                                                            textClassName="text-body font-medium text-fg"
                                                            onSubmit={(next) =>
                                                                handleRename(
                                                                    'item',
                                                                    item.id,
                                                                    item.productName ?? '',
                                                                    next,
                                                                )
                                                            }
                                                        />
                                                        {/*
                                                        META SATIRI — adın altında,
                                                        satırın hizasını bozmadan.
                                                        Sıra numarası buraya indi:
                                                        referans satırında sayı yok
                                                        ve altı sütunluk ızgarada
                                                        yedinci bir kutu, fiyatı her
                                                        satırda başka yere iterdi.
                                                        Bilgi kaybolmadı, yalnız
                                                        ikincil hizaya geçti.
                                                    */}
                                                        <span className="flex flex-wrap items-center gap-[var(--space-2)] text-meta text-fg-secondary">
                                                            <OrderBadge
                                                                position={item.position}
                                                                label={t('menu.item.order.label', {
                                                                    name: item.productName ?? '',
                                                                })}
                                                            />
                                                            {item.outOfStock === true ? (
                                                                <span className="inline-flex items-center gap-[var(--space-1)] rounded-pill bg-surface-warning px-[var(--space-2)] font-bold text-fg-warning">
                                                                    <Prohibit
                                                                        size={14}
                                                                        weight="fill"
                                                                        aria-hidden="true"
                                                                    />
                                                                    {t('menu.item.stock.badge')}
                                                                </span>
                                                            ) : null}
                                                            {item.imageMediaAssetId === null ||
                                                            item.imageMediaAssetId === undefined ? (
                                                                <span className="inline-flex items-center gap-[var(--space-1)]">
                                                                    <ImageIcon
                                                                        size={14}
                                                                        aria-hidden="true"
                                                                    />
                                                                    {t('menu.item.meta.noPhoto')}
                                                                </span>
                                                            ) : null}
                                                            {/*
                                                            AÇIKLAMA EKSİĞİ —
                                                            kaynağın `p.meta`
                                                            alanı (satır 30269).

                                                            Misafirin ürün
                                                            adının altında
                                                            okuyacağı cümle
                                                            eksikse, sahip bunu
                                                            ancak ürünü teker
                                                            teker açarak
                                                            öğrenebilirdi.
                                                            Altmış ürünlü bir
                                                            menüde bu altmış
                                                            tıklamadır; satırdaki
                                                            tek kelime aynı işi
                                                            bir bakışta yapar.
                                                        */}
                                                            {item.description === null ||
                                                            item.description === undefined ||
                                                            item.description.trim() === '' ? (
                                                                <span className="inline-flex items-center gap-[var(--space-1)]">
                                                                    <Warning
                                                                        size={14}
                                                                        aria-hidden="true"
                                                                    />
                                                                    {t(
                                                                        'menu.item.meta.noDescription',
                                                                    )}
                                                                </span>
                                                            ) : null}
                                                        </span>
                                                    </span>
                                                    {/*
                                                    BİÇİMLENDİRME ürünün kanonik
                                                    biçimlendiricisinden gelir
                                                    (`money/format`, CORE-12).

                                                    Bu ekran onu görmezden gelip
                                                    kendi dizesini kuruyordu:
                                                    "42.50 TRY". Türkçe yazım
                                                    "₺42,50"dir ve bunu tahmin
                                                    etmek değil, dilin kendi
                                                    kurallarına sormak gerekir.
                                                    Biçimlendirilemeyen para
                                                    biriminde eski dizeye
                                                    düşülür — uydurmak yerine
                                                    ham gerçeği göstermek.

                                                    FİYATIN KENDİSİ düzenlenir
                                                    (FF-102): yanında ayrı bir
                                                    "Price" düğmesi durması,
                                                    aynı bilgiyi iki kez
                                                    göstermekti — biri okunacak,
                                                    diğeri tıklanacak.

                                                    `tabular-nums`: rakamlar eşit
                                                    genişlikte olmazsa fiyatlar
                                                    hizalanmaz ve karşılaştırma
                                                    gözle yapılamaz.
                                                */}
                                                    {canManageMenu ? (
                                                        <button
                                                            type="button"
                                                            aria-label={t(
                                                                'menu.item.price.edit.button',
                                                                {
                                                                    name: item.productName ?? '',
                                                                },
                                                            )}
                                                            onClick={() => handleEditPrice(item)}
                                                            className={itemPriceClass}
                                                        >
                                                            <span>
                                                                {formatMoneyOr(
                                                                    item.priceMinorAmount,
                                                                    item.currencyCode,
                                                                    `${minorAmountToDecimalString(
                                                                        item.priceMinorAmount,
                                                                        item.currencyCode,
                                                                    )} ${item.currencyCode}`,
                                                                )}
                                                            </span>
                                                            {/*
                                                            Kalem, fiyatın DÜZENLENEBİLİR
                                                            olduğunu söyler. Dolgulu kutu tek
                                                            başına "tıklanabilir" demiyordu;
                                                            sahip fiyatı değiştirmek için
                                                            taşma menüsünü açıyordu.
                                                        */}
                                                            <PencilSimple
                                                                size={16}
                                                                aria-hidden="true"
                                                                className="text-fg-secondary"
                                                            />
                                                        </button>
                                                    ) : (
                                                        /*
                                                            FİYAT KALIR, DÜĞME GİDER.

                                                            Yetkisi olmayan için fiyat
                                                            hâlâ okunur: aşçı doğru
                                                            satıra baktığını üründen
                                                            olduğu kadar fiyattan da
                                                            doğrular ve "48 TL'lik
                                                            levrek" mutfakta gerçek bir
                                                            ayırt edicidir.

                                                            Kalem YOK ve kutu artık
                                                            tıklanabilir görünmüyor:
                                                            biçim, verilen sözün
                                                            kendisidir.
                                                        */
                                                        <span className={itemPriceBaseClass}>
                                                            {formatMoneyOr(
                                                                item.priceMinorAmount,
                                                                item.currencyCode,
                                                                `${minorAmountToDecimalString(
                                                                    item.priceMinorAmount,
                                                                    item.currencyCode,
                                                                )} ${item.currencyCode}`,
                                                            )}
                                                        </span>
                                                    )}
                                                    {/*
                                                    TÜKENDİ satırda kalır: bir
                                                    restoranın gün içinde
                                                    defalarca yaptığı tek iş
                                                    budur. Diğer her şey seyrek
                                                    ve taşma menüsünde adıyla
                                                    duruyor (`docs/103` Döngü 2).
                                                */}
                                                    <button
                                                        type="button"
                                                        className={itemStockButtonClass}
                                                        disabled={stockPending[item.id] === true}
                                                        aria-pressed={item.outOfStock === true}
                                                        aria-label={t(
                                                            item.outOfStock === true
                                                                ? 'menu.item.stock.back.button'
                                                                : 'menu.item.stock.out.button',
                                                            { name: item.productName ?? '' },
                                                        )}
                                                        onClick={() => void handleToggleStock(item)}
                                                    >
                                                        <Prohibit
                                                            size={22}
                                                            weight={
                                                                item.outOfStock === true
                                                                    ? 'fill'
                                                                    : 'regular'
                                                            }
                                                            aria-hidden="true"
                                                            className={
                                                                item.outOfStock === true
                                                                    ? 'text-fg-warning'
                                                                    : undefined
                                                            }
                                                        />
                                                    </button>
                                                    {/*
                                                    GÖRÜNÜRLÜK ANAHTARI — 48×28,
                                                    referansın beşinci sütunu.

                                                    Anahtar biçimi kasıtlı: "bugün
                                                    bitti" anlık bir eylemdir (ikon
                                                    düğmesi), görünürlük ise KALICI
                                                    bir açık/kapalı hâldir. İki farklı
                                                    şeyin iki farklı biçimi olur;
                                                    ikisi de düğme olduğunda sahip
                                                    hangisinin kalıcı olduğunu ancak
                                                    deneyerek öğreniyordu.

                                                    Dokunma hedefi anahtarın GÖRSEL
                                                    boyutundan büyüktür: ray 28px,
                                                    basılabilir alan 44px.
                                                */}
                                                    {canManageMenu ? (
                                                        <button
                                                            type="button"
                                                            role="switch"
                                                            aria-checked={item.isVisible}
                                                            aria-label={t(
                                                                'menu.item.visibility.switch.label',
                                                                { name: item.productName ?? '' },
                                                            )}
                                                            disabled={
                                                                visibilityPending[item.id] === true
                                                            }
                                                            onClick={() =>
                                                                handleToggleVisibility(item)
                                                            }
                                                            className={clsx(
                                                                'flex min-h-[var(--density-hit-area-min)] w-[48px] shrink-0 items-center justify-center',
                                                                'rounded-[var(--radius-md)]',
                                                                'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus',
                                                                'disabled:cursor-not-allowed disabled:opacity-50',
                                                            )}
                                                        >
                                                            <span
                                                                aria-hidden="true"
                                                                className={clsx(
                                                                    'relative block h-[28px] w-[48px] rounded-pill transition-colors',
                                                                    item.isVisible
                                                                        ? 'bg-action'
                                                                        : 'bg-surface-active',
                                                                )}
                                                            >
                                                                <span
                                                                    className={clsx(
                                                                        'absolute top-[3px] block h-[22px] w-[22px] rounded-pill border border-border bg-surface',
                                                                        'transition-[inset-inline-start]',
                                                                        item.isVisible
                                                                            ? 'start-[23px]'
                                                                            : 'start-[3px]',
                                                                    )}
                                                                />
                                                            </span>
                                                        </button>
                                                    ) : null}
                                                    {canManageMenu ? (
                                                        <RowActions
                                                            onDelete={() =>
                                                                setPendingDelete({
                                                                    kind: 'item',
                                                                    item,
                                                                })
                                                            }
                                                            onMoveUp={() =>
                                                                void moveItem(
                                                                    category,
                                                                    category.menuItems.indexOf(
                                                                        item,
                                                                    ),
                                                                    -1,
                                                                )
                                                            }
                                                            onMoveDown={() =>
                                                                void moveItem(
                                                                    category,
                                                                    category.menuItems.indexOf(
                                                                        item,
                                                                    ),
                                                                    1,
                                                                )
                                                            }
                                                            deleteLabel={t(
                                                                'menu.item.delete.label',
                                                                {
                                                                    name: item.productName ?? '',
                                                                },
                                                            )}
                                                            deleteText={t('menu.row.delete')}
                                                            moreLabel={t('menu.row.more', {
                                                                name: item.productName ?? '',
                                                            })}
                                                            /*
                                                        SEYREK İŞLER menüde ve
                                                        ADIYLA durur (FF-102).
                                                        Satırda kalıcı düğme
                                                        olduklarında dokuz
                                                        kontrollük bir duvar
                                                        oluşuyordu; acemi
                                                        kullanıcı için bir
                                                        simgeyi tahmin etmekten
                                                        iyisi, kelimeyi
                                                        okumaktır.
                                                    */
                                                            extraItems={[
                                                                {
                                                                    key: 'presentation',
                                                                    label: t(
                                                                        'menu.item.presentation.edit.short',
                                                                    ),
                                                                    icon: <ImageSquare size={18} />,
                                                                    onSelect: () =>
                                                                        void handleEditPresentation(
                                                                            item,
                                                                        ),
                                                                },
                                                                {
                                                                    key: 'allergens',
                                                                    label: t(
                                                                        'menu.item.allergens.edit.short',
                                                                    ),
                                                                    icon: <Warning size={18} />,
                                                                    onSelect: () =>
                                                                        handleEditAllergens(item),
                                                                },
                                                                /*
                                                            GÖRÜNÜRLÜK BURADAN
                                                            ALINDI ve satırdaki
                                                            anahtara döndü
                                                            (`DESIGN_SPEC` §3).

                                                            Menüde durduğu sürece
                                                            DURUMU da saklıyordu:
                                                            on beş satırın
                                                            hangisinin misafirde
                                                            göründüğünü görmek
                                                            için on beş menü
                                                            açmak gerekiyordu.
                                                            Aynı denetimi iki
                                                            yerde sunmak ise
                                                            hangisinin doğru
                                                            olduğunu sordururdu.
                                                        */
                                                            ]}
                                                            upLabel={t('menu.move.up', {
                                                                name: item.productName ?? '',
                                                            })}
                                                            downLabel={t('menu.move.down', {
                                                                name: item.productName ?? '',
                                                            })}
                                                        />
                                                    ) : (
                                                        /*
                                                            MUTFAĞIN İKİNCİ İŞİ:
                                                            ALERJEN.

                                                            `RowActions` burada
                                                            kullanılamaz — silme
                                                            ve taşımayı zorunlu
                                                            alan olarak ister,
                                                            oysa ikisi de bu rolün
                                                            yapamadığı işler.
                                                            Bir taşma menüsü tek
                                                            satır için de
                                                            gereksizdir: aşçının
                                                            iki işi var, ikisi de
                                                            satırda durur.
                                                        */
                                                        <button
                                                            type="button"
                                                            className={itemStockButtonClass}
                                                            aria-label={t(
                                                                'menu.item.allergens.edit.button',
                                                                { name: item.productName ?? '' },
                                                            )}
                                                            onClick={() =>
                                                                handleEditAllergens(item)
                                                            }
                                                        >
                                                            <Warning size={22} aria-hidden="true" />
                                                        </button>
                                                    )}
                                                </div>

                                                {/*
                                                Alerjenler satırın ALTINDA, çip
                                                olarak durur: satır içinde
                                                hizalamayı bozar ve uzun listede
                                                fiyat sütununu kaydırırdı.
                                            */}
                                                {item.allergens.length > 0 ? (
                                                    <ul
                                                        aria-label={t(
                                                            'menu.item.allergens.list.label',
                                                            { name: item.productName ?? '' },
                                                        )}
                                                        className="flex flex-wrap gap-1"
                                                    >
                                                        {item.allergens.map((allergen) => (
                                                            <li
                                                                key={allergen}
                                                                className="rounded-pill bg-surface-active px-2 py-0.5 text-meta text-fg-muted"
                                                            >
                                                                {allergen}
                                                            </li>
                                                        ))}
                                                    </ul>
                                                ) : null}
                                                {visibilityErrors[item.id] ? (
                                                    <FieldError
                                                        message={
                                                            visibilityErrors[item.id] as string
                                                        }
                                                    />
                                                ) : null}
                                                {/*
                                                ÜRÜN AYRINTI ÇEKMECESİ — `DESIGN_SPEC`
                                                §3: masaüstünde SAĞDAN, telefonda
                                                alttan.

                                                Üç düzenleyici (sunum, alerjen, fiyat)
                                                önce satırın ALTINDA üç ayrı kutu
                                                olarak açılıyordu. Sahibin gördüğü şey
                                                şuydu: fiyatı düzeltmek için tıklıyor,
                                                aşağıdaki on ürün birden kayıyor ve
                                                bakmakta olduğu satır ekrandan
                                                çıkıyordu — düzelttiği şeyi artık
                                                göremiyor.

                                                Çekmece listeyi YERİNDE bırakır. Sağ
                                                kenar, `DrawerPanel`'in "gezinme
                                                soldan" kuralının açıkça yazılmış
                                                istisnasıdır (FF-115): denetçi
                                                panelinde soldaki liste ekranda
                                                kalmalıdır, çünkü sahip bir üründen
                                                diğerine geçerek çalışır.
                                            */}
                                                <DrawerPanel
                                                    open={inspectorOpenFor(item.id)}
                                                    onClose={closeInspector}
                                                    position="right"
                                                    title={t('menu.inspector.title', {
                                                        name: item.productName ?? '',
                                                    })}
                                                    className="w-full max-w-[460px]"
                                                >
                                                    {presentationItemId === item.id ? (
                                                        <form
                                                            className="flex flex-col gap-[var(--space-3)]"
                                                            onSubmit={handleSavePresentation}
                                                            noValidate
                                                        >
                                                            <label
                                                                className={labelClass}
                                                                htmlFor={`item-description-${item.id}`}
                                                            >
                                                                {t('menu.item.description.label')}
                                                            </label>
                                                            <Textarea
                                                                id={`item-description-${item.id}`}
                                                                name={`item-description-${item.id}`}
                                                                rows={3}
                                                                maxLength={500}
                                                                value={descriptionInput}
                                                                onChange={(event) => {
                                                                    setDescriptionInput(
                                                                        event.target.value,
                                                                    );
                                                                    // Kullanıcı öneriyi elle değiştirdi —
                                                                    // ama taslak kimliği KALIR: "Kaydet"
                                                                    // hâlâ onay yoluna gider, düzenlenmiş
                                                                    // metni taşır (`docs/97` R4).
                                                                }}
                                                            />
                                                            <p className="text-meta text-fg-secondary">
                                                                {t('menu.item.description.help')}
                                                            </p>
                                                            <div className="flex flex-wrap items-center gap-2">
                                                                {/* `docs/97` R9 — bkz. fotoğraf bölümündeki aynı kural. */}
                                                                {aiBlockedReason(
                                                                    'product.description',
                                                                ) !== null ? (
                                                                    <p className="text-meta text-fg-secondary">
                                                                        {t(
                                                                            `menu.ai.unavailable.${aiBlockedReason('product.description')}` as never,
                                                                        )}
                                                                    </p>
                                                                ) : (
                                                                    <button
                                                                        type="button"
                                                                        className={
                                                                            inlineActionClass
                                                                        }
                                                                        disabled={
                                                                            aiSuggestionLoading
                                                                        }
                                                                        onClick={
                                                                            handleRequestAiDescription
                                                                        }
                                                                    >
                                                                        {aiSuggestionLoading
                                                                            ? t(
                                                                                  'menu.item.ai.description.loading',
                                                                              )
                                                                            : t(
                                                                                  'menu.item.ai.description.request',
                                                                              )}
                                                                    </button>
                                                                )}
                                                                {aiDraftArtifactId !== null ? (
                                                                    <span className="text-meta text-fg-secondary">
                                                                        {aiSuggestionUsedFallback
                                                                            ? t(
                                                                                  'menu.item.ai.description.suggested.fallback',
                                                                              )
                                                                            : t(
                                                                                  'menu.item.ai.description.suggested',
                                                                              )}
                                                                    </span>
                                                                ) : null}
                                                            </div>
                                                            {aiSuggestionUncertain ? (
                                                                <p
                                                                    className="text-meta text-fg-warning"
                                                                    role="status"
                                                                >
                                                                    {t(
                                                                        'menu.item.ai.description.uncertain',
                                                                    )}
                                                                </p>
                                                            ) : null}
                                                            {aiSuggestionError ? (
                                                                <FieldError
                                                                    message={aiSuggestionError}
                                                                />
                                                            ) : null}

                                                            <label
                                                                className={labelClass}
                                                                htmlFor={`item-image-${item.id}`}
                                                            >
                                                                {t('menu.item.image.label')}
                                                            </label>
                                                            <Select
                                                                id={`item-image-${item.id}`}
                                                                name={`item-image-${item.id}`}
                                                                value={imageChoice}
                                                                onChange={(event) =>
                                                                    setImageChoice(
                                                                        event.target.value,
                                                                    )
                                                                }
                                                            >
                                                                <option value="">
                                                                    {t('menu.item.image.none')}
                                                                </option>
                                                                {readyMedia.map((media) => (
                                                                    <option
                                                                        key={media.id}
                                                                        value={String(media.id)}
                                                                    >
                                                                        {media.altText}
                                                                    </option>
                                                                ))}
                                                            </Select>
                                                            {readyMedia.length === 0 ? (
                                                                <p className="text-meta text-fg-secondary">
                                                                    {t('menu.item.image.empty')}
                                                                </p>
                                                            ) : null}

                                                            {presentationError ? (
                                                                <FieldError
                                                                    message={presentationError}
                                                                />
                                                            ) : null}
                                                            <button
                                                                type="submit"
                                                                className={buttonClass}
                                                                disabled={savingPresentation}
                                                            >
                                                                {t('menu.item.presentation.submit')}
                                                            </button>
                                                        </form>
                                                    ) : null}
                                                    {allergenEditItemId === item.id ? (
                                                        <form
                                                            className="flex flex-col gap-[var(--space-3)] border-t border-border pt-[var(--space-4)]"
                                                            onSubmit={handleSaveAllergens}
                                                            noValidate
                                                        >
                                                            <label
                                                                className={labelClass}
                                                                htmlFor={`item-allergens-edit-${item.id}`}
                                                            >
                                                                {t(
                                                                    'menu.item.allergens.edit.label',
                                                                    {
                                                                        name:
                                                                            item.productName ?? '',
                                                                    },
                                                                )}
                                                            </label>
                                                            <TextInput
                                                                id={`item-allergens-edit-${item.id}`}
                                                                name={`item-allergens-edit-${item.id}`}
                                                                type="text"
                                                                value={allergensInput}
                                                                onChange={(event) =>
                                                                    setAllergensInput(
                                                                        event.target.value,
                                                                    )
                                                                }
                                                            />
                                                            {allergensSubmitError ? (
                                                                <FieldError
                                                                    message={allergensSubmitError}
                                                                />
                                                            ) : null}
                                                            <button
                                                                type="submit"
                                                                className={buttonClass}
                                                                disabled={savingAllergens}
                                                            >
                                                                {t('menu.item.allergens.submit')}
                                                            </button>
                                                        </form>
                                                    ) : null}
                                                    {priceEditItemId === item.id ? (
                                                        <form
                                                            className="flex flex-col gap-[var(--space-3)] border-t border-border pt-[var(--space-4)]"
                                                            onSubmit={handleSavePrice}
                                                            noValidate
                                                        >
                                                            <label
                                                                className={labelClass}
                                                                htmlFor={`item-price-edit-${item.id}`}
                                                            >
                                                                {t('menu.item.price.edit.label', {
                                                                    name: item.productName ?? '',
                                                                })}
                                                            </label>
                                                            <TextInput
                                                                id={`item-price-edit-${item.id}`}
                                                                type="text"
                                                                inputMode="decimal"
                                                                value={priceEditValue}
                                                                onChange={(event) =>
                                                                    setPriceEditValue(
                                                                        event.target.value,
                                                                    )
                                                                }
                                                            />
                                                            {priceEditSubmitError ? (
                                                                <FieldError
                                                                    message={priceEditSubmitError}
                                                                />
                                                            ) : null}
                                                            <button
                                                                type="submit"
                                                                className={buttonClass}
                                                                disabled={savingPriceEdit}
                                                            >
                                                                {t('menu.item.price.edit.submit')}
                                                            </button>
                                                        </form>
                                                    ) : null}
                                                </DrawerPanel>
                                            </li>
                                        ))}
                                    </ul>

                                    {category.menuItems.length === 0 ? (
                                        <p className="text-body text-fg-secondary">
                                            {t('menu.category.empty')}
                                        </p>
                                    ) : null}

                                    {/*
                                    ÜRÜN EKLEME, KATEGORİNİN İÇİNDE.

                                    Öncesinde tek bir form sayfanın EN ALTINDA
                                    duruyordu: dört kategori ve yirmi ürünü
                                    olan bir menüde kullanıcı ürün eklemek
                                    için hepsini geçip aşağı iniyor, sonra
                                    zaten baktığı kategoriyi bir listeden
                                    seçiyordu.

                                    Şimdi kategori bir ALAN değil, TIKLADIĞIN
                                    YER. Bu, "üç tıktan bir tıka"nın gerçek
                                    karşılığı: gereksiz navigasyon ve
                                    tekrarlanan karar kalktı (`docs/50` §8).
                                */}
                                    {currentCategoryId === category.id ? (
                                        <form
                                            className="flex flex-col gap-3 rounded-md border border-border p-3"
                                            onSubmit={handleAddMenuEntry}
                                            aria-label={t('menu.entry.section.label', {
                                                category: category.name,
                                            })}
                                            noValidate
                                        >
                                            <div className="flex flex-wrap items-start gap-3">
                                                <div className="flex min-w-[16ch] flex-[1_1_16ch] flex-col gap-1">
                                                    <label
                                                        className={labelClass}
                                                        htmlFor="product-name"
                                                    >
                                                        {t('menu.product.name.label')}
                                                    </label>
                                                    <TextInput
                                                        id="product-name"
                                                        name="product-name"
                                                        type="text"
                                                        autoFocus
                                                        value={productName}
                                                        onChange={(event) =>
                                                            setProductName(event.target.value)
                                                        }
                                                    />
                                                    {productNameError ? (
                                                        <FieldError message={productNameError} />
                                                    ) : null}
                                                </div>

                                                <div className="flex flex-[0_1_12ch] flex-col gap-1">
                                                    <label
                                                        className={labelClass}
                                                        htmlFor="item-price"
                                                    >
                                                        {t('menu.item.price.label')}
                                                    </label>
                                                    <TextInput
                                                        id="item-price"
                                                        name="item-price"
                                                        type="text"
                                                        inputMode="decimal"
                                                        value={price}
                                                        onChange={(event) =>
                                                            setPrice(event.target.value)
                                                        }
                                                    />
                                                    {priceError ? (
                                                        <FieldError message={priceError} />
                                                    ) : null}
                                                </div>
                                            </div>

                                            <details className="flex flex-col gap-1">
                                                <summary className="cursor-pointer text-body text-fg-secondary">
                                                    {t('menu.entry.allergens.disclose')}
                                                </summary>
                                                <div className="mt-2 flex flex-col gap-1">
                                                    <label
                                                        className={labelClass}
                                                        htmlFor="entry-allergens"
                                                    >
                                                        {t('menu.item.allergens.label')}
                                                    </label>
                                                    <TextInput
                                                        id="entry-allergens"
                                                        name="entry-allergens"
                                                        type="text"
                                                        value={entryAllergens}
                                                        onChange={(event) =>
                                                            setEntryAllergens(event.target.value)
                                                        }
                                                    />
                                                </div>
                                            </details>

                                            {entrySubmitError ? (
                                                <FieldError message={entrySubmitError} />
                                            ) : null}

                                            <div className="flex flex-wrap items-center gap-3">
                                                <button
                                                    type="submit"
                                                    className={buttonClass}
                                                    disabled={creatingEntry}
                                                >
                                                    {t('menu.entry.submit')}
                                                </button>
                                                <button
                                                    type="button"
                                                    className={inlineActionClass}
                                                    onClick={() => setCurrentCategoryId(null)}
                                                >
                                                    {t('menu.entry.cancel')}
                                                </button>
                                                {lastAddedEntry ? (
                                                    <p
                                                        role="status"
                                                        className="text-meta text-fg-secondary"
                                                    >
                                                        {lastAddedEntry}
                                                    </p>
                                                ) : null}
                                            </div>
                                        </form>
                                    ) : null}
                                </li>
                            ))}
                        </ol>
                    </div>

                    {/*
                        KATEGORİ EKLEME FORMU. Düğmesi artık rayın SONUNDA
                        (kaynak, satır 30260) — kategori listesi neredeyse
                        ona ekleme de oradan yapılır. Form burada açılır,
                        çünkü rayın 240px'lik sütunu bir metin alanını
                        okunur genişlikte taşıyamaz.

                        Öncesinde "kategori adı" ile "ürün adı" art arda iki
                        eşit ağırlıkta kutuydu; hangi alanın hangi işe ait
                        olduğu ancak etiketi okuyarak anlaşılıyordu. Kategori
                        eklemek nadir, ürün eklemek sürekli yapılan bir iştir;
                        ikisi aynı yerde aynı ağırlıkta duramaz.
                    */}
                    <div className={clsx(sectionClass, categoryFormOpen ? '' : 'hidden')}>
                        {categoryFormOpen ? (
                            <form
                                className="flex flex-col gap-3"
                                onSubmit={handleCreateCategory}
                                noValidate
                            >
                                <label className={labelClass} htmlFor="category-name">
                                    {t('menu.category.name.label')}
                                </label>
                                <TextInput
                                    id="category-name"
                                    name="category-name"
                                    type="text"
                                    value={categoryName}
                                    onChange={(event) => setCategoryName(event.target.value)}
                                />
                                {categoryNameError ? (
                                    <FieldError message={categoryNameError} />
                                ) : null}
                                {categorySubmitError ? (
                                    <FieldError message={categorySubmitError} />
                                ) : null}
                                <div className="flex flex-wrap gap-2">
                                    <button
                                        type="submit"
                                        className={buttonClass}
                                        disabled={creatingCategory}
                                    >
                                        {t('menu.category.create.submit')}
                                    </button>
                                    <button
                                        type="button"
                                        className={inlineActionClass}
                                        onClick={() => {
                                            setCategoryFormOpen(false);
                                            setCategoryName('');
                                            setCategoryNameError(null);
                                            setCategorySubmitError(null);
                                        }}
                                    >
                                        {t('menu.category.add.cancel')}
                                    </button>
                                </div>
                            </form>
                        ) : null}
                    </div>
                </>
            )}

            {/*
                SİLME ONAYI — ürünün kendi diyaloğu (FF-101).

                Diyalog neyin silineceğini ADIYLA söyler ve sonucunu yazar:
                yayınlanmış sürümler etkilenmez, taslaktaki satır geri gelmez.
                `window.confirm` bunların hiçbirini gösteremiyordu — tek bir
                cümle ve iki tarayıcı düğmesi vardı.
            */}
            <ConfirmDialog
                open={pendingDelete !== null}
                onClose={() => (deleting ? undefined : setPendingDelete(null))}
                destructive
                confirmLoading={deleting}
                title={
                    pendingDelete === null
                        ? ''
                        : pendingDelete.kind === 'item'
                          ? t('menu.item.delete.title', {
                                name: pendingDelete.item.productName ?? '',
                            })
                          : t('menu.category.delete.title', { name: pendingDelete.category.name })
                }
                confirmLabel={t('menu.row.delete')}
                cancelLabel={t('menu.rename.cancel')}
                onConfirm={() => {
                    if (pendingDelete === null) {
                        return;
                    }

                    setDeleting(true);
                    void (async () => {
                        if (pendingDelete.kind === 'item') {
                            await handleDeleteItem(pendingDelete.item);
                        } else {
                            await handleDeleteCategory(pendingDelete.category);
                        }

                        setDeleting(false);
                        setPendingDelete(null);
                    })();
                }}
            >
                <p className="text-body text-fg-secondary">
                    {pendingDelete === null
                        ? ''
                        : pendingDelete.kind === 'item'
                          ? t('menu.item.delete.body')
                          : t('menu.category.delete.body')}
                </p>
            </ConfirmDialog>
        </div>
    );
}
