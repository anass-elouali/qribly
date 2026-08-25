<script setup lang="ts">
import { Banknote, CalendarClock, MapPin, UserRound } from 'lucide-vue-next'

import ServiceRequestProposalPanel from '@/components/profile/ServiceRequestProposalPanel.vue'
import type { ServiceRequest, ServiceRequestProposal } from '@/types/serviceRequest'
import { APP_TIME_ZONE } from '@/utils/dateTime'

defineProps<{
  requests: ServiceRequest[]
}>()

const emit = defineEmits<{
  'proposal-updated': [serviceRequestId: number, proposal: ServiceRequestProposal]
}>()

const dayFormatter = new Intl.DateTimeFormat('fr-MA', {
  weekday: 'short',
  day: 'numeric',
  month: 'short',
  year: 'numeric',
  timeZone: APP_TIME_ZONE,
})

const timeFormatter = new Intl.DateTimeFormat('fr-MA', {
  hour: '2-digit',
  minute: '2-digit',
  hour12: false,
  timeZone: APP_TIME_ZONE,
})

function formatPeriod(request: ServiceRequest): string {
  const start = new Date(request.desired_start_at)
  const end = new Date(request.desired_end_at)
  const sameDay =
    start.toLocaleDateString('fr-MA', { timeZone: APP_TIME_ZONE }) ===
    end.toLocaleDateString('fr-MA', { timeZone: APP_TIME_ZONE })

  if (sameDay) {
    return `${dayFormatter.format(start)} · ${timeFormatter.format(start)}–${timeFormatter.format(end)}`
  }

  return `${dayFormatter.format(start)} ${timeFormatter.format(start)} → ${dayFormatter.format(end)} ${timeFormatter.format(end)}`
}

function formatBudget(value: string | null): string {
  if (value === null) {
    return 'À convenir'
  }

  return `${new Intl.NumberFormat('fr-MA', { maximumFractionDigits: 0 }).format(Number(value))} DH max.`
}

</script>

<template>
  <section>
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <p class="font-mono text-[0.65rem] tracking-[0.2em] text-primary uppercase">Opportunités</p>

        <h2 class="mt-1 font-display text-2xl font-bold text-ink">Demandes compatibles</h2>

        <p class="mt-1 max-w-2xl font-body text-sm leading-6 text-ink/50">
          Ces clients recherchent un service compatible avec l’une de tes annonces actives.
        </p>
      </div>

      <span
        class="w-fit rounded-full border border-primary/15 bg-primary/5 px-3 py-1.5 font-mono text-xs text-primary"
      >
        {{ requests.length }} demande{{ requests.length > 1 ? 's' : '' }}
      </span>
    </div>

    <div
      v-if="requests.length === 0"
      class="rounded-xl border border-dashed border-ink/15 bg-surface px-6 py-16 text-center"
    >
      <p class="font-display text-lg font-bold text-ink">Aucune demande compatible</p>
      <p class="mx-auto mt-2 max-w-lg font-body text-sm leading-6 text-ink/50">
        Les nouvelles demandes apparaîtront ici lorsqu’elles correspondent à la catégorie, la ville,
        au lieu de prestation et au contenu de l’une de tes annonces.
      </p>
    </div>

    <div v-else class="grid gap-4">
      <article
        v-for="request in requests"
        :key="request.id"
        class="rounded-xl border border-ink/10 bg-surface p-5 shadow-sm sm:p-6"
      >
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <span
                class="rounded-full bg-accent/15 px-2.5 py-1 font-mono text-[0.65rem] font-semibold tracking-wide text-ink uppercase"
              >
                Nouvelle demande
              </span>
              <span
                class="rounded-full border border-ink/10 px-2.5 py-1 font-body text-xs text-ink/55"
              >
                {{ request.category.name }}
              </span>
            </div>

            <h3 class="mt-3 font-display text-xl font-bold leading-snug text-ink">
              {{ request.summary }}
            </h3>

            <p
              v-if="request.customer"
              class="mt-2 flex items-center gap-1.5 font-body text-sm text-ink/50"
            >
              <UserRound :size="15" aria-hidden="true" />
              Demande de {{ request.customer.name }}
            </p>
          </div>
        </div>

        <dl class="mt-5 grid gap-3 border-t border-ink/10 pt-5 sm:grid-cols-2 xl:grid-cols-4">
          <div class="rounded-lg bg-ground px-4 py-3">
            <dt
              class="flex items-center gap-2 font-mono text-[0.65rem] tracking-wide text-ink/40 uppercase"
            >
              <MapPin :size="15" aria-hidden="true" /> Ville
            </dt>
            <dd class="mt-1.5 font-body text-sm font-semibold text-ink">{{ request.city }}</dd>
          </div>

          <div class="rounded-lg bg-ground px-4 py-3 sm:col-span-2 xl:col-span-1">
            <dt
              class="flex items-center gap-2 font-mono text-[0.65rem] tracking-wide text-ink/40 uppercase"
            >
              <CalendarClock :size="15" aria-hidden="true" /> Période souhaitée
            </dt>
            <dd class="mt-1.5 font-body text-sm font-semibold leading-5 text-ink">
              {{ formatPeriod(request) }}
            </dd>
          </div>

          <div class="rounded-lg bg-ground px-4 py-3">
            <dt
              class="flex items-center gap-2 font-mono text-[0.65rem] tracking-wide text-ink/40 uppercase"
            >
              <Banknote :size="15" aria-hidden="true" /> Budget
            </dt>
            <dd class="mt-1.5 font-body text-sm font-semibold text-ink">
              {{ formatBudget(request.budget_max) }}
            </dd>
          </div>

          <div class="rounded-lg bg-ground px-4 py-3">
            <dt class="font-mono text-[0.65rem] tracking-wide text-ink/40 uppercase">
              Lieu du service
            </dt>
            <dd class="mt-1.5 font-body text-sm font-semibold text-ink">
              {{ request.at_home ? 'Chez le client' : 'Chez le prestataire' }}
            </dd>
          </div>
        </dl>

        <p class="mt-4 font-body text-xs leading-5 text-ink/45">
          Cette demande correspond à la ville, au lieu de prestation et au contenu d’une de tes
          annonces.
        </p>

        <ServiceRequestProposalPanel
          :request="request"
          @updated="(proposal) => emit('proposal-updated', request.id, proposal)"
        />
      </article>
    </div>
  </section>
</template>
