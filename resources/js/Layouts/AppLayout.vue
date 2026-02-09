<script setup>
import axios from 'axios';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch, watchEffect } from 'vue';

const page = usePage();

// Categorías + tools compartidos desde Laravel
const toolCategories = computed(() => page.props.toolCategories || []);

const isNavCollapsed = ref(true);
const toggleNav = () => {
    isNavCollapsed.value = !isNavCollapsed.value;
};

const searchQuery = ref('');

const currentPath = computed(() => {
    const url = page.url || '/';
    return url.split('?')[0];
});

const isToolActive = (tool) => {
    if (!tool?.path) return false;
    return currentPath.value === tool.path;
};

const isCategoryActive = (category) => {
    if (!category?.items) return false;
    return category.items.some((tool) => isToolActive(tool));
};

const activeTool = computed(() => {
    for (const category of toolCategories.value) {
        const match = (category.items || []).find((tool) => isToolActive(tool));
        if (match) return match;
    }

    return null;
});

const rawTitle = computed(() => page.props.seo?.title || page.props.title || activeTool.value?.name || 'Toolsbox');
const rawDescription = computed(
    () =>
        page.props.seo?.description ||
        page.props.description ||
        activeTool.value?.description ||
        'Suite de herramientas online para optimizar imágenes y recursos digitales.'
);
const emojiForTitle = (title) => {
    const t = (title || '').toLowerCase();
    if (t.includes('imagen')) return '🖼️';
    if (t.includes('video')) return '🎥';
    if (t.includes('html')) return '📄';
    if (t.includes('css')) return '🎨';
    if (t.includes('javascript') || t.includes('js')) return '💻';
    if (t.includes('json')) return '🧩';
    if (t.includes('xml')) return '🧾';
    if (t.includes('whatsapp')) return '💬';
    if (t.includes('emoji')) return '😃';
    if (t.includes('favicon')) return '⭐';
    if (t.includes('país') || t.includes('pais') || t.includes('country')) return '🌎';
    return '🛠️';
};
const titleWithEmoji = computed(() => `${emojiForTitle(rawTitle.value)} ${rawTitle.value}`);

const allTools = computed(() => {
    return toolCategories.value.flatMap((category) =>
        (category.items || []).map((tool) => ({
            ...tool,
            categoryLabel: category.label,
        }))
    );
});

const searchResults = computed(() => {
    const term = (searchQuery.value || '').trim().toLowerCase();
    if (term.length < 4) return [];

    return allTools.value
        .filter((tool) => {
            const name = (tool.name || '').toLowerCase();
            const desc = (tool.description || '').toLowerCase();
            return name.includes(term) || desc.includes(term);
        })
        .slice(0, 10);
});

const showSearchDropdown = computed(() => searchQuery.value.trim().length >= 4 && searchResults.value.length > 0);

const handleResultClick = () => {
    isNavCollapsed.value = true;
};

const SHARE_QUERY_KEY = 'report';
const shareStatus = ref(null);
const shareMessage = ref('');
const shareSubmitting = ref(false);
const lastAppliedShareToken = ref('');
let shareMessageTimeout = null;

const canShareReport = computed(() => Boolean(activeTool.value));
const shareMessageClass = computed(() => {
    if (shareStatus.value === 'success') return 'text-success';
    if (shareStatus.value === 'error') return 'text-danger';
    return 'text-muted';
});

const updateShareMessage = (status, message) => {
    if (shareMessageTimeout) {
        clearTimeout(shareMessageTimeout);
    }

    shareStatus.value = status;
    shareMessage.value = message;
    shareMessageTimeout = setTimeout(() => {
        shareStatus.value = null;
        shareMessage.value = '';
    }, 4500);
};

const isShareableField = (field) => {
    if (!(field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement)) {
        return false;
    }

    if (field.disabled) {
        return false;
    }

    if (field instanceof HTMLInputElement) {
        const blockedTypes = ['button', 'file', 'hidden', 'image', 'password', 'reset', 'submit'];
        if (blockedTypes.includes((field.type || '').toLowerCase())) {
            return false;
        }
    }

    return true;
};

