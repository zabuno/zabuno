import type { ReactNode } from 'react';

import { MediaPage } from '../MediaPage';
import { mediaPropsFromContext } from '../media/mediaSectionProps';
import type { WorkspaceSectionRuntimeContext } from '../../WorkspaceApp';
import { MediaLibraryDesktop } from './MediaLibraryDesktop';

/**
 * MEDYA ekranının MASAÜSTÜ bileşimi — `docs/153` §6.
 *
 * `OrdersScreenDesktop` ile aynı biçim ve aynı sebep: sayfanın kendisi
 * (sekmeler, yükleme, dönüştür, kuyruk, kota, ayarlar, izin kapıları)
 * PAYLAŞILANdır ve buraya kopyalanmaz — iki kopya sekme mantığı, yarın bir
 * izin değiştiğinde yalnız birinde düzeltilirdi. Değişen tek şey
 * KÜTÜPHANENİN çizimidir ve o da bir çizici olarak geçirilir.
 *
 * Yani bu dosya bir sayfa DEĞİL, bir BİLEŞİMdir: paylaşılan sayfa + bu
 * cihazın kütüphanesi. Ortak olan paylaşılır, ayrışan enjekte edilir
 * (`docs/153` §4).
 */
export function MediaScreenDesktop({ ctx }: { ctx: WorkspaceSectionRuntimeContext }): ReactNode {
    return (
        <MediaPage
            {...mediaPropsFromContext(ctx)}
            renderLibrary={(surface) => <MediaLibraryDesktop {...surface} />}
        />
    );
}

export default MediaScreenDesktop;
