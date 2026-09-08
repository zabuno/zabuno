#!/usr/bin/env bash
# scripts/adaptive-bundle-gate için belirlenimci test (docs/54, docs/60).
#
# Kapı, geçen bir ağaçta PASS demekle işe yaramaz: asıl soru, sızıntı geri
# konduğunda KIRILIP kırılmadığıdır. Bu yüzden test geçici bir kaynak ağacı
# kurar ve kapıyı hem temiz hem sızdıran hâlde çalıştırır.
#
# FF-03a'da kapının ilk hâli, sızıntıyı yeniden ürettiğim hâlde PASS demişti:
# bildirim panel HARİTASINI adlandırıyordu, sızan şey panel BİLEŞENİYDİ. Bu
# testin son senaryosu tam o durumu tutar.

set -u
cd "$(dirname "$0")/.."
GATE="$(pwd)/scripts/adaptive-bundle-gate"

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
failures=0

expect() {
  # $1 = beklenen verdict, $2 = açıklama
  local want="$1" desc="$2" got
  got="$("$GATE" --root "$TMP/resources/js" 2>/dev/null | tail -1)"
  if [ "$got" = "$want" ]; then
    printf '  ok   %s\n' "$desc"
  else
    printf '  FAIL %s (beklenen %s, gelen %s)\n' "$desc" "$want" "$got"
    failures=$((failures + 1))
  fi
}

