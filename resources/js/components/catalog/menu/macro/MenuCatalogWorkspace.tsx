import { TextInput } from '../../forms/micro/TextInput';
import { Textarea } from '../../forms/micro/Textarea';
import { Select } from '../../forms/micro/Select';
import clsx from 'clsx';
import { useEffect, useState, type FormEvent } from 'react';
import { RowActions } from '../micro/RowActions';
import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../../../lib/csrfHeader';
import { focusFirstInvalidField, readValidationFailure } from '../../../../lib/validationErrors';
import { t } from '../../../../i18n/menu';
import { FieldError } from '../micro/FieldError';
import { OrderBadge } from '../micro/OrderBadge';

export type MenuCatalogWorkspaceProps = {
    workspaceId: number;
    locationId: number;
    onTreeChange?: (tree: MenuTree) => void;
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
const inlineActionClass = clsx(
    'inline-flex min-h-[var(--density-hit-area-min)] shrink-0 items-center rounded-md px-2 py-1',
    'text-meta font-medium text-fg-secondary',
    'border border-border bg-transparent hover:bg-surface-hover hover:text-fg',
    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus',
    'disabled:cursor-not-allowed disabled:opacity-50',
);

const sectionClass = clsx(
    'flex flex-col gap-3 rounded-lg border border-border p-4',
    'forced-colors:border-[CanvasText]',
);

export function MenuCatalogWorkspace({
    workspaceId,
    locationId,
    onTreeChange,
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

    /* Menüyü almak ve geri koymak (`docs/80`). */
    const [importing, setImporting] = useState(false);
    const [importReport, setImportReport] = useState<{
        importedItems: number;
        importedCategories: number;
        rejectedRows: { line: number; reason: string }[];
    } | null>(null);
    const [importError, setImportError] = useState<string | null>(null);

    const [stockPending, setStockPending] = useState<Record<number, boolean>>({});

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
        if (!window.confirm(t('menu.item.delete.confirm', { name: item.productName ?? '' }))) {
            return;
        }

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
        if (!window.confirm(t('menu.category.delete.confirm', { name: category.name }))) {
            return;
        }

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
     * Adı düzeltir.
     *
     * `prompt` kullanılıyor ve bu bilinçli bir ARA ADIM: satır içi düzenleme
     * daha iyi bir deneyimdir ama bu paketin sorunu "düzeltmenin YOLU YOK"tu.
     * Yolu açmak, güzelleştirmekten önce gelir.
     */
    async function handleRename(kind: 'category' | 'item', id: number, currentName: string) {
        const next = window.prompt(t('menu.rename.prompt'), currentName);

        /*
            İPTAL ile BOŞ BIRAKMAK aynı şey değildir.

            `null` iptaldir: kullanıcı vazgeçti, söylenecek bir şey yok.
            Boş bir metin ise bir NİYETTİR — kullanıcı Tamam'a bastı — ve
            sessizce yutmak, düğmeye basılıp hiçbir şey olmaması demektir
            (`docs/47` Kural 5).
        */
        if (next === null) {
            return;
        }

        if (next.trim() === '') {
            setOperationError(t('menu.rename.error.empty'));

            return;
        }

        if (next.trim() === currentName) {
            return;
        }

        setOperationError(null);

        try {
            const response = await postJson(
                kind === 'category' ? categoryUrl(workspaceId, id) : menuItemUrl(workspaceId, id),
                kind === 'category' ? { name: next.trim() } : { productName: next.trim() },
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
                          categories: current.categories.map((category) =>
                              kind === 'category'
                                  ? category.id === id
                                      ? { ...category, name: next.trim() }
                                      : category
                                  : {
                                        ...category,
                                        menuItems: category.menuItems.map((row) =>
                                            row.id === id
                                                ? { ...row, productName: next.trim() }
                                                : row,
                                        ),
                                    },
                          ),
                      },
            );
        } catch {
            setOperationError(t('menu.ops.error'));
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
                        <label className={labelClass} htmlFor="menu-import-file">
                            {t('menu.import.label')}
                        </label>
                        <input
                            id="menu-import-file"
                            name="menu-import-file"
                            type="file"
                            accept=".csv,text/csv"
                            disabled={importing}
                            onChange={(event) => {
                                const file = event.target.files?.[0];

                                if (file) {
                                    void handleImport(file);
                                }

                                // Aynı dosyayı ikinci kez seçmek de bir olay
                                // üretmeli: kullanıcı dosyayı düzeltip aynı
                                // adla yeniden yükler.
                                event.target.value = '';
                            }}
                        />
                        <p className="text-caption text-fg-secondary">{t('menu.import.help')}</p>

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
                                                count: String(importReport.rejectedRows.length),
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
                    <ol
                        aria-label={t('menu.categories.list.label')}
                        className="flex flex-col gap-4"
                    >
                        {tree.categories.map((category) => (
                            <li key={category.id} className={sectionClass}>
                                <div className="flex flex-wrap items-baseline gap-2">
                                    <h3 className="text-base font-semibold">{category.name}</h3>
                                    <OrderBadge
                                        position={category.position}
                                        label={t('menu.category.order.label', {
                                            name: category.name,
                                        })}
                                    />
                                    {/*
                                        Kategoriyi İŞLETMEK — `docs/73`.
                                        Yukarı/aşağı, sürükle-bırak değil:
                                        sürükleme dokunmatikte ve klavyeyle
                                        güvenilir değildir.
                                    */}
                                    <RowActions
                                        onRename={() =>
                                            void handleRename(
                                                'category',
                                                category.id,
                                                category.name,
                                            )
                                        }
                                        onDelete={() => void handleDeleteCategory(category)}
                                        onMoveUp={() =>
                                            void moveCategory(tree.categories.indexOf(category), -1)
                                        }
                                        onMoveDown={() =>
                                            void moveCategory(tree.categories.indexOf(category), 1)
                                        }
                                        renameLabel={t('menu.rename.label', {
                                            name: category.name,
                                        })}
                                        deleteLabel={t('menu.category.delete.label', {
                                            name: category.name,
                                        })}
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
                                            <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
                                                <span className="min-w-0 flex-1 truncate text-body font-medium text-fg">
                                                    {item.productName ?? `#${item.productId}`}
                                                </span>
                                                <OrderBadge
                                                    position={item.position}
                                                    label={t('menu.item.order.label', {
                                                        name: item.productName ?? '',
                                                    })}
                                                />
                                                <RowActions
                                                    onRename={() =>
                                                        void handleRename(
                                                            'item',
                                                            item.id,
                                                            item.productName ?? '',
                                                        )
                                                    }
                                                    onDelete={() => void handleDeleteItem(item)}
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
                                                    renameLabel={t('menu.rename.label', {
                                                        name: item.productName ?? '',
                                                    })}
                                                    deleteLabel={t('menu.item.delete.label', {
                                                        name: item.productName ?? '',
                                                    })}
                                                    upLabel={t('menu.move.up', {
                                                        name: item.productName ?? '',
                                                    })}
                                                    downLabel={t('menu.move.down', {
                                                        name: item.productName ?? '',
                                                    })}
                                                />
                                                {/*
                                                    `tabular-nums`: rakamlar eşit
                                                    genişlikte olmazsa fiyatlar
                                                    hizalanmaz ve karşılaştırma
                                                    gözle yapılamaz.
                                                */}
                                                <span className="shrink-0 text-body tabular-nums text-fg-secondary">
                                                    {minorAmountToDecimalString(
                                                        item.priceMinorAmount,
                                                        item.currencyCode,
                                                    )}{' '}
                                                    {item.currencyCode}
                                                </span>
                                                <label className="flex shrink-0 items-center">
                                                    <TextInput
                                                        type="checkbox"
                                                        aria-label={t(
                                                            'menu.item.visibility.checkbox.label',
                                                            { name: item.productName ?? '' },
                                                        )}
                                                        checked={item.isVisible}
                                                        disabled={
                                                            visibilityPending[item.id] === true
                                                        }
                                                        onChange={() =>
                                                            handleToggleVisibility(item)
                                                        }
                                                    />
                                                </label>
                                                <button
                                                    type="button"
                                                    className={inlineActionClass}
                                                    aria-label={t(
                                                        'menu.item.allergens.edit.button',
                                                        { name: item.productName ?? '' },
                                                    )}
                                                    onClick={() => handleEditAllergens(item)}
                                                >
                                                    {t('menu.item.allergens.edit.short')}
                                                </button>
                                                <button
                                                    type="button"
                                                    className={inlineActionClass}
                                                    aria-label={t('menu.item.price.edit.button', {
                                                        name: item.productName ?? '',
                                                    })}
                                                    onClick={() => handleEditPrice(item)}
                                                >
                                                    {t('menu.item.price.edit.short')}
                                                </button>
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
                                                <button
                                                    type="button"
                                                    className={inlineActionClass}
                                                    aria-label={t(
                                                        'menu.item.presentation.edit.button',
                                                        { name: item.productName ?? '' },
                                                    )}
                                                    onClick={() =>
                                                        void handleEditPresentation(item)
                                                    }
                                                >
                                                    {t('menu.item.presentation.edit.short')}
                                                </button>
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
                                        className={inlineActionClass}
                                        onClick={() => toggleEntryForm(category.id)}
                                    >
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
                                className={inlineActionClass}
                                onClick={() => setCategoryFormOpen(true)}
                            >
                                {t('menu.category.add.disclose')}
                            </button>
                        )}
                    </div>
                </>
            )}
        </div>
    );
}
