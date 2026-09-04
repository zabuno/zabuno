<?php

declare(strict_types=1);

namespace App\Domain\Media;

/**
 * "Yeri ne dolduruyor?" sorusunun kategori ekseni (kanonik kaynak:
 * `docs/reference/media-manager/Medya Yonetimi v2.dc.html`, ekran etiketi
 * "Kota ve çöp"; somut liste `docs/108` §6.4).
 *
 * NEDEN SLOT? Bir çalışma alanının deposu dolduğunda sahibin ihtiyacı olan
 * şey toplam değil, EYLEM'dir: hangi dosyaları silsin. Bu depoda bir
 * satırın NE İŞE YARADIĞINI söyleyen tek sütun `media_assets.slot`'tur ve
 * `(workspace_id, slot)` indekslidir. Kaynağın kategori adları da bir
 * BİÇİM değil bir AMAÇ adıdır ("Ürünler", "Kampanyalar").
 *
 * NEDEN `mime_type` DEĞİL? "3 GB JPEG" cümlesi doğrudur ve işe yaramaz;
 * sahibi hiçbir karara götürmez.
 *
 * NEDEN `asset_kind` DEĞİL? Sütun `2026_08_27_000400` göçünde
 * `default('image')` ile eklendi ve bugün ona yazan tek bir satır kod
 * YOK. Onunla kırmak, hepsi aynı şeyi söyleyen beş satır çizerdi.
 *
 * KAYNAĞIN "VİDEO" SATIRI BURADA YOKTUR. Bu depo video kabul etmez: her
 * slotun `formats` listesi görsel biçimlerdir ve alım kapısı
 * (`StoreMediaRequest`) JPEG/PNG/GIF/WebP ve temizlenmiş SVG dışını
 * reddeder. Kalıcı olarak sıfır kalacak bir satır, sahibi olmayan bir
 * yeteneğe güvendirir.
 *
 * ÇÖP BURADA YOKTUR ve olmaması bilinçlidir: çöp bir AMAÇ değil bir HAYAT
 * EVRESİDİR (`LifecycleStatus::Trashed`) ve kırılımda kendi satırında,
 * uyarı renginde durur — çünkü sahibin bugün geri kazanabileceği tek
 * dilim odur.
 */
enum StorageCategory: string
{
    /** Misafirin menüde gördüğü yemek fotoğrafları. */
    case Products = 'products';

    /** Kapak, kategori görseli, paylaşım ve e-posta başlığı. */
    case Promotion = 'promotion';

    /** Logo, baskı logosu, favicon, uygulama ikonu, profil görseli. */
    case Brand = 'brand';

    /** Çalışma belgeleri: taranmış/fotoğraflanmış kâğıt menü. */
    case Documents = 'documents';

    /** Tanınmayan ya da bu panele ait olmayan slotlar. */
    case Other = 'other';

    /**
     * Slot → kategori. Tanınmayan her slot `Other`'dır; eşleme bir kapı
     * değil bir OKUMA olduğu için burada hiçbir şey reddedilmez.
     */
    public static function forSlot(string $slot): self
    {
        return match ($slot) {
            'itemImage', 'gallery' => self::Products,
            'cover', 'categoryHero', 'ogImage', 'emailHeader' => self::Promotion,
            'logo', 'printLogo', 'favicon', 'appIcon', 'profileAvatar' => self::Brand,
            /*
                `menuImportSource` diğer Menu slotlarından farklıdır: misafire
                hiç gösterilmez (`alt_required: false`), elle tutulan bir
                kâğıt menünün fotoğrafı ya da taranmış sayfasıdır. Kaynağın
                "Belgeler" satırının bu depodaki tek gerçek karşılığı budur.
            */
            'menuImportSource' => self::Documents,
            default => self::Other,
        };
    }
}
