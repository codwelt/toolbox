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

// -------------------- ESTADO --------------------
const mode = ref('file'); // file | url

const selectedFile = ref(null);
const fileInfo = ref(null);

const imageUrl = ref('');
const previewOriginal = ref(null);
const previewResult = ref(null);

const isProcessing = ref(false);
const errorMessage = ref(null);

// -------------------- HANDLERS --------------------
function onFileChange(e) {
    const file = e.target.files?.[0];
    if (!file) return;

    selectedFile.value = file;
    fileInfo.value = {
        name: file.name,
        size: formatBytes(file.size),
        type: file.type,
    };
    errorMessage.value = null;
    previewResult.value = null;

    const reader = new FileReader();
    reader.onload = (event) => {
        previewOriginal.value = event.target?.result || null;
    };
    reader.readAsDataURL(file);
}

function useUrlMode() {
    mode.value = 'url';
    selectedFile.value = null;
    fileInfo.value = null;
    previewOriginal.value = null;
    previewResult.value = null;
    errorMessage.value = null;
}

function useFileMode() {
    mode.value = 'file';
    imageUrl.value = '';
    previewOriginal.value = null;
    previewResult.value = null;
    errorMessage.value = null;
}

function formatBytes(bytes) {
    if (!bytes && bytes !== 0) return '';
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    const value = bytes / Math.pow(1024, i);
    return `${value.toFixed(2)} ${sizes[i]}`;
}

