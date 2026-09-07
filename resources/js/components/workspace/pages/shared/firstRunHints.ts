/**
 * İlk-kez ipucunun BİLEŞEN-DIŞI parçaları (FF-202): adım adları, yardım
 * makalesinin gerçek çapaları ve cihazda hatırlama anahtarı.
 *
 * Ayrı dosyada, çünkü bileşen dosyasından bileşen olmayan bir şey
 * dışa aktarmak Fast Refresh'i o dosya için kapatır; testler ve başka
 * ekranlar bu sabitleri bileşeni yüklemeden okuyabilmeli.
 */
export type FirstRunStep = 'brand' | 'location' | 'menu' | 'publication' | 'qr';

/** "Devam" kipinin gidebildiği adımlar: ancak bir öncekinin ekranı olan adımlar. */
export type FirstRunNextStep = 'location' | 'menu';

/**
 * Yardım makalesinde GERÇEKTEN var olan bölümler (`resources/help/en/
 * first-15-minutes.blade.php`). Marka, şube ve yayın için bölüm yok; onlara
 * bağlantı UYDURULMAZ — `FirstRunHint.test.tsx` bu listeyi dosyayla
 * karşılaştırır.
 */
export const HELP_ARTICLE_ANCHORS: Partial<Record<FirstRunStep, string>> = {
    menu: '/help#help-import',
    qr: '/help#help-qr',
};

export function firstRunHintStorageKey(
    workspaceId: number,
    step: FirstRunStep,
    mode: 'screen' | 'next' = 'screen',
): string {
    return `zabuno.first-run.${String(workspaceId)}.${mode}.${step}`;
}
