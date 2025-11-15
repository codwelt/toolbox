<script setup>
import { ref } from 'vue';

const props = defineProps({
  title: String,
});

const files = ref([]);
const compressed = ref([]); // { name, originalSize, newSize, url }

const quality = ref(0.7); // 0 – 1
const maxWidth = ref(1920);
const maxHeight = ref(1080);
const isProcessing = ref(false);

function handleFileChange(e) {
  const selected = Array.from(e.target.files || []);
  files.value = selected;
  compressed.value = [];
}

function formatBytes(bytes) {
  if (!bytes && bytes !== 0) return '';
  const sizes = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(1024));
  const value = bytes / Math.pow(1024, i);
  return `${value.toFixed(2)} ${sizes[i]}`;
}

async function compressImages() {
  if (!files.value.length) return;
  isProcessing.value = true;
  compressed.value = [];

  for (const file of files.value) {
    const result = await compressSingle(file);
    if (result) {
      compressed.value.push(result);
    }
  }

  isProcessing.value = false;
}

function compressSingle(file) {
  return new Promise((resolve) => {
    const reader = new FileReader();

    reader.onload = (event) => {
      const img = new Image();
      img.onload = () => {
        const { canvas, ctx } = createCanvas(img);
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

        canvas.toBlob(
          (blob) => {
            if (!blob) {
              return resolve(null);
            }
            const url = URL.createObjectURL(blob);
            resolve({
              name: file.name,
              originalSize: file.size,
              newSize: blob.size,
              url,
            });
          },
          file.type || 'image/jpeg',
          quality.value
        );
      };
      img.src = event.target.result;
    };

    reader.readAsDataURL(file);
  });
}

function createCanvas(img) {
  const canvas = document.createElement('canvas');
  const ctx = canvas.getContext('2d');

  let width = img.width;
  let height = img.height;

  // Mantener proporción dentro de maxWidth / maxHeight
  const ratio = Math.min(maxWidth.value / width, maxHeight.value / height, 1);
  width = width * ratio;
  height = height * ratio;

  canvas.width = width;
  canvas.height = height;

  return { canvas, ctx };
}

function download(file) {
  const a = document.createElement('a');
  a.href = file.url;
  a.download = file.name.replace(/\.(\w+)$/, '_compressed.$1');
  document.body.appendChild(a);
  a.click();
  a.remove();
}

function downloadAll() {
  // Versión simple: dispara descargas una por una
  compressed.value.forEach((f, index) => {
    setTimeout(() => download(f), index * 400);
  });
}
</script>

<template>
  <div class="min-h-screen bg-gray-100">
    <div class="max-w-5xl mx-auto py-10 px-4">
      <h1 class="text-3xl font-bold text-gray-800 mb-2">
        {{ title }}
      </h1>
      <p class="text-gray-600 mb-6">
        Sube una o varias imágenes, ajusta la calidad y descárgalas comprimidas sin perder demasiada calidad.
      </p>

      <div class="bg-white rounded-xl shadow p-6 mb-8">
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Imágenes (JPG, PNG, WebP)
          </label>
          <input
            type="file"
            multiple
            accept="image/*"
            class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50"
            @change="handleFileChange"
          />
          <p class="mt-1 text-xs text-gray-500">
            Puedes seleccionar varias imágenes a la vez.
          </p>
        </div>

        <div class="grid md:grid-cols-3 gap-4 mb-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Calidad
            </label>
            <input
              type="range"
              min="0.2"
              max="1"
              step="0.05"
              v-model.number="quality"
              class="w-full"
            />
            <p class="text-xs text-gray-500">
              {{ Math.round(quality * 100) }}% de calidad
            </p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Ancho máximo (px)
            </label>
            <input
              type="number"
              v-model.number="maxWidth"
              class="w-full border border-gray-300 rounded-lg px-2 py-1 text-sm"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Alto máximo (px)
            </label>
            <input
              type="number"
              v-model.number="maxHeight"
              class="w-full border border-gray-300 rounded-lg px-2 py-1 text-sm"
            />
          </div>
        </div>

        <button
          type="button"
          class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50"
          :disabled="!files.length || isProcessing"
          @click="compressImages"
        >
          <span v-if="!isProcessing">Comprimir imágenes</span>
          <span v-else>Procesando...</span>
        </button>
      </div>

      <div v-if="files.length" class="mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-2">
          Imágenes seleccionadas
        </h2>
        <ul class="text-sm text-gray-700 list-disc list-inside">
          <li v-for="f in files" :key="f.name">
            {{ f.name }} – {{ formatBytes(f.size) }}
          </li>
        </ul>
      </div>

      <div v-if="compressed.length" class="bg-white rounded-xl shadow p-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-semibold text-gray-800">
            Imágenes comprimidas
          </h2>
          <button
            type="button"
            class="text-sm font-semibold text-indigo-600 hover:text-indigo-800"
            @click="downloadAll"
          >
            Descargar todas
          </button>
        </div>

        <div class="space-y-4">
          <div
            v-for="file in compressed"
            :key="file.url"
            class="flex items-center justify-between border border-gray-200 rounded-lg px-3 py-2"
          >
            <div>
              <p class="text-sm font-medium text-gray-800">
                {{ file.name }}
              </p>
              <p class="text-xs text-gray-500">
                Original: {{ formatBytes(file.originalSize) }} ·
                Nuevo: {{ formatBytes(file.newSize) }} ·
                Ahorro:
                {{
                  (100 - (file.newSize / file.originalSize) * 100).toFixed(1)
                }}%
              </p>
            </div>
            <button
              type="button"
              class="text-sm font-semibold text-indigo-600 hover:text-indigo-800"
              @click="download(file)"
            >
              Descargar
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
