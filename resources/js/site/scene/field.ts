import type { SceneEffect, SceneFrame, SceneTier } from './contract';

/**
 * DERİNLİKLİ ALAN — sahnenin omurgası.
 *
 * ── NEDEN GERÇEK PERSPEKTİF, "PARALLAX TAKLİDİ" DEĞİL ──
 *
 * Yaygın çözüm, üç ayrı yıldız katmanını üç farklı hızda yana kaydırmaktır.
 * Ucuzdur ve YANLIŞ görünür: gerçek derinlikte yıldızlar yana kaymaz,
 * MERKEZDEN DIŞARI açılır ve yaklaştıkça hızlanır. Buradaki alanda her
 * yıldızın gerçek bir `z` değeri var ve ekrandaki yeri `x/z` ile
 * hesaplanıyor — yani parallax bir efekt değil, geometrinin sonucu. Üç
 * "düzlem" yerine sürekli bir derinlik var.
 *
 * ── NEDEN KÜTÜPHANE YOK ──
 *
 * Ölçüldü (2026-09-08, `docs/146` §2): three.js 86,6 KB gzip, GSAP+
 * ScrollTrigger 46,3 KB gzip. İkisi de bu sahnenin ihtiyacı olmayan şeyleri
 * taşıyor (sahne grafiği, malzeme sistemi, zaman çizelgesi motoru). Elle
 * yazılan motorun tamamı bir kaç kilobayt ve — asıl mesele — DERECELENDİRME
 * merdiveni kendi kodumuzda: bir kütüphane, düşük güçlü telefonda hangi
 * katmanın söneceğini bilemez.
 *
 * ── HAREKET NEREDE HESAPLANIYOR ──
 *
 * WebGL yolunda yıldızların ilerlemesi TAMAMEN köşe gölgelendiricisindedir:
 * her karede yalnız birkaç `uniform` yazılır, tek bir çizim çağrısı yapılır
 * ve CPU hiç döngü kurmaz. Bu, kare süresini yıldız sayısından neredeyse
 * bağımsız yapan tek şey.
 */

interface FieldOptions {
    /** İmleç ve kaydırmanın kamerayı ne kadar ötelediği (dünya birimi). */
    readonly sway: number;
    /** Yıldızların yaklaşma hızı (dünya birimi / saniye). */
    readonly speed: number;
}

const NEAR = 0.35;
const DEPTH = 5.2;
const FOCAL = 1.15;

/** Derece başına yıldız sayısı ve tuval çözünürlüğü. */
const GRADE: Record<SceneTier, { readonly stars: number; readonly scale: number }> = {
    full: { stars: 1400, scale: 1 },
    reduced: { stars: 640, scale: 0.7 },
    /* `minimal` hiç boyanmaz; sayı yine de tanımlı, çünkü tip eksiksiz olmalı. */
    minimal: { stars: 0, scale: 0.5 },
};

const VERTEX_SOURCE = `
precision mediump float;
attribute vec3 a_pos;
attribute vec3 a_color;
attribute float a_rand;
uniform float u_time;
uniform float u_aspect;
uniform float u_size;
uniform float u_speed;
uniform vec2 u_cam;
varying vec3 v_color;
varying float v_alpha;
void main() {
    float z = ${NEAR.toFixed(2)} + mod(a_pos.z - u_time * u_speed, ${DEPTH.toFixed(2)});
    vec2 p = (a_pos.xy - u_cam) * (${FOCAL.toFixed(2)} / z);
    p.x /= u_aspect;
    gl_Position = vec4(p, 0.0, 1.0);
    float born = 1.0 - smoothstep(${(DEPTH * 0.6).toFixed(2)}, ${DEPTH.toFixed(2)}, z);
    float past = smoothstep(${NEAR.toFixed(2)}, ${(NEAR * 3.0).toFixed(2)}, z);
    v_alpha = born * past * (0.18 + a_rand * 1.15);
    v_color = a_color;
    gl_PointSize = clamp(u_size * (0.35 + a_rand * 2.2) / z, 0.8, 26.0);
}
`;

