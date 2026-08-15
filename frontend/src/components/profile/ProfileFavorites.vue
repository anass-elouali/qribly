<script setup lang="ts">
import OfferCard from '@/components/offers/OfferCard.vue'
import type { Offer } from '@/types/offer'

defineProps<{
  favorites: Offer[]
}>()

const emit = defineEmits<{
  remove: [id: number]
}>()
</script>

<template>
  <section>
    <div class="mb-6">
      <p
        class="font-mono text-[0.65rem] tracking-[0.2em] text-primary uppercase"
      >
        Sauvegardés
      </p>

      <h2 class="mt-1 font-display text-2xl font-bold text-ink">
        Mes favoris
      </h2>

      <p class="mt-1 font-body text-sm text-ink/50">
        Les annonces que tu souhaites retrouver facilement.
      </p>
    </div>

    <div
      v-if="favorites.length === 0"
      class="rounded-xl border border-dashed border-ink/15 px-6 py-16 text-center"
    >
      <p class="font-display text-lg font-bold text-ink">
        Aucun favori
      </p>

      <p class="mx-auto mt-2 max-w-md font-body text-sm text-ink/50">
        Tu n'as encore enregistré aucune annonce.
      </p>
    </div>

    <div
      v-else
      class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3"
    >
      <OfferCard
        v-for="offer in favorites"
        :id="offer.id"
        :key="offer.id"
        :title="offer.title"
        :price="offer.price"
        :status="offer.status"
        :is-negotiable="offer.is_negotiable"
        :category="offer.category ?? null"
        :images="offer.images"
      >
        <template #actions>
          <button
            type="button"
            class="w-full rounded-md border border-ink/15 px-3 py-1.5 text-sm text-ink/70 transition hover:border-primary hover:text-primary"
            @click="emit('remove', offer.id)"
          >
            Retirer des favoris
          </button>
        </template>
      </OfferCard>
    </div>
  </section>
</template>