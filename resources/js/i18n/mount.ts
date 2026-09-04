import { currentLocale } from './locales';
import { loadLocaleOverrides } from './generated-overrides';

/**
 * Uygulama çizilmeden ÖNCE, açık olan dilin çeviri tablosunu indirir.
 *
 * Her giriş noktası bunu bekleyerek başlar (FF-94). Beklemeseydi kullanıcı
 * önce İngilizce bir ekran görür, sonra ekran altından Türkçeye dönerdi —
 * bir ürünün "dilimi biliyor" demesinin en kötü yolu budur.
 *
 * Yükleme başarısız olursa uygulama YİNE ÇİZİLİR: eksik bir çeviri
 * İngilizce metne düşer, ama çizilmeyen bir uygulama hiçbir şeye düşmez.
 */
export async function readyForRender(): Promise<void> {
    try {
        await loadLocaleOverrides(currentLocale());
    } catch {
        // Çeviri indirilemedi; taban katalog (İngilizce) devrede kalır.
    }
}