const FRAGMENT_SOURCE = `
precision mediump float;
varying vec3 v_color;
varying float v_alpha;
void main() {
    vec2 d = gl_PointCoord - vec2(0.5);
    float r = min(dot(d, d) * 4.0, 1.0);
    float core = 1.0 - smoothstep(0.0, 1.0, r);
    gl_FragColor = vec4(v_color, 1.0) * (core * core * v_alpha);
}
`;

/** Bir yıldız kümesi: konum, renk ve dağılım. Her iki çizici de bunu okur. */
interface StarModel {
    readonly position: Float32Array;
    readonly color: Float32Array;
    readonly random: Float32Array;
    readonly count: number;
}

/**
 * Yıldızlar SABİT bir tohumla üretilir.
 *
 * Rastgeleliğin kendisi önemli değil; TEKRARLANABİLİRLİĞİ önemli. Aynı
 * sayfa iki kez açıldığında aynı gökyüzü doğar ve bir ekran görüntüsü
 * karşılaştırması anlamlı kalır. `Math.random()` bunu imkânsız yapardı.
 */
function seeded(seed: number): () => number {
    let state = seed >>> 0;

    return () => {
        state = (state * 1664525 + 1013904223) >>> 0;

        return state / 4294967296;
    };
}

function buildStars(count: number, palette: readonly [number, number, number][]): StarModel {
    const position = new Float32Array(count * 3);
    const color = new Float32Array(count * 3);
    const random = new Float32Array(count);
    const next = seeded(0x5eed_1a2b);

    for (let i = 0; i < count; i += 1) {
        /*
            YAYILMA GENİŞ.

            `x` ve `y` görüntü alanından taşacak kadar geniş bir kutuda
            duruyor; aksi hâlde kamera imleçle ötelendiğinde kenarda BOŞ bir
            şerit görünürdü — yıldızların bittiği yer görünür olurdu.
        */
        position[i * 3] = (next() * 2 - 1) * 2.6;
        position[i * 3 + 1] = (next() * 2 - 1) * 2.6;
        position[i * 3 + 2] = next() * DEPTH;

        /*
            RENK DAĞILIMI: çoğunluk yıldız beyazı, azınlık marka renkleri.
            Tersi olsaydı gökyüzü bir disko olurdu; vurgunun etkisi
            nadirliğinden gelir.
        */
        const roll = next();
        const tone =
            roll < 0.72
                ? palette[0]
                : roll < 0.85
                  ? palette[1]
                  : roll < 0.95
                    ? palette[2]
                    : palette[3];

        color[i * 3] = tone[0];
        color[i * 3 + 1] = tone[1];
        color[i * 3 + 2] = tone[2];

        /*
            PARLAKLIK DAĞILIMI DÜZ DEĞİL, ÜSSEL.

            Düz dağılımda her yıldız birbirine benziyordu ve gökyüzü
            "kar tanesi gürültüsü" gibi okunuyordu (2026-09-08, 1280×800
            ekran görüntüsü). Gerçek bir gökyüzünde parlaklık üssel dağılır:
            binlerce sönük nokta, birkaç tane göze çarpan. Derinlik hissini
            veren şey yıldızların SAYISI değil, aralarındaki FARK.
        */
        const spread = next();
        random[i] = spread * spread * spread;
    }

    return { position, color, random, count };
}

/** `#rrggbb` → 0…1 aralığında üç kanal. Tanınmayan bir değer beyaza düşer. */
export function parseColor(value: string): [number, number, number] {
    const hex = value.trim().replace('#', '');

    if (hex.length !== 6 && hex.length !== 3) {
        return [1, 1, 1];
    }

    const full =
        hex.length === 3
            ? hex
                  .split('')
                  .map((c) => c + c)
                  .join('')
            : hex;

    const number = Number.parseInt(full, 16);

    if (Number.isNaN(number)) {
        return [1, 1, 1];
    }

    return [((number >> 16) & 255) / 255, ((number >> 8) & 255) / 255, (number & 255) / 255];
}

