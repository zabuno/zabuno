import { t } from '../../../i18n/workspace';
import type { BrandProfile } from '../BrandEditForm';
import { AccountSettingsRegion } from './settings/AccountSettingsRegion';
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

                <AccountSettingsRegion currentName={userName} />

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
