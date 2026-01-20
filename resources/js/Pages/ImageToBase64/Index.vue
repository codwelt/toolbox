<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
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
const pageTitle = computed(() => seoData.value.title || 'Convertir imagen a Base64 online gratis');
const pageDescription = computed(
    () => seoData.value.description || 'Convierte una imagen a Base64 sin subirla a ningún servidor.'
);
const { ogImageUrl } = useOgImage(seoData.value);

const base64String = ref('');
const fileName = ref('');
const fileType = ref('');
const fileSize = ref(0);
const previewUrl = ref('');
const isDragActive = ref(false);

const copyFeedback = ref('');

function processFile(file) {
    if (!file || !file.type.startsWith('image/')) return;

    fileName.value = file.name;
    fileType.value = file.type;
    fileSize.value = file.size;

    const reader = new FileReader();
    reader.onload = () => {
        base64String.value = reader.result || '';
        previewUrl.value = reader.result || '';
    };
    reader.readAsDataURL(file);
}

function handleFileChange(event) {
    const file = (event.target.files || [])[0];
    processFile(file);
    if (event.target) {
        event.target.value = '';
    }
}

const handleDragEnter = (event) => {
    event.preventDefault();
    isDragActive.value = true;
};

const handleDragLeave = (event) => {
    if (event.currentTarget === event.target) {
        isDragActive.value = false;
    }
};

const handleDragOver = (event) => {
    event.preventDefault();
    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'copy';
    }
    isDragActive.value = true;
};

const handleDrop = (event) => {
    event.preventDefault();
    event.stopPropagation();
    isDragActive.value = false;
    const file = (event.dataTransfer?.files || [])[0];
    processFile(file);
};

function clearSelection() {
    base64String.value = '';
    previewUrl.value = '';
    fileName.value = '';
    fileType.value = '';
    fileSize.value = 0;
    copyFeedback.value = '';
}

const base64Data = computed(() => {
    if (!base64String.value) return '';
    const parts = base64String.value.split(',');
    return parts[1] || '';
});

const stats = computed(() => ({
    length: base64Data.value.length,
    size: fileSize.value ? `${(fileSize.value / 1024).toFixed(2)} KB` : '—',
}));

async function copyBase64() {
    if (!base64String.value) return;
    try {
        await navigator.clipboard.writeText(base64String.value);
        copyFeedback.value = 'Base64 copiado al portapapeles';
        setTimeout(() => (copyFeedback.value = ''), 1800);
    } catch (error) {
        console.error(error);
        copyFeedback.value = 'No se pudo copiar automáticamente';
        setTimeout(() => (copyFeedback.value = ''), 1800);
    }
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
                        <p class="text-uppercase small mb-2 text-info fw-semibold">Base64</p>
                        <h1 class="display-5 fw-bold mb-3">{{ seoData.h1 }}</h1>
                        <p class="lead text-white-50 mb-3">
                            {{ pageDescription }}
                        </p>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-info text-dark">Procesamiento local</span>
                            <span class="badge bg-info text-dark">Múltiples formatos</span>
                            <span class="badge bg-info text-dark">Listo para copiar</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="container py-5">
            <div class="row g-4 align-items-center">
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body d-flex flex-column gap-3">
                            <div>
                                <h2 class="h6 fw-semibold mb-1">Sube tu imagen</h2>
                                <p class="small text-muted mb-0">Se procesa directamente en tu navegador.</p>
                            </div>
                            <label
                                class="d-flex justify-content-center align-items-center border border-dashed rounded-3 bg-white py-5 text-center cursor-pointer hover-scale"
                                :class="{ 'drag-active': isDragActive }"
                                @dragenter.prevent="handleDragEnter"
                                @dragleave.prevent="handleDragLeave"
                                @dragover.prevent="handleDragOver"
                                @drop.prevent="handleDrop"
                            >
                                <div>
                                    <span class="display-6 text-primary">📷</span>
                                    <p class="fw-semibold mb-1">Selecciona o arrastra una imagen</p>
                                    <p class="small text-muted mb-0">JPG, PNG, WebP, GIF</p>
                                </div>
                                <input type="file" class="d-none" accept="image/*" @change="handleFileChange" />
                            </label>
                            <div v-if="fileName" class="bg-light p-3 rounded-3 border">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong>{{ fileName }}</strong>
                                    <button type="button" class="btn btn-link btn-sm text-decoration-none" @click="clearSelection">Eliminar</button>
                                </div>
                                <div class="small text-muted">Tipo: {{ fileType || '—' }} · Tamaño: {{ stats.size }}</div>
                            </div>
                            <div v-if="previewUrl" class="rounded-3 overflow-hidden border">
                                <img :src="previewUrl" alt="Preview" class="img-fluid d-block" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h2 class="h6 fw-semibold mb-1">Resultado Base64</h2>
                                    <p class="small text-muted mb-0">Incluye el prefijo de datos.</p>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-primary" :disabled="!base64String" @click="copyBase64">
                                    Copiar
                                </button>
                            </div>
                            <textarea
                                class="form-control flex-grow-1 font-monospace border-0 bg-light p-3"
                                rows="10"
                                :value="base64String"
                                readonly
                                placeholder="Aquí aparecerá el Base64 cuando subas una imagen."
                            ></textarea>
                            <div class="d-flex justify-content-between align-items-center mt-3 gap-2 flex-wrap small">
                                <span class="text-muted">{{ copyFeedback }}</span>
                                <span class="badge bg-secondary-subtle text-dark">Longitud: {{ stats.length || '0' }} caracteres</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h3 class="h6 fw-bold mb-3">¿Por qué usar Base64?</h3>
                            <p class="small text-muted mb-2">
                                Base64 te permite incrustar imágenes directamente en HTML, CSS o JSON sin depender de archivos externos.
                            </p>
                            <p class="small text-muted mb-0">
                                Todo el procesamiento ocurre en tu navegador: no subimos ni almacenamos nada.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.border-dashed {
    border-style: dashed !important;
}

.cursor-pointer {
    cursor: pointer;
}

.hover-scale:hover {
    transform: translateY(-2px);
    transition: transform 0.2s;
}

.drag-active {
    border-color: #0d6efd !important;
    background-color: #e3f2ff;
}
</style>
