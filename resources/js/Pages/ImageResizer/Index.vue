<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';
import AppLayout from './../../Layouts/AppLayout.vue';
defineOptions({
    layout: AppLayout,
});
const props = defineProps({
    seo: {
        type: Object,
        required: true,
    },
});

// -------------------- ESTADO --------------------
const files = ref([]);
const resized = ref([]);

const targetWidth = ref(null);
const targetHeight = ref(null);
const keepAspect = ref(true);

const preset = ref('none'); // none | ig_square | ig_story | fb_cover | web_1920 | web_1200

const isProcessing = ref(false);
const errorMessage = ref(null);

// -------------------- HELPERS --------------------
function onFileChange(e) {
    const selected = Array.from(e.target.files || []);
    files.value = selected;
    resized.value = [];
    errorMessage.value = null;
}

function formatBytes(bytes) {
    if (!bytes && bytes !== 0) return '';
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    const value = bytes / Math.pow(1024, i);
    return `${value.toFixed(2)} ${sizes[i]}`;
}

const hasSizeConfig = computed(() => {
    return !!targetWidth.value || !!targetHeight.value || preset.value !== 'none';
});

// -------------------- PRESETS --------------------
function applyPreset() {
    switch (preset.value) {
        case 'ig_square':
            targetWidth.value = 1080;
            targetHeight.value = 1080;
            keepAspect.value = false;
            break;
        case 'ig_story':
            targetWidth.value = 1080;
            targetHeight.value = 1920;
            keepAspect.value = false;
            break;
        case 'fb_cover':
            targetWidth.value = 1200;
            targetHeight.value = 628;
            keepAspect.value = false;
            break;
        case 'web_1920':
            targetWidth.value = 1920;
            targetHeight.value = null;
            keepAspect.value = true;
            break;
        case 'web_1200':
            targetWidth.value = 1200;
            targetHeight.value = null;
            keepAspect.value = true;
            break;
        default:
            targetWidth.value = null;
            targetHeight.value = null;
            keepAspect.value = true;
            break;
    }
}

// -------------------- REDIMENSIONADO --------------------
async function resizeImages() {
    if (!files.value.length) {
        errorMessage.value = 'Por favor selecciona al menos una imagen.';
        return;
    }

    if (!hasSizeConfig.value) {
        errorMessage.value = 'Define al menos un ancho, un alto o selecciona un tamaño predefinido.';
        return;
    }

    errorMessage.value = null;
    isProcessing.value = true;
    resized.value = [];

    for (const file of files.value) {
        const result = await resizeSingle(file);
        if (result) {
            resized.value.push(result);
        }
    }

    isProcessing.value = false;
}

function resizeSingle(file) {
    return new Promise((resolve) => {
        const reader = new FileReader();

        reader.onload = (event) => {
            const img = new Image();
            img.onload = () => {
                const { canvas, ctx, newWidth, newHeight } = createCanvasWithResize(img);
                ctx.drawImage(img, 0, 0, newWidth, newHeight);

                canvas.toBlob(
                    (blob) => {
                        if (!blob) return resolve(null);
                        const url = URL.createObjectURL(blob);

                        resolve({
                            name: file.name,
                            originalSize: file.size,
                            newSize: blob.size,
                            width: newWidth,
                            height: newHeight,
                            url,
                        });
                    },
                    file.type || 'image/jpeg',
                    0.92
                );
            };
            img.src = event.target?.result || '';
        };

        reader.readAsDataURL(file);
    });
}

function createCanvasWithResize(img) {
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');

    const srcW = img.width;
    const srcH = img.height;

    let tw = targetWidth.value ? Number(targetWidth.value) : null;
    let th = targetHeight.value ? Number(targetHeight.value) : null;

    let newW = srcW;
    let newH = srcH;

    if (!tw && !th) {
        canvas.width = srcW;
        canvas.height = srcH;
        return { canvas, ctx, newWidth: srcW, newHeight: srcH };
    }

    if (keepAspect.value) {
        if (tw && th) {
            const ratio = Math.min(tw / srcW, th / srcH);
            newW = Math.round(srcW * ratio);
            newH = Math.round(srcH * ratio);
        } else if (tw && !th) {
            const ratio = tw / srcW;
            newW = tw;
            newH = Math.round(srcH * ratio);
        } else if (!tw && th) {
            const ratio = th / srcH;
            newH = th;
            newW = Math.round(srcW * ratio);
        }
    } else {
        newW = tw || srcW;
        newH = th || srcH;
    }

    canvas.width = newW;
    canvas.height = newH;

    return { canvas, ctx, newWidth: newW, newHeight: newH };
}

