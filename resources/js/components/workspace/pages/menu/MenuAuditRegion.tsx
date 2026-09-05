import { useEffect, useState } from 'react';

import { t } from '../../../../i18n/workspace';
import { currentLocaleTag } from '../../../../money/format';
import { buildAuthRequestInit } from '../../../../lib/csrfHeader';
import { PageState } from '../shared/PageState';

type MenuAuditRow = {
    id: number;
    action: string;
    subjectType: string;
    subjectId: number;
    subjectLabel: string | null;
    before: string | null;
    after: string | null;
    actor: string | null;
    /** Mutlak an (ISO-8601, UTC). Bir SAAT değil. */
    at: string | null;
    /** Kaydın menüsünün bağlı olduğu ŞUBENİN dilimi; bilinmiyorsa null. */
    timeZone: string | null;
};

type MenuAuditPage = {
    data: MenuAuditRow[];
    page: number;
    pageCount: number;
};

type Status = 'loading' | 'error' | 'ready';

/**
 * Sunucunun eylem kodundan sahibin cümlesine giden TEK harita.
 *
 * Tanımadığı bir kod buraya düşerse aşağıda "tanınmayan bir değişiklik"
 * yazılır — uydurulmuş bir cümleyle doldurmak, izin en tehlikeli hâli
 * olurdu: yanlış ama inandırıcı.
 *
 * `menu_ai_imported` KENDİ CÜMLESİNİ TAŞIR ve bu paketin özünde duran
 * ayrımdır. Sahip yanlış bir fiyat bulduğunda sorduğu şey "bunu ben mi
 * yazdım yoksa makine mi okudu"dur; CSV'deki sayıyı insan yazdı,
 * fotoğraftakini bir model OKUDU. Ayrım RENKLE değil KELİMEYLE yapılır.
 */
const ACTION_LABEL: Record<string, Parameters<typeof t>[0]> = {
    menu_created: 'workspace.menu.audit.action.menuCreated',
    menu_renamed: 'workspace.menu.audit.action.menuRenamed',
    menu_deleted: 'workspace.menu.audit.action.menuDeleted',
    menu_imported: 'workspace.menu.audit.action.menuImported',
    menu_ai_imported: 'workspace.menu.audit.action.menuAiImported',
    category_added: 'workspace.menu.audit.action.categoryAdded',
    category_renamed: 'workspace.menu.audit.action.categoryRenamed',
    category_removed: 'workspace.menu.audit.action.categoryRemoved',
    item_added: 'workspace.menu.audit.action.itemAdded',
    item_renamed: 'workspace.menu.audit.action.itemRenamed',
    item_price_changed: 'workspace.menu.audit.action.itemPriceChanged',
    item_visibility_changed: 'workspace.menu.audit.action.itemVisibilityChanged',
    item_allergens_changed: 'workspace.menu.audit.action.itemAllergensChanged',
    item_removed: 'workspace.menu.audit.action.itemRemoved',
};

/**
 * Görünürlük kaydın içinde `visible`/`hidden` olarak saklanır.
 *
 * Ekranda o iki kelime İngilizce kalsaydı, Türkçe okuyan sahip kendi
 * menüsünün kaydını yabancı bir sözlükle okurdu.
 */
const VISIBILITY_LABEL: Record<string, Parameters<typeof t>[0]> = {
    visible: 'workspace.menu.audit.visibility.visible',
    hidden: 'workspace.menu.audit.visibility.hidden',
};

/**
 * Kayıtlı değeri okunur hâline çevirir — YALNIZ GÖRÜNÜRLÜK SATIRINDA.
 *
 * Çeviri EYLEME BAĞLI, değere değil. Değere baksaydı, adı "hidden" olan bir
 * ürünün yeniden adlandırma satırı ekranda "Gizli" diye görünürdü: sahibin
 * kendi yazdığı metin, sistemin sözlüğüyle değiştirilmiş olurdu. Fiyat ve
 * alerjen listesi de aynı sebeple olduğu gibi yazılır — ikisi de zaten
 * sahibin kendi verisidir.
 */
function readableValue(action: string, value: string): string {
    if (action !== 'item_visibility_changed') {
        return value;
    }

    const key = VISIBILITY_LABEL[value];

    return key === undefined ? value : t(key);
}

