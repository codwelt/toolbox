<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppLayout from './../../Layouts/AppLayout.vue';
import { useOgImage } from '@/composables/useOgImage';

defineOptions({
    layout: AppLayout,
});

const props = defineProps({
    seo: {
        type: Object,
        required: true,
    },
});

const seoData = computed(() => props.seo || {});
const pageTitle = computed(() => seoData.value.title || 'Convertidor y paleta de colores online');
const pageDescription = computed(
    () =>
        seoData.value.description ||
        'Ingresa un hexadecimal y obtén sus equivalencias RGB, RGBA, HSL, HSV, CMYK y tonos relacionados para tu paleta de diseño.'
);
const { ogImageUrl } = useOgImage(seoData.value);

const colorInput = ref('#2563eb');
const jsonLdScriptEl = ref(null);

const sanitizedHex = computed(() => sanitizeHex(colorInput.value));
const isValidHex = computed(() => sanitizedHex.value.length === 7);

const colorMeta = computed(() => {
    if (!isValidHex.value) {
        return null;
    }

    const hex = sanitizedHex.value;
    const rgb = hexToRgb(hex);
    const hsl = rgbToHsl(rgb);
    const hsv = rgbToHsv(rgb);
    const cmyk = rgbToCmyk(rgb);

    return {
        hex,
        rgb,
        hsl,
        hsv,
        cmyk,
        palette: buildPalette(hsl),
    };
});

const paletteDefinitions = [
    {
        label: 'Colores complementarios',
        description: 'El color opuesto en la rueda cromática para generar contraste inmediato.',
        deltas: [0, 180],
        labels: ['Color base', 'Complementario'],
    },
    {
        label: 'La triada de colores',
        description: 'Tres tonos equidistantes que mantienen armonía y energía balanceada.',
        deltas: [0, 120, 240],
        labels: ['Color base', 'Triada 1', 'Triada 2'],
    },
    {
        label: 'Colores análogos',
        description: 'Tonos cercanos entre sí que mantienen unidad cromática.',
        deltas: [-30, 0, 30],
        labels: ['Análogo oscuro', 'Color base', 'Análogo claro'],
    },
    {
        label: 'Splits complementarios',
        description: 'El complementario y los dos tonos adyacentes para más sutileza.',
        deltas: [0, 150, 210],
        labels: ['Color base', 'Split 1', 'Split 2'],
    },
    {
        label: 'Cuadrado cromático',
        description: 'Cuatro tonos distribuidos cada 90° para paletas dinámicas.',
        deltas: [0, 90, 180, 270],
        labels: ['Color base', 'Cuadrado 1', 'Cuadrado 2', 'Cuadrado 3'],
    },
];

const paletteGroups = computed(() => {
    if (!colorMeta.value) {
        return [];
    }

    const baseHsl = colorMeta.value.hsl;

    const normalizeHue = (value) => ((value % 360) + 360) % 360;

    return paletteDefinitions.map((definition) => ({
        label: definition.label,
        description: definition.description,
        colors: definition.deltas.map((delta, index) => {
            const hue = normalizeHue(baseHsl.h + delta);
            const rgb = hslToRgb({ h: hue, s: baseHsl.s, l: baseHsl.l });

            return {
                hex: rgbToHex(rgb),
                textColor: getContrastColor(rgb),
                label: definition.labels?.[index] || (delta === 0 ? 'Color base' : `${delta >= 0 ? '+' : ''}${delta}°`),
            };
        }),
    }));
});

const formatList = computed(() => {
    const meta = colorMeta.value;
    if (!meta) {
        return [];
    }

    const { hex, rgb, hsl, hsv, cmyk } = meta;

    return [
        { label: 'HEX', value: hex },
        { label: 'RGB', value: `rgb(${rgb.r}, ${rgb.g}, ${rgb.b})` },
        { label: 'RGBA', value: `rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, 1)` },
        { label: 'HSL', value: `hsl(${Math.round(hsl.h)}, ${Math.round(hsl.s)}%, ${Math.round(hsl.l)}%)` },
        { label: 'HSV', value: `hsv(${Math.round(hsv.h)}, ${Math.round(hsv.s)}%, ${Math.round(hsv.v)}%)` },
        {
            label: 'CMYK',
            value: `${Math.round(cmyk.c)}% · ${Math.round(cmyk.m)}% · ${Math.round(cmyk.y)}% · ${Math.round(cmyk.k)}%`,
        },
    ];
});

