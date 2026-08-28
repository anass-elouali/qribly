<script setup lang="ts">
import dayjs from 'dayjs'
import {
  ArrowLeft,
  ArrowRight,
  Banknote,
  CalendarClock,
  Check,
  CircleCheckBig,
  Clock3,
  House,
  LoaderCircle,
  MapPin,
  RotateCcw,
  ShieldCheck,
  Sparkles,
  Tag,
  UsersRound,
  WandSparkles,
} from 'lucide-vue-next'
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'

import CityCombobox from '@/components/ui/CityCombobox.vue'
import { moroccanCities, type CityOption } from '@/data/moroccanCities'
import { fetchCategories } from '@/services/categories'
import { interpretServiceRequest, publishServiceRequest } from '@/services/serviceRequests'
import type { Category } from '@/types/offer'
import type {
  ServiceRequest,
  ServiceRequestInterpretation,
  ServiceRequestMissingField,
} from '@/types/serviceRequest'
import {
  apiDateTimeToLocalInput,
  localInputToApiDateTime,
  parseAppLocalDateTime,
} from '@/utils/dateTime'
import { extractErrorMessage } from '@/utils/errors'

const totalSteps = 4
const step = ref(1)
const rawText = ref('')
const route = useRoute()
const summary = ref('')
const categories = ref<Category[]>([])
const categoryId = ref<number | null>(null)
const categoryName = ref('')
const city = ref<CityOption | null>(null)
const desiredStartAt = ref('')
const desiredEndAt = ref('')
const budgetMax = ref<string | number>('')
const atHome = ref<boolean | null>(null)
const missingFields = ref<ServiceRequestMissingField[]>([])
const manualMode = ref(false)
const analyzing = ref(false)
const publishing = ref(false)
const error = ref('')
const notice = ref('')
const publishedRequest = ref<ServiceRequest | null>(null)

const examples = [
  'Mon robinet fuit à Marrakech demain. Budget maximum 300 DH, à domicile.',
  'Je cherche un professeur de maths à Rabat cette semaine pour mon fils.',
  'J’ai besoin d’un grand ménage à Casablanca le 29/08/2026.',
]

const requiredFields: ServiceRequestMissingField[] = [
  'category_id',
  'city',
  'desired_period',
  'at_home',
]

const stepLabels = ['Besoin', 'Précisions', 'Validation', 'Publiée']
const progress = computed(() => `${(step.value / totalSteps) * 100}%`)
const visibleMissingFields = computed(() => missingFields.value.slice(0, 2))
const detailFields = computed(() =>
  manualMode.value ? requiredFields : visibleMissingFields.value,
)
const selectedCategory = computed(() =>
  categories.value.find((category) => category.id === categoryId.value),
)
const periodLabel = computed(() => {
  if (!desiredStartAt.value || !desiredEndAt.value) return 'À préciser'

  const start = dayjs(desiredStartAt.value)
  const end = dayjs(desiredEndAt.value)

  if (start.isSame(end, 'day')) {
    return `${start.format('DD/MM/YYYY · HH:mm')}–${end.format('HH:mm')}`
  }

  return `${start.format('DD/MM/YYYY · HH:mm')} → ${end.format('DD/MM/YYYY · HH:mm')}`
})

function chooseExample(example: string) {
  rawText.value = example
  error.value = ''
}

function questionFor(field: ServiceRequestMissingField): string {
  const questions: Record<ServiceRequestMissingField, string> = {
    category_id: 'Quel type de service recherches-tu ?',
    city: 'Dans quelle ville as-tu besoin de ce service ?',
    desired_period: 'Pour quelle date ou période souhaites-tu ce service ?',
    at_home: 'Le prestataire doit-il se déplacer à domicile ?',
  }

  return questions[field]
}