/**
 * Sahnenin paletini DOM'dan okur.
 *
 * Renkler betiğe GÖMÜLMEZ: tema açık/koyu değiştiğinde ya da palet
 * güncellendiğinde ikinci bir yerde ikinci bir renk tanımı kalmasın. Betik
 * yalnız `--scene-*` adlarını bilir; o adların hangi kurumsal jetondan
 * geldiğini bilmez ve bilmemelidir.
 */
export function readPalette(element: Element): readonly [number, number, number][] {
    const style = getComputedStyle(element);
    const names = [
        '--scene-star-core',
        '--scene-star-beam',
        '--scene-star-pulse',
        '--scene-star-signal',
    ];

    return names.map((name) => parseColor(style.getPropertyValue(name)));
}

interface Renderer {
    resize(width: number, height: number): void;
    draw(
        time: number,
        camX: number,
        camY: number,
        aspect: number,
        size: number,
        speed: number,
    ): void;
    destroy(): void;
}

function createWebglRenderer(canvas: HTMLCanvasElement, model: StarModel): Renderer | null {
    const gl =
        (canvas.getContext('webgl', {
            alpha: true,
            antialias: false,
            depth: false,
        }) as WebGLRenderingContext | null) ??
        (canvas.getContext('experimental-webgl') as WebGLRenderingContext | null);

    if (gl === null) {
        return null;
    }

    const compile = (type: number, source: string): WebGLShader | null => {
        const shader = gl.createShader(type);

        if (shader === null) {
            return null;
        }

        gl.shaderSource(shader, source);
        gl.compileShader(shader);

        if (!gl.getShaderParameter(shader, gl.COMPILE_STATUS)) {
            gl.deleteShader(shader);

            return null;
        }

        return shader;
    };

    const vertex = compile(gl.VERTEX_SHADER, VERTEX_SOURCE);
    const fragment = compile(gl.FRAGMENT_SHADER, FRAGMENT_SOURCE);
    const program = gl.createProgram();

    if (vertex === null || fragment === null || program === null) {
        return null;
    }

    gl.attachShader(program, vertex);
    gl.attachShader(program, fragment);
    gl.linkProgram(program);

    if (!gl.getProgramParameter(program, gl.LINK_STATUS)) {
        return null;
    }

    gl.useProgram(program);

    const bind = (data: Float32Array, name: string, size: number): WebGLBuffer | null => {
        const buffer = gl.createBuffer();
        gl.bindBuffer(gl.ARRAY_BUFFER, buffer);
        gl.bufferData(gl.ARRAY_BUFFER, data, gl.STATIC_DRAW);
        const location = gl.getAttribLocation(program, name);
        gl.enableVertexAttribArray(location);
        gl.vertexAttribPointer(location, size, gl.FLOAT, false, 0, 0);

        return buffer;
    };

    const buffers = [
        bind(model.position, 'a_pos', 3),
        bind(model.color, 'a_color', 3),
        bind(model.random, 'a_rand', 1),
    ];

    const uniforms = {
        time: gl.getUniformLocation(program, 'u_time'),
        aspect: gl.getUniformLocation(program, 'u_aspect'),
        size: gl.getUniformLocation(program, 'u_size'),
        speed: gl.getUniformLocation(program, 'u_speed'),
        cam: gl.getUniformLocation(program, 'u_cam'),
    };

    /*
        TOPLAMALI KARIŞIM — ışık ışığın üstüne biner.

        İki yıldız üst üste geldiğinde biri diğerini ÖRTMEZ, ikisi birden
        parlar. Yoğun bölgelerde kendiliğinden bir ışıma (bloom) doğar ve
        bunun için ayrı bir geçiş, ayrı bir tampon, ayrı bir maliyet yok.
    */
    gl.blendFunc(gl.ONE, gl.ONE);
    gl.enable(gl.BLEND);
    gl.disable(gl.DEPTH_TEST);
    gl.clearColor(0, 0, 0, 0);

    return {
        resize(width, height) {
            gl.viewport(0, 0, width, height);
        },
        draw(time, camX, camY, aspect, size, speed) {
            gl.clear(gl.COLOR_BUFFER_BIT);
            gl.uniform1f(uniforms.time, time);
            gl.uniform1f(uniforms.aspect, aspect);
            gl.uniform1f(uniforms.size, size);
            gl.uniform1f(uniforms.speed, speed);
            gl.uniform2f(uniforms.cam, camX, camY);
            gl.drawArrays(gl.POINTS, 0, model.count);
        },
        destroy() {
            for (const buffer of buffers) {
                gl.deleteBuffer(buffer);
            }

            gl.deleteProgram(program);
            gl.deleteShader(vertex);
            gl.deleteShader(fragment);
            /*
                Bağlamı AÇIKÇA kaybettir. Tarayıcı başına eşzamanlı WebGL
                bağlamı sayısı sınırlıdır (çoğu masaüstünde 16); sayfa
                gezintisiz bir uygulamada bırakılan bağlamlar birikir ve
                sonunda YENİ bağlam açılamaz — sahne sessizce ölür.
            */
            gl.getExtension('WEBGL_lose_context')?.loseContext();
        },
    };
}

