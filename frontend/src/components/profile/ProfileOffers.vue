<script setup lang="ts">
import { RouterLink } from 'vue-router'

import OfferCard from '@/components/offers/OfferCard.vue'
import type { Offer } from '@/types/offer'

defineProps<{
  offers: Offer[]
}>()

const emit = defineEmits<{
  delete: [id: number]
}>()
</script>

<template>
  <section>
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <p class="font-mono text-[0.65rem] tracking-[0.2em] text-primary uppercase">Marketplace</p>

        <h2 class="mt-1 font-display text-2xl font-bold text-ink">Mes annonces</h2>

        <p class="mt-1 font-body text-sm text-ink/50">
          Gérez les produits et services que vous avez publiés.
        </p>
      </div>

      <RouterLink
        :to="{ name: 'offer-create' }"
        class="inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 font-mono text-xs tracking-wide text-surface transition hover:opacity-90"
      >
        + Publier une annonce
      </RouterLink>
    </div>

    <div
      v-if="offers.length === 0"
      class="rounded-xl border border-dashed border-ink/15 px-6 py-16 text-center"
    >
      <p class="font-display text-lg font-bold text-ink">Aucune annonce</p>

      <p class="mx-auto mt-2 max-w-md font-body text-sm text-ink/50">
        Tu n'as encore publié aucune annonce. Crée ta première annonce pour commencer.
      </p>

      <RouterLink
        :to="{ name: 'offer-create' }"
        class="mt-5 inline-flex rounded-md bg-primary px-4 py-2 font-mono text-xs text-surface"
      >
        Publier une annonce
      </RouterLink>
    </div>

    <div v-else class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
      <OfferCard
        v-for="offer in offers"
        :id="offer.id"
        :key="offer.id"
        :title="offer.title"
        :price="offer.price"
        :status="offer.status"
        :is-negotiable="offer.is_negotiable"
        :category="offer.category ?? null"
        :city="offer.city"
        :images="offer.images"
      >
        <template #actions>
          <RouterLink
            :to="{
              name: 'offer-edit',
              params: { id: offer.id },
            }"
            class="flex-1 rounded-md border border-ink/15 px-3 py-1.5 text-center text-sm text-ink/70 transition hover:border-primary hover:text-primary"
          >
            Modifier
          </RouterLink>

          <button
            type="button"
            class="flex-1 rounded-md border border-ink/15 px-3 py-1.5 text-sm text-status-reserved transition hover:border-status-reserved"
            @click="emit('delete', offer.id)"
          >
            Supprimer
          </button>
        </template>
      </OfferCard>
    </div>
  </section>
</template>
