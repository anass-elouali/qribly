<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { ArrowRight, MapPin, Sparkles } from 'lucide-vue-next'
import type { Offer } from '@/types/offer'
import { formatPrice } from '@/utils/offer'
import { resolveStorageUrl } from '@/utils/url'

defineProps<{
  offer: Offer
}>()
</script>

<template>
  <section class="mb-14">
    <div
      class="relative overflow-hidden rounded-2xl border border-ink/10 bg-primary text-surface"
    >
      <div class="grid gap-0 sm:grid-cols-2">
        <div class="order-2 flex flex-col justify-center gap-4 p-8 sm:order-1 sm:p-12">
          <span
            class="inline-flex w-fit items-center gap-2 rounded-full bg-accent px-3 py-1.5 font-mono text-xs font-bold tracking-wide text-ink uppercase"
          >
            <Sparkles :size="14" aria-hidden="true" />
            Coup de cœur du moment
          </span>

          <h2 class="font-display text-3xl leading-tight font-bold sm:text-4xl">
            {{ offer.title }}
          </h2>

          <p v-if="offer.category" class="font-body text-sm text-surface/70">
            {{ offer.category.name }}
          </p>

          <div class="flex flex-wrap items-center gap-4">
            <span class="rounded-lg bg-accent px-4 py-2 font-mono text-lg font-bold text-ink">
              {{ formatPrice(offer.price) }} DH
            </span>
            <span v-if="offer.city" class="flex items-center gap-1.5 font-body text-sm text-surface/80">
              <MapPin :size="16" class="shrink-0" aria-hidden="true" />
              {{ offer.city }}
            </span>
          </div>

          <RouterLink
            :to="{ name: 'offer-details', params: { id: offer.id } }"
            class="mt-2 inline-flex w-fit items-center gap-2 rounded-lg bg-surface px-5 py-3 font-semibold text-primary transition hover:opacity-90"
          >
            Découvrir cette annonce
            <ArrowRight :size="17" aria-hidden="true" />
          </RouterLink>
        </div>

        <div class="order-1 aspect-[16/10] overflow-hidden sm:order-2 sm:aspect-auto">
          <img
            v-if="offer.images?.[0]"
            :src="resolveStorageUrl(offer.images[0].url)"
            :alt="offer.title"
            class="h-full w-full object-cover"
          />
          <div v-else class="h-full w-full bg-primary/80"></div>
        </div>
      </div>
    </div>
  </section>
</template>
