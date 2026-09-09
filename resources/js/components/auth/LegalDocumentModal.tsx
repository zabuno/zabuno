import { useEffect, useId, type ReactNode } from 'react';
import clsx from 'clsx';
import { Modal, ModalBody, useModalContext } from 'flowbite-react';
import { CloseButton } from '../catalog/overlays/micro/CloseButton';
import { Button } from '../catalog/forms/micro/Button';
import { CheckboxField } from '../catalog/forms/compound/CheckboxField';
import { t } from '../../i18n/auth';

export type LegalDocumentSection = {
    heading: string;
    paragraphs: string[];
};

/** Sunucunun kayıt sayfasıyla birlikte gönderdiği belge — `RegistrationLegalPayload`. */
export type LegalDocumentPayload = {
    key: string;
    url: string;
    title: string;
    summary: string;
    version: string;
    effectiveDate: string;
    /** BCP-47: sayfa Türkçeyken İngilizce bir sözleşme yanlış dilde okunmasın. */
    language: string;
    sections: LegalDocumentSection[];
};

export type RegisterLegalPayload = {
    /** Metin bir hukukçu tarafından okundu mu (`LegalReview`)? */
    reviewPending: boolean;
    documents: Partial<Record<string, LegalDocumentPayload>>;
};

/*
    YÜZEY BİZİM — `ConfirmDialog` ile aynı gerekçe (`docs/102` §5h).
    Flowbite'ın grisi maviye çalar; yüzey rengi çağrı noktasında token'la
    geçilir. Paylaşılan ilkel BİLEŞEN DEĞİŞTİRİLMEDİ, deseni tekrar edildi.
*/
const TOKEN_SURFACE = 'border-border bg-surface text-fg-secondary';

/**
 * Başlık kimliğini Flowbite'ın `ModalContext`ine kaydeder ki diyalog doğru
 * `aria-labelledby` taşısın — `ConfirmDialog` ile aynı desen, aynı gerekçe:
 * `ModalHeader`ın kendi kapatma düğmesini getirmeden başlığı bağlamak.
 */
function LegalDialogTitle({ id, children }: { id: string; children: ReactNode }) {
    const { setHeaderId } = useModalContext();

    useEffect(() => {
        setHeaderId(id);

        return () => setHeaderId(undefined);
    }, [id, setHeaderId]);

    return (
        <h2 id={id} className="text-subsection font-bold text-fg">
            {children}
        </h2>
    );
}

/**
 * Belgenin kimliği: sürüm, yürürlük tarihi ve METNİN dili.
 *
 * YALNIZ PENCEREDE. Kartta bu satır, henüz okunmamış bir belgenin yanında
 * duran anlamsız bir künyeydi; okunan yerde ise metnin hangi sürümü olduğunu
 * söylediği için gerçekten gerekli.
 */
function DocumentMeta({ legal }: { legal: LegalDocumentPayload }) {
    return (
        <p className="text-meta text-fg-muted">
            {t('auth.register.legal.version', {
                version: legal.version,
                date: legal.effectiveDate,
            })}
            {' · '}
            {t('auth.register.legal.language', { language: languageName(legal.language) })}
        </p>
    );
}

/*
    Dil ADIYLA yazılır, etiketiyle değil: "Metnin dili: en" bir bilgi değil,
    bir sızıntıdır. `Intl` yoksa etikete düşülür — yanlış bir ad uydurmaktansa
    ham etiket dürüsttür.
*/
function languageName(tag: string): string {
    try {
        return new Intl.DisplayNames([tag], { type: 'language' }).of(tag) ?? tag;
    } catch {
        return tag;
    }
}

export type LegalDocumentModalProps = {
    open: boolean;
    onClose: () => void;
    legal: LegalDocumentPayload;
    reviewPending: boolean;
    /** Metnin SONUNDAKİ tek olumlu eylemin etiketi — kabul ya da beyan. */
    actionLabel: string;
    /** Eylem: bu belgeyi işaretler VE pencereyi kapatır. */
    onAccept: () => void;
};