/**
 * Sunucunun gönderdiği ANI, ŞUBENİN duvar saatiyle okunabilir hâle çevirir.
 *
 * BURADA HESAP YAPILMAZ, yalnız biçimlendirilir — ve biçimlendirme dilimi de
 * sunucudan gelir. Bu depoda az önce bir hata bunun tersinden çıktı: sabit
 * `Europe/Istanbul` ile yazılan zamanlanmış yayın, Berlin şubesinde
 * sunucunun kurduğu 03:00'ü ekranda "04:00" gösteriyordu (`docs/62`,
 * `PublishScheduleRegion`). Bir denetim izinde aynı kayma daha da pahalıdır:
 * sahip "saat 18:41'de kim buradaydı" diye sorar ve yanlış vardiyayı arar.
 *
 * DİLİM SATIRIN YANINA YAZILIR (`timeZoneName`). Bu, yayın planındaki
 * desenden bilerek AYRILAN tek nokta: orada tek bir şubenin tek bir planı
 * vardır ve saat tekti. Burada liste çalışma alanının TAMAMINI kapsar; iki
 * şubeli bir işletmede "18:41" hangi şehrin 18:41'idir sorusu, izi
 * okunmaz kılan ikinci bir tartışma başlatırdı.
 *
 * Şubenin dilimi bilinmiyorsa (menüsü silinmiş bir kayıt) `undefined`
 * geçilir ve tarayıcı OKUYANIN kendi saatini kullanır. Bu bir yedek dilim
 * değil, bir itiraftır — ve etiket zaten hangi saatte olduğumuzu yazıyor.
 */
function momentIn(iso: string, timeZone: string | null): string {
    const value = new Date(iso);

    if (Number.isNaN(value.getTime())) {
        return iso;
    }

    const options: Intl.DateTimeFormatOptions = {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
        timeZoneName: 'short',
    };

    try {
        return new Intl.DateTimeFormat(currentLocaleTag(), {
            ...options,
            timeZone: timeZone ?? undefined,
        }).format(value);
    } catch {
        /*
            Sunucunun tanıdığı dilim listesi tarayıcınınkiyle bire bir aynı
            değildir. Tanımadığı bir kimlikte `Intl` fırlatır ve satır hiç
            çizilmezdi — kaydı kaybetmektense okuyanın kendi saatiyle
            yazarız.
        */
        return new Intl.DateTimeFormat(currentLocaleTag(), options).format(value);
    }
}

/**
 * "DÜN KEBABIN FİYATINI KİM DEĞİŞTİRDİ?" — menü denetim izi (FF-163).
 *
 * NEDEN MENÜ EKRANINDA? Sahip bu soruyu Ayarlar'da değil, menüye BAKARKEN
 * sorar: kebabın yanında 420 yazdığını görür ve "bu 380 değil miydi?" der.
 * Depo bu soruya zaten bir cevap vermişti — medya izi Medya ekranında
 * duruyor, Ayarlar'da değil (`MediaAuditRegion`). Kural aynı: bir iz,
 * kaydettiği nesnelerin yaşadığı ekranda durur. Ayarlar'daki birleşik iz
 * (FF-132) ayrı bir sorunun cevabıdır — "çalışma alanında ne oldu" — ve
 * öncesi/sonrası taşımaz; menünün fiyat geçmişini oraya koymak, sahibi
 * menüden çıkıp başka bir bölüme gitmeye zorlardı.
 *
 * BÖLÜM KAPALI AÇILIR (`<details>`), medya izindeki gerekçeyle: günlük iş
 * değildir, bir şey ters gittiğinde açılır. Her gün açık durması, asıl işin
 * (menüyü düzenlemek) altına düşmesi demek olurdu.
 *
 * MEDYA İZİNDEN AYRILAN TEK NOKTA: bu bölüm liste BOŞKEN de çizilir ve
 * "henüz bir değişiklik kaydedilmedi" der. Medyada boş iz bölümü hiç
 * çizilmiyor çünkü orada boş bir başlık "kayıt tutulmuyor" sanılabilirdi;
 * burada tam tersi geçerli — kapalı bir başlık, kaydın TUTULDUĞUNU söyler
 * ve açan kişi neyin olmadığını okur. Uydurma bir satır, "0 değişiklik"
 * rozeti ya da tahmini bir sayı YOKTUR.
 *
 * GEÇMİŞ SİLİNMEZ VE DÜZENLENMEZ: bu bölümde hiçbir silme/düzenleme yolu
 * yok ve olmayacak. Tabloda `updated_at` sütunu bile yok.
 */