const getShareableFields = () => {
    if (typeof document === 'undefined') {
        return [];
    }

    return Array.from(document.querySelectorAll('main input, main textarea, main select')).filter(isShareableField);
};

const getFieldKey = (field, index) => {
    const explicitKey = field.getAttribute('data-share-key');
    if (explicitKey) {
        return explicitKey;
    }

    const nameOrId = field.getAttribute('name') || field.getAttribute('id') || 'field';
    const type = field instanceof HTMLInputElement ? field.type || 'input' : field.tagName.toLowerCase();
    return `${type}:${nameOrId}:${index}`;
};

const encodeSharePayload = (payload) => {
    const utf8 = new TextEncoder().encode(JSON.stringify(payload));
    let binary = '';
    utf8.forEach((byte) => {
        binary += String.fromCharCode(byte);
    });

    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
};

const decodeSharePayload = (encodedPayload) => {
    const base64 = encodedPayload.replace(/-/g, '+').replace(/_/g, '/');
    const padded = base64 + '='.repeat((4 - (base64.length % 4)) % 4);
    const binary = atob(padded);
    const bytes = Uint8Array.from(binary, (char) => char.charCodeAt(0));
    return JSON.parse(new TextDecoder().decode(bytes));
};

const collectCurrentReportState = () => {
    const fields = getShareableFields();
    if (!fields.length) {
        return null;
    }

    const values = {};
    fields.forEach((field, index) => {
        const key = getFieldKey(field, index);

        if (field instanceof HTMLInputElement && (field.type === 'checkbox' || field.type === 'radio')) {
            values[key] = field.checked;
            return;
        }

        if (field instanceof HTMLSelectElement && field.multiple) {
            values[key] = Array.from(field.selectedOptions).map((option) => option.value);
            return;
        }

        values[key] = field.value;
    });

    return {
        version: 1,
        path: currentPath.value,
        values,
    };
};

const triggerAutoRunFromSharedReport = async () => {
    if (typeof document === 'undefined') {
        return false;
    }

    await nextTick();
    const trigger = document.querySelector('main [data-share-auto-run="true"]');
    if (!(trigger instanceof HTMLElement) || trigger.hasAttribute('disabled')) {
        return false;
    }

    trigger.click();
    return true;
};

const applySharedReportState = async (encodedPayload) => {
    if (!encodedPayload) {
        return false;
    }

    const parsed = decodeSharePayload(encodedPayload);
    if (!parsed || typeof parsed !== 'object' || !parsed.values || typeof parsed.values !== 'object') {
        return false;
    }
    if (parsed.path && parsed.path !== currentPath.value) {
        return false;
    }

    await nextTick();
    const fields = getShareableFields();
    fields.forEach((field, index) => {
        const key = getFieldKey(field, index);
        if (!(key in parsed.values)) {
            return;
        }

        const value = parsed.values[key];

        if (field instanceof HTMLInputElement && (field.type === 'checkbox' || field.type === 'radio')) {
            field.checked = Boolean(value);
            field.dispatchEvent(new Event('change', { bubbles: true }));
            return;
        }

        if (field instanceof HTMLSelectElement && field.multiple && Array.isArray(value)) {
            Array.from(field.options).forEach((option) => {
                option.selected = value.includes(option.value);
            });
            field.dispatchEvent(new Event('change', { bubbles: true }));
            return;
        }

        field.value = value ?? '';
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
    });

    await triggerAutoRunFromSharedReport();

    return true;
};

const restoreSharedReportFromUrl = async () => {
    if (typeof window === 'undefined') {
        return;
    }

    const url = new URL(window.location.href);
    const token = url.searchParams.get(SHARE_QUERY_KEY);
    if (!token || token === lastAppliedShareToken.value) {
        return;
    }

    try {
        const restored = await applySharedReportState(token);
        lastAppliedShareToken.value = token;

        if (restored) {
            updateShareMessage('success', 'Se cargó la consulta compartida.');
        }
    } catch {
        lastAppliedShareToken.value = token;
        updateShareMessage('error', 'El enlace compartido no es válido o está incompleto.');
    }
};

