<script setup lang="ts">
import { LayoutGrid } from 'lucide-vue-next'
import type { Category } from '@/types/offer'
import { resolveStorageUrl } from '@/utils/url'

defineProps<{
  categories: Category[]
  categoryImages: Record<number, string | null>
  selectedCategory: number | null
}>()

const emit = defineEmits<{
  select: [id: number | null]
}>()
</script>

<template>
  <section class="mb-14">
    <h2 class="mb-5 font-display text-2xl font-bold text-ink sm:text-3xl">Parcourir par catégorie</h2>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
      <button
        type="button"
        class="group relative flex aspect-[4/3] items-end overflow-hidden rounded-xl border transition"
        :class="
          selectedCategory === null
            ? 'border-primary ring-2 ring-primary/30'
            : 'border-ink/10 hover:border-primary/50'
        "
        @click="emit('select', null)"
      >
        <div class="absolute inset-0 bg-primary"></div>
        <LayoutGrid
          :size="32"
          class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 text-surface/25"
          aria-hidden="true"
        />
        <span
          class="relative z-10 w-full bg-gradient-to-t from-ink/70 to-transparent px-3 py-3 text-left font-display text-sm font-bold text-surface sm:text-base"
        >
          Toutes
        </span>
      </button>

      <button
        v-for="category in categories"
        :key="category.id"
        type="button"
        class="group relative flex aspect-[4/3] items-end overflow-hidden rounded-xl border bg-primary transition"
        :class="
          selectedCategory === category.id
            ? 'border-primary ring-2 ring-primary/30'
            : 'border-ink/10 hover:border-primary/50'
        "
        @click="emit('select', category.id)"
      >
        <img
          v-if="categoryImages[category.id]"
          :src="resolveStorageUrl(categoryImages[category.id]!)"
          :alt="category.name"
          class="absolute inset-0 h-full w-full object-cover transition duration-300 group-hover:scale-105"
        />
        <span
          class="relative z-10 w-full bg-gradient-to-t from-ink/75 via-ink/10 to-transparent px-3 py-3 text-left font-display text-sm font-bold text-surface sm:text-base"
        >
          {{ category.name }}
        </span>
      </button>
    </div>
  </section>
</template>
