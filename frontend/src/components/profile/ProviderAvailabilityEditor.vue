<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { CalendarClock, Check, Pencil, RotateCcw } from 'lucide-vue-next'

import api from '@/services/api'
import type { ProviderAvailabilityResponse } from '@/types/reservation'
import { extractErrorMessage } from '@/utils/errors'

interface EditableDay {
  day_of_week: number
  label: string
  shortLabel: string
  enabled: boolean
  start_time: string
  end_time: string
}

const dayDefinitions = [
  { day_of_week: 1, label: 'Lundi', shortLabel: 'Lun' },
  { day_of_week: 2, label: 'Mardi', shortLabel: 'Mar' },
  { day_of_week: 3, label: 'Mercredi', shortLabel: 'Mer' },
  { day_of_week: 4, label: 'Jeudi', shortLabel: 'Jeu' },
  { day_of_week: 5, label: 'Vendredi', shortLabel: 'Ven' },
  { day_of_week: 6, label: 'Samedi', shortLabel: 'Sam' },
  { day_of_week: 0, label: 'Dimanche', shortLabel: 'Dim' },
]

function defaultDays(): EditableDay[] {
  return dayDefinitions.map((day) => ({
    ...day,
    enabled: day.day_of_week >= 1 && day.day_of_week <= 5,
    start_time: '09:00',
    end_time: '18:00',
  }))
}

const days = ref<EditableDay[]>(defaultDays())
const loading = ref(true)
const saving = ref(false)
const configured = ref(false)
const editing = ref(false)
const error = ref('')
const success = ref('')
const savedAvailability = ref<ProviderAvailabilityResponse | null>(null)

const enabledDays = computed(() => days.value.filter((day) => day.enabled))
const canSave = computed(
  () =>
    enabledDays.value.length > 0 &&
    enabledDays.value.every((day) => day.start_time && day.end_time > day.start_time),
)

const scheduleSummary = computed(() => {
  if (!configured.value) {
    return 'Aucun horaire publié'
  }

  if (enabledDays.value.length === 1) {
    const day = enabledDays.value[0]
    return `${day?.label} · ${day?.start_time}–${day?.end_time}`
  }

  return `${enabledDays.value.length} jours disponibles · heure du Maroc`
})

function applyResponse(response: ProviderAvailabilityResponse) {
  savedAvailability.value = {
    ...response,
    days: response.days.map((day) => ({ ...day })),
  }
  configured.value = response.configured

  if (!response.configured) {
    days.value = defaultDays()
    editing.value = true
    return
  }

  const savedDays = new Map(response.days.map((day) => [day.day_of_week, day]))
  days.value = dayDefinitions.map((day) => {
    const saved = savedDays.get(day.day_of_week)

    return {
      ...day,
      enabled: Boolean(saved),
      start_time: saved?.start_time.slice(0, 5) ?? '09:00',
      end_time: saved?.end_time.slice(0, 5) ?? '18:00',
    }
  })
}

async function loadAvailability() {
  loading.value = true
  error.value = ''

  try {
    const response = await api.get<ProviderAvailabilityResponse>('/provider/availability')
    applyResponse(response.data)
  } catch (exception) {
    error.value = extractErrorMessage(exception, 'Impossible de charger tes disponibilités.')
  } finally {
    loading.value = false
  }
}

async function saveAvailability() {
  if (!canSave.value || saving.value) {
    return
  }

  saving.value = true
  error.value = ''
  success.value = ''

  try {
    const response = await api.put<ProviderAvailabilityResponse>('/provider/availability', {
      days: days.value.map((day) => ({
        day_of_week: day.day_of_week,
        enabled: day.enabled,
        start_time: day.enabled ? day.start_time : null,
        end_time: day.enabled ? day.end_time : null,
      })),
    })

    applyResponse(response.data)
    editing.value = false
    success.value = 'Tes horaires sont publiés. Les clients verront uniquement les créneaux libres.'
  } catch (exception) {
    error.value = extractErrorMessage(exception, "Impossible d'enregistrer tes disponibilités.")
  } finally {
    saving.value = false
  }
}

function startEditing() {
  success.value = ''
  error.value = ''
  editing.value = true
}

function cancelEditing() {
  if (savedAvailability.value) {
    applyResponse(savedAvailability.value)
  }

  error.value = ''
  editing.value = false
}

onMounted(loadAvailability)
</script>

