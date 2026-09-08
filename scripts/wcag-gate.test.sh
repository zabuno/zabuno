#!/usr/bin/env bash
#
# WCAG KAPISININ KENDİ DENEĞİ.
#
# ═══ NEDEN VAR ═══
#
# Bir ölçüm aracının en tehlikeli hâli, sessizce hiçbir şey bulmayan hâlidir:
# rapor temiz görünür, kapı yeşil yanar ve kimse ölçümün çalışmadığını
# öğrenmez. Bu paketin kendi geçmişi bunu iki kez gösterdi —
#
#   · metni gizlediğini sanan arka plan levhası metni gizlemiyordu ve kapı
#     metnin KENDİ rengini "arka plan" sanıp her paragrafta 1:1 bildiriyordu;
#   · %200 punto ölçümü tek geçişte okuyup yazdığı için puntoyu derinlik
#     başına KATLIYOR ve altbilgide "12.684 piksel taşma" diye olmayan bir
#     sayı üretiyordu.
#
# İkisi de "kapı çalışıyor" gibi görünüyordu. Aşağıdaki denekler o sessizliği
# bozar: kontrastı ÖNCEDEN BİLİNEN sentetik görüntüler verilir ve kapının
# aritmetiğinin doğru sayıyı bulması ŞART koşulur.
#
# Kapsam: yalnız aritmetik (`scripts/wcag-gate.contrast.mjs`). Tarayıcı
# tarafı burada ölçülmez — o, kapının kendisini gerçek bir sayfaya karşı
# koşturmakla ölçülür.

set -u

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

pass=0
fail=0

report() {
    local name="$1" ok="$2" detail="$3"
    if [ "$ok" -eq 0 ]; then
        pass=$((pass + 1))
        printf 'ok - %s\n' "$name"
    else
        fail=$((fail + 1))
        printf 'not ok - %s: %s\n' "$name" "$detail"
    fi
}

run_case() {
    local name="$1" script="$2" output status
    output="$(node --input-type=module -e "$script" 2>&1)"
    status=$?
    report "$name" "$status" "$output"
}

MODULE="file://$SCRIPT_DIR/wcag-gate.contrast.mjs"

# Sentetik görüntü: `channels` kanallı, düz renkli bir zemin; istenirse
# içine ikinci bir renkten dikdörtgen boyanır.
PRELUDE=$(
    cat <<PRELUDE_EOF
import assert from 'node:assert/strict';
import { luminance, contrastRatio, worstContrastUnder, bandColors, boundaryContrast } from '$MODULE';

const image = (width, height, base) => {
    const channels = 3;
    const pixels = Buffer.alloc(width * height * channels);
    for (let i = 0; i < width * height; i += 1) {
        pixels[i * 3] = base[0];
        pixels[i * 3 + 1] = base[1];
        pixels[i * 3 + 2] = base[2];
    }
    return { width, height, channels, pixels };
};

const paint = (img, rect, color) => {
    for (let y = rect.y; y < rect.y + rect.h; y += 1) {
        for (let x = rect.x; x < rect.x + rect.w; x += 1) {
            const i = (y * img.width + x) * img.channels;
            img.pixels[i] = color[0];
            img.pixels[i + 1] = color[1];
            img.pixels[i + 2] = color[2];
        }
    }
    return img;
};

const near = (actual, expected, tolerance, label) =>
    assert.ok(
        Math.abs(actual - expected) <= tolerance,
        label + ': beklenen ' + expected + ', ölçülen ' + actual,
    );
PRELUDE_EOF
)

# --- 1. Bilinen WCAG oranları -------------------------------------------
#
# Dört değer, dört ayrı kaynaktan doğrulanabilir: siyah/beyaz tam 21:1'dir,
# #767676 beyaz üstünde ölçütün asgarisini kıl payı geçer, altın sarısı
# beyaz üstünde geçemez ve deponun koyu kip gövde metni geçer.
run_case "bilinen kontrast oranları" "$PRELUDE
near(contrastRatio(luminance(0, 0, 0), luminance(255, 255, 255)), 21, 0.001, 'siyah/beyaz');
near(contrastRatio(luminance(118, 118, 118), luminance(255, 255, 255)), 4.54, 0.01, '#767676/beyaz');
near(contrastRatio(luminance(255, 179, 0), luminance(255, 255, 255)), 1.79, 0.01, 'signal-500/beyaz');
near(contrastRatio(luminance(169, 164, 198), luminance(10, 8, 32)), 8.25, 0.01, 'starlight-dim/void-900');
"

# --- 2. Düz zeminde metin ------------------------------------------------
run_case "düz zeminde kontrast doğru ölçülür" "$PRELUDE
const img = image(60, 20, [10, 8, 32]);
const measured = worstContrastUnder(img, { x: 0, y: 0, w: 60, h: 20 }, { r: 169, g: 164, b: 198 }, 1);
near(measured.ratio, 8.25, 0.01, 'düz zemin');
"