const shareCurrentReport = async () => {
    if (typeof window === 'undefined' || shareSubmitting.value) {
        return;
    }

    try {
        shareSubmitting.value = true;

        const reportState = collectCurrentReportState();
        if (!reportState) {
            updateShareMessage('error', 'No hay datos del informe para compartir todavía.');
            return;
        }

        const encodedState = encodeSharePayload(reportState);
        const shareUrl = new URL(window.location.href);
        shareUrl.searchParams.set(SHARE_QUERY_KEY, encodedState);

        const shareUrlString = shareUrl.toString();
        if (shareUrlString.length > 7800) {
            updateShareMessage('error', 'El informe es muy grande para compartir por enlace. Reduce la consulta e intenta de nuevo.');
            return;
        }

        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(shareUrlString);
            updateShareMessage('success', 'URL del informe copiada al portapapeles.');
            return;
        }

        window.prompt('Copia y comparte este enlace:', shareUrlString);
        updateShareMessage('success', 'Copia manualmente la URL del informe.');
    } catch (error) {
        if (error?.name !== 'AbortError') {
            updateShareMessage('error', 'No se pudo copiar la URL del informe. Intenta nuevamente.');
        }
    } finally {
        shareSubmitting.value = false;
    }
};

watch(
    currentPath,
    async () => {
        await restoreSharedReportFromUrl();
    },
    { immediate: true }
);

watchEffect(() => {
    if (typeof document !== 'undefined') {
        document.title = titleWithEmoji.value;

        let descriptionMetaTag = document.querySelector('meta[name="description"]');
        if (!descriptionMetaTag) {
            descriptionMetaTag = document.createElement('meta');
            descriptionMetaTag.setAttribute('name', 'description');
            document.head.appendChild(descriptionMetaTag);
        }

        descriptionMetaTag.setAttribute('content', rawDescription.value);
    }
});

const FEEDBACK_ENDPOINT = '/api/tools/color-feedback';
const feedbackModalOpen = ref(false);
const feedbackMessageInput = ref('');
const feedbackEmailInput = ref('');
const feedbackSubmitting = ref(false);
const feedbackStatus = ref(null);
const feedbackError = ref('');

const openFeedbackModal = () => {
    feedbackModalOpen.value = true;
    feedbackStatus.value = null;
    feedbackError.value = '';
};

const closeFeedbackModal = () => {
    if (feedbackSubmitting.value) {
        return;
    }

    feedbackModalOpen.value = false;
    feedbackStatus.value = null;
};

const submitFeedback = async () => {
    const message = feedbackMessageInput.value.trim();

    if (!message) {
        feedbackError.value = 'Escribe tu duda, queja o solicitud antes de enviar.';
        feedbackStatus.value = 'error';
        return;
    }

    feedbackSubmitting.value = true;
    feedbackError.value = '';
    feedbackStatus.value = null;

    try {
        await axios.post(FEEDBACK_ENDPOINT, {
            message,
            email: feedbackEmailInput.value.trim() || null,
        });

        feedbackStatus.value = 'success';
        feedbackMessageInput.value = '';
        feedbackEmailInput.value = '';
    } catch (error) {
        feedbackStatus.value = 'error';
        feedbackError.value =
            error.response?.data?.message ||
            'No pudimos enviar tu mensaje. Intenta nuevamente en unos minutos.';
    } finally {
        feedbackSubmitting.value = false;
    }
};
</script>