scaffold() {
  rm -rf "$TMP/resources" "$TMP/vite.config.ts"
  mkdir -p "$TMP/resources/css" \
           "$TMP/resources/js/components/workspace/chrome" \
           "$TMP/resources/js/components/workspace/inspectors" \
           "$TMP/resources/js/components/workspace/shell" \
           "$TMP/resources/js/components/workspace/kitchen" \
           "$TMP/resources/js/components/workspace/pages/menu" \
           "$TMP/resources/js/components/workspace/pages/desktop" \
           "$TMP/resources/js/components/workspace/pages/mobile" \
           "$TMP/resources/js/i18n/workspace-desktop"

  local js="$TMP/resources/js"
  # Bölüm kayıtları glob ile toplanır — asıl sızıntı yolu buydu.
  printf "const mods = import.meta.glob('../pages/*.section.tsx', { eager: true });\nexport default mods;\n" \
    > "$js/components/workspace/shell/registry.tsx"
  printf "export const MenuPage = () => null;\n" > "$js/components/workspace/pages/MenuPage.tsx"
  printf "import { MenuPage } from './MenuPage';\nexport default { render: MenuPage };\n" \
    > "$js/components/workspace/pages/MenuPage.section.tsx"
  printf "export const MenuInspector = () => null;\n" \
    > "$js/components/workspace/pages/menu/MenuInspector.tsx"
  printf "import { MenuInspector } from '../pages/menu/MenuInspector';\nexport const desktopInspectors = { menu: MenuInspector };\n" \
    > "$js/components/workspace/inspectors/desktopInspectors.tsx"
  printf "export type WorkspaceInspectorMap = Record<string, unknown>;\n" \
    > "$js/components/workspace/inspectors/types.ts"
  printf "export const DesktopChrome = () => null;\n" \
    > "$js/components/workspace/chrome/DesktopChrome.tsx"
  # MUTFAK MONİTÖRÜ — kapının DESKTOP_ONLY listesindeki her bildirimin bu
  # sentetik sahnede bir karşılığı OLMAK ZORUNDA. Kapı "eşleşmeyen bildirim"
  # durumunu bilerek LEAK sayıyor (bayat bildirim sessizce geçmesin diye);
  # sahne eksik kalırsa temiz senaryo da LEAK der ve öz-test kendi kapısını
  # suçlar. Yeni bir cihaza özgü klasör eklendiğinde buraya da bir dosya
  # eklenir — bu bir bakım yükü değil, kapının sözleşmesinin kendisi.
  printf "export const KitchenBoard = () => null;\n" \
    > "$js/components/workspace/kitchen/KitchenBoard.tsx"
  printf "export const MobileChrome = () => null;\n" \
    > "$js/components/workspace/chrome/MobileChrome.tsx"
  # CİHAZ KLASÖRÜ KONVANSİYONU (`docs/153` §8): `pages/desktop/` altındaki her
  # dosya masaüstü paketine kilitlidir. `pages/mobile/` bilerek BOŞ bırakılır —
  # boş bir cihaz klasörü hata DEĞİLDİR ve temiz senaryo bunu da kanıtlar.
  printf "export const OrdersScreenDesktop = () => null;\n" \
    > "$js/components/workspace/pages/desktop/OrdersScreenDesktop.tsx"
  # MASAÜSTÜNÜN KENDİ DİZELERİ (`docs/151`). Toplayıcı TÜR ARGÜMANLI bir glob
  # yazar ve bu bilerek böyle: kapının örüntüsü tür argümanını atlamayı
  # unutursa katalog dosyası "ulaşılamıyor" görünür ve temiz senaryo kırılır.
  printf "const mods = import.meta.glob<{ x?: never }>('./workspace-desktop/*.ts', { eager: true });\nexport default mods;\n" \
    > "$js/i18n/workspace-desktop.ts"
  printf "export const orderingDesktop = { a: 'A' };\n" \
    > "$js/i18n/workspace-desktop/ordering.ts"
  printf "import type { WorkspaceInspectorMap } from './inspectors/types';\nimport reg from './shell/registry';\nexport const WorkspaceApp = (p: { i?: WorkspaceInspectorMap }) => [p, reg];\n" \
    > "$js/components/workspace/WorkspaceApp.tsx"
  printf "import { WorkspaceApp } from './components/workspace/WorkspaceApp';\nimport { DesktopChrome } from './components/workspace/chrome/DesktopChrome';\nimport { KitchenBoard } from './components/workspace/kitchen/KitchenBoard';\nimport { OrdersScreenDesktop } from './components/workspace/pages/desktop/OrdersScreenDesktop';\nimport i18nDesktop from './i18n/workspace-desktop';\nimport { desktopInspectors } from './components/workspace/inspectors/desktopInspectors';\nexport default [WorkspaceApp, DesktopChrome, KitchenBoard, OrdersScreenDesktop, i18nDesktop, desktopInspectors];\n" \
    > "$js/workspace.desktop.tsx"
  printf "import { WorkspaceApp } from './components/workspace/WorkspaceApp';\nimport { MobileChrome } from './components/workspace/chrome/MobileChrome';\nexport default [WorkspaceApp, MobileChrome];\n" \
    > "$js/workspace.mobile.tsx"

  # ═══ STİL KATMANI (`docs/151`) ═══
  #
  # Kapı artık CSS zincirini de yürüyor, yani sentetik sahnenin de bir stil
  # tarafı olmak zorunda. Olmasaydı temiz senaryo "masaüstü katmanı yok"
  # diyerek LEAK verir ve JavaScript senaryolarının hepsi anlamsızlaşırdı.
  local css="$TMP/resources/css"
  printf "@import 'tailwindcss';\n@import './shared-bits.css';\nbody { color: #000; }\n" \
    > "$css/app.css"
  printf ".shared { padding: 8px; }\n" > "$css/shared-bits.css"
  printf "[data-device='desktop'] .dk-row { min-height: 32px; }\n@media (hover: hover) and (pointer: fine) {\n  [data-device='desktop'] .dk-row { min-height: 28px; }\n}\n" \
    > "$css/app-desktop.css"
  printf "export default { input: ['resources/css/app.css', 'resources/css/app-desktop.css'] };\n" \
    > "$TMP/vite.config.ts"
}

echo "adaptive-bundle-gate"

scaffold
expect PASS "ayrı paketler temizken geçer"

# ASIL SIZINTI: paylaşılan bölüm kaydı panel bileşenini çeker; glob üzerinden
# mobil girişe ulaşır. Kapının ilk hâli burada yanlışlıkla PASS diyordu.
scaffold
printf "import { MenuInspector } from './menu/MenuInspector';\nvoid MenuInspector;\nimport { MenuPage } from './MenuPage';\nexport default { render: MenuPage };\n" \
  > "$TMP/resources/js/components/workspace/pages/MenuPage.section.tsx"
expect LEAK "panel bileşeni paylaşılan bölüm dosyasından sızarsa kırılır"

