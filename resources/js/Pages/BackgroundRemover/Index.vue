<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';
import axios from 'axios';

const props = defineProps({
    seo: {
        type: Object,
        required: true,
    },
});

// ----------- ESTADO -----------
const selectedFile = ref(null);
const previewOriginal = ref(null);
const previewResult = ref(null);
const isProcessing = ref(false);

const sizeMode = ref('original'); // original | preset | custom
const preset = ref('medium');     // small | medium | large
const customWidth = ref(null);
const customHeight = ref(null);

const errorMessage = ref(null);

// ----------- HELPERS -----------
function onFileChange(e) {
    const file = e.target.files?.[0];
    if (!file) return;

    selectedFile.value = file;
    errorMessage.value = null;
    previewResult.value = null;

    const reader = new FileReader();
    reader.onload = (event) => {
        previewOriginal.value = event.target?.result || null;
    };
    reader.readAsDataURL(file);
}

function formatBytes(bytes) {
    if (!bytes && bytes !== 0) return '';
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    const value = bytes / Math.pow(1024, i);
    return `${value.toFixed(2)} ${sizes[i]}`;
}

const fileInfo = computed(() => {
    if (!selectedFile.value) return null;
    return {
        name: selectedFile.value.name,
        size: formatBytes(selectedFile.value.size),
        type: selectedFile.value.type,
    };
});

// ----------- ENVÍO A API -----------
async function processImage() {
    if (!selectedFile.value) {
        errorMessage.value = 'Por favor selecciona una imagen primero.';
        return;
    }

    errorMessage.value = null;
    isProcessing.value = true;
    previewResult.value = null;

    try {
        const formData = new FormData();
        formData.append('image', selectedFile.value);
        formData.append('size_mode', sizeMode.value);
        formData.append('preset', preset.value || '');
        formData.append('custom_width', customWidth.value || '');
        formData.append('custom_height', customHeight.value || '');

        const response = await axios.post(
            '/api/tools/background-remover',
            formData,
            {
                responseType: 'blob',
            }
        );

        const blob = response.data;
        const url = URL.createObjectURL(blob);
        previewResult.value = url;
    } catch (error) {
        console.error(error);
        errorMessage.value = 'Ocurrió un error al procesar la imagen. Intenta nuevamente.';
    } finally {
        isProcessing.value = false;
    }
}

function downloadResult() {
    if (!previewResult.value || !selectedFile.value) return;
    const a = document.createElement('a');
    const baseName = selectedFile.value.name.replace(/\.[^.]+$/, '');
    a.href = previewResult.value;
    a.download = `${baseName}_sin_fondo.png`;
    document.body.appendChild(a);
    a.click();
    a.remove();
}

