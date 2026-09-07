#!/usr/bin/env bash
# scripts/logical-direction-gate için belirlenimci test (docs/121 Ö10, docs/132).
#
# Bir kapı, temiz bir ağaçta PASS demekle işe yaramaz. Asıl soru şudur:
# fiziksel yön geri konduğunda KIRILIYOR mu, ve gerekçesiz bir istisnayla
# susturulabiliyor mu? Bu depoda daha önce yaşanan kusur ailesi tam olarak
# budur — çalışan ama söylediği şeyi ölçmeyen kapı (`docs/109` §8.7).
#
# Sentetik bir kaynak ağacı kurulur (`--root`), çünkü kapının kendi ölçümü
# `git ls-files`'a dayanır ve geçici bir dizinde Git yoktur.

set -u
cd "$(dirname "$0")/.."
GATE="$(pwd)/scripts/logical-direction-gate"

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
failures=0

expect() {
  # $1 = beklenen verdict, $2 = açıklama
  local want="$1" desc="$2" got
  got="$(node "$GATE" --root "$TMP/src" --baseline "$TMP/baseline.json" 2>/dev/null | tail -1)"
  if [ "$got" = "$want" ]; then
    printf '  ok   %s\n' "$desc"
  else
    printf '  FAIL %s (beklenen %s, gelen %s)\n' "$desc" "$want" "$got"
    failures=$((failures + 1))
  fi
}

scaffold() {
  rm -rf "$TMP/src"
  mkdir -p "$TMP/src/views" "$TMP/src/css"
  printf '<div class="ms-4 pe-2 text-start border-s rounded-ss-lg">x</div>\n' \
    > "$TMP/src/views/clean.blade.php"
  printf '.a{margin-inline-start:1px;padding-inline-end:2px;inset-inline-start:0;text-align:start}\n' \
    > "$TMP/src/css/clean.css"
  printf 'export const A = () => <i style={{ marginInlineStart: 1 }} />;\n' \
    > "$TMP/src/clean.tsx"
  printf '{ "istisnalar": {} }\n' > "$TMP/baseline.json"
}

echo "logical-direction-gate"

scaffold
expect PASS "mantıksal özellikler geçer"

# 1. Tailwind fiziksel sınıfı — bu paketin kapatmak için yazıldığı aile.
scaffold
printf '<div class="ml-4">x</div>\n' > "$TMP/src/views/dirty.blade.php"
expect PHYSICAL_DIRECTION_FOUND "fiziksel Tailwind sınıfı kırar"

# 2. Değersiz fiziksel utility (`text-left`) de bir sınıftır.
scaffold
printf '<div class="text-left">x</div>\n' > "$TMP/src/views/dirty.blade.php"
expect PHYSICAL_DIRECTION_FOUND "değersiz fiziksel utility kırar"

# 3. Ham CSS fiziksel özelliği.
scaffold
printf '.b{padding-left:4px}\n' > "$TMP/src/css/dirty.css"
expect PHYSICAL_DIRECTION_FOUND "ham CSS fiziksel özelliği kırar"

# 4. `text-align: center` yönlü DEĞİLDİR; yanlış alarm vermemeli.
scaffold
printf '.c{text-align:center}\n' > "$TMP/src/css/centered.css"
expect PASS "yönsüz text-align yanlış alarm vermez"

# 5. JSX iç stil anahtarı sınıf tarayıcısına görünmez ama tarayıcıya ulaşır.
scaffold
printf 'export const B = () => <i style={{ paddingRight: 4 }} />;\n' > "$TMP/src/dirty.tsx"
expect PHYSICAL_DIRECTION_FOUND "JSX iç stil fiziksel anahtarı kırar"

# 6. `rtl:` varyantı yönü BİLİYOR — kaçamak değil, açık karar.
scaffold
printf '<div class="rtl:ml-4">x</div>\n' > "$TMP/src/views/aware.blade.php"
expect PASS "rtl: varyantı ihlal sayılmaz"

# 7. Gerekçeli istisna susturur.
scaffold
printf '<div class="ml-4">x</div>\n' > "$TMP/src/views/dirty.blade.php"
printf '{ "istisnalar": { "views/dirty.blade.php": { "ml-4": "Bu bir fotoğrafın fiziksel çizimidir ve okuma yönüyle aynalanmaz." } } }\n' \
  > "$TMP/baseline.json"
expect PASS "gerekçeli istisna susturur"

# 8. GEREKÇESİZ istisna kapıyı KIRAR — susturma bir karar değildir.
scaffold
printf '<div class="ml-4">x</div>\n' > "$TMP/src/views/dirty.blade.php"
printf '{ "istisnalar": { "views/dirty.blade.php": { "ml-4": "" } } }\n' > "$TMP/baseline.json"
expect UNDOCUMENTED_EXCEPTION "gerekçesiz istisna kapıyı kırar"

# 9. İstisna DOSYAYA bağlıdır: aynı sınıf başka bir dosyada hâlâ kırar.
scaffold
printf '<div class="ml-4">x</div>\n' > "$TMP/src/views/dirty.blade.php"
printf '<div class="ml-4">y</div>\n' > "$TMP/src/views/other.blade.php"
printf '{ "istisnalar": { "views/dirty.blade.php": { "ml-4": "Yalnız bu dosya için verilmiş bir gerekçedir, sınıfın kendisi için değil." } } }\n' \
  > "$TMP/baseline.json"
expect PHYSICAL_DIRECTION_FOUND "istisna yalnız yazıldığı dosyayı kapsar"

# 10. Artık bulunmayan istisna raporlanır ama KIRMAZ: bir dosyayı düzelten
#     paketi "listeyi de güncellemedin" diye kırmızıya düşürmek iyileştirmeyi
#     cezalandırırdı (`scripts/mobile-ux-audit` ile aynı karar).
scaffold
printf '{ "istisnalar": { "views/gone.blade.php": { "ml-4": "Çoktan silinmiş bir dosyanın gerekçesi; listede kalması bir kusur değil." } } }\n' \
  > "$TMP/baseline.json"
expect PASS "bayat istisna raporlanır ama kırmaz"

if [ "$failures" -gt 0 ]; then
  printf '%d senaryo başarısız\n' "$failures"
  exit 1
fi
echo "tüm senaryolar geçti"
