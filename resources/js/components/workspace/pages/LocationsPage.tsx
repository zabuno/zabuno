import { useState } from 'react';
import { Button } from 'flowbite-react';
import { t } from '../../../i18n/workspace';
import { LocationEditForm, type LocationProfile } from '../LocationEditForm';
import { LocationOnboardingForm } from '../LocationOnboardingForm';
import { WorkspacePageFrame } from './shared/WorkspacePageFrame';
import { PageState } from './shared/PageState';
import { LocationCard, type LocationCardLocation } from './locations/LocationCard';
import { useAnalyticsTimeSeries } from './analytics/useAnalyticsTimeSeries';

type LocationsPageProps = {
    workspaceId: number;
    /**
     * `locations/new` adresi formu AÇIK getirir — `docs/64`.
     *
     * Global "Oluştur" menüsü buraya yönlendirir. Adres olmadan menü yalnız
     * listeye götürür ve kullanıcı, tıkladığı şeyi ekranda ayrıca aramak
     * zorunda kalırdı.
     */
    addingLocation: boolean;
    onToggleAddLocation: (adding: boolean) => void;
    locations: LocationCardLocation[];
    onLocationSaved: (location: LocationProfile) => void;
    onLocationCreated: (location: LocationProfile) => void;
    /** Kartın "Masalar" düğmesi: o şubeyi seçer ve karekod ekranına götürür. */
    onOpenTables: (locationId: number) => void;
};

/**
 * ŞUBELER — panel v3 kanonik kaynağı (`panel.dc.html`,
 * `data-screen-label="Şubeler"`; plan `docs/109` §6.4).
 *
 * Ekran "şehir başlıklı kart + içinde şube satırları" düzenindeydi. O düzen
 * bir şubeyi yalnız DÜZENLENEBİLİR BİR FORM olarak gösteriyordu: sahip
 * "Kadıköy kaç masalı, bu hafta kaç kez tarandı, kurulumu bitti mi" diye
 * sorduğunda ekranda hiçbir cevap yoktu. Kaynağın düzeni bir IZGARADIR ve
 * ızgaranın işi budur — beş kart yan yana dururken eksik olanı göz kendisi
 * bulur.
 *
 * ŞEHİR GRUPLAMASI KALKTI. Şehir kartın başlığıydı ve gruplama, üç şubesi
 * olan bir markada üç ayrı kart başlığı üretiyordu — her biri tek satırlık.
 * Şehir bilgisi kaybolmadı: şube adresinde ve düzenleme formunda duruyor.
 *
 * SAYFA İÇİ ŞUBE SEÇİCİ KALKTI. Aynı seçim üst çubukta
 * (`WorkspaceContextControls`) zaten var ve orada iki şubeden azında kendini
 * gizliyor. Yetenek kaybolmadı, CÜMLEYE dönüştü: kartın "Masalar" düğmesi o
 * şubeyi seçer ve karekod ekranına götürür (kaynağın `goQr` bağlaması).
 */
