import { useEffect } from 'react';
import { t } from '../../../i18n/workspace';
import { SelectField } from '../../catalog/forms/compound/SelectField';
import { useMarketReference } from './useMarketReference';

export type RegionalFieldsProps = {
    idPrefix: string;
    countryCode: string;
    timezone: string;
    onCountryChange: (country: string) => void;
    onTimezoneChange: (timezone: string) => void;
    countryError?: string;
    timezoneError?: string;
};

/**
 * Şubenin BÖLGESEL alanları: ülke ve saat dilimi — `docs/62`.
 *
 * İki karar bu bileşeni tanımlıyor:
 *
 * 1. **Saat dilimi şubeye aittir, markaya değil.** Aynı markanın İstanbul,
 *    Dubai ve Berlin şubesi olabilir. Alan markada durduğu sürece ikinci şube
 *    açılır açılmaz yanlış olur — ve yanlışlığı görünmez, çünkü tek şubeli
 *    işletmede doğru görünmeye devam eder.
 * 2. **İkisi de LİSTEDEN seçilir.** Ülke alanı önceden serbest metindi ve
 *    kullanıcıdan `TR` yazmasını bekliyordu; bu bir ISO kodudur, restoran
 *    sahibinin dili değil. Saat dilimi de öyle: `ISTANBUL`, `Turkey`,
 *    `UTC+3` ve `Asia/Istanbul` yazılabilir, dördü de geçersizdir.
 *
 * Ülke değişince saat dilimi listesi daralır ve ülkenin varsayılanı
 * ÖNERİLİR — seçilmez. Öneri ile seçim arasındaki fark, kullanıcının
 * listeyi hemen yanında görüyor olmasıdır.
 */
export function RegionalFields({
    idPrefix,
    countryCode,
    timezone,
    onCountryChange,
    onTimezoneChange,
    countryError,
    timezoneError,
}: RegionalFieldsProps) {
    const reference = useMarketReference(countryCode);

    /*
        Önerilen saat dilimi EKRANDA GÖSTERİLİP GÖNDERİLMİYORDU.

        `value` içinde varsayılana düşmek yetmez: kullanıcı `İstanbul —
        UTC+03:00` yazan bir alan görüyor, form ise saat dilimsiz gönderiyordu.
        Gösterilen ile gönderilen ayrıldığı anda ekran yalan söyler. Öneri
        geldiğinde duruma DA yazılır; kullanıcı listeden başkasını seçerse
        koşul artık sağlanmaz ve seçimi ezilmez.
    */
    useEffect(() => {
        if (timezone === '' && reference.defaultTimezone !== null) {
            onTimezoneChange(reference.defaultTimezone);
        }
    }, [timezone, reference.defaultTimezone, onTimezoneChange]);

    /*
        Aynı koruma ÜLKE için de gerekli ve testler bunu bir kusur olarak
        yakaladı: referans listesi gelmediğinde kayıtlı `TR` değeri ekrandan
        KAYBOLUYORDU. Kullanıcı Kaydet'e bastığında ülkesiz bir şube
        gönderilirdi — hiç dokunmadığı bir alanı silmiş olurdu.
    */
    const marketOptions = reference.markets.some((market) => market.code === countryCode)
        ? reference.markets
        : countryCode !== ''
          ? [{ code: countryCode, name: countryCode }, ...reference.markets]
          : reference.markets;

    /*
        Kayıtlı saat dilimi listede yoksa (ülke henüz seçilmemiş ya da liste
        gelmemiş) SEÇENEK OLARAK EKLENİR. Aksi hâlde `select` sessizce ilk
        seçeneğe atlar ve kullanıcı hiçbir şey yapmadan şubenin saati
        değişir — kaydetmeye basana kadar da fark edilmez.
    */
    const options = reference.timezones.some((zone) => zone.id === timezone)
        ? reference.timezones
        : timezone !== ''
          ? [{ id: timezone, label: timezone }, ...reference.timezones]
          : reference.timezones;

    return (
        <>
            <SelectField
                id={`${idPrefix}-country-code`}
                name="country_code"
                label={t('workspace.location.countryCode')}
                errorText={countryError}
                value={countryCode}
                onChange={(event) => {
                    const next = event.target.value;
                    onCountryChange(next);
                    /*
                        Ülke değişince saat dilimi TEMİZLENİR. Eski ülkenin
                        saat dilimini yeni ülkede bırakmak, formun yanlış bir
                        değeri doğruymuş gibi göstermesi olurdu; yeni liste
                        geldiğinde önerilen değer alana yazılır.
                    */
                    onTimezoneChange('');
                }}
            >
                <option value="">{t('workspace.location.regional.chooseCountry')}</option>
                {marketOptions.map((market) => (
                    <option key={market.code} value={market.code}>
                        {market.name}
                    </option>
                ))}
            </SelectField>

            <SelectField
                id={`${idPrefix}-timezone`}
                name="timezone"
                label={t('workspace.location.timezone')}
                helpText={t('workspace.location.timezone.help')}
                errorText={timezoneError}
                value={timezone}
                disabled={countryCode === ''}
                onChange={(event) => onTimezoneChange(event.target.value)}
            >
                <option value="">{t('workspace.location.regional.chooseTimezone')}</option>
                {options.map((zone) => (
                    <option key={zone.id} value={zone.id}>
                        {zone.label}
                    </option>
                ))}
            </SelectField>
        </>
    );
}
