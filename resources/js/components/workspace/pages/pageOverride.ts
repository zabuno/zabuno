import type { ReactNode } from 'react';
import type { WorkspaceSectionRuntimeContext } from '../WorkspaceApp';

/**
 * CİHAZA ÖZGÜ SAYFA HARİTASI — `docs/149` §5.
 *
 * Bölüm kaydı (`*.section.tsx`) bir bölümün METADATASINI ve VARSAYILAN
 * çizimini taşır. Kayıtlar `import.meta.glob` ile TOPLUCA ve EAGER
 * okunduğu için her kayıt iki pakete birden girer; yani cihaza özgü bir
 * ekran yeni bir kayıt dosyası OLAMAZ — yazıldığı anda telefona da inerdi.
 *
 * Ayrım bu yüzden kayıtta değil, GİRİŞ NOKTASINDA yapılır: masaüstü girişi
 * bu haritayı verir, mobil giriş hiç vermez. Harita boşsa (telefon) her
 * bölüm bugünkü çizimiyle çizilir ve hiçbir şey değişmez.
 *
 * `inspectors` ve `renderKitchenMonitor` ile aynı desenin genelidir: onlar
 * bir bölümün PARÇASINI cihaza özgü kılar, bu harita bölümün TAMAMINI.
 * Yeni bir ekran masaüstüne taşındığında bu depoda değişen tek yer, cihaz
 * paketindeki harita olur — paylaşılan hiçbir dosya cihaz adı taşımaz.
 *
 * ÖNEMLİ: bu bir "masaüstü haritası" değildir. Tür cihaz adı taşımaz;
 * yarın mobil giriş kendi haritasını verirse aynı sözleşme çalışır.
 */
export type WorkspacePageRenderer = (ctx: WorkspaceSectionRuntimeContext) => ReactNode;

export type WorkspacePageOverrideMap = Record<string, WorkspacePageRenderer>;
