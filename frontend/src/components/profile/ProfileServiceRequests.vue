<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { Banknote, CalendarClock, MapPin, MessagesSquare } from 'lucide-vue-next'

import type { ServiceRequest } from '@/types/serviceRequest'
import { APP_TIME_ZONE } from '@/utils/dateTime'

defineProps<{
  requests: ServiceRequest[]
}>()

const statusLabels: Record<ServiceRequest['status'], string> = {
  open: 'Ouverte',
  fulfilled: 'Finalisée',
  cancelled: 'Annulée',
}

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

function proposalsLabel(request: ServiceRequest): string {
  const count = request.proposals_count ?? request.proposals?.length ?? 0

  if (count === 0) {
    return 'Aucune proposition pour le moment'
  }

  return `${count} proposition${count > 1 ? 's' : ''} reçue${count > 1 ? 's' : ''}`
}
</script>

<template>
  <section>
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <p class="font-mono text-[0.65rem] tracking-[0.2em] text-primary uppercase">
          Mes demandes
        </p>

        <h2 class="mt-1 font-display text-2xl font-bold text-ink">Demandes de service</h2>

        <p class="mt-1 max-w-2xl font-body text-sm leading-6 text-ink/50">
          Les demandes que tu as publiées via l'assistant, et les propositions reçues.
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
      <p class="font-display text-lg font-bold text-ink">Aucune demande publiée</p>
      <p class="mx-auto mt-2 max-w-lg font-body text-sm leading-6 text-ink/50">
        Utilise l'assistant "Demander à Qrib" pour décrire ton besoin, tes demandes apparaîtront
        ici avec les propositions des prestataires.
      </p>
    </div>

    <div v-else class="grid gap-4">
      <RouterLink
        v-for="request in requests"
        :key="request.id"
        :to="{ name: 'service-request-details', params: { id: request.id } }"
        class="block rounded-xl border border-ink/10 bg-surface p-5 shadow-sm transition hover:border-primary/40 sm:p-6"
      >
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <span
                class="rounded-full px-2.5 py-1 font-mono text-[0.65rem] font-semibold tracking-wide uppercase"
                :class="
                  request.status === 'open'
                    ? 'bg-status-active/15 text-status-active'
                    : request.status === 'cancelled'
                      ? 'bg-status-reserved/15 text-status-reserved'
                      : 'bg-ink/10 text-ink/60'
                "
              >
                {{ statusLabels[request.status] }}
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
          </div>

          <span
            class="inline-flex w-fit shrink-0 items-center gap-1.5 rounded-full border border-ink/10 px-3 py-1.5 font-mono text-xs text-ink/60"
          >
            <MessagesSquare :size="14" aria-hidden="true" />
            {{ proposalsLabel(request) }}
          </span>
        </div>

        <dl class="mt-5 grid gap-3 border-t border-ink/10 pt-5 sm:grid-cols-3">
          <div class="rounded-lg bg-ground px-4 py-3">
            <dt
              class="flex items-center gap-2 font-mono text-[0.65rem] tracking-wide text-ink/40 uppercase"
            >
              <MapPin :size="15" aria-hidden="true" /> Ville
            </dt>
            <dd class="mt-1.5 font-body text-sm font-semibold text-ink">{{ request.city }}</dd>
          </div>

          <div class="rounded-lg bg-ground px-4 py-3">
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
        </dl>
      </RouterLink>
    </div>
  </section>
</template>