/**
 * Kaydolurken imzalanan metnin TAM HÂLİ — REG-LEGAL-01.
 *
 * ═══ TEK KABUL YOLU: METNİN SONUNDAKİ DÜĞME ═══
 *
 * Kapatma düğmesi, Escape ve arka plana tıklama pencereyi kapatır ve HİÇBİR
 * ŞEYİ işaretlemez. Sona kaydırmak da işaretlemez: bir kutunun görünür olması
 * onun okunduğunu kanıtlamaz.
 *
 * Eylem, metnin ALTINDA ve AYNI kaydırma alanının içindedir. Sabit bir
 * altbilgiye konsaydı, metin daha ilk satırdayken "kabul ediyorum" düğmesi
 * ekranda dururdu. Kısa belgelerde kaydırma hiç olmaz ve düğme doğrudan
 * görünür — bu bir kusur değil, kısa metnin doğal sonucudur.
 */
export function LegalDocumentModal({
    open,
    onClose,
    legal,
    reviewPending,
    actionLabel,
    onAccept,
}: LegalDocumentModalProps) {
    const titleId = useId();

    return (
        <Modal show={open} onClose={onClose} dismissible size="3xl" className={TOKEN_SURFACE}>
            <div className="flex items-start justify-between gap-2 p-4">
                <div className="flex flex-col gap-1">
                    <LegalDialogTitle id={titleId}>{legal.title}</LegalDialogTitle>
                    <DocumentMeta legal={legal} />
                </div>
                <CloseButton onClick={onClose} label={t('auth.register.legal.close')} />
            </div>

            <ModalBody className="p-0">
                {/* TEK kaydırma alanı: metin ve eylem birlikte kayar. */}
                <div
                    data-testid="legal-document-scroll"
                    className="max-h-[60dvh] overflow-y-auto px-4 pb-4"
                >
                    {reviewPending && (
                        <p
                            role="note"
                            className="mb-3 rounded-lg border border-warning bg-surface-warning p-3 text-meta text-fg-warning"
                        >
                            {t('auth.register.legal.review_pending')}
                        </p>
                    )}

                    {/*
                        `lang` BELGENİN dilidir, sayfanın değil: Türkçe bir
                        kabuğun içine düşen İngilizce bir sözleşme, dilini
                        söylemezse ekran okuyucuya yanlış dilde okunur.
                    */}
                    <div
                        data-testid="legal-document-text"
                        lang={legal.language}
                        className="flex flex-col gap-4"
                    >
                        <p className="text-body text-fg-secondary">{legal.summary}</p>

                        {legal.sections.map((section, index) => (
                            <section key={section.heading} className="flex flex-col gap-2">
                                <h3 className="text-body font-semibold text-fg">
                                    {index + 1}. {section.heading}
                                </h3>
                                {section.paragraphs.map((paragraph) => (
                                    <p key={paragraph} className="text-body text-fg-secondary">
                                        {paragraph}
                                    </p>
                                ))}
                            </section>
                        ))}
                    </div>

                    {/* METNİN SONUNDAKİ AÇIK EYLEM — kaydırma alanının İÇİNDE. */}
                    <div
                        data-testid="legal-document-action"
                        className="mt-4 flex flex-col gap-3 rounded-lg border border-border bg-surface-subtle p-3"
                    >
                        <Button type="button" onClick={onAccept}>
                            {actionLabel}
                        </Button>
                        <p className="text-meta text-fg-muted">
                            {t('auth.register.legal.action_hint')}
                        </p>
                    </div>
                </div>
            </ModalBody>
        </Modal>
    );
}

