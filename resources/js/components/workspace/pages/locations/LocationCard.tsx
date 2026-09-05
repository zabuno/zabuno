import type { ReactNode } from 'react';
import { MapPin, Wrench } from '@phosphor-icons/react';

import { t } from '../../../../i18n/workspace';
import type { LocationProfile } from '../../LocationEditForm';
import { summarizeOpeningHours } from '../../location/openingHours';

/**
 * Kartın okuduğu şube kaydı.
 *
 * `table_count` liste ucundan gelir (`LocationProfile` DTO'su, `docs/109`
 * §6.4). Tip OPSİYONEL tutulur çünkü aynı kayıt formu besleyen yollardan da
 * geçiyor ve alan orada yoktur; kart o zaman sıfır masa okur — ki bu da
 * gerçek bir cevaptır.
 */
export type LocationCardLocation = LocationProfile & { table_count?: number };

export type LocationCardProps = {
    location: LocationCardLocation;
    /**
     * Haftalık tarama sayısı — analitik zaman serisinden.
     *
     * `null` "ölçemedim" demektir (plan raporlamayı içermiyor, yetki yok ya
     * da pencere eşiğin altında). O hâlde sayı HİÇ ÇİZİLMEZ. `0` ise gerçek
     * bir ölçümdür: o hafta kimse taramamış.
     */
    weeklyScans: number | null;
    editing: boolean;
    onOpenTables: () => void;
    onToggleEdit: () => void;
    /** Düzenleme açıkken kartın içinde açılan form. */
    children?: ReactNode;
};

const ACTION_CLASS = [
    'flex min-h-[var(--control-height)] flex-1 items-center justify-center',
    'rounded-[var(--radius-md)] border border-border bg-surface',
    'px-[var(--space-3)] py-[var(--space-2)] text-body font-medium text-fg',
    'transition-colors duration-[var(--duration-fast)] ease-[var(--easing-standard)]',
    'hover:bg-surface-hover',
    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus',
].join(' ');

function addressOf(location: LocationCardLocation): string | null {
    const line = [location.address_line1, location.address_line2]
        .map((part) => (part ?? '').trim())
        .filter((part) => part !== '')
        .join(' ');

    return line === '' ? null : line;
}

/**
 * ŞUBE KARTI — panel v3 kanonik kaynağı (`panel.dc.html`,
 * `data-screen-label="Şubeler"`).
 *
 * Ekran önce "şehir başlıklı kart + içinde şube satırları" düzenindeydi ve
 * bu düzen sahibin sorularını cevaplamıyordu: bir satır yalnız düzenlenebilir
 * bir formdu, yani "Kadıköy kaç masalı, bu hafta kaç kez tarandı, kurulumu
 * bitti mi" ekranda hiç yazmıyordu. Kaynağın düzeni bir IZGARADIR ve
 * ızgaranın işi tam olarak bu: beş şube yan yana dururken eksik olan kart
 * göze kendiliğinden çarpar.
 *
 * DURUM ROZETİ TEK YÖNLÜDÜR. Kaynak köşeye "Açık" ya da "Kurulumda"
 * yazıyor. Depoda şubenin AÇIK olduğunu söyleyen hiçbir alan yok — masası
 * olan bir şube tadilatta da olabilir ve ekranda "Açık" yazması sahibin
 * bilmediği bir iddia olurdu. Masası OLMAYAN şube ise taranamaz: kurulumu
 * bitmemiştir ve bu, `dining_tables` satırlarından okunan bir olgudur. Kart
 * yalnız kanıtlayabildiği yönü söyler.
 *
 * ÇALIŞMA SAATLERİ ARTIK GERÇEK. Kaynağın üçüncü ölçüsü çalışma saatidir ve
 * bu paket ona alanını, ucunu ve giriş yüzeyini verdi (`docs/109` §6.4).
 * Kart yine yalnız KAYITLI olanı çizer: saat girilmemiş bir şubede o satır
 * hiç görünmez. Hafta değişiyorsa tek bir aralık yazmak yalan olurdu; kart
 * o zaman bugünü söyler ve bunu açıkça belirtir.
 */
