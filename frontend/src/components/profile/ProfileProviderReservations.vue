<script setup lang="ts">
import { computed, ref } from 'vue'

import ProviderReservationCard from '@/components/reservations/ProviderReservationCard.vue'
import type { Reservation } from '@/types/reservation'

const props = defineProps<{
  reservations: Reservation[]
}>()

const emit = defineEmits<{
  confirm: [id: number]
  cancel: [id: number]
  complete: [id: number]
  view: [reservation: Reservation]
}>()

type ProviderFilter =
  | 'all'
  | 'pending'
  | 'confirmed'
  | 'completed'
  | 'cancelled'

const providerFilter = ref<ProviderFilter>('all')

const providerFilters: {
  key: ProviderFilter
  label: string
}[] = [
  { key: 'all', label: 'Toutes' },
  { key: 'pending', label: 'En attente' },
  { key: 'confirmed', label: 'Confirmées' },
  { key: 'completed', label: 'Terminées' },
  { key: 'cancelled', label: 'Annulées' },
]

const providerStats = computed(() => ({
  total: props.reservations.length,

  pending: props.reservations.filter(
    (reservation) => reservation.status === 'pending',
  ).length,

  confirmed: props.reservations.filter(
    (reservation) => reservation.status === 'confirmed',
  ).length,

  completed: props.reservations.filter(
    (reservation) => reservation.status === 'completed',
  ).length,

  cancelled: props.reservations.filter(
    (reservation) => reservation.status === 'cancelled',
  ).length,
}))

const filteredReservations = computed(() => {
  if (providerFilter.value === 'all') {
    return props.reservations
  }

  return props.reservations.filter(
    (reservation) => reservation.status === providerFilter.value,
  )
})
</script>

<template>
  <section>
    <!-- Header -->
    <div
      class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
    >
      <div>
        <p
          class="font-mono text-[0.65rem] tracking-[0.2em] text-primary uppercase"
        >
          Espace fournisseur
        </p>

        <h2 class="mt-1 font-display text-2xl font-bold text-ink">
          Réservations reçues
        </h2>

        <p class="mt-1 max-w-xl font-body text-sm text-ink/50">
          Gérez les réservations de vos services et suivez vos rendez-vous.
        </p>
      </div>
    </div>

    <!-- Statistics -->
    <div class="mb-7 grid grid-cols-2 gap-3 lg:grid-cols-4">
      <div class="rounded-lg border border-ink/10 bg-surface p-4">
        <p
          class="font-mono text-[0.65rem] tracking-wide text-ink/40 uppercase"
        >
          Total
        </p>

        <p class="mt-2 font-display text-2xl font-bold text-ink">
          {{ providerStats.total }}
        </p>
      </div>

      <div class="rounded-lg border border-ink/10 bg-surface p-4">
        <p
          class="font-mono text-[0.65rem] tracking-wide text-ink/40 uppercase"
        >
          En attente
        </p>

        <p class="mt-2 font-display text-2xl font-bold text-ink">
          {{ providerStats.pending }}
        </p>
      </div>

      <div class="rounded-lg border border-ink/10 bg-surface p-4">
        <p
          class="font-mono text-[0.65rem] tracking-wide text-ink/40 uppercase"
        >
          Confirmées
        </p>

        <p class="mt-2 font-display text-2xl font-bold text-ink">
          {{ providerStats.confirmed }}
        </p>
      </div>

      <div class="rounded-lg border border-ink/10 bg-surface p-4">
        <p
          class="font-mono text-[0.65rem] tracking-wide text-ink/40 uppercase"
        >
          Terminées
        </p>

        <p class="mt-2 font-display text-2xl font-bold text-ink">
          {{ providerStats.completed }}
        </p>
      </div>
    </div>

    <!-- Filters -->
    <div class="mb-6 flex flex-wrap gap-2">
      <button
        v-for="filter in providerFilters"
        :key="filter.key"
        type="button"
        class="rounded-full border px-3 py-1.5 font-mono text-xs transition"
        :class="
          providerFilter === filter.key
            ? 'border-primary bg-primary text-surface'
            : 'border-ink/10 text-ink/55 hover:border-ink/20 hover:text-ink'
        "
        @click="providerFilter = filter.key"
      >
        {{ filter.label }}
      </button>
    </div>

    <!-- Reservations -->
    <div class="flex flex-col gap-4">
      <div
        v-if="filteredReservations.length === 0"
        class="rounded-xl border border-dashed border-ink/15 px-6 py-16 text-center"
      >
        <p class="font-display text-lg font-bold text-ink">
          Aucune réservation
        </p>

        <p class="mx-auto mt-2 max-w-md font-body text-sm text-ink/50">
          Aucune réservation ne correspond à ce filtre.
        </p>
      </div>

      <ProviderReservationCard
        v-for="reservation in filteredReservations"
        :key="reservation.id"
        :reservation="reservation"
        @view="emit('view', $event)"
        @confirm="emit('confirm', $event)"
        @cancel="emit('cancel', $event)"
        @complete="emit('complete', $event)"
      />
    </div>
  </section>
</template>