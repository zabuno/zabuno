import { expect } from 'vitest';
import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import type { RegisterLegalPayload } from './LegalDocumentModal';

/**
 * Kayıt ekranının yasal metinleri — TEST YOLCULUĞU İÇİN (REG-LEGAL-01).
 *
 * Kutular metin olmadan işaretlenemez: gösteremediğimiz bir belgeyi
 * okuduğunu kimseye söyletmeyiz. Bu yüzden kayıt akışını sınayan her test
 * belgeleri sunucudan gelmiş gibi taşır ve beyanı, gerçek kullanıcı gibi,
 * metnin sonundaki düğmeden verir. Tek bir yerde durur ki dört test dosyası
 * aynı yolculuğu dört farklı şekilde taklit etmesin.
 */
export const REGISTER_LEGAL: RegisterLegalPayload = {
    reviewPending: true,
    documents: Object.fromEntries(
        (
            [
                ['terms', '/terms', 'Terms of Service'],
                ['privacy', '/privacy', 'Privacy Policy'],
                ['marketing-consent', '/marketing-consent', 'Commercial message consent text'],
            ] as const
        ).map(([key, url, title]) => [
            key,
            {
                key,
                url,
                title,
                summary: `The ${title} in short.`,
                version: '0.1',
                effectiveDate: '2026-09-09',
                language: 'en',
                sections: [{ heading: title, paragraphs: [`The body of the ${title}.`] }],
            },
        ]),
    ),
};

/** Kutuya basar (metin açılır) ve metnin sonundaki tek olumlu düğmeye basar. */
export async function acceptLegalDocument(name: RegExp): Promise<void> {
    fireEvent.click(screen.getByRole('checkbox', { name }));

    const dialog = await screen.findByRole('dialog');

    fireEvent.click(
        within(within(dialog).getByTestId('legal-document-action')).getByRole('button'),
    );

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
}

/** Zorunlu iki belge: Hizmet Koşulları KABUL, Gizlilik Politikası BEYAN. */
export async function acceptMandatoryLegalDocuments(): Promise<void> {
    await acceptLegalDocument(/accept the terms of service/i);
    await acceptLegalDocument(/read and understood the privacy policy/i);
}
