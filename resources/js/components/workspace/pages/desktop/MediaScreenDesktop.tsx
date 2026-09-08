import type { ReactNode } from 'react';

import { MediaPage } from '../MediaPage';
import { mediaPropsFromContext } from '../media/mediaSectionProps';
import type { WorkspaceSectionRuntimeContext } from '../../WorkspaceApp';
import { MediaLibraryDesktop } from './MediaLibraryDesktop';

/**
 * MEDYA ekranının MASAÜSTÜ bileşimi — `docs/151` §B.
 *
 * `OrdersScreenDesktop` ile aynı biçim: sayfanın kendisi (kabuk, bölüm
 * sekmeleri, klasör şeridi, yükleme, toplu sihirbaz, denetim izi)
 * PAYLAŞILANdIR ve buraya kopyalanmaz. Değişen tek şey KÜTÜPHANENİN
 * çizimidir ve o da bir çizici olarak geçirilir.
 *
 * İki kopya sekme mantığı, yarın bir izin değiştiğinde yalnız birinde
 * düzeltilirdi — ve düzeltilmeyen taraf, o izne sahip olmayan birine
 * yapamayacağı bir işi göstermeye devam ederdi.
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
