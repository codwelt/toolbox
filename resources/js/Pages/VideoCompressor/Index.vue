<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';
import axios from 'axios';
import AppLayout from './../../Layouts/AppLayout.vue';
defineOptions({
    layout: AppLayout,
});

const props = defineProps({
    seo: {
        type: Object,
        required: true,
    },
    presets: {
        type: Array,
        required: true,
    },
});

// Estado
const selectedFile = ref(null);
const videoPreview = ref(null);
const compression = ref(40); // %
const maxWidth = ref(1920);
const maxHeight = ref(1080);

const uploadProgress = ref(0);
const processingProgress = ref(0);
const isUploading = ref(false);
const isProcessing = ref(false);
const errorMessage = ref(null);
const resultUrl = ref(null);

const fakeProcessingInterval = ref(null);

// Helpers
function onFileChange(e) {
    const file = e.target.files?.[0];
    if (!file) return;

    if (!['video/mp4', 'video/quicktime'].includes(file.type)) {
        errorMessage.value = 'Solo se permiten videos MP4 o MOV.';
        selectedFile.value = null;
        videoPreview.value = null;
        return;
    }

    selectedFile.value = file;
    errorMessage.value = null;
    resultUrl.value = null;

    const url = URL.createObjectURL(file);
    videoPreview.value = url;
}

const fileInfo = computed(() => {
    if (!selectedFile.value) return null;
    return {
        name: selectedFile.value.name,
        size: formatBytes(selectedFile.value.size),
        type: selectedFile.value.type,
    };
});

function formatBytes(bytes) {
    if (!bytes && bytes !== 0) return '';
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    const value = bytes / Math.pow(1024, i);
    return `${value.toFixed(2)} ${sizes[i]}`;
}

function applyPreset(presetKey) {
    const preset = props.presets.find(p => p.key === presetKey);
    if (!preset) return;

    compression.value = preset.compression;
    maxWidth.value = preset.max_width;
    maxHeight.value = preset.max_height;
}

// Proceso principal
async function compressVideo() {
    if (!selectedFile.value) {
        errorMessage.value = 'Selecciona un video primero.';
        return;
    }

    errorMessage.value = null;
    resultUrl.value = null;
    uploadProgress.value = 0;
    processingProgress.value = 0;

    const formData = new FormData();
    formData.append('video', selectedFile.value);
    formData.append('compression', compression.value.toString());
    formData.append('max_width', maxWidth.value ? maxWidth.value.toString() : '');
    formData.append('max_height', maxHeight.value ? maxHeight.value.toString() : '');

    isUploading.value = true;
    isProcessing.value = false;

    try {
        const response = await axios.post(
            '/api/tools/video-compressor',
            formData,
            {
                responseType: 'blob',
                onUploadProgress: (event) => {
                    if (event.total) {
                        uploadProgress.value = Math.round((event.loaded / event.total) * 100);
                    }
                },
            }
        );

        // Fin de subida -> empezamos "progreso" de compresión simulado mientras llega la respuesta
        isUploading.value = false;
        isProcessing.value = true;
        startFakeProcessingProgress();

        // Cuando ya tenga la respuesta, detenemos el fake y ponemos 100%
        const blob = response.data;
        stopFakeProcessingProgress(true);
        const url = URL.createObjectURL(blob);
        resultUrl.value = url;
        isProcessing.value = false;
    } catch (error) {
        console.error(error);
        isUploading.value = false;
        stopFakeProcessingProgress(false);
        isProcessing.value = false;

        if (error.response && error.response.data && error.response.data.message) {
            errorMessage.value = error.response.data.message;
        } else {
            errorMessage.value = 'Ocurrió un error al comprimir el video. Intenta de nuevo.';
        }
    }
}

function startFakeProcessingProgress() {
    processingProgress.value = 0;
    if (fakeProcessingInterval.value) {
        clearInterval(fakeProcessingInterval.value);
    }
    fakeProcessingInterval.value = setInterval(() => {
        if (processingProgress.value < 95) {
            processingProgress.value += 3;
        }
    }, 500);
}

function stopFakeProcessingProgress(forceComplete) {
    if (fakeProcessingInterval.value) {
        clearInterval(fakeProcessingInterval.value);
        fakeProcessingInterval.value = null;
    }
    if (forceComplete) {
        processingProgress.value = 100;
    }
}

