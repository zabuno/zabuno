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
  rm -rf "$TMP/resources"
  mkdir -p "$TMP/resources/js/components/workspace/chrome" \
           "$TMP/resources/js/components/workspace/inspectors" \
           "$TMP/resources/js/components/workspace/shell" \
           "$TMP/resources/js/components/workspace/kitchen" \
           "$TMP/resources/js/components/workspace/pages/menu"

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
  printf "import type { WorkspaceInspectorMap } from './inspectors/types';\nimport reg from './shell/registry';\nexport const WorkspaceApp = (p: { i?: WorkspaceInspectorMap }) => [p, reg];\n" \
    > "$js/components/workspace/WorkspaceApp.tsx"
  printf "import { WorkspaceApp } from './components/workspace/WorkspaceApp';\nimport { DesktopChrome } from './components/workspace/chrome/DesktopChrome';\nimport { KitchenBoard } from './components/workspace/kitchen/KitchenBoard';\nimport { desktopInspectors } from './components/workspace/inspectors/desktopInspectors';\nexport default [WorkspaceApp, DesktopChrome, KitchenBoard, desktopInspectors];\n" \
    > "$js/workspace.desktop.tsx"
  printf "import { WorkspaceApp } from './components/workspace/WorkspaceApp';\nimport { MobileChrome } from './components/workspace/chrome/MobileChrome';\nexport default [WorkspaceApp, MobileChrome];\n" \
    > "$js/workspace.mobile.tsx"
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

# Giriş yoksa ayrım ölçülemez; PASS demek yalan olurdu.
scaffold
rm "$TMP/resources/js/workspace.mobile.tsx"
expect NO_ENTRY "giriş eksikse ölçüm yapıldığını iddia etmez"

if [ "$failures" -gt 0 ]; then
  printf '%d senaryo başarısız\n' "$failures"
  exit 1
fi
echo "tüm senaryolar geçti"