export function LocationCard({
    location,
    weeklyScans,
    editing,
    onOpenTables,
    onToggleEdit,
    children,
}: LocationCardProps) {
    const address = addressOf(location);
    const tableCount = location.table_count ?? 0;
    const inSetup = tableCount === 0;
    /*
        Özet ŞUBENİN saat dilimine göre hesaplanır (`locations.timezone`,
        `docs/62`) — tarayıcınınkine göre değil. Berlin'den bakan bir sahip,
        İstanbul şubesinin gününü Berlin'in gününe göre görseydi kart
        pazartesi kapalı bir şubeyi pazar günü açık gösterebilirdi.
    */
    const hours = summarizeOpeningHours(location.opening_hours, location.timezone, new Date());

    return (
        <article
            /*
                Kimlik KART'A taşındı. Bu işaret, şube "satırının" ekrandaki
                karşılığını gösteriyordu; satır artık bir kart ve karşılık
                aynı şeyin aynı yeri — testler o yüzden yeniden yazılmadı,
                yalnız düzenleme panelini AÇAN bir tık kazandı.
            */
            data-testid="brand-location-row"
            className="flex flex-col overflow-hidden rounded-[var(--radius-lg)] border border-border bg-surface"
        >
            {/*
                HARİTA DOKUSU kaynağın kendi başlık alanıdır ve süslemedir:
                gerçek bir harita değil, kartın "burası bir yer" demesinin
                yolu. Nokta ızgarası jetondan (`--color-border`) ve atomik
                boşluk gridinden okunur; sabit bir renk ya da piksel yazılsaydı
                koyu temada görünmez olurdu.
            */}
            <div
                aria-hidden="true"
                className="relative h-[6rem] bg-surface-subtle"
                style={{
                    backgroundImage: 'radial-gradient(var(--color-border) 1px, transparent 1px)',
                    backgroundSize: 'var(--space-4) var(--space-4)',
                }}
            >
                <span className="absolute bottom-[var(--space-3)] start-[var(--space-4)] flex h-[2.5rem] w-[2.5rem] items-center justify-center rounded-[var(--radius-md)] bg-action text-action-fg">
                    <MapPin size={22} weight="fill" />
                </span>

                {inSetup ? (
                    /*
                        Rozet METİN taşır ve yanında bir ikon durur. Kaynak
                        durumu yalnız renkle anlatıyor; renk tek başına bir
                        işaret değildir (WCAG 2.2 §1.4.1) ve kırmızı-yeşil
                        ayırt edemeyen bir sahip beş kart arasında hiçbir fark
                        görmezdi.

                        Katalogdaki `Badge` BİLEREK kullanılmadı: bugün
                        Flowbite'ın kendi sarı palet basamağını ve ölçek dışı
                        bir yazı ağırlığını basıyor, yani jeton kökünü
                        atlıyor. O bileşeni düzeltmek bu paketin işi değil;
                        ama bu kart onun borcunu devralmak zorunda da değil.
                    */
                    <span className="absolute end-[var(--space-3)] top-[var(--space-3)] inline-flex items-center gap-[var(--space-1)] rounded-pill border border-warning bg-surface-warning px-[var(--space-3)] py-[var(--space-1)] text-body font-medium text-fg-warning">
                        <Wrench size={16} weight="regular" />
                        {t('workspace.locations.card.status.setup')}
                    </span>
                ) : null}
            </div>

            <div className="flex flex-col gap-[var(--space-3)] p-[var(--space-4)]">
                <div className="flex flex-col gap-[var(--space-1)]">
                    <h3 className="text-subsection font-bold text-fg">{location.display_name}</h3>
                    {/*
                        İşaret, kartın KENDİ adresini gösterir. Düzenleme
                        paneli açıldığında aynı adres bir de etiketli özet
                        satırı olarak görünüyor (`LocationEditForm`); düz metin
                        araması ikisini ayırt edemiyordu.
                    */}
                    <p data-testid="location-card-address" className="text-body text-fg-secondary">
                        {address ?? t('workspace.locations.card.noAddress')}
                    </p>
                </div>

                {/*
                    SAYILAR YAN YANA. Kaynak üç ölçüyü tek satırda gösteriyor;
                    burada yalnız GERÇEĞİ OLANLAR duruyor ve satır sarılabilir,
                    böylece 320 pikselde alt alta iner.
                */}
                <p className="flex flex-wrap gap-[var(--space-4)] text-body text-fg-secondary">
                    <span className="tabular-nums">
                        {t('workspace.locations.card.tables', { count: String(tableCount) })}
                    </span>
                    {weeklyScans === null ? null : (
                        <span className="tabular-nums">
                            {t('workspace.locations.card.scansPerWeek', {
                                count: String(weeklyScans),
                            })}
                        </span>
                    )}
                    {/*
                        ÜÇÜNCÜ ÖLÇÜ: çalışma saatleri. `null` "girilmemiş"
                        demektir ve satır HİÇ çizilmez — kaynağın kendisi de
                        saati olmayan şubede "—" gösteriyor, yani boş bir
                        aralık uydurmuyor.
                    */}
                    {hours === null ? null : (
                        <span data-testid="location-card-hours" className="tabular-nums">
                            {hours.kind === 'always' ? hours.range : null}
                            {hours.kind === 'today'
                                ? t('workspace.locations.card.hours.today', {
                                      range: hours.range,
                                  })
                                : null}
                            {hours.kind === 'todayClosed'
                                ? t('workspace.locations.card.hours.closedToday')
                                : null}
                            {hours.kind === 'closedAllWeek'
                                ? t('workspace.locations.card.hours.closedAllWeek')
                                : null}
                        </span>
                    )}
                </p>

                <div className="flex flex-wrap gap-[var(--space-2)]">
                    {/*
                        Düğme adları ŞUBEYİ ADIYLA taşır. Beş kartlık bir
                        ızgarada beş kez "Masalar" yazan bir düğme, ekran
                        okuyucuyla gezen birine hangi şubenin masalarına
                        gittiğini söylemezdi.
                    */}
                    <button
                        type="button"
                        className={ACTION_CLASS}
                        aria-label={t('workspace.locations.card.tables.action', {
                            name: location.display_name,
                        })}
                        onClick={onOpenTables}
                    >
                        {t('workspace.locations.card.tables.label')}
                    </button>
                    <button
                        type="button"
                        className={ACTION_CLASS}
                        aria-label={t('workspace.locations.card.edit.action', {
                            name: location.display_name,
                        })}
                        aria-expanded={editing}
                        onClick={onToggleEdit}
                    >
                        {t('workspace.locations.card.edit.label')}
                    </button>
                </div>

                {editing ? children : null}
            </div>
        </article>
    );
}

export default LocationCard;
