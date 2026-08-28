import { useEffect, useState } from 'react';
import { buildAuthRequestInit } from '../../../lib/csrfHeader';

export type Market = { code: string; name: string };
export type Timezone = { id: string; label: string };

export type MarketReference = {
    markets: Market[];
    timezones: Timezone[];
    defaultTimezone: string | null;
};

const EMPTY: MarketReference = { markets: [], timezones: [], defaultTimezone: null };

/**
 * Ülke ve saat dilimi listelerini getirir — `docs/62`.
 *
 * Saat dilimi listesi ÜLKEYE göre daraltılır. Küresel liste 400'den fazla
 * kimlik içerir; ülke seçildikten sonra Türkiye'de bir, Almanya'da bir,
 * ABD'de yirmi dokuz tane kalır. Bu yüzden burada aranabilir bir combobox
 * yok: daraltılmış liste zaten kısa, ve aranacak bir şey bırakmıyor.
 *
 * Küresel liste bir gün ülkeden bağımsız sunulursa combobox gerekli hâle
 * gelir; o gün gelene kadar yazmak, kullanılmayan bir etkileşim modelini
 * bakım yüküne çevirmek olurdu.
 */
export function useMarketReference(country: string): MarketReference {
    const [reference, setReference] = useState<MarketReference>(EMPTY);

    useEffect(() => {
        let cancelled = false;

        void (async () => {
            const params = new URLSearchParams();
            if (country !== '') params.set('country', country);

            try {
                const response = await fetch(
                    `/api/reference/markets?${params.toString()}`,
                    buildAuthRequestInit({ method: 'GET' }),
                );

                if (!response.ok || cancelled) return;

                const data = (await response.json()) as {
                    markets?: Market[];
                    timezones?: Timezone[];
                    defaults?: { timezone: string } | null;
                };

                if (cancelled) return;

                setReference({
                    markets: data.markets ?? [],
                    timezones: data.timezones ?? [],
                    defaultTimezone: data.defaults?.timezone ?? null,
                });
            } catch {
                /*
                    Referans gelmezse form ÇALIŞMAYA DEVAM EDER: listeler boş
                    kalır, kayıtlı değer alanda durur ve sunucu doğrulaması son
                    söz olur. Ağ hatasında formu kilitlemek, düzeltilebilir bir
                    aksaklığı çıkışsız bir duvara çevirirdi.
                */
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [country]);

    return reference;
}
