import { t } from '../../../i18n/workspace';
import type { BrandProfile } from '../BrandEditForm';
import { AccountSettingsRegion } from './profile/AccountSettingsRegion';
import { AppearanceRegion } from './profile/AppearanceRegion';
import { BrandColorsRegion } from './profile/BrandColorsRegion';
import { ProfileAvatarRegion } from './profile/ProfileAvatarRegion';
import { WorkspacePageFrame } from './shared/WorkspacePageFrame';

export type ProfilePageProps = {
    workspaceId: number;
    email: string;
    userName?: string;
    avatarMediaAssetId: number | null;
    avatarUrl: string | null;
    brand: BrandProfile | null;
    onBrandSaved: (brand: BrandProfile) => void;
    /** Marka rengini yalnız yönetme izni olan değiştirebilir. */
    canManageBrand: boolean;
};

/**
 * Profil — sahibin isteği (2026-09-04).
 *
 * "Bu menüde 'profile' adlı menü item olsun, kişi profil bilgilerini buradan
 * düzenleyebilsin. Ve tokens (renk, theme) buradan değiştirebilsin. Restoran
 * yöneticisi olarak marka renklerimi değiştirebilmeliyim, profil ve kişisel
 * bilgilerimi güncelleyebilmeliyim, avatar profil fotoğrafımı (media
 * components ile) yükleyebilmeliyim."
 *
 * Neden AYARLAR'ın bir sekmesi değil de kendi ekranı: Ayarlar çalışma alanına
 * aittir (marka kaydı, plan, fatura) ve çalışma alanı değişince içeriği de
 * değişir. Profil KİŞİYE aittir ve kişi hangi restorana geçerse geçsin aynı
 * kalır. İkisini tek ekrana koymak, "adımı değiştirdim, acaba diğer
 * restoranda da değişti mi?" sorusunu her seferinde doğururdu.
 *
 * Marka rengi burada bir istisna olarak durur ve bunun bilinçli bir bedeli
 * var: renk çalışma alanına aittir, kişiye değil. Sahibin istediği yer burası
 * olduğu için buradadır ve bölüm, izni olmayana hiç ÇİZİLMEZ — bir müdür
 * yardımcısı, dokunamayacağı bir kontrolü görüp denemez.
 */
export function ProfilePage({
    workspaceId,
    email,
    userName,
    avatarMediaAssetId,
    avatarUrl,
    brand,
    onBrandSaved,
    canManageBrand,
}: ProfilePageProps) {
    return (
        <div id="section-profile">
            <WorkspacePageFrame
                measure="settings"
                title={t('workspace.profile.title')}
                description={t('workspace.profile.description')}
                cardChildren
            >
                <ProfileAvatarRegion
                    workspaceId={workspaceId}
                    initialMediaAssetId={avatarMediaAssetId}
                    initialAvatarUrl={avatarUrl}
                    fallbackInitial={(userName || email).slice(0, 1).toLocaleUpperCase()}
                />

                {/*
                    Kişisel bilgi kartının da BAŞLIĞI olmalı. Başlıksız tek
                    kart, diğer üçünün arasında "bu bölüm neyin nesi?" sorusunu
                    doğuruyordu; göz kartları başlıklarından tarar.
                */}
                <section
                    aria-labelledby="profile-details-heading"
                    className="flex flex-col gap-[var(--space-3)]"
                >
                    <h3 id="profile-details-heading" className="text-body font-bold text-fg">
                        {t('workspace.profile.details.heading')}
                    </h3>
                    {/*
                        AD, E-POSTA VE ŞİFRE ARTIK YALNIZ BURADA (docs/109).
                        Aynı form Ayarlar > Hesap sekmesinde de çiziliyordu;
                        kullanıcı adını oradan değiştirdiğinde "bu yalnız bu
                        restoranda mı değişti?" diye sormakta haklıydı.
                    */}
                    <AccountSettingsRegion currentName={userName} email={email} />
                </section>

                {/*
                    GÖRÜNÜM BURADA (FF-130). Kişiye ait olan her şey bu
                    ekrandadır: ad, fotoğraf, tema ve satır aralığı. Ayarlar
                    çalışma alanına aittir ve çalışma alanı değişince içeriği
                    değişir; tema orada dururken kişisel bir tercih, restoran
                    değişince değişecekmiş gibi görünüyordu.
                */}
                <AppearanceRegion />

                {canManageBrand && brand !== null ? (
                    <BrandColorsRegion
                        workspaceId={workspaceId}
                        brand={brand}
                        onSaved={onBrandSaved}
                    />
                ) : null}
            </WorkspacePageFrame>
        </div>
    );
}

export default ProfilePage;