const previewStyle = computed(() => ({
    background: colorMeta.value ? colorMeta.value.hex : '#e2e8f0',
}));

const previewContrastColor = computed(() => (colorMeta.value ? getContrastColor(colorMeta.value.rgb) : '#0f172a'));

const faqItems = computed(() => seoData.value.faq || []);

const faqStructured = computed(() =>
    faqItems.value.map((item) => ({
        '@type': 'Question',
        name: item.question,
        acceptedAnswer: {
            '@type': 'Answer',
            text: item.answer,
        },
    }))
);

const jsonLd = computed(() =>
    JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'WebApplication',
        name: pageTitle.value,
        url: seoData.value.canonical,
        applicationCategory: 'DesignApplication',
        description: pageDescription.value,
        offers: {
            '@type': 'Offer',
            price: '0',
            priceCurrency: 'USD',
        },
        potentialAction: {
            '@type': 'UseAction',
            target: seoData.value.canonical,
        },
        mainEntity: {
            '@type': 'FAQPage',
            mainEntity: faqStructured.value,
        },
    })
);

onMounted(() => {
    if (typeof document === 'undefined') {
        return;
    }

    const script = document.createElement('script');
    script.type = 'application/ld+json';
    script.text = jsonLd.value;
    document.head.appendChild(script);
    jsonLdScriptEl.value = script;
});

onBeforeUnmount(() => {
    if (jsonLdScriptEl.value && jsonLdScriptEl.value.parentNode) {
        jsonLdScriptEl.value.parentNode.removeChild(jsonLdScriptEl.value);
    }
});

function sanitizeHex(value) {
    if (!value) {
        return '';
    }

    const cleaned = value.trim().replace(/[^a-fA-F0-9]/g, '');

    if (cleaned.length === 3) {
        return `#${cleaned
            .split('')
            .map((char) => `${char}${char}`)
            .join('')
            .toUpperCase()}`;
    }

    if (cleaned.length >= 6) {
        return `#${cleaned.slice(0, 6).toUpperCase()}`;
    }

    return '';
}

function hexToRgb(hex) {
    const clean = hex.replace('#', '');
    const r = parseInt(clean.substring(0, 2), 16);
    const g = parseInt(clean.substring(2, 4), 16);
    const b = parseInt(clean.substring(4, 6), 16);

    return { r, g, b };
}

function rgbToHex({ r, g, b }) {
    const toHex = (value) => value.toString(16).padStart(2, '0');
    return `#${toHex(r)}${toHex(g)}${toHex(b)}`.toUpperCase();
}

function rgbToHsl({ r, g, b }) {
    const rNorm = r / 255;
    const gNorm = g / 255;
    const bNorm = b / 255;
    const max = Math.max(rNorm, gNorm, bNorm);
    const min = Math.min(rNorm, gNorm, bNorm);
    const delta = max - min;

    let h = 0;
    if (delta !== 0) {
        if (max === rNorm) {
            h = ((gNorm - bNorm) / delta) % 6;
        } else if (max === gNorm) {
            h = (bNorm - rNorm) / delta + 2;
        } else {
            h = (rNorm - gNorm) / delta + 4;
        }
        h *= 60;
        if (h < 0) {
            h += 360;
        }
    }

    const l = (max + min) / 2;
    const s = delta === 0 ? 0 : delta / (1 - Math.abs(2 * l - 1));

    return {
        h,
        s: s * 100,
        l: l * 100,
    };
}

function rgbToHsv({ r, g, b }) {
    const rNorm = r / 255;
    const gNorm = g / 255;
    const bNorm = b / 255;
    const max = Math.max(rNorm, gNorm, bNorm);
    const min = Math.min(rNorm, gNorm, bNorm);
    const delta = max - min;

    let h = 0;
    if (delta !== 0) {
        if (max === rNorm) {
            h = ((gNorm - bNorm) / delta) % 6;
        } else if (max === gNorm) {
            h = (bNorm - rNorm) / delta + 2;
        } else {
            h = (rNorm - gNorm) / delta + 4;
        }
        h *= 60;
        if (h < 0) {
            h += 360;
        }
    }

    const s = max === 0 ? 0 : delta / max;
    const v = max;

    return {
        h,
        s: s * 100,
        v: v * 100,
    };
}

