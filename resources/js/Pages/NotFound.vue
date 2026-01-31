<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '../Layouts/AppLayout.vue';

defineOptions({
    layout: AppLayout,
});

const props = defineProps({
    toolList: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();

const requestedUrl = computed(() => page.url || '/');

const groupedTools = computed(() => {
    const groups = {};

    props.toolList.forEach((tool) => {
        const label = tool.category?.label || 'Herramientas';
        if (!groups[label]) {
            groups[label] = {
                label,
                tools: [],
            };
        }

        groups[label].tools.push(tool);
    });

    return Object.values(groups);
});
</script>

<template>
    <div class="bg-light min-vh-100 pt-5 pb-5">
        <Head>
            <title>404 · Página no encontrada · Toolsbox</title>
            <meta name="robots" content="noindex" />
        </Head>

        <div class="container py-5">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body text-center">
                    <p class="text-uppercase text-info small fw-semibold mb-2">404 · Página no encontrada</p>
                    <h1 class="display-6 fw-bold mb-3">¡Ups! Esta URL no existe</h1>
                    <p class="text-muted mb-3">
                        La dirección <strong>{{ requestedUrl }}</strong> no se encuentra en Toolsbox.
                        Pero no te preocupes, todavía puedes explorar nuestras herramientas disponibles.
                    </p>
                    <div class="d-flex flex-column flex-sm-row justify-content-center gap-2">
                        <Link href="/" class="btn btn-primary">Ir a la inicio</Link>
                        <Link href="/" class="btn btn-outline-secondary">Ver herramientas</Link>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div v-for="group in groupedTools" :key="group.label" class="col-12">
                    <h2 class="h5 fw-semibold mb-3">{{ group.label }}</h2>
                    <div class="row g-3">
                        <div v-for="tool in group.tools" :key="tool.key" class="col-sm-6 col-lg-4">
                            <div class="card h-100 tool-card border-0 shadow-sm">
                                <div class="card-body d-flex flex-column">
                                    <h3 class="h6 fw-bold mb-2">{{ tool.name }}</h3>
                                    <p class="text-muted small flex-grow-1">{{ tool.description }}</p>
                                    <Link :href="tool.path" class="btn btn-sm btn-outline-primary mt-3">Ir a la herramienta</Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.tool-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.tool-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 15px 35px rgba(15, 23, 42, 0.15);
}
</style>