export function MenuAuditRegion({ workspaceId }: { workspaceId: number }) {
    const [status, setStatus] = useState<Status>('loading');
    const [rows, setRows] = useState<MenuAuditRow[]>([]);
    const [page, setPage] = useState(1);
    const [pageCount, setPageCount] = useState(1);

    /*
        Yeniden deneme bir SAYAÇLA yapılır (`AuditTrailRegion` deseni):
        kullanıcı düğmeye bastığında sayaç artar ve etki yeniden koşar.
        Böylece "yeniden yükle" ile "ilk yükleme" aynı yoldan geçer.
    */
    const [attempt, setAttempt] = useState(0);

    useEffect(() => {
        let cancelled = false;

        void (async () => {
            try {
                const response = await fetch(
                    `/api/workspaces/${String(workspaceId)}/menu/audits?page=${String(page)}`,
                    buildAuthRequestInit(),
                );

                if (cancelled) return;

                if (!response.ok) {
                    setStatus('error');

                    return;
                }

                const body = (await response.json()) as Partial<MenuAuditPage>;

                if (cancelled) return;

                setRows(body.data ?? []);
                setPageCount(body.pageCount ?? 1);
                setStatus('ready');
            } catch {
                if (!cancelled) setStatus('error');
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [workspaceId, page, attempt]);

    return (
        <details className="rounded-[var(--radius-lg)] border border-border bg-surface p-[var(--space-4)]">
            <summary className="cursor-pointer text-body font-bold text-fg">
                {t('workspace.menu.audit.heading')}
            </summary>

            <section
                aria-label={t('workspace.menu.audit.region')}
                className="mt-[var(--space-3)] flex flex-col gap-[var(--space-3)]"
            >
                <p className="text-body text-fg-secondary">{t('workspace.menu.audit.help')}</p>
                {/*
                    NE KAYDEDİLMEDİĞİ DE YAZILIR.

                    Sıralama, "bugün bitti" ve yayınlama bilerek ize
                    yazılmıyor (`MenuAuditAction` docblock'u gerekçeleriyle
                    listeler). Bunu söylemeyen bir ekran, olmayan bir kaydı
                    "olmadı" diye okutur: sahip listede yayın göremeyince
                    "demek menü hiç yayına çıkmamış" der. Eksik bir denetim
                    izi TAM görünür; bu cümle o yanılgıyı kapatır.
                */}
                <p className="text-meta text-fg-muted">{t('workspace.menu.audit.notRecorded')}</p>

                {status === 'loading' && (
                    <p role="status" className="text-body text-fg-muted">
                        {t('workspace.menu.audit.loading')}
                    </p>
                )}

                {status === 'error' && (
                    <PageState
                        kind="error"
                        title={t('workspace.menu.audit.error')}
                        action={
                            <button
                                type="button"
                                onClick={() => {
                                    setStatus('loading');
                                    setAttempt((previous) => previous + 1);
                                }}
                                className="min-h-[var(--control-height)] rounded-[var(--radius-md)] border border-border px-[var(--space-3)] py-[var(--space-2)] text-body font-medium whitespace-nowrap text-fg-secondary hover:bg-surface-hover"
                            >
                                {t('workspace.menu.audit.retry')}
                            </button>
                        }
                    />
                )}

                {status === 'ready' && rows.length === 0 && (
                    <p role="status" className="text-body text-fg-muted">
                        {t('workspace.menu.audit.empty')}
                    </p>
                )}

                {status === 'ready' && rows.length > 0 && (
                    /*
                        SATIR KART DEĞİL: iz bir listedir, olaylar zaten bir
                        aradadır ve her olaya ayrı kenarlık vermek onları
                        bağımsız nesneler gibi gösterirdi. Ayraç ÜSTTEDİR —
                        alttan ayraçta son satırın çizgisi kartın kendi
                        kenarlığıyla çakışır.
                    */
                    <ul className="flex flex-col">
                        {rows.map((row) => (
                            <li
                                key={row.id}
                                className="flex min-h-[var(--density-row-height)] flex-wrap items-baseline gap-x-[var(--space-3)] gap-y-[var(--space-1)] border-t border-border py-[var(--space-2)] text-body text-fg-secondary first:border-t-0"
                            >
                                {/*
                                    NE ZAMAN — `tabular-nums` ŞARTTIR: göz
                                    damgaları yukarıdan aşağıya tarar ve
                                    orantılı rakamda sütun titrer.
                                */}
                                <span className="w-[10rem] flex-none text-meta tabular-nums text-fg-muted">
                                    {row.at === null
                                        ? t('workspace.menu.audit.at.unknown')
                                        : momentIn(row.at, row.timeZone)}
                                </span>

                                {/* NEYİ oldu. */}
                                <span className="font-medium text-fg">
                                    {t(
                                        ACTION_LABEL[row.action] ??
                                            'workspace.menu.audit.action.unknown',
                                    )}
                                </span>

                                {/*
                                    NEYİN üstünde. Kayıt olay ANINDAKİ adı
                                    saklıyor, bu yüzden silinmiş bir ürünün
                                    satırı da "137 numaralı ürün" değil
                                    "Adana Kebap" diye okunur. Ad hiç
                                    kaydedilmemişse kimlik UYDURULMAZ;
                                    bilinmediği yazılır.
                                */}
                                <span className="text-fg">
                                    {row.subjectLabel ?? t('workspace.menu.audit.subject.unknown')}
                                </span>

                                {/*
                                    NEYDEN NEYE. Fiyat için "öncesi" olmadan
                                    satır işe yaramaz: sahip 380'den 420'ye
                                    mi çıkıldığını sorar, "bir şey değişti"yi
                                    değil. Tek taraf varsa (ekleme, silme)
                                    yalnız o yazılır — olmayan bir "öncesi"
                                    uydurulmaz.
                                */}
                                {renderChange(row)}

                                {/*
                                    KİM — e-postayla, çünkü bir ekipte iki
                                    "Mehmet" olabilir. Kullanıcı silinmişse
                                    kayıt gizlenmez, failin bilinmediği
                                    söylenir.
                                */}
                                <span className="ms-auto text-meta text-fg-muted">
                                    {row.actor ?? t('workspace.menu.audit.actor.unknown')}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}

                {/*
                    Sayfa kontrolü YALNIZ taşma varsa çizilir: tek sayfalık
                    bir listede "sonraki" düğmesi hiçbir yere götürmez
                    (`MediaFolderRail` ile aynı kural).
                */}
                {status === 'ready' && pageCount > 1 && (
                    <div className="flex items-center gap-[var(--space-2)]">
                        <button
                            type="button"
                            disabled={page <= 1}
                            onClick={() => {
                                setStatus('loading');
                                setPage((previous) => Math.max(1, previous - 1));
                            }}
                            className="min-h-[var(--control-height)] rounded-[var(--radius-md)] border border-border px-[var(--space-3)] text-body font-medium whitespace-nowrap text-fg-secondary disabled:opacity-50 hover:bg-surface-hover"
                        >
                            {t('workspace.menu.audit.previous')}
                        </button>
                        <span className="text-meta tabular-nums text-fg-muted">
                            {t('workspace.menu.audit.page', {
                                page: String(page),
                                total: String(pageCount),
                            })}
                        </span>
                        <button
                            type="button"
                            disabled={page >= pageCount}
                            onClick={() => {
                                setStatus('loading');
                                setPage((previous) => Math.min(pageCount, previous + 1));
                            }}
                            className="min-h-[var(--control-height)] rounded-[var(--radius-md)] border border-border px-[var(--space-3)] text-body font-medium whitespace-nowrap text-fg-secondary disabled:opacity-50 hover:bg-surface-hover"
                        >
                            {t('workspace.menu.audit.next')}
                        </button>
                    </div>
                )}
            </section>
        </details>
    );
}

/** "380.00 TRY → 420.00 TRY" — ya da yalnız var olan taraf. */
function renderChange(row: MenuAuditRow) {
    if (row.before !== null && row.after !== null) {
        return (
            <span className="text-fg">
                {t('workspace.menu.audit.change', {
                    before: readableValue(row.action, row.before),
                    after: readableValue(row.action, row.after),
                })}
            </span>
        );
    }

    const single = row.before ?? row.after;

    return single === null ? null : (
        <span className="text-fg">{readableValue(row.action, single)}</span>
    );
}

export default MenuAuditRegion;