function rgbToCmyk({ r, g, b }) {
    const rNorm = r / 255;
    const gNorm = g / 255;
    const bNorm = b / 255;
    const k = 1 - Math.max(rNorm, gNorm, bNorm);

    if (k === 1) {
        return { c: 0, m: 0, y: 0, k: 100 };
    }

    const c = (1 - rNorm - k) / (1 - k);
    const m = (1 - gNorm - k) / (1 - k);
    const y = (1 - bNorm - k) / (1 - k);

    return {
        c: c * 100,
        m: m * 100,
        y: y * 100,
        k: k * 100,
    };
}

function hslToRgb({ h, s, l }) {
    const hNorm = h / 360;
    const sNorm = s / 100;
    const lNorm = l / 100;

    if (sNorm === 0) {
        const gray = Math.round(lNorm * 255);
        return { r: gray, g: gray, b: gray };
    }

    const q = lNorm < 0.5 ? lNorm * (1 + sNorm) : lNorm + sNorm - lNorm * sNorm;
    const p = 2 * lNorm - q;

    const r = hueToRgb(p, q, hNorm + 1 / 3);
    const g = hueToRgb(p, q, hNorm);
    const b = hueToRgb(p, q, hNorm - 1 / 3);

    return {
        r: Math.round(r * 255),
        g: Math.round(g * 255),
        b: Math.round(b * 255),
    };
}

function hueToRgb(p, q, t) {
    let temp = t;

    if (temp < 0) {
        temp += 1;
    }
    if (temp > 1) {
        temp -= 1;
    }
    if (temp < 1 / 6) {
        return p + (q - p) * 6 * temp;
    }
    if (temp < 1 / 2) {
        return q;
    }
    if (temp < 2 / 3) {
        return p + (q - p) * (2 / 3 - temp) * 6;
    }

    return p;
}

function buildPalette(hsl) {
    const steps = [
        { delta: -28, label: 'Muy oscuro' },
        { delta: -12, label: 'Oscuro' },
        { delta: 0, label: 'Base' },
        { delta: 12, label: 'Claro' },
        { delta: 28, label: 'Muy claro' },
    ];

    return steps.map(({ delta, label }) => {
        const lightness = clamp(hsl.l + delta, 3, 97);
        const rgb = hslToRgb({ h: hsl.h, s: hsl.s, l: lightness });

        return {
            label,
            hex: rgbToHex(rgb),
            textColor: getContrastColor(rgb),
            delta,
        };
    });
}

function clamp(value, min, max) {
    return Math.min(Math.max(value, min), max);
}

function getContrastColor({ r, g, b }) {
    const brightness = (r * 299 + g * 587 + b * 114) / 1000;
    return brightness > 160 ? '#0f172a' : '#f8fafc';
}
</script>