<template>
    <div class="min-vh-100 d-flex flex-column bg-light">
        <!-- Navbar Bootstrap -->
        <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm">
            <div class="container">
                <!-- Logo -->

                <Link href="/" class="navbar-brand d-flex align-items-center">

                <div class="rounded-circle d-flex align-items-center justify-content-center me-2">
                    <img src="/public/toolsbox.png" alt="Toolsbox codwelt" style="width: 80px;">
                </div>
                <span class="fw-semibold text-dark">
                    Toolsbox
                </span>
                </Link>

                <!-- Toggler -->
                <button class="navbar-toggler" type="button" @click="toggleNav" aria-controls="navbarSupportedContent"
                    :aria-expanded="!isNavCollapsed" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <!-- Menú -->
                <div class="collapse navbar-collapse" :class="{ show: !isNavCollapsed }" id="navbarSupportedContent">
                    <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center w-100">
                        <!-- Categorías (Imágenes, Desarrollo, Finanzas, etc.) -->
                        <li v-for="category in toolCategories" :key="category.key" class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" :id="'navbarDropdown-' + category.key"
                                role="button" data-bs-toggle="dropdown" aria-expanded="false"
                                :class="{ active: isCategoryActive(category) }">
                                {{ category.label }}
                            </a>
                            <ul class="dropdown-menu" :aria-labelledby="'navbarDropdown-' + category.key">
                                <li v-for="tool in category.items" :key="tool.key">
                                    <Link :href="tool.path || '#'" class="dropdown-item"
                                        :class="{ active: isToolActive(tool) }">
                                    {{ tool.name }}
                                    </Link>
                                </li>
                            </ul>
                        </li>
                        <li class="nav-item search-nav-item my-3 my-lg-0">
                            <div class="position-relative search-container">
                                <span class="search-icon" aria-hidden="true">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                        <path
                                            d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85zm-5.242.656a5 5 0 1 1 0-10 5 5 0 0 1 0 10z"
                                        />
                                    </svg>
                                </span>
                                <input
                                    v-model="searchQuery"
                                    type="search"
                                    class="form-control search-input"
                                    placeholder="Buscar herramienta (mín. 4 letras)..."
                                />
                                <div v-if="showSearchDropdown" class="search-results shadow-sm">
                                    <Link
                                        v-for="tool in searchResults"
                                        :key="tool.key"
                                        :href="tool.path || '#'"
                                        class="search-result-item"
                                        @click="handleResultClick"
                                    >
                                        <div class="d-flex justify-content-between align-items-start">
                                            <span class="search-result-title">{{ tool.name }}</span>
                                            <span class="badge bg-light text-muted ms-2">{{ tool.categoryLabel }}</span>
                                        </div>
                                        <div class="search-result-desc">
                                            {{ tool.description || 'Herramienta de la caja de Toolsbox.' }}
                                        </div>
                                    </Link>
                                </div>
                                <div
                                    v-else-if="searchQuery.trim().length >= 4"
                                    class="search-results shadow-sm p-3 text-muted small"
                                >
                                    No encontramos herramientas para “{{ searchQuery }}”.
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
        <section v-if="canShareReport" class="share-report-banner border-bottom bg-white">
            <div class="container py-2 d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-2">
                <div class="small text-muted">
                    Comparte esta consulta con tu equipo usando un enlace directo.
                </div>
                <div class="d-flex flex-column align-items-lg-end">
                    <button
                        type="button"
                        class="btn btn-success btn-sm share-report-btn"
                        :disabled="shareSubmitting"
                        @click="shareCurrentReport"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            width="17"
                            height="17"
                            viewBox="0 0 24 24"
                            class="me-2"
                            aria-hidden="true"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path d="M10 13a5 5 0 0 0 7.07 0l3.54-3.54a5 5 0 0 0-7.07-7.07L10 5" />
                            <path d="M14 11a5 5 0 0 0-7.07 0l-3.54 3.54a5 5 0 1 0 7.07 7.07L14 19" />
                        </svg>
                        {{ shareSubmitting ? 'Preparando URL...' : 'Copiar URL del informe' }}
                    </button>
                    <span
                        v-if="shareMessage"
                        class="small mt-1 text-lg-end"
                        :class="shareMessageClass"
                    >
                        {{ shareMessage }}
                    </span>
                </div>
            </div>
        </section>

        <!-- Contenido -->
        <main class="flex-fill">
            <slot />
        </main>
        <!-- Footer -->
        <footer class="border-top bg-white mt-4">
            <div class="container py-3 d-flex flex-column flex-lg-row align-items-center justify-content-between gap-3 small text-muted">
                <span>© {{ new Date().getFullYear() }} Codwelt SAS - Toolsbox</span>
                <ul class="footer-nav list-unstyled d-flex mb-0">
                    <li>
                        <a href="https://codwelt.com/empresa-de-paginas-web-tiendas-virtuales-aplicaciones/" class="footer-link" target="_blank">
                            ¿Quiénes somos?
                        </a>
                    </li>
                    <li>
                        <a href="https://codwelt.com/categorias/tutoriales/" class="footer-link" target="_blank">
                            Blog
                        </a>
                    </li>
                </ul>
                <button
                    type="button"
                    class="btn btn-outline-primary btn-sm feedback-btn"
                    @click="openFeedbackModal"
                >
                    Sugerencias / Soporte
                </button>
                <span>Desarrollado por <a href="https://codwelt.com/" target="_blank">codwelt.com</a></span>
            </div>
        </footer>
        <div
            v-if="feedbackModalOpen"
            class="position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center"
            style="background: rgba(15, 23, 42, 0.85); z-index: 1100;"
            role="dialog"
            aria-modal="true"
            @click.self="closeFeedbackModal"
        >
            <div class="modal-dialog w-100" style="max-width: 540px;">
                <div class="modal-content rounded-4 shadow-lg border-0 position-relative bg-white">
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
                                <label class="form-label fw-semibold" for="layoutFeedbackMessage">Mensaje</label>
                                <textarea
                                    id="layoutFeedbackMessage"
                                    class="form-control"
                                    rows="4"
                                    placeholder="Escribe aquí tus dudas, quejas o solicitudes..."
                                    v-model="feedbackMessageInput"
                                ></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold" for="layoutFeedbackEmail">Correo electrónico (opcional)</label>
                                <input
                                    id="layoutFeedbackEmail"
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
.nav-item {
    margin: 0 15px;
}