function download(file) {
    const a = document.createElement('a');
    a.href = file.url;
    a.download = file.name.replace(/\.(\w+)$/, `_resized.$1`);
    document.body.appendChild(a);
    a.click();
    a.remove();
}

function downloadAll() {
    resized.value.forEach((f, index) => {
        setTimeout(() => download(f), index * 400);
    });
}

// -------------------- JSON-LD SEO --------------------
const jsonLd = computed(() => {
    const faqStructured = (props.seo.faq || []).map((item) => ({
        '@type': 'Question',
        name: item.question,
        acceptedAnswer: {
            '@type': 'Answer',
            text: item.answer,
        },
    }));

    return JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'WebApplication',
        name: props.seo.title,
        url: props.seo.url,
        applicationCategory: 'Multimedia',
        offers: {
            '@type': 'Offer',
            price: '0',
            priceCurrency: 'USD',
        },
        description: props.seo.description,
        potentialAction: {
            '@type': 'UseAction',
            target: props.seo.url,
        },
        mainEntity: {
            '@type': 'FAQPage',
            mainEntity: faqStructured,
        },
    });
});

const jsonLdScriptEl = ref(null);

onMounted(() => {
    const el = document.createElement('script');
    el.type = 'application/ld+json';
    el.text = jsonLd.value;
    document.head.appendChild(el);
    jsonLdScriptEl.value = el;
});

onBeforeUnmount(() => {
    if (jsonLdScriptEl.value && jsonLdScriptEl.value.parentNode) {
        jsonLdScriptEl.value.parentNode.removeChild(jsonLdScriptEl.value);
    }
});
</script>

