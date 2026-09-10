import { describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { TeamMemberTableDesktop } from './TeamMemberTableDesktop';
import type {
    TeamMember,
    TeamMemberRemoveOutcome,
    TeamMemberRoleOutcome,
    TeamMemberTransferOutcome,
} from '../team/TeamMemberList';

/**
 * MASAÜSTÜ EKİP TABLOSU — `docs/153` §6.
 *
 * Burada donan şey EKİBİN VERİSİ DEĞİL: onu `TeamPage.members` ve
 * `TeamMemberList` testleri zaten donduruyor ve iki yüzey de aynı
 * `onChangeRole` / `onRemoveMember` / `onTransferOwnership` yolunu
 * kullanıyor.
 *
 * Bu dosyanın İKİ sorusu var:
 *
 * 1. Masasında oturan kişi ekibi bir TABLO olarak okuyabiliyor mu? Telefonda
 *    dört kişilik bir liste tek tek okunur; masada oturan kişi "kim yönetici"
 *    sorusunu SÜTUNA bakarak sorar. Sütun başlıkları görünür değilse tablo
 *    yalnız hizalanmış bir listedir ve göz her satırda yeniden hangi alanın
 *    ne olduğunu çıkarmak zorunda kalır.
 * 2. Genişleyen ekran YETKİ SINIRINI gevşetmiş mi? Sahibin kendi satırında
 *    rol kutusu ya da "Çıkar" düğmesi çizmek, sahipsiz kalabilecek bir
 *    çalışma alanı vaat ederdi — sahiplik silinmez, DEVREDİLİR. Ve sunucunun
 *    kesin cevapları (403/404) tek bir "olmadı"ya inerse ekran, sahibi sonu
 *    olmayan bir "tekrar dene" döngüsüne çağırır.
 */

const MEMBERS: TeamMember[] = [
    { id: 1, name: 'Mehmet Usta', email: 'mehmet@ornek.com', role: 'owner' },
    { id: 2, name: 'Ayşe Yılmaz', email: 'ayse@ornek.com', role: 'editor' },
    { id: 3, name: 'Kerem Aksu', email: 'kerem@ornek.com', role: 'manager' },
];

const REMOVE_BUTTON = 'Çıkar';
const REMOVE_CONFIRM = 'Çıkarmayı onayla';
const REMOVE_CANCEL = 'Vazgeç';
const REMOVE_RETRY = 'Tekrar dene';
const REMOVE_FORBIDDEN = 'Bunu yalnız çalışma alanının sahibi yapabilir.';
const REMOVE_MISSING = 'Bu kişi artık ekip listesinde değil.';
const TRANSFER_BUTTON = 'Sahipliği devret';
const TRANSFER_CONFIRM = 'Onayla';

function surfaceProps(overrides: Record<string, unknown> = {}) {
    return {
        status: 'success' as const,
        members: MEMBERS,
        label: 'Ekip üyeleri',
        loadingText: 'Yükleniyor…',
        errorText: 'Ekip üyeleri yüklenemedi.',
        emptyText: 'Bu çalışma alanında henüz üye yok.',
        onRemoveMember: vi.fn(async (): Promise<TeamMemberRemoveOutcome> => 'success'),
        removeButtonText: REMOVE_BUTTON,
        removeConfirmText: REMOVE_CONFIRM,
        removeCancelText: REMOVE_CANCEL,
        removeBusyText: 'Çıkarılıyor…',
        removeErrorText: 'Bu üye çıkarılamadı.',
        removeForbiddenText: REMOVE_FORBIDDEN,
        removeMissingText: REMOVE_MISSING,
        removeSuccessText: 'Üye çıkarıldı.',
        removeRetryText: REMOVE_RETRY,
        // Sunucunun çıkarabildiği roller; bu dosya kümenin kendisini değil,
        // masaüstü çiziminin o kümeye UYMASINI sınar.
        removableRoles: ['editor', 'manager', 'kitchen', 'member'],
        viewerIsOwner: true,
        onTransferOwnership: vi.fn(async (): Promise<TeamMemberTransferOutcome> => 'success'),
        transferButtonText: TRANSFER_BUTTON,
        transferDialogTitle: 'Çalışma alanı sahipliğini devret',
        transferDialogBody: 'Bu üye çalışma alanının sahibi olacak.',
        transferConfirmText: TRANSFER_CONFIRM,
        transferCancelText: 'Vazgeç',
        transferBusyText: 'Devrediliyor…',
        transferErrorText: 'Sahiplik devredilemedi.',
        transferRetryText: 'Tekrar dene',
        transferSuccessText: 'Sahiplik devredildi.',
        onChangeRole: vi.fn(async (): Promise<TeamMemberRoleOutcome> => 'success'),
        assignableRoles: [
            { value: 'editor', label: 'Editör' },
            { value: 'manager', label: 'Yönetici' },
            { value: 'kitchen', label: 'Mutfak' },
        ],
        roleLabelFor: (name: string) => `${name} için rol`,
        roleErrorText: 'Rol değiştirilemedi.',
        ...overrides,
    };
}

function renderTable(overrides: Record<string, unknown> = {}) {
    const props = surfaceProps(overrides);

    render(<TeamMemberTableDesktop {...props} />);

    return props;
}

function rowOf(name: RegExp) {
    return within(screen.getByRole('table')).getByRole('row', { name });
}

describe('TeamMemberTableDesktop', () => {
    it('ekibi sütun başlıkları görünür bir tablo olarak çizer; sahip satırında eylem yoktur, uygun satır geçirilen geri çağrıları kullanır', async () => {
        const user = userEvent.setup();
        const props = renderTable();

        const table = screen.getByRole('table');

        /*
            BAŞLIKLAR GÖRÜNÜRDÜR. Görünmez bir `aria-label` ekran okuyucuya
            yeter ama masada oturan kişi tam olarak bu dört kelimeye bakarak
            sütun arasında gezinir.
        */
        for (const header of [/name/i, /e-?mail/i, /role/i, /action/i]) {
            expect(within(table).getByRole('columnheader', { name: header })).toBeInTheDocument();
        }

        /*
            SAHİP SATIRI: ne rol kutusu, ne "Çıkar", ne "Sahipliği devret".
            Sahiplik buradan değişmez ve sahipsiz kalan bir çalışma alanını
            kimse onaramaz.
        */
        const ownerRow = rowOf(/Mehmet Usta/);

        expect(within(ownerRow).queryByRole('combobox')).toBeNull();
        expect(within(ownerRow).queryAllByRole('button')).toHaveLength(0);

        // Rol: seçim GEÇİRİLEN geri çağrıya iner, tablonun kendi yoluna değil.
        await user.selectOptions(within(rowOf(/Ayşe Yılmaz/)).getByRole('combobox'), 'manager');

        expect(props.onChangeRole).toHaveBeenCalledWith(2, 'manager');

        // Çıkarma: onay basamağı korunur ve geri çağrı üyenin kimliğini alır.
        await user.click(within(rowOf(/Ayşe Yılmaz/)).getByRole('button', { name: REMOVE_BUTTON }));
        await user.click(
            within(rowOf(/Ayşe Yılmaz/)).getByRole('button', { name: REMOVE_CONFIRM }),
        );

        expect(props.onRemoveMember).toHaveBeenCalledWith(2);

        // Devir: ayrı bir akış, ayrı bir onay, aynı kural — geçirilen geri çağrı.
        await user.click(
            within(rowOf(/Ayşe Yılmaz/)).getByRole('button', { name: TRANSFER_BUTTON }),
        );
        await user.click(screen.getByRole('button', { name: TRANSFER_CONFIRM }));

        expect(props.onTransferOwnership).toHaveBeenCalledWith(2);
    });

    it('yasak ve bulunamadı cevapları ayrı kalır ve tek çıkış yolu vazgeçmektir', async () => {
        const user = userEvent.setup();
        const onRemoveMember = vi.fn(async (memberId: number): Promise<TeamMemberRemoveOutcome> =>
            memberId === 2 ? 'forbidden' : 'missing',
        );

        renderTable({ onRemoveMember });

        await user.click(within(rowOf(/Ayşe Yılmaz/)).getByRole('button', { name: REMOVE_BUTTON }));
        await user.click(
            within(rowOf(/Ayşe Yılmaz/)).getByRole('button', { name: REMOVE_CONFIRM }),
        );

        /*
            "Bu iş sahibin" ile "o üyelik zaten orada değil" İKİ AYRI
            gerçektir ve çıkış yolları farklıdır: birinde sahibinden
            istenir, diğerinde yapacak bir şey yoktur.
        */
        const forbiddenRow = rowOf(/Ayşe Yılmaz/);

        expect(within(forbiddenRow).getByText(REMOVE_FORBIDDEN)).toBeInTheDocument();
        expect(within(forbiddenRow).queryByText(REMOVE_MISSING)).toBeNull();
        expect(within(forbiddenRow).queryByRole('button', { name: REMOVE_CONFIRM })).toBeNull();
        expect(within(forbiddenRow).queryByRole('button', { name: REMOVE_RETRY })).toBeNull();
        expect(
            within(forbiddenRow).getByRole('button', { name: REMOVE_CANCEL }),
        ).toBeInTheDocument();

        await user.click(within(rowOf(/Kerem Aksu/)).getByRole('button', { name: REMOVE_BUTTON }));
        await user.click(within(rowOf(/Kerem Aksu/)).getByRole('button', { name: REMOVE_CONFIRM }));

        const missingRow = rowOf(/Kerem Aksu/);

        expect(within(missingRow).getByText(REMOVE_MISSING)).toBeInTheDocument();
        expect(within(missingRow).queryByText(REMOVE_FORBIDDEN)).toBeNull();
        expect(within(missingRow).queryByRole('button', { name: REMOVE_CONFIRM })).toBeNull();
        expect(within(missingRow).queryByRole('button', { name: REMOVE_RETRY })).toBeNull();
        expect(within(missingRow).getByRole('button', { name: REMOVE_CANCEL })).toBeInTheDocument();

        // Kesin cevap TEKRAR DENENMEZ: sunucuya ikinci bir istek gitmedi.
        expect(onRemoveMember).toHaveBeenCalledTimes(2);

        // Vazgeçmek satırı başa döndürür — sahip listede mahsur kalmaz.
        await user.click(within(rowOf(/Kerem Aksu/)).getByRole('button', { name: REMOVE_CANCEL }));

        expect(
            within(rowOf(/Kerem Aksu/)).getByRole('button', { name: REMOVE_BUTTON }),
        ).toBeInTheDocument();
    });
});