.navbar {
    position: relative;
    z-index: 1040;
}

.navbar .dropdown-menu {
    z-index: 1050;
}

.nav-link:hover,
.dropdown-item:hover {
    color: #0dcaf0 !important;
}

.nav-link.active,
.dropdown-item.active {
    color: #0dcaf0 !important;
    font-weight: 600;
    background-color: #f3f3f3;
}

.footer-nav {
    gap: 16px;
}

.footer-link {
    color: #6c757d;
    text-decoration: none;
    padding: 4px 6px;
}

.footer-link:hover {
    color: #0dcaf0;
    text-decoration: underline;
}

.search-container {
    min-width: 260px;
    width: 100%;
    padding-left: 30px;
}

.search-results {
    position: absolute;
    top: 110%;
    left: 0;
    right: 0;
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    z-index: 1050;
    max-height: 360px;
    overflow: auto;
}

.search-result-item {
    display: block;
    padding: 10px 12px;
    color: #212529;
    text-decoration: none;
    transition: background-color 0.15s ease, transform 0.15s ease;
}

.search-result-item:hover {
    background-color: #f1f8ff;
    transform: translateX(2px);
}

.search-result-title {
    font-weight: 600;
}

.search-result-desc {
    font-size: 0.9rem;
    color: #6c757d;
    margin-top: 4px;
}

.search-icon {
    position: absolute;
    left: 8px;
    top: 50%;
    transform: translateY(-50%);
    color: #6c757d;
}

.search-input {
    padding-left: 28px;
}

.search-nav-item {
    flex: 1 1 320px;
    min-width: 260px;
}

.feedback-btn {
    border-radius: 999px;
    padding: 0.35rem 1rem;
}

.share-report-banner {
    position: sticky;
    top: 0;
    z-index: 1010;
    box-shadow: 0 6px 16px rgba(15, 23, 42, 0.06);
}

.share-report-btn {
    border-radius: 999px;
    font-weight: 600;
    padding: 0.45rem 1rem;
    display: inline-flex;
    align-items: center;
}

.feedback-modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.55);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2000;
    padding: 1rem;
}

.feedback-modal-card {
    background: #fff;
    border-radius: 12px;
    width: min(420px, 100%);
    box-shadow: 0 20px 40px rgba(15, 23, 42, 0.2);
    overflow: hidden;
}

.feedback-modal-header {
    padding: 1rem 1.25rem 0.5rem;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.feedback-modal-body {
    padding: 0.75rem 1.25rem 1.25rem;
}
</style>