<template>
    <div class="bg-light min-vh-100">
        <!-- HEAD SEO -->

        <Head :title="seo.title">
            <meta name="description" :content="seo.description" />
            <meta v-if="seo.keywords && seo.keywords.length" name="keywords" :content="seo.keywords.join(', ')" />
            <meta property="og:type" content="website" />
            <meta property="og:title" :content="seo.title" />
            <meta property="og:description" :content="seo.description" />
            <meta property="og:url" :content="seo.canonical" />
            <meta name="twitter:card" content="summary_large_image" />
            <meta name="twitter:title" :content="seo.title" />
            <meta name="twitter:description" :content="seo.description" />
            <link rel="canonical" :href="seo.canonical" />
        </Head>

        <div class="container py-5">
            <!-- HERO -->
            <div class="row mb-4">
                <div class="col-lg-10 mx-auto text-center">
                    <h1 class="display-5 fw-bold mb-3">
                        {{ seo.h1 }}
                    </h1>
                    <p class="lead text-muted mb-2">
                        {{ seo.description }}
                    </p>
                    <p class="text-secondary">
                        Ajusta el tamaño de tus imágenes para web y redes sociales manteniendo su
                        calidad y proporción.
                    </p>
                </div>
            </div>

            <!-- TARJETA PRINCIPAL -->
            <div class="row justify-content-center mb-4">
                <div class="col-lg-10">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-4 p-md-5">
                            <div class="row g-4 align-items-start">
                                <!-- CONFIGURACIÓN -->
                                <div class="col-lg-5">
                                    <div class="mb-3 text-center text-lg-start">
                                        <h2 class="h5 fw-bold mb-2">
                                            Sube tus imágenes
                                        </h2>
                                        <p class="small text-muted mb-0">
                                            Formatos admitidos: JPG, PNG, WebP. Puedes seleccionar varias imágenes a la
                                            vez.
                                        </p>
                                    </div>

                                    <div class="upload-area mb-3 mx-auto mx-lg-0">
                                        <label
                                            class="w-100 h-100 d-flex flex-column align-items-center justify-content-center cursor-pointer">
                                            <div class="mb-2 display-6 text-primary">
                                                📐
                                            </div>
                                            <p class="fw-semibold mb-1">
                                                Selecciona imágenes o suéltalas aquí
                                            </p>
                                            <p class="small text-muted mb-0">
                                                Se aplicará la misma configuración de tamaño a todas.
                                            </p>
                                            <input type="file" class="d-none" multiple accept="image/*"
                                                @change="onFileChange" />
                                        </label>
                                    </div>

                                    <!-- LISTA DE ARCHIVOS -->
                                    <div v-if="files.length" class="mb-3">
                                        <div class="border rounded p-2 bg-white small">
                                            <div v-for="file in files" :key="file.name"
                                                class="d-flex justify-content-between align-items-center">
                                                <span class="text-truncate me-2">
                                                    {{ file.name }}
                                                </span>
                                                <span class="text-muted">
                                                    {{ formatBytes(file.size) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- PRESETS -->
                                    <div class="mb-3">
                                        <h3 class="h6 fw-bold mb-2">
                                            Tamaños predefinidos
                                        </h3>
                                        <select class="form-select form-select-sm mb-2" v-model="preset"
                                            @change="applyPreset">
                                            <option value="none">
                                                Sin preset (usa las dimensiones manuales)
                                            </option>
                                            <option value="ig_square">
                                                Instagram cuadrado (1080 x 1080)
                                            </option>
                                            <option value="ig_story">
                                                Instagram historia (1080 x 1920)
                                            </option>
                                            <option value="fb_cover">
                                                Portada Facebook (1200 x 628)
                                            </option>
                                            <option value="web_1920">
                                                Web ancho (1920px lado más largo)
                                            </option>
                                            <option value="web_1200">
                                                Web mediano (1200px lado más largo)
                                            </option>
                                        </select>
                                        <p class="small text-muted mb-0">
                                            Puedes ajustar los valores manualmente después de elegir un preset.
                                        </p>
                                    </div>

                                    <!-- ANCHO / ALTO -->
                                    <div class="mb-3">
                                        <h3 class="h6 fw-bold mb-2">
                                            Dimensiones personalizadas
                                        </h3>
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <label class="form-label small text-muted mb-1">
                                                    Ancho (px)
                                                </label>
                                                <input type="number" class="form-control form-control-sm"
                                                    v-model.number="targetWidth" />
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label small text-muted mb-1">
                                                    Alto (px)
                                                </label>
                                                <input type="number" class="form-control form-control-sm"
                                                    v-model.number="targetHeight" />
                                            </div>
                                        </div>
                                        <div class="form-check mt-2">
                                            <input class="form-check-input" type="checkbox" id="keepAspect"
                                                v-model="keepAspect" />
                                            <label class="form-check-label small" for="keepAspect">
                                                Mantener proporción de la imagen
                                            </label>
                                        </div>
                                    </div>

                                    <div class="d-grid gap-2">
                                        <button type="button" class="btn btn-primary"
                                            :disabled="!files.length || isProcessing" @click="resizeImages">
                                            <span v-if="!isProcessing">
                                                Redimensionar imágenes
                                            </span>
                                            <span v-else>
                                                Procesando...
                                            </span>
                                        </button>
                                        <p class="small text-muted mb-0">
                                            El tiempo de procesamiento depende de la cantidad y tamaño de las imágenes.
                                        </p>
                                    </div>

                                    <div v-if="errorMessage" class="alert alert-danger mt-3 py-2 small" role="alert">
                                        {{ errorMessage }}
                                    </div>
                                </div>

                                <!-- RESULTADOS -->
                                <div class="col-lg-7">
                                    <h2 class="h6 fw-bold mb-3 text-center text-lg-start">
                                        Resultado de las imágenes redimensionadas
                                    </h2>

                                    <div v-if="!resized.length" class="text-muted small">
                                        Aún no has redimensionado ninguna imagen. Configura el tamaño y haz clic en
                                        <strong>“Redimensionar imágenes”</strong>.
                                    </div>

                                    <div v-else class="card border-0 shadow-sm">
                                        <div
                                            class="card-header bg-white d-flex justify-content-between align-items-center">
                                            <span class="small fw-semibold">
                                                Imágenes listas para descargar
                                            </span>
                                            <button type="button" class="btn btn-link btn-sm text-decoration-none"
                                                @click="downloadAll">
                                                Descargar todas
                                            </button>
                                        </div>
                                        <div class="card-body p-3">
                                            <div class="list-group list-group-flush">
                                                <div v-for="file in resized" :key="file.url"
                                                    class="list-group-item d-flex justify-content-between align-items-center flex-wrap">
                                                    <div class="me-2">
                                                        <div class="small fw-semibold text-truncate">
                                                            {{ file.name }}
                                                        </div>
                                                        <div class="small text-muted">
                                                            {{ file.width }} x {{ file.height }} px ·
                                                            Original: {{ formatBytes(file.originalSize) }} ·
                                                            Nuevo: {{ formatBytes(file.newSize) }}
                                                        </div>
                                                    </div>
                                                    <button type="button" class="btn btn-outline-primary btn-sm"
                                                        @click="download(file)">
                                                        Descargar
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <p class="small text-muted mt-3 mb-0">
                                        Usar tamaños adecuados mejora la velocidad de carga de tu sitio y la experiencia
                                        del usuario, además de ayudar al rendimiento SEO de tus páginas.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CONTENIDO SEO: CÓMO FUNCIONA + FAQ + ENLACES INTERNOS -->
            <div class="row gy-4 mb-4">
                <div class="col-lg-10 mx-auto">
                    <section aria-labelledby="how-it-works">
                        <h2 id="how-it-works" class="h4 fw-bold mb-3">
                            ¿Cómo funciona la herramienta para redimensionar imágenes online?
                        </h2>
                        <p class="text-muted small mb-2">
                            La herramienta procesa las imágenes directamente en tu navegador utilizando tecnologías
                            modernas de canvas. De esta forma, puedes ajustar el tamaño de tus archivos sin necesidad
                            de instalar programas pesados ni subir tus imágenes a servidores externos.
                        </p>
                        <p class="text-muted small mb-0">
                            Solo seleccionas las imágenes, eliges un tamaño predefinido o personalizado, y descargas los
                            archivos listos para usar en tu sitio web, tienda virtual o redes sociales.
                        </p>
                    </section>
                </div>
            </div>

            <div class="row gy-4 mb-5">
                <div class="col-lg-10 mx-auto">
                    <section aria-labelledby="faq">
                        <h2 id="faq" class="h4 fw-bold mb-3">
                            Preguntas frecuentes sobre redimensionar imágenes online
                        </h2>

                        <div class="accordion" id="accordionFaqResizer">
                            <div class="accordion-item" v-for="(item, index) in seo.faq" :key="index">
                                <h2 class="accordion-header" :id="`heading-resizer-${index}`">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                        :data-bs-target="`#collapse-resizer-${index}`" aria-expanded="false"
                                        :aria-controls="`collapse-resizer-${index}`">
                                        <span class="small fw-semibold">
                                            {{ item.question }}
                                        </span>
                                    </button>
                                </h2>
                                <div :id="`collapse-resizer-${index}`" class="accordion-collapse collapse"
                                    :aria-labelledby="`heading-resizer-${index}`" data-bs-parent="#accordionFaqResizer">
                                    <div class="accordion-body small text-muted">
                                        {{ item.answer }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-lg-10 mx-auto">
                    <section aria-label="otras-herramientas">
                        <p class="small text-muted">
                            También puedes aprovechar otras herramientas gratuitas de Toolbox Codwelt, como
                            <a href="/comprimir-imagenes-online-gratis" class="link-primary">
                                comprimir imágenes online
                            </a>
                            y
                            <a href="/quitar-fondo-imagen-gratis" class="link-primary">
                                quitar el fondo de tus imágenes
                            </a>
                            de forma automática.
                        </p>
                    </section>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.upload-area {
    max-width: 420px;
    height: 190px;
    border: 2px dashed #ced4da;
    border-radius: 0.75rem;
    background-color: #f8f9fa;
    transition: background-color 0.15s ease-in-out, border-color 0.15s ease-in-out,
        transform 0.15s ease-in-out;
}

.upload-area:hover {
    background-color: #eef2f7;
    border-color: #0d6efd;
    transform: translateY(-1px);
    cursor: pointer;
}
</style>