<template>
    <div class="bg-light">
        <Head :title="pageTitle">
            <meta name="description" :content="pageDescription" />
            <meta v-if="seoData.keywords && seoData.keywords.length" name="keywords" :content="seoData.keywords.join(', ')" />
            <meta name="robots" content="index,follow" />
            <meta property="og:type" content="website" />
            <meta property="og:title" :content="pageTitle" />
            <meta property="og:description" :content="pageDescription" />
            <meta property="og:url" :content="seoData.canonical" />
            <meta property="og:image" :content="ogImageUrl" />
            <meta property="og:image:alt" :content="pageTitle" />
            <meta name="twitter:card" content="summary_large_image" />
            <meta name="twitter:title" :content="pageTitle" />
            <meta name="twitter:description" :content="pageDescription" />
            <meta name="twitter:image" :content="ogImageUrl" />
            <link rel="canonical" :href="seoData.canonical" />
        </Head>

        <section class="py-5 bg-dark text-white">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-lg-10">
                        <p class="text-uppercase small mb-2 text-info fw-semibold">Colores</p>
                        <h1 class="display-5 fw-bold mb-3">{{ seoData.h1 }}</h1>
                        <p class="lead text-white-50 mb-3">{{ pageDescription }}</p>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-info text-dark">HEX → RGB · HSL</span>
                            <span class="badge bg-info text-dark">CMYK · HSV · RGBA</span>
                            <span class="badge bg-info text-dark">Paleta tonal</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="py-5">
            <div class="container">
                <div class="row g-4">
                    <div class="col-lg-5">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h2 class="h5 fw-semibold mb-1">Color base</h2>
                                        <p class="text-muted small mb-0">Escribe un hexadecimal completo (ej: #ef4444).</p>
                                    </div>
                                    <span :class="[ 'badge', isValidHex ? 'bg-success' : colorInput ? 'bg-warning text-dark' : 'bg-secondary-subtle text-dark' ]">
                                        {{ isValidHex ? 'Válido' : colorInput ? 'Pendiente' : 'Listo' }}
                                    </span>
                                </div>

                                <div class="input-group input-group-lg">
                                    <span class="input-group-text fw-semibold">HEX</span>
                                    <input
                                        v-model="colorInput"
                                        type="text"
                                        placeholder="#1A2B3C"
                                        class="form-control form-control-lg"
                                    />
                                </div>

                                <p v-if="colorInput && !isValidHex" class="form-text text-danger mt-2">
                                    Ingresa un hexadecimal válido de 6 caracteres.
                                </p>

                                <div class="rounded-3 overflow-hidden mt-3" :style="previewStyle">
                                    <div class="p-4 text-center" :style="{ color: previewContrastColor }">
                                        <p class="mb-1 small">Vista previa del color</p>
                                        <strong class="fs-5">{{ colorMeta ? colorMeta.hex : '#______' }}</strong>
                                    </div>
                                </div>

                                <div class="mt-3 text-muted small">
                                    Todos los cálculos se realizan en tu navegador y no se comparte el valor con terceros.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-7">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h2 class="h5 fw-semibold mb-1">Conversiones disponibles</h2>
                                        <p class="text-muted small mb-0">Copialas directamente para usar en CSS, diseño o impresión.</p>
                                    </div>
                                    <span class="badge bg-secondary-subtle text-dark">{{ formatList.length }} formatos</span>
                                </div>

                                <ul v-if="formatList.length" class="list-group list-group-flush mb-3">
                                    <li
                                        v-for="format in formatList"
                                        :key="format.label"
                                        class="list-group-item d-flex justify-content-between align-items-center small"
                                    >
                                        <span class="text-muted">{{ format.label }}</span>
                                        <span class="fw-semibold text-end">{{ format.value }}</span>
                                    </li>
                                </ul>

                                <p v-else class="text-muted small mb-0">
                                    Ingresa un hexadecimal válido para ver todas las conversiones disponibles.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mt-4" v-if="colorMeta">
                    <div class="col-12">
                        <div class="card shadow-sm border-0">
                            <div class="card-body">
                                <h2 class="h5 fw-semibold mb-3">Paleta tonal relacionada</h2>
                                <div class="row row-cols-1 row-cols-sm-2 row-cols-md-5 g-3">
                                    <div v-for="swatch in colorMeta.palette" :key="swatch.hex">
                                        <div
                                            class="rounded-3 p-3 h-100 d-flex flex-column justify-content-between"
                                            :style="{ backgroundColor: swatch.hex, color: swatch.textColor }"
                                        >
                                            <span class="small text-uppercase">{{ swatch.label }}</span>
                                            <strong class="fs-5">{{ swatch.hex }}</strong>
                                            <span class="small">
                                                {{ swatch.delta === 0 ? 'Color base' : swatch.delta > 0 ? `+${swatch.delta}% luminosidad` : `${swatch.delta}% luminosidad` }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mt-4" v-if="paletteGroups.length">
                    <div class="col-lg-6" v-for="group in paletteGroups" :key="group.label">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h2 class="h5 fw-semibold mb-1">{{ group.label }}</h2>
                                        <p class="text-muted small mb-0">{{ group.description }}</p>
                                    </div>
                                </div>

                                <div class="row row-cols-1 row-cols-sm-2 g-3 mt-3">
                                    <div v-for="color in group.colors" :key="color.hex" class="col">
                                        <div
                                            class="rounded-3 p-3 h-100 d-flex flex-column justify-content-between"
                                            :style="{ backgroundColor: color.hex, color: color.textColor }"
                                        >
                                            <span class="small text-uppercase">{{ color.label }}</span>
                                            <strong class="fs-5">{{ color.hex }}</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mt-4">
                    <div class="col-lg-6">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-body">
                                <h2 class="h5 fw-semibold mb-3">Cómo usarla</h2>
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item small">1) Escribe el código hexadecimal en el campo de la izquierda.</li>
                                    <li class="list-group-item small">2) Copia cualquiera de las conversiones (RGB, HSL, HSV, CMYK o RGBA) para tu proyecto.</li>
                                    <li class="list-group-item small">3) Observa la paleta tonal para elegir variantes más claras u oscuras que mantengan el matiz.</li>
                                    <li class="list-group-item small">4) Úsala como referencia rápida para diseño web, branding o impresión.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-body">
                                <h2 class="h5 fw-semibold mb-3">Preguntas frecuentes</h2>
                                <div class="accordion" id="accordionColorFaq">
                                    <div class="accordion-item" v-for="(item, index) in faqItems" :key="index">
                                        <h2 class="accordion-header" :id="`heading-color-${index}`">
                                            <button
                                                class="accordion-button collapsed"
                                                type="button"
                                                data-bs-toggle="collapse"
                                                :data-bs-target="`#collapse-color-${index}`"
                                                aria-expanded="false"
                                                :aria-controls="`collapse-color-${index}`"
                                            >
                                                <span class="small fw-semibold">{{ item.question }}</span>
                                            </button>
                                        </h2>
                                        <div
                                            :id="`collapse-color-${index}`"
                                            class="accordion-collapse collapse"
                                            :aria-labelledby="`heading-color-${index}`"
                                            data-bs-parent="#accordionColorFaq"
                                        >
                                            <div class="accordion-body small text-muted">
                                                {{ item.answer }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <div
            v-if="feedbackModalOpen"
            class="position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center"
            style="background: rgba(15, 23, 42, 0.85); z-index: 1100;"
            role="dialog"
            aria-modal="true"
            @click.self="closeFeedbackModal"
        >
            <div class="modal-dialog w-100" style="max-width: 540px;">
                <div class="modal-content rounded-4 shadow-lg border-0 position-relative">
                    <button
                        type="button"
                        class="btn-close position-absolute top-0 end-0 m-3"
                        aria-label="Cerrar"
                        @click="closeFeedbackModal"
                    ></button>
                    <div class="modal-body p-4">
                        <h3 class="h5 fw-bold mb-3">Cuéntanos qué necesitas</h3>
                        <p class="small text-muted mb-3">
                            Describe tu duda, queja o solicitud para que el equipo pueda ayudarte en minutos.
                        </p>
                        <form @submit.prevent="submitFeedback">
                            <div class="mb-3">
                                <label class="form-label fw-semibold" for="feedbackMessage">Mensaje</label>
                                <textarea
                                    id="feedbackMessage"
                                    class="form-control"
                                    rows="4"
                                    placeholder="Escribe aquí tus dudas, quejas o solicitudes..."
                                    v-model="feedbackMessageInput"
                                ></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold" for="feedbackEmail">Correo electrónico (opcional)</label>
                                <input
                                    id="feedbackEmail"
                                    type="email"
                                    class="form-control"
                                    placeholder="correo@ejemplo.com"
                                    v-model="feedbackEmailInput"
                                />
                            </div>
                            <p
                                class="small mb-3"
                                :class="feedbackStatus === 'success' ? 'text-success' : feedbackStatus === 'error' ? 'text-danger' : 'text-muted'"
                            >
                                {{
                                    feedbackStatus === 'success'
                                        ? 'Gracias, tu mensaje fue enviado y pronto te respondemos.'
                                        : feedbackStatus === 'error'
                                        ? feedbackError || 'No pudimos enviar tu mensaje. Intenta nuevamente.'
                                        : 'Tu mensaje llega directo al equipo. Te escribimos en breve.'
                                }}
                            </p>
                            <div class="d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-outline-secondary" @click="closeFeedbackModal" :disabled="feedbackSubmitting">
                                    Cerrar
                                </button>
                                <button type="submit" class="btn btn-primary" :disabled="feedbackSubmitting">
                                    <span
                                        v-if="feedbackSubmitting"
                                        class="spinner-border spinner-border-sm me-2"
                                        role="status"
                                        aria-hidden="true"
                                    ></span>
                                    Enviar mensaje
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
input.form-control {
    text-transform: uppercase;
}
</style>
