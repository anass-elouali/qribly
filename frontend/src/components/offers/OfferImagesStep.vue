<script setup lang="ts">
import { computed, ref } from 'vue'
import { resolveStorageUrl } from '@/utils/url'

interface ExistingImage {
  id: number
  url: string
}

const props = defineProps<{
  modelValue: File[]
  existingImages?: ExistingImage[]
  isEdit?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: File[]]
  'remove-existing': [id: number]
  next: []
  back: []
}>()

const fileInput = ref<HTMLInputElement | null>(null)
const dragging = ref(false)

const previewUrls = computed(() => {
  return props.modelValue.map((file) => ({
    file,
    url: URL.createObjectURL(file),
  }))
})

const totalImages = computed(() => {
  return (props.existingImages?.length ?? 0) + props.modelValue.length
})

const remainingSlots = computed(() => {
  return Math.max(0, 5 - totalImages.value)
})

function addFiles(files: File[]) {
  if (!files.length || remainingSlots.value <= 0) {
    return
  }

  const validFiles = files.filter((file) =>
    [
      'image/jpeg',
      'image/png',
      'image/webp',
      'image/avif',
    ].includes(file.type),
  )

  const selected = validFiles.slice(0, remainingSlots.value)

  emit('update:modelValue', [
    ...props.modelValue,
    ...selected,
  ])
}

function onFileChange(event: Event) {
  const input = event.target as HTMLInputElement

  if (!input.files) {
    return
  }

  addFiles(Array.from(input.files))

  input.value = ''
}

function removeSelected(index: number) {
  const updated = [...props.modelValue]
  updated.splice(index, 1)

  emit('update:modelValue', updated)
}

function removeExisting(id: number) {
  emit('remove-existing', id)
}

function openFilePicker() {
  if (remainingSlots.value <= 0) {
    return
  }

  fileInput.value?.click()
}

function onDrop(event: DragEvent) {
  dragging.value = false

  if (!event.dataTransfer?.files) {
    return
  }

  addFiles(Array.from(event.dataTransfer.files))
}

function next() {
  emit('next')
}
</script>

<template>
  <div class="flex flex-col gap-6">
    <!-- Header -->
    <div>
      <p class="mb-2 font-mono text-xs tracking-widest text-primary uppercase">
        Étape 2 sur 3
      </p>

      <h2 class="font-display text-2xl font-bold text-primary">
        Photos
      </h2>

      <p class="mt-1 font-body text-sm text-ink/60">
        Ajoute jusqu'à 5 photos pour présenter ton annonce.
      </p>
    </div>

    <!-- Existing images -->
    <div
      v-if="existingImages?.length"
      class="grid grid-cols-2 gap-3 sm:grid-cols-3"
    >
      <div
        v-for="image in existingImages"
        :key="image.id"
        class="group relative aspect-square overflow-hidden rounded-xl border border-ink/10 bg-surface"
      >
        <img
          :src="resolveStorageUrl(image.url)"
          alt=""
          class="h-full w-full object-cover"
        />

        <div
          class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/60 to-transparent px-3 pb-2 pt-8"
        >
          <span class="text-xs text-white">
            Déjà publiée
          </span>
        </div>

        <button
          type="button"
          class="absolute top-2 right-2 flex h-8 w-8 items-center justify-center rounded-full bg-black/70 text-lg leading-none text-white transition hover:bg-status-reserved"
          aria-label="Supprimer cette photo"
          @click="removeExisting(image.id)"
        >
          ×
        </button>
      </div>
    </div>

    <!-- New image previews -->
    <div
      v-if="previewUrls.length"
      class="grid grid-cols-2 gap-3 sm:grid-cols-3"
    >
      <div
        v-for="(preview, index) in previewUrls"
        :key="preview.url"
        class="group relative aspect-square overflow-hidden rounded-xl border border-primary/20 bg-surface"
      >
        <img
          :src="preview.url"
          alt="Aperçu"
          class="h-full w-full object-cover"
        />

        <div
          class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/60 to-transparent px-3 pb-2 pt-8"
        >
          <span class="text-xs text-white">
            Nouvelle photo
          </span>
        </div>

        <button
          type="button"
          class="absolute top-2 right-2 flex h-8 w-8 items-center justify-center rounded-full bg-black/70 text-lg leading-none text-white transition hover:bg-status-reserved"
          aria-label="Supprimer cette photo"
          @click="removeSelected(index)"
        >
          ×
        </button>
      </div>
    </div>

    <!-- Upload area -->
    <button
      v-if="remainingSlots > 0"
      type="button"
      class="relative flex min-h-48 flex-col items-center justify-center rounded-2xl border-2 border-dashed px-6 py-8 transition"
      :class="
        dragging
          ? 'border-primary bg-primary/5'
          : 'border-ink/15 bg-ground hover:border-primary/40 hover:bg-primary/5'
      "
      @click="openFilePicker"
      @dragover.prevent="dragging = true"
      @dragleave.prevent="dragging = false"
      @drop.prevent="onDrop"
    >
      <div
        class="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-primary/10 text-2xl"
      >
        +
      </div>

      <p class="font-semibold text-primary">
        Ajouter des photos
      </p>

      <p class="mt-1 text-center font-body text-sm text-ink/50">
        Clique ou glisse tes photos ici
      </p>

      <p class="mt-2 font-mono text-xs text-ink/40">
        JPG, PNG, WEBP ou AVIF · {{ remainingSlots }} emplacement(s) restant(s)
      </p>

      <input
        ref="fileInput"
        type="file"
        accept="image/png,image/jpeg,image/webp,image/avif"
        multiple
        class="hidden"
        @change="onFileChange"
      />
    </button>

    <div
      v-else
      class="rounded-xl bg-primary/5 px-4 py-3 text-center font-mono text-xs text-primary"
    >
      Maximum de 5 photos atteint.
    </div>

    <!-- Navigation -->
    <div class="flex justify-between border-t border-ink/10 pt-5">
      <button
        type="button"
        class="rounded-xl border border-ink/15 px-6 py-3 font-body text-sm text-ink/70 transition hover:border-primary hover:text-primary"
        @click="emit('back')"
      >
        ← Retour
      </button>

      <button
        type="button"
        class="rounded-xl bg-primary px-6 py-3 font-semibold text-surface transition hover:opacity-90"
        @click="next"
      >
        Continuer
        <span class="ml-2">→</span>
      </button>
    </div>
  </div>
</template>