// ----------- JSON-LD -----------
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
                        Sube tu imagen, elimina el fondo automáticamente y descárgala en PNG
                        transparente en el tamaño que necesites.
                    </p>
                </div>
            </div>

            <!-- TARJETA PRINCIPAL -->
            <div class="row justify-content-center mb-4">
                <div class="col-lg-10">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-4 p-md-5">
                            <div class="row g-4">
                                <!-- CARGA Y OPCIONES -->
                                <div class="col-lg-5">
                                    <div class="mb-3 text-center text-lg-start">
                                        <h2 class="h5 fw-bold mb-2">
                                            Sube tu imagen
                                        </h2>
                                        <p class="small text-muted mb-0">
                                            Archivos recomendados: JPG, PNG. Peso máximo: 5 MB.
                                        </p>
                                    </div>

                                    <div class="upload-area mb-3 mx-auto mx-lg-0">
                                        <label
                                            class="w-100 h-100 d-flex flex-column align-items-center justify-content-center cursor-pointer"
                                        >
                                            <div class="mb-2 display-6 text-primary">
                                                🖼️
                                            </div>
                                            <p class="fw-semibold mb-1">
                                                Selecciona una imagen o suéltala aquí
                                            </p>
                                            <p class="small text-muted mb-0">
                                                El fondo será eliminado automáticamente.
                                            </p>
                                            <input
                                                type="file"
                                                class="d-none"
                                                accept="image/*"
                                                @change="onFileChange"
                                            />
                                        </label>
                                    </div>

                                    <div v-if="fileInfo" class="mb-3">
                                        <div class="border rounded p-2 bg-white small">
                                            <div class="fw-semibold text-truncate">
                                                {{ fileInfo.name }}
                                            </div>
                                            <div class="text-muted">
                                                {{ fileInfo.size }} · {{ fileInfo.type }}
                                            </div>
                                        </div>
                                    </div>

                                    <!-- OPCIONES DE TAMAÑO -->
                                    <div class="mb-3">
                                        <h3 class="h6 fw-bold mb-2">
                                            Tamaño de descarga
                                        </h3>

                                        <div class="form-check">
                                            <input
                                                class="form-check-input"
                                                type="radio"
                                                id="size-original"
                                                value="original"
                                                v-model="sizeMode"
                                            />
                                            <label class="form-check-label small" for="size-original">
                                                Tamaño original
                                            </label>
                                        </div>

                                        <div class="form-check">
                                            <input
                                                class="form-check-input"
                                                type="radio"
                                                id="size-preset"
                                                value="preset"
                                                v-model="sizeMode"
                                            />
                                            <label class="form-check-label small" for="size-preset">
                                                Tamaño predefinido (ideal para web y redes)
                                            </label>
                                        </div>
                                        <div
                                            class="ms-3 mt-2"
                                            v-if="sizeMode === 'preset'"
                                        >
                                            <select
                                                class="form-select form-select-sm"
                                                v-model="preset"
                                            >
                                                <option value="small">
                                                    Pequeño (800 px lado más largo)
                                                </option>
                                                <option value="medium">
                                                    Mediano (1200 px lado más largo)
                                                </option>
                                                <option value="large">
                                                    Grande (1920 px lado más largo)
                                                </option>
                                            </select>
                                        </div>

                                        <div class="form-check mt-2">
                                            <input
                                                class="form-check-input"
                                                type="radio"
                                                id="size-custom"
                                                value="custom"
                                                v-model="sizeMode"
                                            />
                                            <label class="form-check-label small" for="size-custom">
                                                Personalizado
                                            </label>
                                        </div>

                                        <div
                                            class="row g-2 ms-3 mt-2"
                                            v-if="sizeMode === 'custom'"
                                        >
                                            <div class="col-6">
                                                <input
                                                    type="number"
                                                    class="form-control form-control-sm"
                                                    placeholder="Ancho (px)"
                                                    v-model.number="customWidth"
                                                />
                                            </div>
                                            <div class="col-6">
                                                <input
                                                    type="number"
                                                    class="form-control form-control-sm"
                                                    placeholder="Alto (px)"
                                                    v-model.number="customHeight"
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-grid gap-2">
                                        <button
                                            type="button"
                                            class="btn btn-primary"
                                            :disabled="!selectedFile || isProcessing"
                                            @click="processImage"
                                        >
                                            <span v-if="!isProcessing">
                                                Quitar fondo de la imagen
                                            </span>
                                            <span v-else>
                                                Procesando...
                                            </span>
                                        </button>

                                        <p class="small text-muted mb-0">
                                            El procesamiento puede tardar unos segundos según el tamaño
                                            de la imagen.
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

                                <!-- PREVIEW ANTES / DESPUÉS -->
                                <div class="col-lg-7">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <h3 class="h6 fw-bold mb-2 text-center">
                                                Imagen original
                                            </h3>
                                            <div class="preview-box border rounded bg-white d-flex align-items-center justify-content-center">
                                                <img
                                                    v-if="previewOriginal"
                                                    :src="previewOriginal"
                                                    alt="Vista previa original"
                                                    class="img-fluid"
                                                />
                                                <span
                                                    v-else
                                                    class="text-muted small"
                                                >
                                                    Aún no has seleccionado una imagen.
                                                </span>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <h3 class="h6 fw-bold mb-2 text-center">
                                                Imagen sin fondo
                                            </h3>
                                            <div class="preview-box border rounded bg-transparent pattern-bg d-flex align-items-center justify-content-center">
                                                <img
                                                    v-if="previewResult"
                                                    :src="previewResult"
                                                    alt="Vista previa sin fondo"
                                                    class="img-fluid"
                                                />
                                                <span
                                                    v-else
                                                    class="text-muted small text-center px-2"
                                                >
                                                    El resultado aparecerá aquí una vez se procese la imagen.
                                                </span>
                                            </div>

                                            <div
                                                v-if="previewResult"
                                                class="text-center mt-3"
                                            >
                                                <button
                                                    type="button"
                                                    class="btn btn-outline-primary btn-sm"
                                                    @click="downloadResult"
                                                >
                                                    Descargar PNG sin fondo
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <p class="small text-muted mt-3 mb-0">
                                        El fondo se elimina para resaltar solo el sujeto principal, ideal
                                        para fotoproducto, perfiles profesionales y piezas gráficas.
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
                            ¿Cómo funciona la herramienta para borrar el fondo de una imagen?
                        </h2>
                        <p class="text-muted small mb-2">
                            La herramienta está diseñada para integrarse con servicios de
                            inteligencia artificial especializados en segmentación de imágenes.
                            Tú solo subes la foto, eliges el tamaño de salida y obtienes un PNG
                            transparente listo para usar.
                        </p>
                        <p class="text-muted small mb-0">
                            Es perfecta para catálogos de productos, banners, anuncios y
                            cualquier pieza donde necesites aislar el sujeto del fondo original.
                        </p>
                    </section>
                </div>
            </div>

            <div class="row gy-4 mb-5">
                <div class="col-lg-10 mx-auto">
                    <section aria-labelledby="faq">
                        <h2 id="faq" class="h4 fw-bold mb-3">
                            Preguntas frecuentes sobre quitar el fondo de una imagen online
                        </h2>

                        <div class="accordion" id="accordionFaq">
                            <div
                                class="accordion-item"
                                v-for="(item, index) in seo.faq"
                                :key="index"
                            >
                                <h2 class="accordion-header" :id="`heading-${index}`">
                                    <button
                                        class="accordion-button collapsed"
                                        type="button"
                                        data-bs-toggle="collapse"
                                        :data-bs-target="`#collapse-${index}`"
                                        aria-expanded="false"
                                        :aria-controls="`collapse-${index}`"
                                    >
                                        <span class="small fw-semibold">
                                            {{ item.question }}
                                        </span>
                                    </button>
                                </h2>
                                <div
                                    :id="`collapse-${index}`"
                                    class="accordion-collapse collapse"
                                    :aria-labelledby="`heading-${index}`"
                                    data-bs-parent="#accordionFaq"
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
                            También puedes probar otras herramientas gratuitas de Toolbox Codwelt,
                            como
                            <a href="/comprimir-imagenes-online-gratis" class="link-primary">
                                comprimir imágenes online
                            </a>
                            y
                            <a href="/redimensionar-imagenes-online-gratis" class="link-primary">
                                redimensionar imágenes para web y redes sociales.
                            </a>
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

.preview-box {
    width: 100%;
    height: 260px;
    overflow: hidden;
}

.preview-box img {
    max-height: 100%;
    object-fit: contain;
}

/* Fondo tipo "checkerboard" para visualizar transparencia */
.pattern-bg {
    background-image: linear-gradient(45deg, #f0f0f0 25%, transparent 25%),
        linear-gradient(-45deg, #f0f0f0 25%, transparent 25%),
        linear-gradient(45deg, transparent 75%, #f0f0f0 75%),
        linear-gradient(-45deg, transparent 75%, #f0f0f0 75%);
    background-size: 20px 20px;
    background-position: 0 0, 0 10px, 10px -10px, -10px 0;
}
</style>