<template>
  <section class="mb-7 overflow-hidden rounded-xl border border-ink/10 bg-surface">
    <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex items-start gap-3">
        <span
          class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
        >
          <CalendarClock :size="20" aria-hidden="true" />
        </span>

        <div>
          <p class="font-mono text-[0.65rem] tracking-[0.16em] text-primary uppercase">
            Planning hebdomadaire
          </p>
          <h3 class="mt-0.5 font-display text-lg font-bold text-ink">Mes disponibilités</h3>
          <p class="mt-1 font-body text-sm text-ink/50">
            {{ loading ? 'Chargement des horaires…' : scheduleSummary }}
          </p>
        </div>
      </div>

      <button
        v-if="!loading && configured && !editing"
        type="button"
        class="inline-flex items-center justify-center gap-2 rounded-lg border border-primary px-4 py-2 font-mono text-xs text-primary transition hover:bg-primary/5"
        @click="startEditing"
      >
        <Pencil :size="15" aria-hidden="true" />
        Modifier les horaires
      </button>
    </div>

    <div v-if="error && !editing" class="border-t border-ink/10 px-5 py-4">
      <p class="font-body text-sm text-status-reserved" role="alert">{{ error }}</p>
      <button
        type="button"
        class="mt-2 inline-flex items-center gap-2 font-mono text-xs text-primary"
        @click="loadAvailability"
      >
        <RotateCcw :size="14" aria-hidden="true" />
        Réessayer
      </button>
    </div>

    <p
      v-if="success && !editing"
      class="flex items-start gap-2 border-t border-status-active/15 bg-status-active/5 px-5 py-3 font-body text-sm text-status-active"
      role="status"
      aria-live="polite"
    >
      <Check :size="17" class="mt-0.5 shrink-0" aria-hidden="true" />
      {{ success }}
    </p>

    <div v-if="editing" class="border-t border-ink/10 px-4 py-5 sm:px-5">
      <p v-if="!configured" class="mb-4 font-body text-sm leading-6 text-ink/60">
        Publie au moins un jour disponible. Les horaires sont interprétés selon l’heure du Maroc.
      </p>

      <div class="space-y-2">
        <div
          v-for="day in days"
          :key="day.day_of_week"
          class="flex flex-wrap items-center gap-3 rounded-lg border px-3 py-3 transition"
          :class="day.enabled ? 'border-primary/20 bg-primary/[0.03]' : 'border-ink/10 bg-ground'"
        >
          <label class="flex min-w-28 flex-1 cursor-pointer items-center gap-3">
            <input v-model="day.enabled" type="checkbox" class="h-4 w-4 accent-primary" />
            <span class="font-body text-sm font-semibold text-ink">{{ day.label }}</span>
          </label>

          <div class="ml-auto flex items-center gap-2">
            <input
              v-model="day.start_time"
              type="time"
              :disabled="!day.enabled"
              :aria-label="`Début ${day.label}`"
              class="w-28 rounded-md border border-ink/15 bg-surface px-2.5 py-2 font-mono text-xs text-ink disabled:opacity-35"
            />
            <span class="font-body text-xs text-ink/40">à</span>
            <input
              v-model="day.end_time"
              type="time"
              :disabled="!day.enabled"
              :aria-label="`Fin ${day.label}`"
              class="w-28 rounded-md border border-ink/15 bg-surface px-2.5 py-2 font-mono text-xs text-ink disabled:opacity-35"
            />
          </div>
        </div>
      </div>

      <p v-if="!canSave" class="mt-3 font-body text-xs text-status-reserved" role="alert">
        Active au moins un jour et vérifie que chaque heure de fin est après l’heure de début.
      </p>

      <p v-if="error" class="mt-3 font-body text-sm text-status-reserved" role="alert">
        {{ error }}
      </p>

      <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <button
          v-if="configured"
          type="button"
          :disabled="saving"
          class="rounded-lg border border-ink/15 px-4 py-2.5 font-mono text-xs text-ink/60 disabled:opacity-50"
          @click="cancelEditing"
        >
          Annuler
        </button>

        <button
          type="button"
          :disabled="!canSave || saving"
          class="rounded-lg bg-primary px-4 py-2.5 font-mono text-xs font-semibold text-surface transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-45"
          @click="saveAvailability"
        >
          {{ saving ? 'Enregistrement…' : 'Publier mes horaires' }}
        </button>
      </div>
    </div>
  </section>
</template>