# --- 3. YARI SAYDAM METİN gerçekten soluk ölçülür ------------------------
#
# Opaklığı yok sayan bir ölçüm burada 8.25 der ve bir kusuru kaçırır.
run_case "opaklık metnin gerçek rengini soldurur" "$PRELUDE
const img = image(60, 20, [10, 8, 32]);
const opaque = worstContrastUnder(img, { x: 0, y: 0, w: 60, h: 20 }, { r: 169, g: 164, b: 198 }, 1);
const faded = worstContrastUnder(img, { x: 0, y: 0, w: 60, h: 20 }, { r: 169, g: 164, b: 198 }, 0.3);
assert.ok(faded.ratio < opaque.ratio, 'saydam metin daha kötü çıkmalıydı');
assert.ok(faded.ratio < 2, 'opaklık 0.3 iken oran 2:1 altında olmalı, ölçülen ' + faded.ratio);
"

# --- 4. LEKELİ ZEMİN — bu paketin asıl deneği ---------------------------
#
# Zeminin %20'si eşiğin altına düşüyorsa metin orada okunmuyor demektir.
# Ayrık renk sayan eski ölçüm bunu kaçırıyordu; yüzdelik ölçüm görmeli.
run_case "zeminin beşte biri parlaksa bulgu verir" "$PRELUDE
const img = image(100, 20, [10, 8, 32]);
paint(img, { x: 0, y: 0, w: 20, h: 20 }, [230, 230, 230]);
const measured = worstContrastUnder(img, { x: 0, y: 0, w: 100, h: 20 }, { r: 169, g: 164, b: 198 }, 1);
assert.ok(measured.ratio < 4.5, '%20 parlak zeminde 4.5 altına düşmeliydi, ölçülen ' + measured.ratio);
"

# --- 5. TEK BİR YILDIZ paragrafı kusurlu göstermez ----------------------
#
# Aynı ölçümün öteki yüzü: binde bir pikselin bütün bir paragrafı devirmesi
# gürültüdür ve gürültü üreten bir kapı kapatılır.
run_case "tek parlak piksel paragrafı devirmez" "$PRELUDE
const img = image(200, 20, [10, 8, 32]);
paint(img, { x: 0, y: 0, w: 2, h: 2 }, [255, 255, 255]);
const measured = worstContrastUnder(img, { x: 0, y: 0, w: 200, h: 20 }, { r: 169, g: 164, b: 198 }, 1);
assert.ok(measured.ratio > 4.5, 'tek piksel bulgu üretmemeliydi, ölçülen ' + measured.ratio);
near(measured.absoluteWorst, 2.38, 0.05, 'mutlak en kötü piksel yine de raporlanmalı');
"

# --- 6. KONTROLÜN SINIRI: bir piksellik çizgi görülür -------------------
#
# İlk sürüm burada kırılıyordu: dolgusu zeminle aynı olan bir girdinin bir
# piksellik kenarını "baskın renk" sorgusu her zaman kaybediyordu.
run_case "bir piksellik kenar çizgisi sınır sayılır" "$PRELUDE
const img = image(80, 40, [13, 10, 36]);
const box = { x: 10, y: 10, w: 60, h: 20 };
for (let x = box.x; x < box.x + box.w; x += 1) {
    paint(img, { x, y: box.y, w: 1, h: 1 }, [200, 200, 210]);
    paint(img, { x, y: box.y + box.h - 1, w: 1, h: 1 }, [200, 200, 210]);
}
for (let y = box.y; y < box.y + box.h; y += 1) {
    paint(img, { x: box.x, y, w: 1, h: 1 }, [200, 200, 210]);
    paint(img, { x: box.x + box.w - 1, y, w: 1, h: 1 }, [200, 200, 210]);
}
const measured = boundaryContrast(img, box);
assert.ok(measured.ratio >= 3, 'kenar çizgisi 3:1 üstü sayılmalıydı, ölçülen ' + measured.ratio);
"

# --- 7. SINIRI OLMAYAN GİRDİ yakalanır ----------------------------------
run_case "kenarsız girdi bulgu verir" "$PRELUDE
const img = image(80, 40, [13, 10, 36]);
const measured = boundaryContrast(img, { x: 10, y: 10, w: 60, h: 20 });
near(measured.ratio, 1, 0.001, 'kenarsız girdi');
"

# --- 8. ŞERİT RENKLERİ ortalanmaz ---------------------------------------
#
# İki renkli bir şeridin ortalaması ikisinden de olmayan üçüncü bir renktir
# ve o renk ekranda hiç yoktur.
run_case "şerit renkleri ortalanmaz, sayılır" "$PRELUDE
const img = image(40, 10, [0, 0, 0]);
paint(img, { x: 0, y: 0, w: 30, h: 10 }, [255, 255, 255]);
const colors = bandColors(img, [{ x: 0, y: 0, w: 40, h: 10 }]);
assert.equal(colors[0].r, 255, 'baskın renk beyaz olmalıydı');
near(colors[0].share, 0.75, 0.001, 'baskın rengin payı');
assert.equal(colors[1].r, 0, 'ikinci renk siyah olmalıydı');
"

printf '\n%d ok, %d not ok\n' "$pass" "$fail"

[ "$fail" -eq 0 ] || exit 1