function downloadResult() {
    if (!resultUrl.value || !selectedFile.value) return;
    const a = document.createElement('a');
    const baseName = selectedFile.value.name.replace(/\.[^.]+$/, '');
    a.href = resultUrl.value;
    a.download = `${baseName}_compressed.mp4`;
    document.body.appendChild(a);
    a.click();
    a.remove();
}

// JSON-LD
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
    if (fakeProcessingInterval.value) {
        clearInterval(fakeProcessingInterval.value);
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
                        Sube tu video en formato MP4 o MOV, elige la compresión ideal y
                        descarga un MP4 más ligero, perfecto para web, redes sociales o apps
                        de mensajería.
                    </p>
                </div>
            </div>

            <!-- TARJETA PRINCIPAL -->
            <div class="row justify-content-center mb-4">
                <div class="col-lg-10">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-4 p-md-5">
                            <div class="row g-4">
                                <!-- Panel de carga y opciones -->
                                <div class="col-lg-5">
                                    <h2 class="h5 fw-bold mb-3">
                                        Sube tu video
                                    </h2>
                                    <div class="upload-area mb-3">
                                        <label class="w-100 h-100 d-flex flex-column align-items-center justify-content-center cursor-pointer">
                                            <div class="mb-2 display-6 text-primary">
                                                🎬
                                            </div>
                                            <p class="fw-semibold mb-1">
                                                Selecciona un video o suéltalo aquí
                                            </p>
                                            <p class="small text-muted mb-0">
                                                Formatos: MP4, MOV · Máx. recomendado: 200 MB
                                            </p>
                                            <input
                                                type="file"
                                                class="d-none"
                                                accept="video/mp4,video/quicktime"
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

                                    <!-- Configuración de compresión -->
                                    <div class="mb-3">
                                        <h3 class="h6 fw-bold mb-2">
                                            Porcentaje de compresión
                                        </h3>
                                        <input
                                            type="range"
                                            min="10"
                                            max="90"
                                            step="5"
                                            v-model.number="compression"
                                            class="form-range"
                                        />
                                        <div class="small text-muted">
                                            {{ compression }}% de compresión aproximada
                                            (valores más altos = video más liviano).
                                        </div>
                                    </div>

                                    <!-- Configuración de dimensiones -->
                                    <div class="mb-3">
                                        <h3 class="h6 fw-bold mb-2">
                                            Resolución máxima
                                        </h3>
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <label class="form-label small text-muted mb-1">
                                                    Ancho máximo (px)
                                                </label>
                                                <input
                                                    type="number"
                                                    class="form-control form-control-sm"
                                                    v-model.number="maxWidth"
                                                />
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label small text-muted mb-1">
                                                    Alto máximo (px)
                                                </label>
                                                <input
                                                    type="number"
                                                    class="form-control form-control-sm"
                                                    v-model.number="maxHeight"
                                                />
                                            </div>
                                        </div>
                                        <p class="small text-muted mt-1 mb-0">
                                            Se mantendrá la proporción original del video dentro de
                                            estos límites.
                                        </p>
                                    </div>

                                    <!-- Presets recomendados -->
                                    <div class="mb-3">
                                        <h3 class="h6 fw-bold mb-2">
                                            Configuraciones recomendadas
                                        </h3>
                                        <div class="d-flex flex-wrap gap-2">
                                            <button
                                                v-for="preset in presets"
                                                :key="preset.key"
                                                type="button"
                                                class="btn btn-outline-secondary btn-sm"
                                                @click="applyPreset(preset.key)"
                                            >
                                                {{ preset.label }}
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Botón principal -->
                                    <div class="d-grid gap-2">
                                        <button
                                            type="button"
                                            class="btn btn-primary"
                                            :disabled="!selectedFile || isUploading || isProcessing"
                                            @click="compressVideo"
                                        >
                                            <span v-if="!isUploading && !isProcessing">
                                                Comprimir video
                                            </span>
                                            <span v-else-if="isUploading">
                                                Subiendo video...
                                            </span>
                                            <span v-else>
                                                Comprimiendo video...
                                            </span>
                                        </button>
                                    </div>

                                    <!-- Progreso -->
                                    <div class="mt-3">
                                        <div v-if="isUploading" class="mb-2">
                                            <div class="small text-muted mb-1">
                                                Progreso de subida: {{ uploadProgress }}%
                                            </div>
                                            <div class="progress">
                                                <div
                                                    class="progress-bar"
                                                    role="progressbar"
                                                    :style="{ width: uploadProgress + '%' }"
                                                    :aria-valuenow="uploadProgress"
                                                    aria-valuemin="0"
                                                    aria-valuemax="100"
                                                ></div>
                                            </div>
                                        </div>

                                        <div v-if="isProcessing" class="mb-2">
                                            <div class="small text-muted mb-1">
                                                Progreso de compresión: {{ processingProgress }}%
                                            </div>
                                            <div class="progress">
                                                <div
                                                    class="progress-bar bg-success"
                                                    role="progressbar"
                                                    :style="{ width: processingProgress + '%' }"
                                                    :aria-valuenow="processingProgress"
                                                    aria-valuemin="0"
                                                    aria-valuemax="100"
                                                ></div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Error -->
                                    <div
                                        v-if="errorMessage"
                                        class="alert alert-danger mt-3 py-2 small"
                                        role="alert"
                                    >
                                        {{ errorMessage }}
                                    </div>
                                </div>

                                <!-- Previews -->
                                <div class="col-lg-7">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <h3 class="h6 fw-bold mb-2 text-center">
                                                Video original
                                            </h3>
                                            <div class="preview-box border rounded bg-white d-flex align-items-center justify-content-center">
                                                <video
                                                    v-if="videoPreview"
                                                    :src="videoPreview"
                                                    controls
                                                    class="w-100 h-100"
                                                ></video>
                                                <span
                                                    v-else
                                                    class="text-muted small text-center px-2"
                                                >
                                                    Aún no has seleccionado un video.
                                                </span>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <h3 class="h6 fw-bold mb-2 text-center">
                                                Video comprimido
                                            </h3>
                                            <div class="preview-box border rounded bg-white d-flex align-items-center justify-content-center">
                                                <video
                                                    v-if="resultUrl"
                                                    :src="resultUrl"
                                                    controls
                                                    class="w-100 h-100"
                                                ></video>
                                                <span
                                                    v-else
                                                    class="text-muted small text-center px-2"
                                                >
                                                    El resultado aparecerá aquí una vez se complete la
                                                    compresión.
                                                </span>
                                            </div>

                                            <div
                                                v-if="resultUrl"
                                                class="text-center mt-3"
                                            >
                                                <button
                                                    type="button"
                                                    class="btn btn-outline-primary btn-sm"
                                                    @click="downloadResult"
                                                >
                                                    Descargar MP4 comprimido
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <p class="small text-muted mt-3 mb-0">
                                        Esta herramienta está pensada para reducir el peso de tus videos
                                        manteniendo una calidad adecuada para el uso que elijas:
                                        mensajería, redes sociales o distribución web.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CONTENIDO SEO / FAQ -->
            <div class="row gy-4 mb-4">
                <div class="col-lg-10 mx-auto">
                    <section aria-labelledby="how-it-works">
                        <h2 id="how-it-works" class="h4 fw-bold mb-3">
                            ¿Cómo funciona el compresor de videos de Toolbox Codwelt?
                        </h2>
                        <p class="text-muted small mb-2">
                            La herramienta ajusta el bitrate y la resolución de tu video para
                            reducir su peso, manteniendo un equilibrio entre calidad y tamaño.
                            Además, el archivo final se entrega en formato MP4, compatible con la
                            mayoría de plataformas.
                        </p>
                        <p class="text-muted small mb-0">
                            Puedes partir de configuraciones recomendadas o definir tu propia
                            combinación de compresión y resolución según el destino del video.
                        </p>
                    </section>
                </div>
            </div>

            <div class="row gy-4 mb-5">
                <div class="col-lg-10 mx-auto">
                    <section aria-labelledby="faq">
                        <h2 id="faq" class="h4 fw-bold mb-3">
                            Preguntas frecuentes sobre comprimir videos online
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
                            También puedes optimizar tus recursos multimedia con otras
                            herramientas gratuitas de Toolbox Codwelt, como
                            <a href="/comprimir-imagenes-online" class="link-primary">
                                comprimir imágenes online
                            </a>
                            o
                            <a href="/quitar-fondo-imagen" class="link-primary">
                                quitar el fondo de imágenes automáticamente.
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
    border: 2px dashed #ced4da;
    border-radius: 0.75rem;
    background-color: #f8f9fa;
    height: 160px;
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
    background-color: #000;
}

.preview-box video {
    width: 100%;
    height: 100%;
    object-fit: contain;
}
</style>