// -------------------- CONVERSIÓN --------------------
async function convert() {
    errorMessage.value = null;
    previewResult.value = null;

    const formData = new FormData();

    if (mode.value === 'file') {
        if (!selectedFile.value) {
            errorMessage.value = 'Por favor selecciona un archivo .webp.';
            return;
        }
        formData.append('source_type', 'file');
        formData.append('image', selectedFile.value);
    } else {
        if (!imageUrl.value) {
            errorMessage.value = 'Por favor ingresa una URL de imagen WebP.';
            return;
        }
        formData.append('source_type', 'url');
        formData.append('image_url', imageUrl.value);
        previewOriginal.value = imageUrl.value;
    }

    isProcessing.value = true;

    try {
        const response = await axios.post(
            '/api/tools/webp-to-png',
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
        if (error.response?.data?.message) {
            errorMessage.value = error.response.data.message;
        } else {
            errorMessage.value = 'Ocurrió un error al convertir la imagen.';
        }
    } finally {
        isProcessing.value = false;
    }
}

function downloadResult() {
    if (!previewResult.value) return;

    let filename = 'imagen_convertida.png';

    if (mode.value === 'file' && selectedFile.value) {
        const baseName = selectedFile.value.name.replace(/\.[^.]+$/, '');
        filename = `${baseName}.png`;
    }

    const a = document.createElement('a');
    a.href = previewResult.value;
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
                        Convierte tus archivos WebP a PNG para asegurar compatibilidad con todos los navegadores,
                        editores y CMS.
                    </p>
                </div>
            </div>

            <!-- TARJETA PRINCIPAL -->
            <div class="row justify-content-center mb-4">
                <div class="col-lg-10">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-4 p-md-5">
                            <div class="row g-4">
                                <!-- ORIGEN -->
                                <div class="col-lg-5">
                                    <div class="btn-group btn-group-sm mb-3" role="group">
                                        <button type="button" class="btn"
                                            :class="mode === 'file' ? 'btn-primary' : 'btn-outline-primary'"
                                            @click="useFileMode">
                                            Subir archivo
                                        </button>
                                        <button type="button" class="btn"
                                            :class="mode === 'url' ? 'btn-primary' : 'btn-outline-primary'"
                                            @click="useUrlMode">
                                            Desde URL
                                        </button>
                                    </div>

                                    <!-- SUBIR ARCHIVO -->
                                    <div v-if="mode === 'file'">
                                        <div class="mb-3 text-center text-lg-start">
                                            <h2 class="h6 fw-bold mb-2">
                                                Sube tu archivo WebP
                                            </h2>
                                            <p class="small text-muted mb-0">
                                                Formato admitido: WebP. Tamaño máximo: 5 MB.
                                            </p>
                                        </div>

                                        <div class="upload-area mb-3 mx-auto mx-lg-0">
                                            <label
                                                class="w-100 h-100 d-flex flex-column align-items-center justify-content-center cursor-pointer">
                                                <div class="mb-2 display-6 text-primary">
                                                    🖼️
                                                </div>
                                                <p class="fw-semibold mb-1">
                                                    Selecciona un archivo .webp o suéltalo aquí
                                                </p>
                                                <p class="small text-muted mb-0">
                                                    La imagen se convertirá automáticamente a PNG.
                                                </p>
                                                <input type="file" class="d-none" accept="image/webp"
                                                    @change="onFileChange" />
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
                                    </div>

                                    <!-- DESDE URL -->
                                    <div v-else>
                                        <div class="mb-3 text-center text-lg-start">
                                            <h2 class="h6 fw-bold mb-2">
                                                Usa una URL de imagen WebP
                                            </h2>
                                            <p class="small text-muted mb-0">
                                                Pega la URL pública de una imagen .webp para convertirla a PNG.
                                            </p>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label small text-muted mb-1">
                                                URL de la imagen WebP
                                            </label>
                                            <input type="url" class="form-control form-control-sm"
                                                placeholder="https://ejemplo.com/imagen.webp" v-model="imageUrl" />
                                        </div>
                                    </div>

                                    <div class="d-grid gap-2">
                                        <button type="button" class="btn btn-primary" :disabled="isProcessing ||
                                            (mode === 'file' && !selectedFile) ||
                                            (mode === 'url' && !imageUrl)
                                            " @click="convert">
                                            <span v-if="!isProcessing">
                                                Convertir a PNG
                                            </span>
                                            <span v-else>
                                                Procesando...
                                            </span>
                                        </button>
                                        <p class="small text-muted mb-0">
                                            El archivo resultante se generará en formato PNG manteniendo la resolución
                                            original.
                                        </p>
                                    </div>

                                    <div v-if="errorMessage" class="alert alert-danger mt-3 py-2 small" role="alert">
                                        {{ errorMessage }}
                                    </div>
                                </div>

                                <!-- PREVIEWS -->
                                <div class="col-lg-7">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <h3 class="h6 fw-bold mb-2 text-center">
                                                Imagen original
                                            </h3>
                                            <div
                                                class="preview-box border rounded bg-white d-flex align-items-center justify-content-center">
                                                <img v-if="previewOriginal" :src="previewOriginal"
                                                    alt="Vista previa original" class="img-fluid" />
                                                <span v-else class="text-muted small text-center px-2">
                                                    Aún no has cargado ninguna imagen.
                                                </span>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <h3 class="h6 fw-bold mb-2 text-center">
                                                Imagen convertida a PNG
                                            </h3>
                                            <div
                                                class="preview-box border rounded bg-white d-flex align-items-center justify-content-center">
                                                <img v-if="previewResult" :src="previewResult" alt="Vista previa en PNG"
                                                    class="img-fluid" />
                                                <span v-else class="text-muted small text-center px-2">
                                                    El resultado aparecerá aquí una vez se complete la conversión.
                                                </span>
                                            </div>

                                            <div v-if="previewResult" class="text-center mt-3">
                                                <button type="button" class="btn btn-outline-primary btn-sm"
                                                    @click="downloadResult">
                                                    Descargar PNG
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <p class="small text-muted mt-3 mb-0">
                                        Esta herramienta es ideal cuando descargas recursos en WebP desde la web y
                                        necesitas
                                        transformarlos a PNG para usarlos en editores, CMS o piezas gráficas sin
                                        complicaciones.
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
                            ¿Cómo funciona el conversor de WebP a PNG de Toolbox Codwelt?
                        </h2>
                        <p class="text-muted small mb-2">
                            La herramienta recibe tu archivo WebP (cargado desde tu equipo o descargado desde una URL),
                            lo procesa en el servidor y genera una versión equivalente en formato PNG.
                        </p>
                        <p class="text-muted small mb-0">
                            Así obtienes archivos compatibles con la mayoría de aplicaciones, manteniendo la calidad
                            y resolución original de la imagen.
                        </p>
                    </section>
                </div>
            </div>

            <div class="row gy-4 mb-5">
                <div class="col-lg-10 mx-auto">
                    <section aria-labelledby="faq">
                        <h2 id="faq" class="h4 fw-bold mb-3">
                            Preguntas frecuentes sobre convertir WebP a PNG online
                        </h2>

                        <div class="accordion" id="accordionFaqWebpPng">
                            <div class="accordion-item" v-for="(item, index) in seo.faq" :key="index">
                                <h2 class="accordion-header" :id="`heading-webp-png-${index}`">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                        :data-bs-target="`#collapse-webp-png-${index}`" aria-expanded="false"
                                        :aria-controls="`collapse-webp-png-${index}`">
                                        <span class="small fw-semibold">
                                            {{ item.question }}
                                        </span>
                                    </button>
                                </h2>
                                <div :id="`collapse-webp-png-${index}`" class="accordion-collapse collapse"
                                    :aria-labelledby="`heading-webp-png-${index}`"
                                    data-bs-parent="#accordionFaqWebpPng">
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
                            Explora otras herramientas gratuitas de Toolbox Codwelt como
                            <a href="/comprimir-imagenes-online" class="link-primary">
                                comprimir imágenes online
                            </a>,
                            <a href="/redimensionar-imagenes-online" class="link-primary">
                                redimensionar imágenes
                            </a>
                            y
                            <a href="/quitar-fondo-imagen" class="link-primary">
                                quitar el fondo de tus imágenes
                            </a>
                            en cuestión de segundos.
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
</style>
