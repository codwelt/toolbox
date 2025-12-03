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

const seoData = computed(() => props.seo || {});
const displayTitle = computed(() =>
    seoData.value.h1?.toLowerCase().includes('webp') && seoData.value.h1?.toLowerCase().includes('png')
        ? 'Convertir imágenes a cualquier formato online'
        : seoData.value.h1
);
const displayDescription = computed(() =>
    seoData.value.description?.toLowerCase().includes('webp') && seoData.value.description?.toLowerCase().includes('png')
        ? 'Convierte imágenes entre formatos (PNG, JPG, WEBP, GIF, BMP o TIFF), subiendo varias a la vez, seleccionando cuáles procesar y descargando cada resultado por separado.'
        : seoData.value.description
);
const displayHeadTitle = computed(() =>
    seoData.value.title?.toLowerCase().includes('webp') && seoData.value.title?.toLowerCase().includes('png')
        ? 'Convertir imágenes a cualquier formato online gratis | Toolbox Codwelt'
        : seoData.value.title
);
const pageTitle = computed(() => displayHeadTitle.value || seoData.value.title);
const pageDescription = computed(() => displayDescription.value || seoData.value.description);
const { ogImageUrl } = useOgImage(seoData.value);
const availableFormats = [
    { value: 'png', label: 'PNG', helper: 'Mantiene transparencia y alta compatibilidad.' },
    { value: 'jpg', label: 'JPG', helper: 'Ideal para fotos y menor peso.' },
    { value: 'webp', label: 'WEBP', helper: 'Buena compresión con calidad equilibrada.' },
    { value: 'gif', label: 'GIF', helper: 'Animaciones o imágenes simples.' },
    { value: 'bmp', label: 'BMP', helper: 'Formato sin compresión.' },
    { value: 'tiff', label: 'TIFF', helper: 'Flujos de trabajo de impresión.' },
];

// -------------------- ESTADO --------------------
const mode = ref('file'); // file | url

const files = ref([]);
const results = ref([]);

const imageUrl = ref('');
const targetFormat = ref('png');

const isProcessing = ref(false);
const errorMessage = ref(null);
const targetFormatLabel = computed(() => {
    const found = availableFormats.find((format) => format.value === targetFormat.value);
    return found ? found.label : targetFormat.value.toUpperCase();
});

const selectedFiles = computed(() => files.value.filter((file) => file.selected));

// -------------------- HANDLERS --------------------
function onFileChange(e) {
    const fileList = e.target.files;
    handleNewFiles(fileList);
}

function onFileDrop(e) {
    const fileList = e.dataTransfer?.files;
    handleNewFiles(fileList);
}

function handleNewFiles(fileList) {
    const list = Array.from(fileList || []);
    const newFiles = list.filter((file) => file.type?.startsWith('image/'));

    if (!newFiles.length) return;

    newFiles.forEach((file) => {
        const id = `${file.name}-${file.lastModified}-${Math.random().toString(16).slice(2)}`;
        const preview = URL.createObjectURL(file);

        files.value.push({
            id,
            file,
            name: file.name,
            size: formatBytes(file.size),
            type: file.type,
            preview,
            selected: true,
        });
    });

    errorMessage.value = null;
    results.value = [];
}

function useUrlMode() {
    mode.value = 'url';
    files.value.forEach((f) => f.preview && URL.revokeObjectURL(f.preview));
    files.value = [];
    results.value.forEach((r) => URL.revokeObjectURL(r.url));
    results.value = [];
    errorMessage.value = null;
}