export function LocationsPage({
    workspaceId,
    addingLocation,
    onToggleAddLocation,
    locations,
    onLocationSaved,
    onLocationCreated,
    onOpenTables,
}: LocationsPageProps) {
    /*
        Düzenleme AÇILIP KAPANIR ve aynı anda yalnız bir kart açıktır. Eskiden
        her satır kendi formunu sürekli açık taşıyordu: üç şubeli bir markanın
        ekranı üç uzun formdu ve şubeleri karşılaştırmak imkânsızdı.
    */
    const [editingId, setEditingId] = useState<number | null>(null);

    /*
        HAFTALIK TARAMA GERÇEK ÖLÇÜMDÜR — `docs/109` §6.4.

        Kaynağın kartı "N tarama/hafta" yazıyor. Sayı marka kapsamlı zaman
        serisi ucunun ŞUBE PAYINDAN okunur: tek istek, bütün kartlar. Şube
        başına ayrı bir istek atmak, beş şubeli bir markada beş ek istek ve
        beş ayrı hata yolu demekti.

        Şube YOKKEN uç hiç çağrılmaz: cevabı boş olduğu bilinen bir soruyu
        sormak, boş bir ekranı yavaşlatmaktan başka bir şey yapmaz.
    */
    const { status, series } = useAnalyticsTimeSeries(
        locations.length > 0 ? workspaceId : undefined,
        undefined,
        '7d',
    );

    /*
        Ölçüm HAZIR DEĞİLSE sayı hiç çizilmez. "Hazır değil" üç ayrı sebeple
        olabilir ve üçünde de doğru davranış aynıdır: plan raporlamayı
        içermiyor (402), kullanıcının yetkisi yok (404) ya da pencere gizlilik
        eşiğinin altında (`not_enough_data`). Yerine "0" yazmak, ölçülmemiş
        bir şeyi ölçülmüş göstermek olurdu.
    */
    const measured = status === 'ready' && series !== null && series.state === 'ready';
    const scansByLocation = new Map<number, number>(
        (series?.locationShare ?? []).map((row) => [row.id, row.qrResolveCount]),
    );

    return (
        <div id="section-locations">
            <WorkspacePageFrame
                measure="wide"
                title={t('workspace.shell.nav.locations')}
                description={t('workspace.locations.operational.description')}
                /*
                    Liste BOŞKEN başlıktaki düğme çizilmez.

                    Boş sayfada asıl yüzey boş durumun kendisidir ve eylemi
                    orada taşır; başlıkta bir kopyası daha durursa aynı iş
                    ekranda iki kez görünür. Kaynağın ızgarası hep dolu
                    çiziliyor, yani boş durumu hiç modellemiyor — bu kural
                    kaynakla çelişmez, onun anlatmadığı bir hâli anlatır.
                */
                actions={
                    locations.length === 0 && !addingLocation ? undefined : (
                        <Button
                            type="button"
                            size="sm"
                            onClick={() => onToggleAddLocation(!addingLocation)}
                        >
                            {t('workspace.locations.add.button')}
                        </Button>
                    )
                }
            >
                {addingLocation && (
                    <LocationOnboardingForm
                        workspaceId={workspaceId}
                        onCreated={(location) => {
                            onLocationCreated(location);
                            onToggleAddLocation(false);
                        }}
                    />
                )}

                {/*
                    SAYFA DÜZEYİNDE boş durum: ekranda başka içerik yok.
                    `PageState` çıkış yolunu TİP DÜZEYİNDE zorunlu kılar
                    (`docs/59`); düz bir paragraf, kullanıcıyı "burada
                    yapılacak bir şey yok" diye bırakabilirdi.
                */}
                {locations.length === 0 && !addingLocation && (
                    <PageState
                        kind="empty"
                        screen="locations"
                        title={t('workspace.locations.empty')}
                        description={t('workspace.locations.empty.description')}
                        action={
                            <Button
                                type="button"
                                size="sm"
                                onClick={() => onToggleAddLocation(true)}
                            >
                                {t('workspace.locations.add.button')}
                            </Button>
                        }
                    />
                )}

                {locations.length > 0 && (
                    <section
                        aria-label={t('workspace.locations.region')}
                        /*
                            `auto-fill` + `minmax` breakpoint kullanmadan uyum
                            sağlar: 320 pikselde tek sütun, geniş ekranda üç.
                            Sabit sütun sayısı ikisinden birini bozardı.
                        */
                        className="grid gap-[var(--space-fluid-md)]"
                        style={{
                            gridTemplateColumns: 'repeat(auto-fill, minmax(min(100%, 18rem), 1fr))',
                        }}
                    >
                        {locations.map((location) => (
                            <LocationCard
                                key={location.id}
                                location={location}
                                weeklyScans={
                                    measured ? (scansByLocation.get(location.id) ?? 0) : null
                                }
                                editing={editingId === location.id}
                                onOpenTables={() => onOpenTables(location.id)}
                                onToggleEdit={() =>
                                    setEditingId((current) =>
                                        current === location.id ? null : location.id,
                                    )
                                }
                            >
                                <LocationEditForm
                                    workspaceId={workspaceId}
                                    location={location}
                                    onSaved={onLocationSaved}
                                />
                            </LocationCard>
                        ))}
                    </section>
                )}
            </WorkspacePageFrame>
        </div>
    );
}

export default LocationsPage;
