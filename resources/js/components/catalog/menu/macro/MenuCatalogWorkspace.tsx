import { TextInput } from '../../forms/micro/TextInput';
import { Select } from '../../forms/micro/Select';
import clsx from 'clsx';
import { useEffect, useState, type ChangeEvent, type FormEvent } from 'react';
import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../../../lib/csrfHeader';
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

function productsUrl(workspaceId: number, categoryId: number): string {
    return `/api/workspaces/${workspaceId}/menu-categories/${categoryId}/products`;
}

function menuItemsUrl(workspaceId: number, categoryId: number): string {
    return `/api/workspaces/${workspaceId}/menu-categories/${categoryId}/menu-items`;
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
    method: 'POST' | 'PUT' = 'POST',
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

async function parseErrorMessage(response: Response, fallback: string): Promise<string> {
    try {
        const body = (await response.json()) as { message?: string };
        return body.message ?? fallback;
    } catch {
        return fallback;
    }
}

// Bu yüzey menü kataloğunun kalbidir ve restoran sahibinin en çok gördüğü
// ekrandır; bu yüzden kendi rengini seçmemesi özellikle önemlidir.
const labelClass = 'block text-sm font-medium text-fg-secondary';

const buttonClass = clsx(
    'inline-flex min-h-[var(--density-hit-area-min)] items-center justify-center rounded-md px-4 py-2 text-sm font-semibold',
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

    const [menuName, setMenuName] = useState('');
    const [menuNameError, setMenuNameError] = useState<string | null>(null);
    const [menuSubmitError, setMenuSubmitError] = useState<string | null>(null);
    const [creatingMenu, setCreatingMenu] = useState(false);

    const [categoryName, setCategoryName] = useState('');
    const [categoryNameError, setCategoryNameError] = useState<string | null>(null);
    const [categorySubmitError, setCategorySubmitError] = useState<string | null>(null);
    const [creatingCategory, setCreatingCategory] = useState(false);
    const [currentCategoryId, setCurrentCategoryId] = useState<number | null>(null);

    const [productName, setProductName] = useState('');
    const [productNameError, setProductNameError] = useState<string | null>(null);
    const [productSubmitError, setProductSubmitError] = useState<string | null>(null);
    const [creatingProduct, setCreatingProduct] = useState(false);
    const [currentProductId, setCurrentProductId] = useState<number | null>(null);
    const [currentProductName, setCurrentProductName] = useState('');

    const [price, setPrice] = useState('');
    const [priceError, setPriceError] = useState<string | null>(null);
    const [itemSubmitError, setItemSubmitError] = useState<string | null>(null);
    const [creatingItem, setCreatingItem] = useState(false);
    const [currentMenuItemId, setCurrentMenuItemId] = useState<number | null>(null);

    const [allergensInput, setAllergensInput] = useState('');
    const [allergensSubmitError, setAllergensSubmitError] = useState<string | null>(null);
    const [savingAllergens, setSavingAllergens] = useState(false);

    const [priceEditItemId, setPriceEditItemId] = useState<number | null>(null);
    const [priceEditValue, setPriceEditValue] = useState('');
    const [priceEditSubmitError, setPriceEditSubmitError] = useState<string | null>(null);
    const [savingPriceEdit, setSavingPriceEdit] = useState(false);

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

        setProductName('');
        setProductNameError(null);
        setProductSubmitError(null);
        setCreatingProduct(false);
        setCurrentProductId(null);
        setCurrentProductName('');

        setPrice('');
        setPriceError(null);
        setItemSubmitError(null);
        setCreatingItem(false);
        setCurrentMenuItemId(null);

        setAllergensInput('');
        setAllergensSubmitError(null);
        setSavingAllergens(false);

        setPriceEditItemId(null);
        setPriceEditValue('');
        setPriceEditSubmitError(null);
        setSavingPriceEdit(false);

        setVisibilityPending({});
        setVisibilityErrors({});
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
                    const firstCategory = loaded.categories[0] ?? null;
                    if (firstCategory) {
                        setCurrentCategoryId(firstCategory.id);
                    }
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

    const busy =
        creatingMenu ||
        creatingCategory ||
        creatingProduct ||
        creatingItem ||
        savingAllergens ||
        savingPriceEdit;

    function resetDownstreamOfCategory() {
        setProductName('');
        setProductNameError(null);
        setProductSubmitError(null);
        setCreatingProduct(false);
        setCurrentProductId(null);
        setCurrentProductName('');

        setPrice('');
        setPriceError(null);
        setItemSubmitError(null);
        setCreatingItem(false);
        setCurrentMenuItemId(null);

        setAllergensInput('');
        setAllergensSubmitError(null);
        setSavingAllergens(false);

        setPriceEditItemId(null);
        setPriceEditValue('');
        setPriceEditSubmitError(null);
        setSavingPriceEdit(false);

        setVisibilityPending({});
        setVisibilityErrors({});
    }

    function handleEditAllergens(item: MenuItemRow) {
        setCurrentMenuItemId(item.id);
        setAllergensInput(item.allergens.join(', '));
        setAllergensSubmitError(null);
    }

    function handleEditPrice(item: MenuItemRow) {
        setPriceEditItemId(item.id);
        setPriceEditValue(minorAmountToDecimalString(item.priceMinorAmount, item.currencyCode));
        setPriceEditSubmitError(null);
    }

    function handleCategorySelect(event: ChangeEvent<HTMLSelectElement>) {
        const selectedId = Number(event.target.value);
        if (selectedId === currentCategoryId) return;
        setCurrentCategoryId(selectedId);
        resetDownstreamOfCategory();
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
            setCurrentCategoryId(created.id);
            resetDownstreamOfCategory();
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

    async function handleCreateProduct(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (currentCategoryId === null) return;
        const trimmed = productName.trim();
        if (!trimmed) {
            setProductNameError(t('menu.product.name.error.required'));
            return;
        }
        setProductNameError(null);
        setProductSubmitError(null);
        setCreatingProduct(true);
        try {
            const response = await postJson(productsUrl(workspaceId, currentCategoryId), {
                name: trimmed,
            });
            if (!response.ok) {
                setProductSubmitError(
                    await parseErrorMessage(response, t('menu.product.create.error.submit')),
                );
                return;
            }
            const created = (await response.json()) as { id: number; name: string };
            setCurrentProductId(created.id);
            setCurrentProductName(created.name);
            setProductName('');

            setPrice('');
            setPriceError(null);
            setItemSubmitError(null);
            setCreatingItem(false);
            setCurrentMenuItemId(null);

            setAllergensInput('');
            setAllergensSubmitError(null);
            setSavingAllergens(false);

            setPriceEditItemId(null);
            setPriceEditValue('');
            setPriceEditSubmitError(null);
            setSavingPriceEdit(false);

            setVisibilityPending({});
            setVisibilityErrors({});
        } catch {
            setProductSubmitError(t('menu.product.create.error.submit'));
        } finally {
            setCreatingProduct(false);
        }
    }

    async function handleCreateMenuItem(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (currentCategoryId === null || currentProductId === null) return;
        const trimmed = price.trim();
        if (!trimmed) {
            setPriceError(t('menu.item.price.error.required'));
            return;
        }
        setPriceError(null);
        setItemSubmitError(null);
        setCreatingItem(true);
        try {
            const currency = brand?.currency ?? 'TRY';
            const response = await postJson(menuItemsUrl(workspaceId, currentCategoryId), {
                productId: currentProductId,
                price: trimmed,
                currency,
            });
            if (!response.ok) {
                setItemSubmitError(
                    await parseErrorMessage(response, t('menu.item.create.error.submit')),
                );
                return;
            }
            const created = (await response.json()) as Omit<
                MenuItemRow,
                'allergens' | 'productName'
            >;
            setCurrentMenuItemId(created.id);
            setTree((previous) => {
                if (!previous) return previous;
                return {
                    ...previous,
                    categories: previous.categories.map((category) =>
                        category.id === created.categoryId
                            ? {
                                  ...category,
                                  menuItems: [
                                      ...category.menuItems,
                                      {
                                          ...created,
                                          productName: currentProductName,
                                          allergens: [],
                                      },
                                  ],
                              }
                            : category,
                    ),
                };
            });
        } catch {
            setItemSubmitError(t('menu.item.create.error.submit'));
        } finally {
            setCreatingItem(false);
        }
    }

    async function handleSaveAllergens(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (currentMenuItemId === null) return;
        setAllergensSubmitError(null);
        setSavingAllergens(true);
        try {
            const allergens = parseAllergens(allergensInput);
            const response = await postJson(
                allergensUrl(workspaceId, currentMenuItemId),
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
                                                            className="rounded-full bg-surface-active px-2 py-0.5 text-meta text-fg-muted"
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
                                                        {t('menu.item.price.label')}
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
                            </li>
                        ))}
                    </ol>

                    {tree.categories.length > 1 ? (
                        <div className={sectionClass}>
                            <label className={labelClass} htmlFor="category-select">
                                {t('menu.category.select.label')}
                            </label>
                            <Select
                                id="category-select"
                                value={currentCategoryId ?? ''}
                                onChange={handleCategorySelect}
                            >
                                {tree.categories.map((category) => (
                                    <option key={category.id} value={category.id}>
                                        {category.name}
                                    </option>
                                ))}
                            </Select>
                        </div>
                    ) : null}

                    <form className={sectionClass} onSubmit={handleCreateCategory} noValidate>
                        <label className={labelClass} htmlFor="category-name">
                            {t('menu.category.name.label')}
                        </label>
                        <TextInput
                            id="category-name"
                            type="text"
                            value={categoryName}
                            onChange={(event) => setCategoryName(event.target.value)}
                        />
                        {categoryNameError ? <FieldError message={categoryNameError} /> : null}
                        {categorySubmitError ? <FieldError message={categorySubmitError} /> : null}
                        <button type="submit" className={buttonClass} disabled={creatingCategory}>
                            {t('menu.category.create.submit')}
                        </button>
                    </form>

                    {currentCategoryId !== null ? (
                        <form className={sectionClass} onSubmit={handleCreateProduct} noValidate>
                            <label className={labelClass} htmlFor="product-name">
                                {t('menu.product.name.label')}
                            </label>
                            <TextInput
                                id="product-name"
                                type="text"
                                value={productName}
                                onChange={(event) => setProductName(event.target.value)}
                            />
                            {productNameError ? <FieldError message={productNameError} /> : null}
                            {productSubmitError ? (
                                <FieldError message={productSubmitError} />
                            ) : null}
                            <button
                                type="submit"
                                className={buttonClass}
                                disabled={creatingProduct}
                            >
                                {t('menu.product.create.submit')}
                            </button>
                        </form>
                    ) : null}

                    {currentProductId !== null ? (
                        <form className={sectionClass} onSubmit={handleCreateMenuItem} noValidate>
                            <label className={labelClass} htmlFor="item-price">
                                {t('menu.item.price.label')}
                            </label>
                            <TextInput
                                id="item-price"
                                type="text"
                                inputMode="decimal"
                                value={price}
                                onChange={(event) => setPrice(event.target.value)}
                            />
                            {priceError ? <FieldError message={priceError} /> : null}
                            {itemSubmitError ? <FieldError message={itemSubmitError} /> : null}
                            <button type="submit" className={buttonClass} disabled={creatingItem}>
                                {t('menu.item.create.submit')}
                            </button>
                        </form>
                    ) : null}

                    {currentMenuItemId !== null ? (
                        <form className={sectionClass} onSubmit={handleSaveAllergens} noValidate>
                            <label className={labelClass} htmlFor="item-allergens">
                                {t('menu.item.allergens.label')}
                            </label>
                            <TextInput
                                id="item-allergens"
                                type="text"
                                value={allergensInput}
                                onChange={(event) => setAllergensInput(event.target.value)}
                            />
                            {allergensSubmitError ? (
                                <FieldError message={allergensSubmitError} />
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
                </>
            )}
        </div>
    );
}