function createCanvasRenderer(canvas: HTMLCanvasElement, model: StarModel): Renderer | null {
    const context = canvas.getContext('2d');

    if (context === null) {
        return null;
    }

    let width = 0;
    let height = 0;

    return {
        resize(nextWidth, nextHeight) {
            width = nextWidth;
            height = nextHeight;
        },
        draw(time, camX, camY, aspect, size, speed) {
            context.clearRect(0, 0, width, height);
            /* Aynı toplamalı karışım, aynı gerekçe. */
            context.globalCompositeOperation = 'lighter';

            const halfW = width / 2;
            const halfH = height / 2;

            for (let i = 0; i < model.count; i += 1) {
                const z =
                    NEAR + ((((model.position[i * 3 + 2] - time * speed) % DEPTH) + DEPTH) % DEPTH);
                const px = ((model.position[i * 3] - camX) * (FOCAL / z)) / aspect;
                const py = (model.position[i * 3 + 1] - camY) * (FOCAL / z);

                if (px < -1 || px > 1 || py < -1 || py > 1) {
                    continue;
                }

                const rand = model.random[i];
                const born = 1 - smoothstep(DEPTH * 0.6, DEPTH, z);
                const past = smoothstep(NEAR, NEAR * 3, z);
                const alpha = Math.min(born * past * (0.18 + rand * 1.15), 1);

                if (alpha <= 0.01) {
                    continue;
                }

                const radius = Math.min(Math.max((size * (0.35 + rand * 2.2)) / z / 2, 0.4), 13);
                const r = Math.round(model.color[i * 3] * 255);
                const g = Math.round(model.color[i * 3 + 1] * 255);
                const b = Math.round(model.color[i * 3 + 2] * 255);

                context.fillStyle = `rgba(${r},${g},${b},${alpha.toFixed(3)})`;
                context.beginPath();
                context.arc(halfW + px * halfW, halfH + py * halfH, radius, 0, Math.PI * 2);
                context.fill();
            }
        },
        destroy() {
            context.clearRect(0, 0, width, height);
        },
    };
}

function smoothstep(edge0: number, edge1: number, x: number): number {
    const t = Math.min(Math.max((x - edge0) / (edge1 - edge0), 0), 1);

    return t * t * (3 - 2 * t);
}

/**
 * Alanı bir `<canvas>` üstüne kurar.
 *
 * @returns Efekt, ya da tuval çizilemiyorsa `null` — çizilemeyen bir sahne
 *          sessizce boş bir tuval bırakmaz, hiç kaydolmaz.
 */
