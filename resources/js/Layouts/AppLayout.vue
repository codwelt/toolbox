<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref, watchEffect } from 'vue';

const page = usePage();

// Categorías + tools compartidos desde Laravel
const toolCategories = computed(() => page.props.toolCategories || []);

const isNavCollapsed = ref(true);
const toggleNav = () => {
    isNavCollapsed.value = !isNavCollapsed.value;
};

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

const rawTitle = computed(() => page.props.seo?.title || page.props.title || 'Toolsbox');
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

watchEffect(() => {
    if (typeof document !== 'undefined') {
        document.title = titleWithEmoji.value;
    }
});
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
                    <ul class="navbar-nav ms-auto mb-2 mb-lg-0">

                        <!-- Inicio -->
                        <li class="nav-item">
                            <Link href="/" class="nav-link" :class="{ active: currentPath === '/' }">
                            Inicio
                            </Link>
                        </li>

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
                        <!-- Inicio -->
                        <li class="nav-item">
                            <a href="https://codwelt.com/empresa-de-paginas-web-tiendas-virtuales-aplicaciones/"
                                class="nav-link" target="_blank">¿Quiénes somos?
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="https://codwelt.com/categorias/tutoriales/" class="nav-link"
                                target="_blank">Blog</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <!-- Contenido -->
        <main class="flex-fill">
            <slot />
        </main>

        <!-- Footer -->
        <footer class="border-top bg-white mt-4">
            <div class="container py-3 d-flex justify-content-between small text-muted">
                <span>© {{ new Date().getFullYear() }} Codwelt SAS - Toolsbox</span>
                <span>Desarrollado por <a href="https://codwelt.com/" target="_blank">codwelt.com</a></span>
            </div>
        </footer>
    </div>
</template>

<style scoped>
.nav-item {
    margin: 0 15px;
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
</style>
