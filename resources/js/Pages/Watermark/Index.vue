<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';
import axios from 'axios';
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

const { ogImageUrl } = useOgImage(props.seo);

// -------------------- ESTADO --------------------
const baseFile = ref(null);
const watermarkFile = ref(null);

const basePreview = ref(null);
const watermarkPreview = ref(null);
const resultPreview = ref(null);

const baseInfo = ref(null);
const watermarkInfo = ref(null);

const position = ref('bottom-right');
const opacity = ref(60);
const scalePercent = ref(30);
const outputFormat = ref('png');

const isProcessing = ref(false);
const errorMessage = ref(null);

// -------------------- HELPERS --------------------
function formatBytes(bytes) {
    if (!bytes && bytes !== 0) return '';
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    const value = bytes / Math.pow(1024, i);
    return `${value.toFixed(2)} ${sizes[i]}`;
}

function onBaseChange(e) {
    const file = e.target.files?.[0];
    if (!file) return;

    baseFile.value = file;
    baseInfo.value = {
        name: file.name,
        size: formatBytes(file.size),
        type: file.type,
    };
    errorMessage.value = null;
    resultPreview.value = null;

    const reader = new FileReader();
    reader.onload = (event) => {
        basePreview.value = event.target?.result || null;
    };
    reader.readAsDataURL(file);
}

function onWatermarkChange(e) {
    const file = e.target.files?.[0];
    if (!file) return;

    watermarkFile.value = file;
    watermarkInfo.value = {
        name: file.name,
        size: formatBytes(file.size),
        type: file.type,
    };
    errorMessage.value = null;
    resultPreview.value = null;

    const reader = new FileReader();
    reader.onload = (event) => {
        watermarkPreview.value = event.target?.result || null;
    };
    reader.readAsDataURL(file);
}

// -------------------- API CALL --------------------
async function applyWatermark() {
    if (!baseFile.value) {
        errorMessage.value = 'Por favor sube la imagen base.';
        return;
    }
    if (!watermarkFile.value) {
        errorMessage.value = 'Por favor sube la imagen que será la marca de agua.';
        return;
    }

    errorMessage.value = null;
    isProcessing.value = true;
    resultPreview.value = null;

    try {
        const formData = new FormData();
        formData.append('base_image', baseFile.value);
        formData.append('watermark_image', watermarkFile.value);
        formData.append('position', position.value);
        formData.append('opacity', opacity.value.toString());
        formData.append('scale_percent', scalePercent.value ? scalePercent.value.toString() : '');
        formData.append('output_format', outputFormat.value);

        const response = await axios.post(
            '/api/tools/watermark',
            formData,
            {
                responseType: 'blob',
            }
        );

        const blob = response.data;
        const url = URL.createObjectURL(blob);
        resultPreview.value = url;
    } catch (error) {
        console.error(error);
        if (error.response?.data?.message) {
            errorMessage.value = error.response.data.message;
        } else {
            errorMessage.value = 'Ocurrió un error al aplicar la marca de agua.';
        }
    } finally {
        isProcessing.value = false;
    }
}

