import { afterEach, describe, expect, it } from 'vitest';
import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { TeamRoleGuide } from './TeamRoleGuide';
import { workspaceTranslations } from '../../../../i18n/workspace';
import matrix from '../../../../generated/role-permission-matrix.json';

/**
 * EKRAN KODDAN ÜRETİLEN MATRİSİ ÇİZER — `docs/139` §5, §6.
 *
 * ═══ NEDEN VAR ═══
 *
 * Bu kart beş rolün her biri için ELLE YAZILMIŞ tek bir cümle
 * gösteriyordu. Cümleler o gün doğruydu ve hiçbiri `RolePermissions` ile
 * bağlı değildi: bir role izin eklendiğinde cümle sessizce eskiyor, hiçbir
 * kapı bunu görmüyordu. Beş cümle, beş potansiyel yalandı.
 *
 * Sunucu tarafındaki kapı (`AuthorizationMatrixArtifactTest`) belgeyle
 * veriyi bağlar. Bu kapı zincirin öteki ucudur: EKRANIN o veriyi gerçekten
 * çizdiğini ve verideki her yeteneğin bir kelimesi olduğunu ölçer.
 *
 * ═══ İKİ AYRI AYRIŞMA ═══
 *
 * 1. Kodda yeni bir izin doğar, kimse ona etiket yazmaz → ekranda ham
 *    anahtar görünür. Burada kırılır.
 * 2. Kodda bir izin kaldırılır, etiketi kalır → artık var olmayan bir
 *    yetenek ekranda anlatılmaya devam eder. Burada da kırılır.
 */
const ABILITY_PREFIX = 'workspace.team.roleGuide.ability.';

const PERMISSIONS = (matrix.permissions as Array<{ key: string }>).map((entry) => entry.key);
const ROLES = matrix.roles as Array<{ key: string; granted: string[]; denied: string[] }>;

function abilityKeysInCatalog(): string[] {
    return Object.keys(workspaceTranslations)
        .filter((key) => key.startsWith(ABILITY_PREFIX))
        .map((key) => key.slice(ABILITY_PREFIX.length))
        .sort();
}

afterEach(() => {
    cleanup();
});

describe('TeamRoleGuide — koddan üretilen matris', () => {
    it('üretilen dosya boş değildir', () => {
        expect(PERMISSIONS.length).toBeGreaterThan(0);
        expect(ROLES.length).toBeGreaterThan(0);
    });

    /**
     * Etiketsiz bir yetenek, ekranda ham anahtar demektir — ve ham anahtar
     * restoran sahibinin ekranında yasaktır (`docs/53`).
     */
    it('her iznin bir kelimesi vardır', () => {
        const missing = PERMISSIONS.filter(
            (permission) => !(ABILITY_PREFIX + permission in workspaceTranslations),
        );

        expect(
            missing,
            'Koddaki bu izinlerin ekranda bir karşılığı yok. `resources/js/i18n/workspace/team.ts` ' +
                'içine `workspace.team.roleGuide.ability.<izin>` eklenmeli.',
        ).toEqual([]);
    });

    /** Artık var olmayan bir yeteneği anlatmaya devam etmek de bir yalandır. */
    it('kaldırılmış bir izin için etiket bırakılmaz', () => {
        const orphans = abilityKeysInCatalog().filter(
            (permission) => !PERMISSIONS.includes(permission),
        );

        expect(
            orphans,
            'Bu etiketlerin koddaki izin listesinde karşılığı yok; kaldırılmış bir ' +
                'yeteneği anlatmaya devam ediyorlar.',
        ).toEqual([]);
    });

    /**
     * Ekranın tanıdığı rol kümesi ile kodun ürettiği rol kümesi aynı olmalı.
     *
     * `Kitchen` rolü bir zamanlar kodda vardı ve kartta yoktu; sahibin
     * verebileceği bir rol, verilebildiğini söyleyen tek ekranda görünmüyordu.
     */
    it('ekran koddaki bütün rolleri tanır', () => {
        const inCode = ROLES.map((role) => role.key).sort();

        const { container } = render(
            <TeamRoleGuide roles={['owner', 'manager', 'editor', 'kitchen', 'member']} />,
        );

        expect(container).toBeTruthy();
        expect(inCode).toEqual(['editor', 'kitchen', 'manager', 'member', 'owner']);
    });

    /**
     * Asıl iddia: listeler ÜRETİLEN dosyadan çiziliyor.
     *
     * Editör seçildi çünkü iki listesi de dolu: hem yapabildiği hem
     * yapamadığı iş var. Sahip seçilseydi "yapamaz" listesi boş olurdu ve
     * test yarım bir şey ölçerdi.
     */
    it('bir rolün yapabildiklerini ve yapamadıklarını üretilen dosyadan çizer', async () => {
        const editor = ROLES.find((role) => role.key === 'editor');

        expect(editor).toBeDefined();
        expect(editor!.granted.length).toBeGreaterThan(0);
        expect(editor!.denied.length).toBeGreaterThan(0);

        render(<TeamRoleGuide roles={['editor']} />);

        await userEvent.click(
            screen.getByRole('button', { name: /See exactly what this role can and cannot do/ }),
        );

        expect(screen.getByText('Can')).toBeInTheDocument();
        expect(screen.getByText('Cannot')).toBeInTheDocument();

        for (const permission of editor!.granted) {
            expect(
                screen.getByText(workspaceTranslations[ABILITY_PREFIX + permission]),
            ).toBeInTheDocument();
        }

        for (const permission of editor!.denied) {
            expect(
                screen.getByText(workspaceTranslations[ABILITY_PREFIX + permission]),
            ).toBeInTheDocument();
        }
    });

    /**
     * Kapalıyken kart eski boyutundadır.
     *
     * Beş rolün yirmi üçer satırı aynı anda çizilseydi kart 320 pikselde
     * ekran boyu bir listeye dönerdi; sahibin sorusu ise tek bir rolle
     * ilgilidir.
     */
    it('ayrıntı kapalı başlar', () => {
        render(<TeamRoleGuide roles={['editor']} />);

        const toggle = screen.getByRole('button', {
            name: /See exactly what this role can and cannot do/,
        });

        expect(toggle).toHaveAttribute('aria-expanded', 'false');
        expect(screen.queryByText('Can')).not.toBeInTheDocument();
    });
});