function isFieldComplete(field: ServiceRequestMissingField): boolean {
  if (field === 'category_id') return categoryId.value !== null
  if (field === 'city') return city.value !== null
  if (field === 'desired_period') {
    return Boolean(desiredStartAt.value && desiredEndAt.value)
  }

  return atHome.value !== null
}

function resetDetails() {
  summary.value = ''
  categoryId.value = null
  categoryName.value = ''
  city.value = null
  desiredStartAt.value = ''
  desiredEndAt.value = ''
  budgetMax.value = ''
  atHome.value = null
  missingFields.value = []
}

function applyInterpretation(data: ServiceRequestInterpretation) {
  summary.value = data.summary

  if (data.category_id !== null) {
    categoryId.value = data.category_id
    categoryName.value = data.category_name ?? ''
  }

  if (data.city) {
    city.value = moroccanCities.find((item) => item.name === data.city) ?? null
  }

  if (data.desired_start_at && data.desired_end_at) {
    desiredStartAt.value = apiDateTimeToLocalInput(data.desired_start_at)
    desiredEndAt.value = apiDateTimeToLocalInput(data.desired_end_at)
  }

  if (data.budget_max !== null) {
    budgetMax.value = String(data.budget_max)
  }

  if (data.at_home !== null) {
    atHome.value = data.at_home
  }

  missingFields.value = data.missing_fields.filter((field) => !isFieldComplete(field))
}

function startManualMode(message: string) {
  manualMode.value = true
  missingFields.value = [...requiredFields]
  error.value = ''
  notice.value = message
  summary.value ||= rawText.value.trim()
  step.value = 2
}

async function analyze(text = rawText.value.trim(), reset = false) {
  if (text.length < 10) {
    error.value = 'Décris ton besoin avec un peu plus de précision.'
    return
  }

  analyzing.value = true
  error.value = ''
  notice.value = ''

  if (reset) {
    resetDetails()
  }

  try {
    const response = await interpretServiceRequest(text)
    applyInterpretation(response.data)

    if (missingFields.value.length > 0) {
      step.value = 2
    } else {
      step.value = 3
    }
  } catch (err) {
    startManualMode(
      extractErrorMessage(
        err,
        'L’assistant est indisponible. Tu peux compléter ta demande manuellement sans perdre ton texte.',
      ),
    )
  } finally {
    analyzing.value = false
  }
}

async function analyzeInitialRequest() {
  manualMode.value = false
  await analyze(rawText.value.trim(), true)
}

function validateField(field: ServiceRequestMissingField): string | null {
  if (field === 'category_id' && categoryId.value === null) {
    return 'Choisis une catégorie.'
  }
  if (field === 'city' && city.value === null) {
    return 'Choisis une ville.'
  }
  if (field === 'desired_period') {
    if (!desiredStartAt.value || !desiredEndAt.value) {
      return 'Indique le début et la fin de la période souhaitée.'
    }

    const start = parseAppLocalDateTime(desiredStartAt.value)
    const end = parseAppLocalDateTime(desiredEndAt.value)

    if (!start.isAfter(dayjs())) return 'La période doit commencer dans le futur.'
    if (!end.isAfter(start)) return 'La fin doit être postérieure au début.'
    if (end.isAfter(dayjs().add(31, 'day'))) {
      return 'La période doit se situer dans les 31 prochains jours.'
    }
  }
  if (field === 'at_home' && atHome.value === null) {
    return 'Précise où le service doit être réalisé.'
  }

  return null
}

function continueFromDetails() {
  const fieldsToValidate = manualMode.value ? requiredFields : visibleMissingFields.value

  for (const field of fieldsToValidate) {
    const validationError = validateField(field)
    if (validationError) {
      error.value = validationError
      return
    }
  }

  error.value = ''

  if (manualMode.value) {
    categoryName.value = selectedCategory.value?.name ?? categoryName.value
    missingFields.value = []
    step.value = 3
    return
  }

  const answeredFields = [...visibleMissingFields.value]
  missingFields.value = missingFields.value.filter((field) => !answeredFields.includes(field))

  if (missingFields.value.length === 0) {
    step.value = 3
  }
}