function downloadResult() {
    if (!resultPreview.value) return;

    let filename = 'imagen_marca_agua.png';
    if (baseFile.value) {
        const baseName = baseFile.value.name.replace(/\.[^.]+$/, '');
        const ext = outputFormat.value === 'jpeg' ? 'jpg' : 'png';
        filename = `${baseName}_watermark.${ext}`;
    }

    const a = document.createElement('a');
    a.href = resultPreview.value;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
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
            <meta
                v-if="seo.keywords && seo.keywords.length"
                name="keywords"
                :content="seo.keywords.join(', ')"
            />
            <meta property="og:type" content="website" />
            <meta property="og:title" :content="seo.title" />
            <meta property="og:description" :content="seo.description" />
            <meta property="og:url" :content="seo.canonical" />
            <meta property="og:image" :content="ogImageUrl" />
            <meta property="og:image:alt" :content="seo.title" />
            <meta name="twitter:card" content="summary_large_image" />
            <meta name="twitter:title" :content="seo.title" />
            <meta name="twitter:description" :content="seo.description" />
            <meta name="twitter:image" :content="ogImageUrl" />
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
                        Protege tus fotos, recursos gráficos y piezas comerciales añadiendo tu logo como marca de agua
                        en cuestión de segundos.
                    </p>
                </div>
            </div>

            <!-- TARJETA PRINCIPAL -->
            <div class="row justify-content-center mb-4">
                <div class="col-lg-10">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-4 p-md-5">
                            <div class="row g-4">
                                <!-- CARGA E OPCIONES -->
                                <div class="col-lg-5">
                                    <div class="mb-3">
                                        <h2 class="h6 fw-bold mb-2">
                                            1. Sube tu imagen base
                                        </h2>
                                        <p class="small text-muted mb-1">
                                            Esta es la imagen sobre la que se aplicará la marca de agua.
                                        </p>
                                        <div class="upload-area mb-2">
                                            <label class="w-100 h-100 d-flex flex-column align-items-center justify-content-center cursor-pointer">
                                                <div class="mb-2 display-6 text-primary">
                                                    🖼️
                                                </div>
                                                <p class="fw-semibold mb-1">
                                                    Selecciona la imagen base o suéltala aquí
                                                </p>
                                                <p class="small text-muted mb-0">
                                                    Formatos recomendados: JPG, PNG.
                                                </p>
                                                <input
                                                    type="file"
                                                    class="d-none"
                                                    accept="image/*"
                                                    @change="onBaseChange"
                                                />
                                            </label>
                                        </div>
                                        <div v-if="baseInfo" class="border rounded p-2 bg-white small">
                                            <div class="fw-semibold text-truncate">
                                                {{ baseInfo.name }}
                                            </div>
                                            <div class="text-muted">
                                                {{ baseInfo.size }} · {{ baseInfo.type }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <h2 class="h6 fw-bold mb-2">
                                            2. Sube tu logo o marca de agua
                                        </h2>
                                        <p class="small text-muted mb-1">
                                            Este archivo se colocará sobre la imagen base como marca de agua.
                                        </p>
                                        <div class="upload-area mb-2">
                                            <label class="w-100 h-100 d-flex flex-column align-items-center justify-content-center cursor-pointer">
                                                <div class="mb-2 display-6 text-primary">
                                                    💧
                                                </div>
                                                <p class="fw-semibold mb-1">
                                                    Selecciona tu logo o marca de agua
                                                </p>
                                                <p class="small text-muted mb-0">
                                                    Ideal en PNG con fondo transparente.
                                                </p>
                                                <input
                                                    type="file"
                                                    class="d-none"
                                                    accept="image/*"
                                                    @change="onWatermarkChange"
                                                />
                                            </label>
                                        </div>
                                        <div v-if="watermarkInfo" class="border rounded p-2 bg-white small">
                                            <div class="fw-semibold text-truncate">
                                                {{ watermarkInfo.name }}
                                            </div>
                                            <div class="text-muted">
                                                {{ watermarkInfo.size }} · {{ watermarkInfo.type }}
                                            </div>
                                        </div>
                                    </div>

                                    <!-- CONTROLES -->
                                    <div class="mb-3">
                                        <h2 class="h6 fw-bold mb-2">
                                            3. Ajusta la marca de agua
                                        </h2>

                                        <div class="mb-2">
                                            <label class="form-label small text-muted mb-1">
                                                Posición
                                            </label>
                                            <select
                                                class="form-select form-select-sm"
                                                v-model="position"
                                            >
                                                <option value="top-left">Arriba izquierda</option>
                                                <option value="top-right">Arriba derecha</option>
                                                <option value="bottom-left">Abajo izquierda</option>
                                                <option value="bottom-right">Abajo derecha</option>
                                                <option value="center">Centrada</option>
                                            </select>
                                        </div>

                                        <div class="mb-2">
                                            <label class="form-label small text-muted mb-1">
                                                Opacidad de la marca de agua ({{ opacity }}%)
                                            </label>
                                            <input
                                                type="range"
                                                class="form-range"
                                                min="10"
                                                max="100"
                                                v-model.number="opacity"
                                            />
                                        </div>

                                        <div class="mb-2">
                                            <label class="form-label small text-muted mb-1">
                                                Tamaño de la marca de agua (% del ancho de la imagen)
                                            </label>
                                            <input
                                                type="number"
                                                class="form-control form-control-sm"
                                                v-model.number="scalePercent"
                                                min="10"
                                                max="200"
                                            />
                                            <p class="small text-muted mt-1 mb-0">
                                                Ejemplo: 30% hará que el logo mida aprox. el 30% del ancho de la imagen base.
                                            </p>
                                        </div>

                                        <div class="mb-2">
                                            <label class="form-label small text-muted mb-1">
                                                Formato de salida
                                            </label>
                                            <select
                                                class="form-select form-select-sm"
                                                v-model="outputFormat"
                                            >
                                                <option value="png">PNG (recomendado)</option>
                                                <option value="jpeg">JPEG</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="d-grid gap-2">
                                        <button
                                            type="button"
                                            class="btn btn-primary"
                                            :disabled="isProcessing"
                                            @click="applyWatermark"
                                        >
                                            <span v-if="!isProcessing">
                                                Aplicar marca de agua
                                            </span>
                                            <span v-else>
                                                Procesando...
                                            </span>
                                        </button>
                                        <p class="small text-muted mb-0">
                                            La nueva imagen se generará con tu marca de agua y podrás descargarla al instante.
                                        </p>
                                    </div>

                                    <div
                                        v-if="errorMessage"
                                        class="alert alert-danger mt-3 py-2 small"
                                        role="alert"
                                    >
                                        {{ errorMessage }}
                                    </div>
                                </div>

                                <!-- PREVIEW -->
                                <div class="col-lg-7">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <h3 class="h6 fw-bold mb-2 text-center">
                                                Imagen base
                                            </h3>
                                            <div class="preview-box border rounded bg-white d-flex align-items-center justify-content-center">
                                                <img
                                                    v-if="basePreview"
                                                    :src="basePreview"
                                                    alt="Vista previa base"
                                                    class="img-fluid"
                                                />
                                                <span
                                                    v-else
                                                    class="text-muted small text-center px-2"
                                                >
                                                    Sube una imagen base para ver la vista previa aquí.
                                                </span>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <h3 class="h6 fw-bold mb-2 text-center">
                                                Resultado con marca de agua
                                            </h3>
                                            <div class="preview-box border rounded bg-white d-flex align-items-center justify-content-center">
                                                <img
                                                    v-if="resultPreview"
                                                    :src="resultPreview"
                                                    alt="Vista previa con marca de agua"
                                                    class="img-fluid"
                                                />
                                                <span
                                                    v-else
                                                    class="text-muted small text-center px-2"
                                                >
                                                    Aquí verás el resultado una vez apliques la marca de agua.
                                                </span>
                                            </div>

                                            <div
                                                v-if="resultPreview"
                                                class="text-center mt-3"
                                            >
                                                <button
                                                    type="button"
                                                    class="btn btn-outline-primary btn-sm"
                                                    @click="downloadResult"
                                                >
                                                    Descargar imagen con marca de agua
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <p class="small text-muted mt-3 mb-0">
                                        Añadir marcas de agua a tus imágenes es una buena práctica para proteger tu trabajo
                                        creativo y reforzar el reconocimiento de marca en redes sociales, portafolios y sitios web.
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
                            ¿Cómo funciona la herramienta para poner marca de agua a tus imágenes?
                        </h2>
                        <p class="text-muted small mb-2">
                            La herramienta combina tu imagen base con tu logo o marca de agua aplicando un nivel de
                            opacidad, tamaño y posición configurable. De esta forma, puedes unificar el look & feel de
                            tus piezas visuales sin recurrir a programas de edición avanzados.
                        </p>
                        <p class="text-muted small mb-0">
                            Es ideal para fotógrafos, diseñadores, agencias y marcas que desean proteger y firmar su
                            contenido visual antes de publicarlo en sitios web, tiendas virtuales o redes sociales.
                        </p>
                    </section>
                </div>
            </div>

            <div class="row gy-4 mb-5">
                <div class="col-lg-10 mx-auto">
                    <section aria-labelledby="faq">
                        <h2 id="faq" class="h4 fw-bold mb-3">
                            Preguntas frecuentes sobre marcas de agua en imágenes
                        </h2>

                        <div class="accordion" id="accordionFaqWatermark">
                            <div
                                class="accordion-item"
                                v-for="(item, index) in seo.faq"
                                :key="index"
                            >
                                <h2 class="accordion-header" :id="`heading-watermark-${index}`">
                                    <button
                                        class="accordion-button collapsed"
                                        type="button"
                                        data-bs-toggle="collapse"
                                        :data-bs-target="`#collapse-watermark-${index}`"
                                        aria-expanded="false"
                                        :aria-controls="`collapse-watermark-${index}`"
                                    >
                                        <span class="small fw-semibold">
                                            {{ item.question }}
                                        </span>
                                    </button>
                                </h2>
                                <div
                                    :id="`collapse-watermark-${index}`"
                                    class="accordion-collapse collapse"
                                    :aria-labelledby="`heading-watermark-${index}`"
                                    data-bs-parent="#accordionFaqWatermark"
                                >
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
                            Complementa esta herramienta con
                            <a href="/comprimir-imagenes-online-gratis" class="link-primary">comprimir imágenes online</a>,
                            <a href="/redimensionar-imagenes-online-gratis" class="link-primary">redimensionar imágenes</a>
                            y
                            <a href="/quitar-fondo-imagen-gratis" class="link-primary">quitar fondos de imágenes</a>
                            para optimizar todo tu contenido visual en Toolbox Codwelt.
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
    height: 150px;
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

.preview-box {
    width: 100%;
    height: 260px;
    overflow: hidden;
}

.preview-box img {
    max-height: 100%;
    object-fit: contain;
}
</style>
