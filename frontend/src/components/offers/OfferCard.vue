<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import type { Category } from '@/types/offer'
import { statusLabel, statusColor, formatPrice } from '@/utils/offer'
import { resolveStorageUrl } from '@/utils/url'
import FavoriteButton from '@/components/offers/FavoriteButton.vue'

const props = defineProps<{
  id: number
  title: string
  price: string | number
  status: 'active' | 'reserved' | 'sold' | 'inactive'
  isNegotiable?: boolean
  category?: Category | null
  distance?: number | null
  images?: { id: number; url: string }[]
}>()

const thumbnailUrl = computed(() => {
  const first = props.images?.[0]
  return first ? resolveStorageUrl(first.url) : null
})

function formatDistance(meters: number) {
  return meters < 1000 ? `${Math.round(meters)} m` : `${(meters / 1000).toFixed(1)} km`
}
</script>

<template>
  <div class="relative flex flex-col overflow-hidden rounded-md border border-ink/10 bg-surface">
    <FavoriteButton :offer-id="props.id" class="absolute top-2 right-2 z-10" />

    <RouterLink
      :to="{ name: 'offer-details', params: { id: props.id } }"
      class="group flex flex-1 flex-col transition-shadow hover:shadow-lg"
    >
      <div class="aspect-[4/3] overflow-hidden bg-primary">
        <img v-if="thumbnailUrl" :src="thumbnailUrl" :alt="title" class="h-full w-full object-cover" />
        <div
          v-else
          class="flex h-full items-center justify-center font-mono text-xs uppercase tracking-wide text-surface/70"
        >
          Photo
        </div>
      </div>

      <div
        class="relative border-t-2 border-dashed border-ink/15 before:absolute before:-top-1.5 before:-left-1.5 before:h-3 before:w-3 before:rounded-full before:bg-ground before:content-[''] after:absolute after:-top-1.5 after:-right-1.5 after:h-3 after:w-3 after:rounded-full after:bg-ground after:content-['']"
      ></div>

      <div class="flex flex-1 flex-col gap-2 p-4">
        <div class="flex items-start justify-between gap-2">
          <div>
            <p v-if="category" class="font-mono text-[0.65rem] tracking-wide text-ink/50 uppercase">
              {{ category.name }}
            </p>
            <p class="font-body font-semibold text-ink">{{ title }}</p>
          </div>
          <span class="-rotate-2 shrink-0 rounded bg-accent px-2 py-0.5 font-mono text-sm font-bold text-ink">
            {{ formatPrice(price) }} DH
          </span>
        </div>

        <div class="flex items-center justify-between">
          <span v-if="distance != null" class="font-mono text-xs text-ink/50">
            {{ formatDistance(distance) }}
          </span>
          <span v-else-if="isNegotiable" class="font-mono text-xs text-ink/50">Négociable</span>
          <span v-else></span>

          <span
            class="rounded px-2 py-0.5 font-mono text-[0.65rem] tracking-wide text-surface uppercase"
            :class="statusColor[status]"
          >
            {{ statusLabel[status] }}
          </span>
        </div>
      </div>
    </RouterLink>

    <div v-if="$slots.actions" class="flex gap-2 border-t border-ink/10 p-3">
      <slot name="actions" />
    </div>
  </div>
</template>