export type LegalDocumentCardProps = {
    /** Belge yoksa kart yine çizilir, ama kutusu işaretlenemez. */
    legal?: LegalDocumentPayload;
    reviewPending: boolean;
    /** Metin gelmediyse kullanılacak yedek başlık. */
    fallbackTitle: string;
    checkboxId: string;
    checkboxName: string;
    checkboxLabel: string;
    checkboxNote?: string;
    required?: boolean;
    checked: boolean;
    onChange: (checked: boolean) => void;
    errorText?: string;
    readLabel: string;
    /*
        Metnin sonundaki olumlu eylemin etiketi. VARSAYILAN kutu etiketidir:
        düğme ile kutu aynı şeyi söylemeli, yoksa okuduğu metnin altında
        bastığı düğmenin hangi kutuyu doldurduğu belirsiz kalır.

        Ayrı verilebilmesinin tek sebebi ÖLÇÜLDÜ: ticari ileti kutusunun
        etiketi iki cümledir ve ikincisi ("İsteğe bağlıdır") seçimin bir
        NİTELİĞİDİR, eylemin kendisi değil. Aynı cümle düğmeye konunca 320
        pikselde beş satıra sarıyor ve 480 piksellik ekranın dörtte birini
        kaplıyordu. Kutunun tam metni ve isteğe bağlı notu YERİNDE KALIR;
        kısalan yalnız eylemin adı, ve o ad neyin kabul edildiğini
        (ticari e-posta) açıkça söylemeye devam eder.
    */
    actionLabel?: string;
    open: boolean;
    onOpen: () => void;
    onClose: () => void;
};

/**
 * Bir belge = bir kart: başlığı, kutusu ve tam metni.
 *
 * ═══ İŞARETLEMEK ÖNCE METNİ AÇAR ═══
 *
 * Boş bir kutuya basmak kutuyu işaretlemez; belgeyi açar ve kutu boş kalır.
 * Okunmamış bir metnin "okudum" kutusunu tek dokunuşla doldurmak, beyanı
 * bir formaliteye çevirirdi. İşaret ancak metnin sonundaki düğmeyle konur.
 *
 * GERİ ALMAK ANINDADIR: işaretli bir kutuyu boşaltmak için metni tekrar
 * açmaya gerek yoktur — verilmiş bir beyanı geri almak hiçbir zaman
 * zorlaştırılmaz.
 */
export function LegalDocumentCard({
    legal,
    reviewPending,
    fallbackTitle,
    checkboxId,
    checkboxName,
    checkboxLabel,
    checkboxNote,
    required = false,
    checked,
    onChange,
    errorText,
    readLabel,
    actionLabel,
    open,
    onOpen,
    onClose,
}: LegalDocumentCardProps) {
    return (
        <section
            data-legal-card={legal?.key ?? checkboxName}
            className={clsx(
                'flex flex-col gap-3 rounded-lg border p-3',
                errorText ? 'border-border-danger' : 'border-border',
                'bg-surface-subtle',
            )}
        >
            <h2 className="text-body font-semibold text-fg">{legal?.title ?? fallbackTitle}</h2>

            <CheckboxField
                id={checkboxId}
                name={checkboxName}
                required={required}
                disabled={!legal}
                checked={checked}
                onChange={(event) => {
                    /*
                        İŞARETLEMEK BİR İSTEKTİR, BİR BEYAN DEĞİL: kutu boşken
                        gelen dokunuş metni açar ve değer FALSE kalır. Boşaltmak
                        ise doğrudan uygulanır.
                    */
                    if (event.target.checked) {
                        onOpen();

                        return;
                    }

                    onChange(false);
                }}
                label={checkboxLabel}
                helpText={checkboxNote}
                errorText={errorText}
            />

            {legal ? (
                <div className="flex flex-wrap items-center gap-x-4 gap-y-1">
                    <Button type="button" color="alternative" size="sm" onClick={onOpen}>
                        {readLabel}
                    </Button>
                </div>
            ) : (
                /*
                    METİN YOKSA BEYAN DA YOK: gösteremediğimiz bir belgeyi
                    okuduğunu söyletmeyiz ve yerine bir bağlantı koyup işi
                    kullanıcıya devretmeyiz.
                */
                <p className="text-meta text-fg-muted">{t('auth.register.legal.unavailable')}</p>
            )}

            {legal ? (
                <LegalDocumentModal
                    open={open}
                    onClose={onClose}
                    legal={legal}
                    reviewPending={reviewPending}
                    actionLabel={actionLabel ?? checkboxLabel}
                    onAccept={() => {
                        onChange(true);
                        onClose();
                    }}
                />
            ) : null}
        </section>
    );
}