# Doğrudan sızıntı: kabuk masaüstü haritasını çalışma zamanında çeker.
scaffold
printf "import { desktopInspectors } from './inspectors/desktopInspectors';\nimport reg from './shell/registry';\nexport const WorkspaceApp = () => [desktopInspectors, reg];\n" \
  > "$TMP/resources/js/components/workspace/WorkspaceApp.tsx"
expect LEAK "paylaşılan kabuk masaüstü haritasını çekerse kırılır"

# Ters yön: telefona özgü kabuk masaüstü paketine girerse.
scaffold
printf "import { WorkspaceApp } from './components/workspace/WorkspaceApp';\nimport { DesktopChrome } from './components/workspace/chrome/DesktopChrome';\nimport { MobileChrome } from './components/workspace/chrome/MobileChrome';\nimport { desktopInspectors } from './components/workspace/inspectors/desktopInspectors';\nexport default [WorkspaceApp, DesktopChrome, MobileChrome, desktopInspectors];\n" \
  > "$TMP/resources/js/workspace.desktop.tsx"
expect LEAK "telefona özgü kabuk masaüstü paketine girerse kırılır"

# Tip importu derlemede silinir: sızıntı DEĞİLDİR, yanlış alarm vermemeli.
scaffold
printf "import type { desktopInspectors } from './inspectors/desktopInspectors';\nimport reg from './shell/registry';\nexport const WorkspaceApp = (p: typeof desktopInspectors) => [p, reg];\n" \
  > "$TMP/resources/js/components/workspace/WorkspaceApp.tsx"
expect PASS "yalnız tip importu yanlış alarm vermez"

# Ölü bildirim: dosya yoksa sessizce geçmemeli.
scaffold
rm "$TMP/resources/js/components/workspace/chrome/MobileChrome.tsx"
printf "import { WorkspaceApp } from './components/workspace/WorkspaceApp';\nexport default [WorkspaceApp];\n" \
  > "$TMP/resources/js/workspace.mobile.tsx"
expect LEAK "bildirilen dosya yoksa sessizce geçmez"

# ═══ CİHAZ KLASÖRÜ KONVANSİYONU (`docs/153` §8) ═══
#
# Yukarıdaki senaryolar tek tek ADLANDIRILAN modülleri koruyor. Klasör kuralı
# ise adlandırılmayanı korur: yarın `pages/desktop/` altına yazılan bir dosya,
# kapının listesine hiç yazılmadan kilitli olmalı. Aşağıdaki üç senaryo tam
# olarak bunu sınar — biri kaldırılırsa konvansiyon bir yorum satırına döner.

# Masaüstü sayfası paylaşılan bölüm kaydından mobil girişe sızarsa.
scaffold
printf "import { OrdersScreenDesktop } from './desktop/OrdersScreenDesktop';\nvoid OrdersScreenDesktop;\nimport { MenuPage } from './MenuPage';\nexport default { render: MenuPage };\n" \
  > "$TMP/resources/js/components/workspace/pages/MenuPage.section.tsx"
expect LEAK "pages/desktop altındaki dosya mobil pakete sızarsa kırılır"

# Ölü masaüstü sayfası: klasörde duruyor ama hiçbir girişten ulaşılmıyor.
scaffold
printf "import { WorkspaceApp } from './components/workspace/WorkspaceApp';\nimport { DesktopChrome } from './components/workspace/chrome/DesktopChrome';\nimport { KitchenBoard } from './components/workspace/kitchen/KitchenBoard';\nimport { desktopInspectors } from './components/workspace/inspectors/desktopInspectors';\nexport default [WorkspaceApp, DesktopChrome, KitchenBoard, desktopInspectors];\n" \
  > "$TMP/resources/js/workspace.desktop.tsx"
expect LEAK "pages/desktop altındaki dosyaya masaüstünden ulaşılmıyorsa kırılır"

# Ters yön: `pages/mobile/` altındaki bir dosya masaüstü paketine girerse.
scaffold
printf "export const OrderQueueTouch = () => null;\n" \
  > "$TMP/resources/js/components/workspace/pages/mobile/OrderQueueTouch.tsx"
