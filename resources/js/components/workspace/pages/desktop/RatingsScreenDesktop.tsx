import type { ReactNode } from 'react';

import { RatingsPage } from '../RatingsPage';
import { ratingsPropsFromContext } from '../ratings/ratingsSectionProps';
import type { WorkspaceSectionRuntimeContext } from '../../WorkspaceApp';
import { RatingListDesktop } from './RatingListDesktop';

/**
 * PUANLAR ekranının MASAÜSTÜ bileşimi — `docs/151` §B.
 *
 * Sayfanın kararları (izin, ön koşul, yükleme, hata, boş liste, algoritma
 * sürümü ve "puan kaldırılamaz" cümlesi) PAYLAŞILANdIR ve buraya
 * kopyalanmaz. Değişen tek şey LİSTENİN çizimidir.
 */
export function RatingsScreenDesktop({ ctx }: { ctx: WorkspaceSectionRuntimeContext }): ReactNode {
    return (
        <RatingsPage
            {...ratingsPropsFromContext(ctx)}
            renderRatingList={(surface) => <RatingListDesktop {...surface} />}
        />
    );
}

export default RatingsScreenDesktop;