export function createField(canvas: HTMLCanvasElement, options: FieldOptions): SceneEffect | null {
    const stage = canvas.closest('.site-stage') ?? canvas.parentElement ?? canvas;
    const palette = readPalette(canvas);

    let tier: SceneTier = 'full';
    let renderer: Renderer | null = null;
    let model: StarModel | null = null;
    let aspect = 1;
    let pixelSize = 1;
    let onScreen = true;

    /*
        GÖRÜNMEYEN SAHNE BOYANMAZ.

        Ziyaretçi fiyat bölümüne indiğinde kahraman sahnesi ekranda yoktur;
        onu boyamaya devam etmek, hiç kimsenin görmediği bir şey için pil
        harcamaktır. `IntersectionObserver` bunu tek bir geri çağrımla
        çözüyor — her karede konum ölçmeden.
    */
    const observer =
        typeof IntersectionObserver === 'function'
            ? new IntersectionObserver(
                  (entries) => {
                      onScreen = entries.some((entry) => entry.isIntersecting);
                  },
                  { rootMargin: '10% 0px' },
              )
            : null;

    observer?.observe(stage);

    const build = (nextTier: SceneTier): void => {
        renderer?.destroy();
        renderer = null;
        model = null;

        if (nextTier === 'minimal') {
            canvas.dataset.sceneLive = 'false';

            return;
        }

        const grade = GRADE[nextTier];
        model = buildStars(grade.stars, palette);
        renderer = createWebglRenderer(canvas, model);
        /*
            HANGİ ÇİZİCİ KULLANILDI — ölçüm için, süs için değil.

            `scripts/scene-perf-gate` bu özniteliği okur: yedek yola düşmüş
            bir sahnenin kare süresi elbette farklıdır ve iki ölçümü aynı
            satırda göstermek, ikisini de anlamsız yapardı.
        */
        canvas.dataset.sceneRenderer = renderer === null ? 'canvas' : 'webgl';

        if (renderer === null) {
            /*
                WebGL yoksa alan KAYBOLMAZ, ucuzlar: aynı geometri, CPU'da,
                yarı yıldızla. "Desteklenmiyorsa hiç gösterme" kararı,
                kurumsal bir vitrini eski bir tarayıcıda boş bırakırdı.
            */
            model = buildStars(Math.round(grade.stars / 2), palette);
            renderer = createCanvasRenderer(canvas, model);
        }

        if (renderer === null) {
            canvas.dataset.sceneLive = 'false';
        }
    };

    return {
        measure(nextTier) {
            const changed = nextTier !== tier || renderer === null;
            tier = nextTier;

            if (changed) {
                build(tier);
            }

            const rect = stage.getBoundingClientRect();
            const grade = GRADE[tier];
            const dpr = Math.min(window.devicePixelRatio || 1, tier === 'full' ? 2 : 1.5);
            const width = Math.max(Math.round(rect.width * dpr * grade.scale), 1);
            const height = Math.max(Math.round(rect.height * dpr * grade.scale), 1);

            canvas.width = width;
            canvas.height = height;
            aspect = rect.height === 0 ? 1 : rect.width / rect.height;
            /*
                Yıldız boyu tuvalin GERÇEK piksel yüksekliğine bağlı: aynı
                sahne, iki katı çözünürlüklü bir ekranda yarı boyutta
                yıldızlar göstermemeli.
            */
            pixelSize = height / 78;
            renderer?.resize(width, height);
        },
        visible() {
            return onScreen && tier !== 'minimal' && renderer !== null;
        },
        frame(scene: SceneFrame) {
            if (renderer === null) {
                return;
            }

            /*
                KAMERA — imleç ve kaydırma birlikte.

                Kaydırma katkısı bölümün KENDİ yüksekliğine göre değil,
                görüntü alanına göre normalize ediliyor: sahne sayfanın
                neresinde olursa olsun aynı tempoda süzülür.
            */
            const scrollShift = scene.height === 0 ? 0 : scene.scroll / scene.height;
            const camX = scene.pointerX * options.sway;
            const camY = scene.pointerY * options.sway * 0.6 + scrollShift * options.sway * 1.4;

            renderer.draw(scene.time, camX, camY, aspect, pixelSize, options.speed);
            canvas.dataset.sceneLive = 'true';
        },
        destroy() {
            observer?.disconnect();
            renderer?.destroy();
            renderer = null;
            canvas.dataset.sceneLive = 'false';
        },
    };
}