printf "import { WorkspaceApp } from './components/workspace/WorkspaceApp';\nimport { DesktopChrome } from './components/workspace/chrome/DesktopChrome';\nimport { KitchenBoard } from './components/workspace/kitchen/KitchenBoard';\nimport { OrdersScreenDesktop } from './components/workspace/pages/desktop/OrdersScreenDesktop';\nimport i18nDesktop from './i18n/workspace-desktop';\nimport { OrderQueueTouch } from './components/workspace/pages/mobile/OrderQueueTouch';\nimport { desktopInspectors } from './components/workspace/inspectors/desktopInspectors';\nexport default [WorkspaceApp, DesktopChrome, KitchenBoard, OrdersScreenDesktop, i18nDesktop, OrderQueueTouch, desktopInspectors];\n" \
  > "$TMP/resources/js/workspace.desktop.tsx"
expect LEAK "pages/mobile altındaki dosya masaüstü paketine girerse kırılır"

# MASAÜSTÜ DİZE KATALOĞU MOBİL PAKETE SIZARSA (`docs/151` M1).
#
# Bir dize tablosu da bayttır ve bileşenden farkı yoktur: paylaşılan bir dosya
# onu adıyla andığı anda telefon indirir, çizilmese bile. Sızıntı yolu bilerek
# TÜR ARGÜMANLI bir glob üzerinden kuruluyor — kapının örüntüsü tür argümanını
# atlamayı bırakırsa bu senaryo sessizce PASS derdi ve depodaki bütün katalog
# glob'ları görünmez kalırdı (bu paketten önce tam olarak öyleydi).
scaffold
printf "const mods = import.meta.glob<{ x?: never }>('../../../i18n/workspace-desktop/*.ts', { eager: true });\nimport { MenuPage } from './MenuPage';\nexport default { render: MenuPage, mods };\n" \
  > "$TMP/resources/js/components/workspace/pages/MenuPage.section.tsx"
expect LEAK "masaüstü dize kataloğu mobil pakete sızarsa kırılır"

# ═══ STİL KATMANI (`docs/151`) ═══
#
# Bileşen kapısının stil karşılığı. Üç senaryo, üç ayrı arıza ailesi: sızıntı,
# ölü kod ve genişlikle cihaz seçimi. Biri düşerse "masaüstü stili yalnız
# masaüstüne iner" cümlesi bir yorum satırına döner.

# SIZINTI: paylaşılan stil girişi masaüstü katmanını içeri alırsa telefon da
# indirir — bileşen tarafındaki "paylaşılan dosya cihaza özgü modülü anıyor"un
# birebir aynısı.
scaffold
printf "@import 'tailwindcss';\n@import './app-desktop.css';\nbody { color: #000; }\n" \
  > "$TMP/resources/css/app.css"
expect LEAK "masaüstü stili paylaşılan CSS girişine sızarsa kırılır"

# ÖLÜ KOD: katman Vite girdi listesinde yoksa hiç derlenmez; yeşil bir kapı
# çizilmeyen bir stili "ayrılmış" diye raporlardı.
scaffold
printf "export default { input: ['resources/css/app.css'] };\n" > "$TMP/vite.config.ts"
expect LEAK "masaüstü stili Vite girdisi değilse (ölü) kırılır"

# GENİŞLİKLE CİHAZ SEÇİMİ: `docs/54`'ün reddettiği kararın CSS'e taşınmış hâli.
# `hover`/`pointer` sorguları serbesttir ve temiz senaryo bunu kanıtlıyor.
scaffold
printf "@media (min-width: 1024px) {\n  [data-device='desktop'] .dk-row { min-height: 28px; }\n}\n" \
  > "$TMP/resources/css/app-desktop.css"
expect LEAK "masaüstü stili genişlikle cihaz seçerse kırılır"

# Giriş yoksa ayrım ölçülemez; PASS demek yalan olurdu.
scaffold
rm "$TMP/resources/js/workspace.mobile.tsx"
expect NO_ENTRY "giriş eksikse ölçüm yapıldığını iddia etmez"

if [ "$failures" -gt 0 ]; then
  printf '%d senaryo başarısız\n' "$failures"
  exit 1
fi
echo "tüm senaryolar geçti"