function editDetails() {
  manualMode.value = true
  missingFields.value = [...requiredFields]
  error.value = ''
  notice.value = ''
  step.value = 2
}

function validateBeforePublishing(): string | null {
  for (const field of requiredFields) {
    const validationError = validateField(field)
    if (validationError) return validationError
  }

  if (summary.value.trim().length < 10) {
    return 'Le résumé doit contenir au moins 10 caractères.'
  }

  const budget = normalizedBudget()
  if (budget && (Number.isNaN(Number(budget)) || Number(budget) < 0)) {
    return 'Le budget doit être un montant positif.'
  }

  return null
}

function normalizedBudget(): string {
  return String(budgetMax.value ?? '').trim()
}

function backToNeed() {
  step.value = 1
  error.value = ''
  notice.value = ''
}

async function publish() {
  const validationError = validateBeforePublishing()
  if (validationError) {
    error.value = validationError
    return
  }

  publishing.value = true
  error.value = ''
  const budget = normalizedBudget()

  try {
    publishedRequest.value = await publishServiceRequest({
      raw_text: rawText.value.trim(),
      summary: summary.value.trim(),
      category_id: categoryId.value!,
      city: city.value!.name,
      desired_start_at: localInputToApiDateTime(desiredStartAt.value),
      desired_end_at: localInputToApiDateTime(desiredEndAt.value),
      budget_max: budget ? Number(budget) : null,
      at_home: atHome.value!,
    })
    step.value = 4
  } catch (err) {
    error.value = extractErrorMessage(err, 'Impossible de publier cette demande pour le moment.')
  } finally {
    publishing.value = false
  }
}

function restart() {
  rawText.value = ''
  manualMode.value = false
  publishedRequest.value = null
  error.value = ''
  notice.value = ''
  resetDetails()
  step.value = 1
}

onMounted(async () => {
  const initialRequest = typeof route.query.q === 'string' ? route.query.q.trim() : ''
  if (initialRequest) rawText.value = initialRequest

  try {
    categories.value = await fetchCategories()
  } catch (err) {
    notice.value = extractErrorMessage(
      err,
      'Les catégories ne sont pas disponibles. Tu peux réessayer dans un instant.',
    )
  }
})
</script>

