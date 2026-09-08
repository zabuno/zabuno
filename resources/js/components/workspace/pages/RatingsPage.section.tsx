import { Star } from '@phosphor-icons/react';
import { lazy, Suspense, type ReactNode } from 'react';
import type { WorkspaceSectionRuntimeContext } from '../WorkspaceApp';
import type { WorkspaceSectionDescriptor } from '../shell/WorkspaceSectionRegistry';
import { ratingsPropsFromContext } from './ratings/ratingsSectionProps';

/*
    EKRAN İSTENDİĞİNDE İNER (FF-97): kaydın metadatası (ad, ikon, sıra, izin)
    kenar çubuğunu çizmek için eager kalır, yalnız ÇİZİM ertelenir.
*/
const RatingsPage = lazy(async () => ({
    default: (await import('./RatingsPage')).RatingsPage,
}));

function render(ctx: WorkspaceSectionRuntimeContext): ReactNode {
    return (
        <Suspense fallback={null}>
            <RatingsPage {...ratingsPropsFromContext(ctx)} />
        </Suspense>
    );
}

const ratingsSection: WorkspaceSectionDescriptor = {
    key: 'ratings',
    /*
        GERÇEK BİR ADRES, FRAGMENT DEĞİL (`docs/38` §4): `/app/{w}/ratings`
        paylaşılabilir, ölçülebilir ve tarayıcı geçmişinde anlamlıdır.
    */
    path: 'ratings',
    order: 13,
    labelKey: 'workspace.shell.nav.ratings',
    icon: <Star size={18} weight="regular" />,
    permission: 'rating.view',
    group: 'primary',
    render,
};

export default ratingsSection;
