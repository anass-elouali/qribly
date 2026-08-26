<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import { ChevronLeft, ChevronRight, MapPin } from 'lucide-vue-next'
import type { Offer } from '@/types/offer'
import { formatPrice } from '@/utils/offer'
import { resolveStorageUrl } from '@/utils/url'

defineProps<{
  offers: Offer[]
}>()

const track = ref<HTMLElement | null>(null)

function scrollByCard(direction: 1 | -1) {
  const element = track.value
  if (!element) return

  const card = element.querySelector('[data-carousel-card]') as HTMLElement | null
  const distance = (card?.offsetWidth ?? 280) + 16

  element.scrollBy({ left: distance * direction, behavior: 'smooth' })
}
</script>

<template>
  <section class="mb-14">
    <div class="mb-5 flex items-end justify-between">
      <div>
        <p class="font-mono text-xs tracking-[0.14em] text-primary uppercase">À la une</p>
        <h2 class="mt-1 font-display text-2xl font-bold text-ink sm:text-3xl">
          Annonces vedettes
        </h2>
      </div>

      <div class="hidden shrink-0 gap-2 sm:flex">
        <button
          type="button"
          aria-label="Précédent"
          class="flex h-10 w-10 items-center justify-center rounded-full border border-ink/15 bg-surface text-ink transition hover:border-primary hover:text-primary"
          @click="scrollByCard(-1)"
        >
          <ChevronLeft :size="18" aria-hidden="true" />
        </button>
        <button
          type="button"
          aria-label="Suivant"
          class="flex h-10 w-10 items-center justify-center rounded-full border border-ink/15 bg-surface text-ink transition hover:border-primary hover:text-primary"
          @click="scrollByCard(1)"
        >
          <ChevronRight :size="18" aria-hidden="true" />
        </button>
      </div>
    </div>

    <div
      ref="track"
      class="featured-track -mx-6 flex snap-x snap-mandatory gap-4 overflow-x-auto px-6 pb-2"
    >
      <RouterLink
        v-for="offer in offers"
        :key="offer.id"
        data-carousel-card
        :to="{ name: 'offer-details', params: { id: offer.id } }"
        class="group w-64 shrink-0 snap-start overflow-hidden rounded-xl border border-ink/10 bg-surface transition hover:-translate-y-0.5 hover:shadow-lg sm:w-72"
      >
        <div class="aspect-[4/3] overflow-hidden bg-primary">
          <img
            v-if="offer.images?.[0]"
            :src="resolveStorageUrl(offer.images[0].url)"
            :alt="offer.title"
            class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
          />
        </div>

        <div class="p-4">
          <p
            v-if="offer.category"
            class="font-mono text-[0.65rem] tracking-wide text-ink/45 uppercase"
          >
            {{ offer.category.name }}
          </p>
          <p class="mt-1 truncate font-display text-lg font-bold text-ink">
            {{ offer.title }}
          </p>

          <div class="mt-2 flex items-center justify-between">
            <span v-if="offer.city" class="flex items-center gap-1 font-body text-xs text-ink/55">
              <MapPin :size="13" class="shrink-0 text-primary" aria-hidden="true" />
              {{ offer.city }}
            </span>
            <span class="rounded bg-accent px-2 py-0.5 font-mono text-xs font-bold text-ink">
              {{ formatPrice(offer.price) }} DH
            </span>
          </div>
        </div>
      </RouterLink>
    </div>
  </section>
</template>

<style scoped>
.featured-track {
  scrollbar-width: none;
}

.featured-track::-webkit-scrollbar {
  display: none;
}
</style>
