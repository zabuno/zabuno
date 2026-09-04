import { TextInput } from '../../forms/micro/TextInput';
import { Textarea } from '../../forms/micro/Textarea';
import { Select } from '../../forms/micro/Select';
import clsx from 'clsx';
import { Eye, EyeSlash, ImageSquare, Plus, Warning } from '@phosphor-icons/react';
import { useEffect, useState, type FormEvent } from 'react';
import { RowActions } from '../compound/RowActions';
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

function brandUrl(workspaceId: number): string {
    return `/api/workspaces/${workspaceId}/brand`;
}

function menuUrl(workspaceId: number, locationId: number): string {
    return `/api/workspaces/${workspaceId}/brand/locations/${locationId}/menu`;
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
async function parseErrorMessage(response: Response, fallback: string): Promise<string> {
    const failure = await readValidationFailure(response, fallback);
    const firstField = Object.values(failure.fields)[0];

    return firstField ?? failure.message ?? fallback;
}

// Bu yüzey menü kataloğunun kalbidir ve restoran sahibinin en çok gördüğü
// ekrandır; bu yüzden kendi rengini seçmemesi özellikle önemlidir.
const labelClass = 'block text-body font-medium text-fg-secondary';

const buttonClass = clsx(
    'inline-flex min-h-[var(--density-hit-area-min)] items-center justify-center rounded-md px-4 py-2 text-body font-semibold',
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
/*
    EKLEME düğmeleri kendi sınıfını taşır (FF-102).

    `inlineActionClass` ile çizildiklerinde, dikey bir yığının içinde
    `inline-flex` esneyip TAM GENİŞLİK bir kutuya dönüşüyordu: ekranda boş
    bir metin alanı gibi duruyor ve kullanıcı içine yazmayı deniyordu. Bir
    düğme, düğme gibi görünmelidir — içeriği kadar geniş ve başında bir artı
    işaretiyle.
*/
const addActionClass = clsx(
    'inline-flex min-h-[var(--density-hit-area-min)] w-fit shrink-0 items-center gap-[var(--space-2)]',
    'self-start rounded-[var(--radius-md)] px-[var(--space-3)] py-[var(--space-1)]',
    'text-meta font-medium text-fg-secondary',
    'border border-dashed border-border bg-transparent hover:bg-surface-hover hover:text-fg',
    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus',
);

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

export function MenuCatalogWorkspace({
    workspaceId,
    locationId,
    onTreeChange,
    onNavigateToSection,
}: MenuCatalogWorkspaceProps) {
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

    const [categoryName, setCategoryName] = useState('');
    const [categoryNameError, setCategoryNameError] = useState<string | null>(null);
    const [categorySubmitError, setCategorySubmitError] = useState<string | null>(null);
    const [creatingCategory, setCreatingCategory] = useState(false);
    const [currentCategoryId, setCurrentCategoryId] = useState<number | null>(null);

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

        setCategoryName('');
        setCategoryNameError(null);
        setCategorySubmitError(null);
        setCreatingCategory(false);
        setCurrentCategoryId(null);

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
                    const loaded = (await menuResponse.json()) as MenuTree;
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
                    setTree((await partial.json()) as MenuTree);
                }

                return;
            }

            const refreshed = await fetch(menuUrl(workspaceId, locationId));

            if (refreshed.ok) {
                setTree((await refreshed.json()) as MenuTree);
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
                setTree((await refreshed.json()) as MenuTree);
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
                setTree((await refreshed.json()) as MenuTree);
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
        } catch {
            setMenuSubmitError(t('menu.create.error.submit'));
        } finally {
            setCreatingMenu(false);
        }
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
            setCurrentCategoryId(created.id);
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
                setTree((await refreshed.json()) as MenuTree);
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
                    <h2 className="text-lg font-semibold">{tree.name}</h2>

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
                            <h3
                                id="duplicate-candidates-heading"
                                className="text-body font-semibold"
                            >
                                {t('menu.duplicates.heading', {
                                    count: String(duplicateCandidates.length),
                                })}
                            </h3>
                            <p className="text-caption text-fg-secondary">
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
                        `docs/101` A5/A8 (FF-73): uzman araçları (fotoğraftan
                        ve CSV'den içe aktarma) TEK kutuda. Menü boşken kutu
                        AÇIK gelir — ilk adım oradadır, 60 ürünü tek tek
                        yazdırmayız; ürün varken KAPALI durur, ilk ekran
                        kalabalıklaşmaz. `<details>`: JS'siz, klavyeyle,
                        ekran okuyucuyla çalışır.
                    */}
                    {menuHasItems ? null : (
                        <p className="text-body text-fg-secondary">{t('menu.empty.guide')}</p>
                    )}
                    <details className={sectionClass} open={!menuHasItems}>
                        <summary className="cursor-pointer text-body font-semibold text-fg">
                            {t('menu.tools.summary')}
                        </summary>
                        <div
                            role="group"
                            aria-label={t('menu.tools.summary')}
                            className="flex flex-col gap-4 pt-2"
                        >
                            <p className="text-caption text-fg-secondary">{t('menu.tools.help')}</p>
                            {/*
                        FOTOĞRAFTAN İÇE AKTARMA (AI) — `docs/92`/`docs/97`
                        Yolculuk A. Yükleme Media sayfasında olur; burası
                        yalnız hazır bir görseli okutur ve inceletir.
                    */}
                            <div className={sectionClass}>
                                {/*
                            AI kapalı/bütçesiz/rotasızsa eylem HİÇ GÖSTERİLMEZ
                            (`skills/ai-no-credit-degradation.md`) — ama yerine
                            tek satırlık bir SEBEP konur (`docs/97` R9): yok
                            olan bir düğme, sahibin "burada bir şey vardı"
                            diye aramasına yol açardı.
                        */}
                                {aiBlockedReason('menu.extract') !== null ? (
                                    <p className="text-caption text-fg-secondary">
                                        {t(
                                            `menu.ai.unavailable.${aiBlockedReason('menu.extract')}` as never,
                                        )}
                                    </p>
                                ) : (
                                    <button
                                        type="button"
                                        className={inlineActionClass}
                                        onClick={() => void handleToggleAiImport()}
                                    >
                                        {aiImportOpen
                                            ? t('menu.item.ai.import.cancel')
                                            : t('menu.item.ai.import.disclose')}
                                    </button>
                                )}

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
                                                <p className="text-caption text-fg-secondary">
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
                                                className="text-caption text-fg-secondary"
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
                                                className="text-caption text-fg-secondary"
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
                                                        className="text-caption text-fg-warning"
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
                                                <h3 className="text-body font-semibold">
                                                    {t('menu.item.ai.import.preview.heading')}
                                                </h3>
                                                {aiImportUsedFallback ? (
                                                    <p className="text-caption text-fg-secondary">
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
                                                                        className="text-caption text-fg-secondary"
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
                            <div className={sectionClass}>
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
                                <p className="text-caption text-fg-secondary">
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
                                                            className="text-caption text-fg-secondary"
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
                    </details>
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

                    <ol
                        aria-label={t('menu.categories.list.label')}
                        className="flex flex-col gap-4"
                    >
                        {tree.categories.map((category) => (
                            <li key={category.id} className={sectionClass}>
                                <div className="flex flex-wrap items-center gap-2">
                                    {/*
                                        SIRA → BAŞLIK → TOPLU STOK → EYLEMLER.
                                        Ürün satırıyla AYNI dizilim: iki farklı
                                        sıralama, gözü her kategoride yeniden
                                        eğitmek olurdu (`docs/103` Döngü 5).
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
                                    <h3 className="min-w-0 text-section font-semibold tracking-tight text-fg">
                                        <InlineRename
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
                                            disabled={categoryStockPending[category.id] === true}
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
                                    <RowActions
                                        onDelete={() =>
                                            setPendingDelete({ kind: 'category', category })
                                        }
                                        onMoveUp={() =>
                                            void moveCategory(tree.categories.indexOf(category), -1)
                                        }
                                        onMoveDown={() =>
                                            void moveCategory(tree.categories.indexOf(category), 1)
                                        }
                                        deleteLabel={t('menu.category.delete.label', {
                                            name: category.name,
                                        })}
                                        deleteText={t('menu.row.delete')}
                                        moreLabel={t('menu.row.more', { name: category.name })}
                                        upLabel={t('menu.move.up', { name: category.name })}
                                        downLabel={t('menu.move.down', { name: category.name })}
                                    />
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
                                            className="flex flex-col gap-2 border-b border-border py-2 last:border-b-0"
                                        >
                                            <div
                                                className={clsx(
                                                    'flex flex-wrap items-center gap-x-3 gap-y-1',
                                                    // Dar ekranda satır iki
                                                    // parçaya bölünür; dikey
                                                    // boşluk küçük kalmalı ki
                                                    // iki parça TEK satır gibi
                                                    // okunsun, iki ayrı satır
                                                    // gibi değil.
                                                )}
                                            >
                                                {/*
                                                    SIRA → AD → FİYAT → TÜKENDİ → EYLEMLER.

                                                    Önceki dizilim eylemleri satırın
                                                    ORTASINA koyuyordu: göz addan fiyata
                                                    giderken üç düğmenin üstünden atlamak
                                                    zorundaydı. İçerik solda, sayı sağda,
                                                    eylemler en sağda — liste ancak böyle
                                                    okunur ve iki ürünün fiyatı ancak böyle
                                                    karşılaştırılır (`docs/103` Döngü 5).
                                                */}
                                                <OrderBadge
                                                    position={item.position}
                                                    label={t('menu.item.order.label', {
                                                        name: item.productName ?? '',
                                                    })}
                                                />
                                                <span className="flex min-w-0 flex-1">
                                                    <InlineRename
                                                        value={
                                                            item.productName ?? `#${item.productId}`
                                                        }
                                                        label={t('menu.rename.label', {
                                                            name: item.productName ?? '',
                                                        })}
                                                        emptyMessage={t('menu.rename.error.empty')}
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
                                                <button
                                                    type="button"
                                                    aria-label={t('menu.item.price.edit.button', {
                                                        name: item.productName ?? '',
                                                    })}
                                                    onClick={() => handleEditPrice(item)}
                                                    className={clsx(
                                                        // Dokunma hedefi 44 px: telefonda ölçüldü.
                                                        'flex min-h-[var(--density-hit-area-min)] shrink-0 items-center',
                                                        'rounded-[var(--radius-sm)] px-[var(--space-1)]',
                                                        'text-body tabular-nums text-fg-secondary',
                                                        'hover:bg-surface-hover hover:text-fg',
                                                        'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus',
                                                    )}
                                                >
                                                    {formatMoneyOr(
                                                        item.priceMinorAmount,
                                                        item.currencyCode,
                                                        `${minorAmountToDecimalString(
                                                            item.priceMinorAmount,
                                                            item.currencyCode,
                                                        )} ${item.currencyCode}`,
                                                    )}
                                                </button>
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
                                                    className={inlineActionClass}
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
                                                    {t(
                                                        item.outOfStock === true
                                                            ? 'menu.item.stock.back.short'
                                                            : 'menu.item.stock.out.short',
                                                    )}
                                                </button>
                                                <RowActions
                                                    onDelete={() =>
                                                        setPendingDelete({ kind: 'item', item })
                                                    }
                                                    onMoveUp={() =>
                                                        void moveItem(
                                                            category,
                                                            category.menuItems.indexOf(item),
                                                            -1,
                                                        )
                                                    }
                                                    onMoveDown={() =>
                                                        void moveItem(
                                                            category,
                                                            category.menuItems.indexOf(item),
                                                            1,
                                                        )
                                                    }
                                                    deleteLabel={t('menu.item.delete.label', {
                                                        name: item.productName ?? '',
                                                    })}
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
                                                                void handleEditPresentation(item),
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
                                                        {
                                                            key: 'visibility',
                                                            /*
                                                                İstek uçarken
                                                                madde kapalı:
                                                                iki kez basmak
                                                                iki ters istek
                                                                gönderirdi.
                                                            */
                                                            disabled:
                                                                visibilityPending[item.id] === true,
                                                            label: t(
                                                                item.isVisible
                                                                    ? 'menu.item.visibility.hide'
                                                                    : 'menu.item.visibility.show',
                                                            ),
                                                            icon: item.isVisible ? (
                                                                <EyeSlash size={18} />
                                                            ) : (
                                                                <Eye size={18} />
                                                            ),
                                                            onSelect: () =>
                                                                handleToggleVisibility(item),
                                                        },
                                                    ]}
                                                    upLabel={t('menu.move.up', {
                                                        name: item.productName ?? '',
                                                    })}
                                                    downLabel={t('menu.move.down', {
                                                        name: item.productName ?? '',
                                                    })}
                                                />
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
                                                    message={visibilityErrors[item.id] as string}
                                                />
                                            ) : null}
                                            {presentationItemId === item.id ? (
                                                <form
                                                    className={sectionClass}
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
                                                            setDescriptionInput(event.target.value);
                                                            // Kullanıcı öneriyi elle değiştirdi —
                                                            // ama taslak kimliği KALIR: "Kaydet"
                                                            // hâlâ onay yoluna gider, düzenlenmiş
                                                            // metni taşır (`docs/97` R4).
                                                        }}
                                                    />
                                                    <p className="text-caption text-fg-secondary">
                                                        {t('menu.item.description.help')}
                                                    </p>
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        {/* `docs/97` R9 — bkz. fotoğraf bölümündeki aynı kural. */}
                                                        {aiBlockedReason('product.description') !==
                                                        null ? (
                                                            <p className="text-caption text-fg-secondary">
                                                                {t(
                                                                    `menu.ai.unavailable.${aiBlockedReason('product.description')}` as never,
                                                                )}
                                                            </p>
                                                        ) : (
                                                            <button
                                                                type="button"
                                                                className={inlineActionClass}
                                                                disabled={aiSuggestionLoading}
                                                                onClick={handleRequestAiDescription}
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
                                                            <span className="text-caption text-fg-secondary">
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
                                                            className="text-caption text-fg-warning"
                                                            role="status"
                                                        >
                                                            {t(
                                                                'menu.item.ai.description.uncertain',
                                                            )}
                                                        </p>
                                                    ) : null}
                                                    {aiSuggestionError ? (
                                                        <FieldError message={aiSuggestionError} />
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
                                                            setImageChoice(event.target.value)
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
                                                        <p className="text-caption text-fg-secondary">
                                                            {t('menu.item.image.empty')}
                                                        </p>
                                                    ) : null}

                                                    {presentationError ? (
                                                        <FieldError message={presentationError} />
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
                                                    className={sectionClass}
                                                    onSubmit={handleSaveAllergens}
                                                    noValidate
                                                >
                                                    <label
                                                        className={labelClass}
                                                        htmlFor={`item-allergens-edit-${item.id}`}
                                                    >
                                                        {t('menu.item.allergens.edit.label', {
                                                            name: item.productName ?? '',
                                                        })}
                                                    </label>
                                                    <TextInput
                                                        id={`item-allergens-edit-${item.id}`}
                                                        name={`item-allergens-edit-${item.id}`}
                                                        type="text"
                                                        value={allergensInput}
                                                        onChange={(event) =>
                                                            setAllergensInput(event.target.value)
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
                                                    className={sectionClass}
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
                                                            setPriceEditValue(event.target.value)
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
                                                <label className={labelClass} htmlFor="item-price">
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
                                ) : (
                                    <button
                                        type="button"
                                        className={addActionClass}
                                        onClick={() => toggleEntryForm(category.id)}
                                    >
                                        <Plus size={14} weight="bold" aria-hidden="true" />
                                        {t('menu.entry.open')}
                                    </button>
                                )}
                            </li>
                        ))}
                    </ol>

                    {/*
                        KATEGORİ EKLEME kendi eylemidir ve KAPALI durur.

                        Öncesinde "kategori adı" ile "ürün adı" art arda iki
                        eşit ağırlıkta kutuydu; hangi alanın hangi işe ait
                        olduğu ancak etiketi okuyarak anlaşılıyordu. Kategori
                        eklemek nadir, ürün eklemek sürekli yapılan bir iştir;
                        ikisi aynı yerde aynı ağırlıkta duramaz.
                    */}
                    <div className={sectionClass}>
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
                        ) : (
                            <button
                                type="button"
                                className={addActionClass}
                                onClick={() => setCategoryFormOpen(true)}
                            >
                                <Plus size={14} weight="bold" aria-hidden="true" />
                                {t('menu.category.add.disclose')}
                            </button>
                        )}
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