function useFileMode() {
    mode.value = 'file';
    imageUrl.value = '';
    results.value.forEach((r) => URL.revokeObjectURL(r.url));
    results.value = [];
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
    results.value.forEach((r) => URL.revokeObjectURL(r.url));
    results.value = [];

    isProcessing.value = true;

    try {
        if (mode.value === 'file') {
            if (!selectedFiles.value.length) {
                errorMessage.value = 'Selecciona al menos una imagen para convertir.';
                return;
            }

            for (const item of selectedFiles.value) {
                const formData = new FormData();
                formData.append('source_type', 'file');
                formData.append('image', item.file);
                formData.append('target_format', targetFormat.value);

                const response = await axios.post('/api/tools/webp-to-png', formData, {
                    responseType: 'blob',
                });

                const blob = response.data;
                const url = URL.createObjectURL(blob);
                results.value.push({
                    id: item.id,
                    name: item.name,
                    url,
                    format: targetFormat.value,
                });
            }
        } else {
            if (!imageUrl.value) {
                errorMessage.value = 'Por favor ingresa una URL de imagen.';
                return;
            }

            const formData = new FormData();
            formData.append('source_type', 'url');
            formData.append('image_url', imageUrl.value);
            formData.append('target_format', targetFormat.value);

            const response = await axios.post('/api/tools/webp-to-png', formData, {
                responseType: 'blob',
            });

            const blob = response.data;
            const url = URL.createObjectURL(blob);
            results.value.push({
                id: 'url',
                name: imageUrl.value,
                url,
                format: targetFormat.value,
            });
        }
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

function downloadResult(result) {
    if (!result?.url) return;

    const baseName = result.name?.toString().split('/').pop()?.replace(/\.[^.]+$/, '') || 'imagen_convertida';
    const filename = `${baseName}.${result.format || targetFormat.value}`;

    const a = document.createElement('a');
    a.href = result.url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
}

function removeFile(id) {
    const file = files.value.find((f) => f.id === id);
    if (file?.preview) {
        URL.revokeObjectURL(file.preview);
    }
    files.value = files.value.filter((f) => f.id !== id);
    results.value = results.value.filter((r) => r.id !== id);
}

// -------------------- JSON-LD SEO --------------------
const jsonLd = computed(() => {
    const faqStructured = (seoData.value.faq || []).map((item) => ({
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
        name: pageTitle.value,
        url: seoData.value.url,
        applicationCategory: 'Multimedia',
        offers: {
            '@type': 'Offer',
            price: '0',
            priceCurrency: 'USD',
        },
        description: pageDescription.value,
        potentialAction: {
            '@type': 'UseAction',
            target: seoData.value.url,
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

    files.value.forEach((f) => f.preview && URL.revokeObjectURL(f.preview));
    results.value.forEach((r) => r.url && URL.revokeObjectURL(r.url));
});
</script>

<template>
    <div class="bg-light min-vh-100">
        <!-- HEAD SEO -->

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
                        <p class="text-uppercase small mb-2 text-info fw-semibold">Conversor de imágenes</p>
                        <h1 class="display-5 fw-bold mb-3">{{ displayTitle }}</h1>
                        <p class="lead text-white-50 mb-3">
                            {{ pageDescription }}
                        </p>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-info text-dark">Varios formatos</span>
                            <span class="badge bg-info text-dark">Procesa múltiples archivos</span>
                            <span class="badge bg-info text-dark">Optimizado SEO</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="container py-5">

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
                                                Sube tu imagen
                                            </h2>
                                            <p class="small text-muted mb-0">
                                                Formatos admitidos: PNG, JPG, WEBP, GIF, BMP y TIFF. Tamaño máximo: 5
                                                MB.
                                            </p>
                                            <p class="small text-muted mb-0">
                                                Carga varias imágenes a la vez, actívalas con el checkbox y decide cuáles
                                                quieres convertir.
                                            </p>
                                        </div>

                                        <div class="upload-area mb-3 mx-auto mx-lg-0" @dragover.prevent
                                            @dragenter.prevent @drop.prevent="onFileDrop">
                                            <label
                                                class="w-100 h-100 d-flex flex-column align-items-center justify-content-center cursor-pointer">
                                                <div class="mb-2 display-6 text-primary">
                                                    🖼️
                                                </div>
                                                <p class="fw-semibold mb-1">
                                                    Selecciona una imagen o suéltala aquí
                                                </p>
                                                <p class="small text-muted mb-0">
                                                    Elige el formato de salida y generaremos copias optimizadas de todas
                                                    las imágenes seleccionadas.
                                                </p>
                                                <input type="file" class="d-none" accept="image/*" multiple
                                                    @change="onFileChange" />
                                            </label>
                                        </div>

                                        <div v-if="files.length" class="mb-3">
                                            <p class="small text-muted mb-2">
                                            Selecciona qué imágenes convertir o elimínalas de la lista.
                                            </p>
                                            <div class="list-group small">
                                                <div class="list-group-item d-flex align-items-center justify-content-between gap-2"
                                                    v-for="file in files" :key="file.id">
                                                    <div class="d-flex align-items-center gap-2 flex-grow-1">
                                                        <input class="form-check-input mt-0" type="checkbox"
                                                            v-model="file.selected" :id="`file-${file.id}`" />
                                                        <label class="form-check-label w-100" :for="`file-${file.id}`">
                                                            <div class="fw-semibold text-truncate">
                                                                {{ file.name }}
                                                            </div>
                                                            <div class="text-muted">
                                                                {{ file.size }} · {{ file.type }}
                                                            </div>
                                                        </label>
                                                    </div>
                                                    <button type="button" class="btn btn-link text-danger btn-sm p-0"
                                                        @click="removeFile(file.id)">
                                                        Eliminar
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- DESDE URL -->
                                    <div v-else>
                                        <div class="mb-3 text-center text-lg-start">
                                            <h2 class="h6 fw-bold mb-2">
                                                Usa una URL de imagen
                                            </h2>
                                            <p class="small text-muted mb-0">
                                                Pega la URL pública de cualquier imagen (png, jpg, webp, gif...) para
                                                convertirla.
                                            </p>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label small text-muted mb-1">
                                                URL de la imagen
                                            </label>
                                            <input type="url" class="form-control form-control-sm"
                                                placeholder="https://ejemplo.com/imagen.jpg" v-model="imageUrl" />
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label small text-muted mb-1">
                                            Formato de salida
                                        </label>
                                        <select class="form-select form-select-sm" v-model="targetFormat">
                                            <option v-for="option in availableFormats" :key="option.value"
                                                :value="option.value">
                                                {{ option.label }} — {{ option.helper }}
                                            </option>
                                        </select>
                                    </div>

                                    <div class="d-grid gap-2">
                                        <button type="button" class="btn btn-primary" :disabled="isProcessing ||
                                            (mode === 'file' && !selectedFiles.length) ||
                                            (mode === 'url' && !imageUrl)
                                            " @click="convert">
                                            <span v-if="!isProcessing">
                                                Convertir a {{ targetFormatLabel }}
                                            </span>
                                            <span v-else>
                                                Procesando...
                                            </span>
                                        </button>
                                        <p class="small text-muted mb-0">
                                            El archivo resultante se generará en formato {{ targetFormatLabel }}
                                            manteniendo la resolución original siempre que el formato lo permita.
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
                                                Imágenes originales
                                            </h3>
                                            <div
                                                class="preview-box border rounded bg-white d-flex align-items-center justify-content-center">
                                                <div v-if="files.length"
                                                    class="d-flex flex-wrap gap-2 w-100 h-100 overflow-auto p-2">
                                                    <div v-for="file in files" :key="file.id"
                                                        class="border rounded p-1 d-flex flex-column align-items-center"
                                                        style="width: 110px;">
                                                        <img :src="file.preview" :alt="file.name"
                                                            class="img-fluid rounded" />
                                                        <small class="text-truncate w-100 mt-1">{{ file.name }}</small>
                                                    </div>
                                                </div>
                                                <span v-else class="text-muted small text-center px-2">
                                                    Aún no has cargado ninguna imagen.
                                                </span>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <h3 class="h6 fw-bold mb-2 text-center">
                                                Imágenes convertidas a {{ targetFormatLabel }}
                                            </h3>
                                            <div
                                                class="preview-box border rounded bg-white d-flex align-items-center justify-content-center">
                                                <div v-if="results.length"
                                                    class="d-flex flex-wrap gap-2 w-100 h-100 overflow-auto p-2">
                                                    <div v-for="result in results" :key="result.id"
                                                        class="border rounded p-1 d-flex flex-column align-items-center"
                                                        style="width: 110px;">
                                                        <img :src="result.url" :alt="`Vista previa en ${targetFormatLabel}`"
                                                            class="img-fluid rounded" />
                                                        <small class="text-truncate w-100 mt-1">
                                                            {{ result.name }}
                                                        </small>
                                                        <button type="button" class="btn btn-outline-primary btn-sm mt-1 w-100"
                                                            @click="downloadResult(result)">
                                                            Descargar
                                                        </button>
                                                    </div>
                                                </div>
                                                <span v-else class="text-muted small text-center px-2">
                                                    El resultado aparecerá aquí una vez se complete la conversión.
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <p class="small text-muted mt-3 mb-0">
                                        Esta herramienta es ideal para transformar imágenes en lote cuando un programa
                                        no soporta el formato original, necesitas optimizar para web o quieres copiar
                                        una librería completa a otro tipo de archivo.
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
                            ¿Cómo funciona el conversor de imágenes de Toolbox Codwelt?
                        </h2>
                        <p class="text-muted small mb-2">
                            La herramienta recibe tus imágenes (subidas en bloque o desde una URL), las procesa en el
                            servidor y genera versiones equivalentes en el formato que elijas.
                        </p>
                        <p class="text-muted small mb-0">
                            Sube varias imágenes, marca las que quieras convertir, elige el formato de salida (PNG, JPG,
                            WEBP, GIF, BMP o TIFF) y descarga cada resultado por separado manteniendo la mejor calidad
                            posible para cada formato.
                        </p>
                    </section>
                </div>
            </div>

            <div class="row gy-4 mb-5">
                <div class="col-lg-10 mx-auto">
                    <section aria-labelledby="faq">
                        <h2 id="faq" class="h4 fw-bold mb-3">
                            Preguntas frecuentes sobre convertir imágenes online
                        </h2>

                        <div class="accordion" id="accordionFaqImageConverter">
                            <div class="accordion-item" v-for="(item, index) in seoData.faq" :key="index">
                                <h2 class="accordion-header" :id="`heading-image-converter-${index}`">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                        :data-bs-target="`#collapse-image-converter-${index}`" aria-expanded="false"
                                        :aria-controls="`collapse-image-converter-${index}`">
                                        <span class="small fw-semibold">
                                            {{ item.question }}
                                        </span>
                                    </button>
                                </h2>
                                <div :id="`collapse-image-converter-${index}`" class="accordion-collapse collapse"
                                    :aria-labelledby="`heading-image-converter-${index}`"
                                    data-bs-parent="#accordionFaqImageConverter">
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
                            <a href="/comprimir-imagenes-online-gratis" class="link-primary">
                                comprimir imágenes online
                            </a>,
                            <a href="/redimensionar-imagenes-online-gratis" class="link-primary">
                                redimensionar imágenes
                            </a>
                            y
                            <a href="/quitar-fondo-imagen-gratis" class="link-primary">
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
    overflow: auto;
}

.preview-box img {
    max-height: 100%;
    object-fit: contain;
}
</style>