<template>
  <section class="mx-auto w-full max-w-5xl px-4 py-8 sm:px-6 sm:py-12">
    <header class="mb-6">
      <div
        class="mb-2 flex items-center gap-2 font-mono text-xs tracking-[0.14em] text-primary uppercase"
      >
        <span
          class="flex h-7 w-7 items-center justify-center rounded-[0.6rem_0.6rem_0.6rem_0.18rem] bg-primary text-accent"
        >
          <Sparkles :size="14" aria-hidden="true" />
        </span>
        Assistant de demande
      </div>
      <h1 class="font-display text-3xl font-semibold text-primary sm:text-4xl">
        Explique ton besoin, Qrib structure la demande
      </h1>
      <p class="mt-2 max-w-2xl text-sm leading-6 text-ink/60 sm:text-base">
        Plus besoin de parcourir toutes les annonces. Décris ta situation et reçois ensuite des
        propositions de prestataires compatibles.
      </p>
    </header>

    <div class="mb-5" aria-label="Progression de la demande">
      <div class="mb-2 grid grid-cols-4 gap-1">
        <span
          v-for="(label, index) in stepLabels"
          :key="label"
          class="text-center font-mono text-[0.62rem] tracking-wide uppercase sm:text-xs"
          :class="index + 1 === step ? 'font-semibold text-primary' : 'text-ink/40'"
        >
          {{ label }}
        </span>
      </div>
      <div
        class="h-1.5 overflow-hidden rounded-full bg-ink/10"
        role="progressbar"
        :aria-valuenow="step"
        aria-valuemin="1"
        :aria-valuemax="totalSteps"
      >
        <div
          class="h-full rounded-full bg-primary transition-all duration-300"
          :style="{ width: progress }"
        ></div>
      </div>
    </div>

    <main
      class="overflow-visible rounded-2xl border border-ink/10 bg-surface shadow-[0_18px_50px_rgba(20,73,90,0.08)]"
    >
      <div class="p-5 sm:p-8">
        <div
          v-if="notice"
          class="mb-5 flex items-start gap-3 rounded-xl border border-accent/30 bg-accent/10 px-4 py-3 text-sm leading-5 text-ink/70"
          role="status"
        >
          <WandSparkles :size="18" class="mt-0.5 shrink-0 text-primary" aria-hidden="true" />
          <span>{{ notice }}</span>
        </div>

        <div
          v-if="error"
          class="mb-5 rounded-xl border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800"
          role="alert"
        >
          {{ error }}
        </div>

        <section v-if="step === 1" aria-labelledby="need-title">
          <p class="font-mono text-xs tracking-[0.12em] text-primary uppercase">Étape 1 sur 4</p>
          <h2
            id="need-title"
            class="mt-2 font-display text-2xl font-semibold text-primary sm:text-3xl"
          >
            De quoi as-tu besoin ?
          </h2>
          <p class="mt-2 max-w-2xl text-sm leading-6 text-ink/60">
            Écris naturellement : le service recherché, la ville, le moment souhaité et ton budget
            si tu en as un.
          </p>

          <label for="service-need" class="mt-6 block text-sm font-semibold text-ink">
            Décris ton besoin
          </label>
          <textarea
            id="service-need"
            v-model="rawText"
            rows="5"
            maxlength="2000"
            placeholder="Ex. Mon robinet fuit. Je cherche un plombier à Marrakech demain après-midi, avec un budget maximum de 300 DH."
            class="mt-2 block w-full resize-y rounded-xl border border-ink/15 bg-ground px-4 py-3.5 text-sm leading-6 text-ink outline-none transition placeholder:text-ink/35 focus:border-primary focus:ring-3 focus:ring-primary/10"
            @input="error = ''"
          ></textarea>
          <div
            class="mt-2 flex items-center justify-between gap-3 font-mono text-[0.65rem] text-ink/40"
          >
            <span>Une phrase suffit pour commencer</span>
            <span>{{ rawText.length }}/2 000</span>
          </div>

          <div class="mt-5">
            <p class="mb-2 text-xs font-semibold text-ink/55">Exemples</p>
            <div class="flex flex-wrap gap-2">
              <button
                v-for="example in examples"
                :key="example"
                type="button"
                class="rounded-full border border-ink/15 px-3 py-2 text-left text-xs text-ink/65 transition hover:border-primary hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                @click="chooseExample(example)"
              >
                {{ example }}
              </button>
            </div>
          </div>

          <div
            class="mt-7 flex flex-col-reverse items-stretch justify-between gap-3 sm:flex-row sm:items-center"
          >
            <p class="flex items-center gap-2 text-xs text-ink/50">
              <ShieldCheck :size="17" class="text-status-active" aria-hidden="true" />
              Rien ne sera publié sans ta confirmation.
            </p>
            <button
              type="button"
              class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-accent px-5 py-2.5 text-sm font-semibold text-ink transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="analyzing"
              @click="analyzeInitialRequest"
            >
              <LoaderCircle v-if="analyzing" :size="18" class="animate-spin" aria-hidden="true" />
              <Sparkles v-else :size="18" aria-hidden="true" />
              {{ analyzing ? 'Analyse en cours…' : 'Analyser ma demande' }}
            </button>
          </div>
        </section>

        <section v-else-if="step === 2" aria-labelledby="details-title">
          <p class="font-mono text-xs tracking-[0.12em] text-primary uppercase">
            {{ manualMode ? 'Complétion manuelle' : 'Analyse terminée' }}
          </p>
          <h2
            id="details-title"
            class="mt-2 font-display text-2xl font-semibold text-primary sm:text-3xl"
          >
            {{
              manualMode
                ? 'Précise les détails de ta demande'
                : 'Il me manque juste quelques précisions'
            }}
          </h2>
          <p class="mt-2 max-w-2xl text-sm leading-6 text-ink/60">
            {{
              manualMode
                ? 'Ces informations permettent de contacter uniquement les prestataires compatibles.'
                : 'Qrib pose au maximum deux questions à la fois pour garder le parcours rapide.'
            }}
          </p>

          <div class="mt-6 space-y-5">
            <article
              v-for="field in detailFields"
              :key="field"
              class="rounded-xl border border-ink/10 bg-ground/70 p-4 sm:p-5"
            >
              <h3 class="font-display text-lg font-semibold text-ink">{{ questionFor(field) }}</h3>

              <div v-if="field === 'category_id'" class="mt-3">
                <label for="request-category" class="sr-only">Catégorie du service</label>
                <select
                  id="request-category"
                  v-model="categoryId"
                  class="h-11 w-full rounded-lg border border-ink/15 bg-surface px-3 text-sm text-ink outline-none focus:border-primary focus:ring-2 focus:ring-primary/10"
                >
                  <option :value="null" disabled>Choisir une catégorie</option>
                  <option v-for="category in categories" :key="category.id" :value="category.id">
                    {{ category.name }}
                  </option>
                </select>
              </div>

              <div v-else-if="field === 'city'" class="mt-3">
                <CityCombobox
                  v-model="city"
                  id="request-city"
                  label="Ville de la demande"
                  placeholder="Rechercher une ville…"
                />
              </div>

              <div v-else-if="field === 'desired_period'" class="mt-3 grid gap-3 sm:grid-cols-2">
                <label class="text-xs font-semibold text-ink/65">
                  Début souhaité
                  <input
                    v-model="desiredStartAt"
                    type="datetime-local"
                    class="mt-1.5 h-11 w-full rounded-lg border border-ink/15 bg-surface px-3 text-sm font-normal text-ink outline-none focus:border-primary focus:ring-2 focus:ring-primary/10"
                  />
                </label>
                <label class="text-xs font-semibold text-ink/65">
                  Fin de disponibilité
                  <input
                    v-model="desiredEndAt"
                    type="datetime-local"
                    class="mt-1.5 h-11 w-full rounded-lg border border-ink/15 bg-surface px-3 text-sm font-normal text-ink outline-none focus:border-primary focus:ring-2 focus:ring-primary/10"
                  />
                </label>
              </div>

              <div v-else class="mt-3 grid gap-2 sm:grid-cols-2">
                <button
                  type="button"
                  class="flex min-h-11 items-center justify-center gap-2 rounded-lg border px-4 text-sm font-semibold transition"
                  :class="
                    atHome === true
                      ? 'border-primary bg-primary text-surface'
                      : 'border-ink/15 bg-surface text-ink hover:border-primary'
                  "
                  :aria-pressed="atHome === true"
                  @click="atHome = true"
                >
                  <House :size="17" aria-hidden="true" /> À mon domicile
                </button>
                <button
                  type="button"
                  class="flex min-h-11 items-center justify-center gap-2 rounded-lg border px-4 text-sm font-semibold transition"
                  :class="
                    atHome === false
                      ? 'border-primary bg-primary text-surface'
                      : 'border-ink/15 bg-surface text-ink hover:border-primary'
                  "
                  :aria-pressed="atHome === false"
                  @click="atHome = false"
                >
                  <MapPin :size="17" aria-hidden="true" /> Je me déplace
                </button>
              </div>
            </article>
          </div>

          <div v-if="manualMode" class="mt-5 rounded-xl border border-ink/10 p-4 sm:p-5">
            <label
              for="request-budget"
              class="flex items-center gap-2 text-sm font-semibold text-ink"
            >
              <Banknote :size="17" class="text-primary" aria-hidden="true" />
              Budget maximum <span class="font-normal text-ink/40">(facultatif)</span>
            </label>
            <div class="relative mt-2 max-w-xs">
              <input
                id="request-budget"
                v-model="budgetMax"
                type="number"
                min="0"
                step="1"
                placeholder="Ex. 300"
                class="h-11 w-full rounded-lg border border-ink/15 bg-surface px-3 pr-12 text-sm text-ink outline-none focus:border-primary focus:ring-2 focus:ring-primary/10"
              />
              <span class="absolute top-1/2 right-3 -translate-y-1/2 font-mono text-xs text-ink/45"
                >DH</span
              >
            </div>
          </div>

          <div class="mt-7 flex flex-col-reverse justify-between gap-3 sm:flex-row">
            <button
              type="button"
              class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-ink/15 px-4 text-sm font-semibold text-ink transition hover:border-primary"
              @click="backToNeed"
            >
              <ArrowLeft :size="17" aria-hidden="true" /> Modifier mon texte
            </button>
            <button
              type="button"
              class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-accent px-5 text-sm font-semibold text-ink transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="analyzing"
              @click="continueFromDetails"
            >
              <LoaderCircle v-if="analyzing" :size="18" class="animate-spin" aria-hidden="true" />
              {{ analyzing ? 'Analyse en cours…' : 'Continuer' }}
              <ArrowRight v-if="!analyzing" :size="17" aria-hidden="true" />
            </button>
          </div>
        </section>

        <section v-else-if="step === 3" aria-labelledby="review-title">
          <p class="font-mono text-xs tracking-[0.12em] text-primary uppercase">À confirmer</p>
          <h2
            id="review-title"
            class="mt-2 font-display text-2xl font-semibold text-primary sm:text-3xl"
          >
            Ta demande est prête
          </h2>
          <p class="mt-2 text-sm leading-6 text-ink/60">
            Vérifie le résumé et les informations. La publication préviendra les prestataires
            compatibles.
          </p>

          <div class="mt-6 rounded-r-xl border-l-4 border-accent bg-ground px-5 py-4">
            <label
              for="request-summary"
              class="font-mono text-[0.65rem] tracking-wide text-ink/45 uppercase"
            >
              Résumé modifiable
            </label>
            <textarea
              id="request-summary"
              v-model="summary"
              rows="3"
              maxlength="1000"
              class="mt-2 block w-full resize-y border-0 bg-transparent p-0 text-base leading-6 text-ink outline-none"
            ></textarea>
          </div>

          <dl class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-xl bg-ground p-4">
              <dt
                class="flex items-center gap-2 font-mono text-[0.65rem] tracking-wide text-ink/45 uppercase"
              >
                <Tag :size="14" /> Service
              </dt>
              <dd class="mt-2 text-sm font-semibold">
                {{ selectedCategory?.name ?? categoryName }}
              </dd>
            </div>
            <div class="rounded-xl bg-ground p-4">
              <dt
                class="flex items-center gap-2 font-mono text-[0.65rem] tracking-wide text-ink/45 uppercase"
              >
                <MapPin :size="14" /> Ville
              </dt>
              <dd class="mt-2 text-sm font-semibold">{{ city?.name }}</dd>
            </div>
            <div class="rounded-xl bg-ground p-4 sm:col-span-2 lg:col-span-1">
              <dt
                class="flex items-center gap-2 font-mono text-[0.65rem] tracking-wide text-ink/45 uppercase"
              >
                <CalendarClock :size="14" /> Disponibilité
              </dt>
              <dd class="mt-2 text-sm font-semibold">{{ periodLabel }}</dd>
            </div>
            <div class="rounded-xl bg-ground p-4">
              <dt
                class="flex items-center gap-2 font-mono text-[0.65rem] tracking-wide text-ink/45 uppercase"
              >
                <Banknote :size="14" /> Budget maximum
              </dt>
              <dd class="mt-2 text-sm font-semibold">
                {{ budgetMax ? `${budgetMax} DH` : 'Non indiqué' }}
              </dd>
            </div>
            <div class="rounded-xl bg-ground p-4">
              <dt
                class="flex items-center gap-2 font-mono text-[0.65rem] tracking-wide text-ink/45 uppercase"
              >
                <House :size="14" /> Déplacement
              </dt>
              <dd class="mt-2 text-sm font-semibold">
                {{ atHome ? 'À mon domicile' : 'Je me déplace' }}
              </dd>
            </div>
            <div class="rounded-xl bg-ground p-4">
              <dt
                class="flex items-center gap-2 font-mono text-[0.65rem] tracking-wide text-ink/45 uppercase"
              >
                <Clock3 :size="14" /> Réponse
              </dt>
              <dd class="mt-2 text-sm font-semibold">Propositions des prestataires</dd>
            </div>
          </dl>

          <div class="mt-7 flex flex-col-reverse justify-between gap-3 sm:flex-row">
            <button
              type="button"
              class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-ink/15 px-4 text-sm font-semibold text-ink transition hover:border-primary"
              @click="editDetails"
            >
              <ArrowLeft :size="17" aria-hidden="true" /> Modifier les détails
            </button>
            <button
              type="button"
              class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-accent px-5 text-sm font-semibold text-ink transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="publishing"
              @click="publish"
            >
              <LoaderCircle v-if="publishing" :size="18" class="animate-spin" aria-hidden="true" />
              <UsersRound v-else :size="18" aria-hidden="true" />
              {{ publishing ? 'Publication…' : 'Publier ma demande' }}
            </button>
          </div>
        </section>

        <section v-else class="py-3 text-center" aria-labelledby="success-title">
          <span
            class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-status-active/10 text-status-active"
          >
            <CircleCheckBig :size="34" aria-hidden="true" />
          </span>
          <p class="mt-5 font-mono text-xs tracking-[0.12em] text-status-active uppercase">
            Demande publiée
          </p>
          <h2
            id="success-title"
            class="mt-2 font-display text-2xl font-semibold text-primary sm:text-3xl"
          >
            Les prestataires compatibles sont prévenus
          </h2>
          <p class="mx-auto mt-3 max-w-xl text-sm leading-6 text-ink/60">
            Ta demande n°{{ publishedRequest?.id }} est ouverte. Tu pourras comparer les prix,
            horaires et messages avant d’accepter une proposition.
          </p>

          <div
            class="mx-auto mt-6 max-w-lg rounded-xl border border-ink/10 bg-ground p-4 text-left"
          >
            <p class="flex items-center gap-2 text-sm font-semibold text-ink">
              <Check :size="17" class="text-status-active" aria-hidden="true" />
              Prochaine étape
            </p>
            <p class="mt-2 text-sm leading-6 text-ink/55">
              Les propositions apparaîtront dans le suivi de tes demandes. Aucune réservation n’est
              créée sans ton accord.
            </p>
          </div>

          <div class="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
            <button
              type="button"
              class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-ink/15 px-4 text-sm font-semibold text-ink transition hover:border-primary"
              @click="restart"
            >
              <RotateCcw :size="17" aria-hidden="true" /> Nouvelle demande
            </button>
            <RouterLink
              :to="`/demandes/${publishedRequest?.id}`"
              class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-primary px-5 text-sm font-semibold text-surface transition hover:opacity-95"
            >
              Suivre ma demande n°{{ publishedRequest?.id }}
              <ArrowRight :size="17" aria-hidden="true" />
            </RouterLink>
          </div>
        </section>
      </div>
    </main>
  </section>
</template>
