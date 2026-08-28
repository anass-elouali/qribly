<script setup lang="ts">
import { computed, onMounted, ref, watch, type Component } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import {
  AcademicCapIcon,
  ArrowRightIcon,
  BriefcaseIcon,
  CalendarDaysIcon,
  CheckBadgeIcon,
  ChatBubbleLeftRightIcon,
  ComputerDesktopIcon,
  HomeIcon,
  LockClosedIcon,
  MapPinIcon,
  PencilSquareIcon,
  ShieldCheckIcon,
  SparklesIcon,
  Squares2X2Icon,
  TruckIcon,
  UserGroupIcon,
} from '@heroicons/vue/24/outline'
import api from '@/services/api'
import { fetchCategories } from '@/services/categories'
import { cityByName, type CityOption } from '@/data/moroccanCities'
import { extractErrorMessage } from '@/utils/errors'
import { formatPrice } from '@/utils/offer'
import { resolveStorageUrl } from '@/utils/url'
import AsyncStatePanel from '@/components/ui/AsyncStatePanel.vue'
import CityCombobox from '@/components/ui/CityCombobox.vue'
import FavoriteButton from '@/components/offers/FavoriteButton.vue'
import OfferGridSkeleton from '@/components/offers/OfferGridSkeleton.vue'
import type { Category, Offer, PaginatedResponse } from '@/types/offer'

const router = useRouter()

const offers = ref<Offer[]>([])
const showcasePool = ref<Offer[]>([])
const categories = ref<Category[]>([])
const selectedCategory = ref<number | null>(null)
const selectedType = ref<'product' | 'service' | null>(null)
const exploreCity = ref<CityOption | null>(cityByName('Casablanca'))
const requestQuery = ref('')
const page = ref(1)
const lastPage = ref(1)
const loading = ref(false)
const error = ref('')
const categoriesLoading = ref(true)
const categoriesError = ref('')

let offersRequestId = 0

const requestExamples = [
  'Je cherche un professeur de maths à Rabat demain soir.',
  'J’ai besoin d’un grand ménage à Casablanca samedi matin.',
  'Je cherche quelqu’un pour réparer mon téléphone à Marrakech.',
]

const categoryIcons: Record<string, Component> = {
  education: AcademicCapIcon,
  éducation: AcademicCapIcon,
  informatique: ComputerDesktopIcon,
  electronics: ComputerDesktopIcon,
  électronique: ComputerDesktopIcon,
  transport: TruckIcon,
  transportation: TruckIcon,
  maison: HomeIcon,
  'home services': HomeIcon,
  beauté: SparklesIcon,
  beauty: SparklesIcon,
  services: BriefcaseIcon,
}

const activeShowcaseOffers = computed(() =>
  showcasePool.value.filter((offer) => offer.status === 'active'),
)

const photographedOffers = computed(() =>
  activeShowcaseOffers.value.filter((offer) => offer.images?.[0]?.url),
)

const heroOffers = computed(() => photographedOffers.value.slice(0, 3))
const exploreOffers = computed(() => photographedOffers.value.slice(0, 4))

const providers = computed(() => {
  const seen = new Set<number>()

  return activeShowcaseOffers.value
    .filter((offer) => {
      if (!offer.owner || seen.has(offer.owner.id)) return false
      seen.add(offer.owner.id)
      return true
    })
    .slice(0, 3)
    .map((offer) => ({
      id: offer.owner!.id,
      name: offer.owner!.name,
      service: offer.title,
      city: offer.city,
      initials: offer
        .owner!.name.split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join(''),
    }))
})

const categoryPreview = computed(() => categories.value.slice(0, 8))

function offerImage(offer: Offer) {
  return offer.images?.[0]?.url ? resolveStorageUrl(offer.images[0].url) : null
}

function categoryIcon(category: Category) {
  return categoryIcons[category.name.trim().toLowerCase()] ?? Squares2X2Icon
}

function setRequestExample(example: string) {
  requestQuery.value = example
}

function startRequest() {
  const query = requestQuery.value.trim()

  router.push({
    name: 'service-request-create',
    query: query ? { q: query } : undefined,
  })
}

function exploreSelectedCity() {
  router.push({
    name: 'nearby',
    query: exploreCity.value ? { city: exploreCity.value.name } : undefined,
  })
}

function selectCategory(id: number | null) {
  selectedCategory.value = id
  page.value = 1
}

function selectType(type: 'product' | 'service' | null) {
  selectedType.value = type
  page.value = 1
}

function resetOfferFilters() {
  const hasFilters =
    selectedCategory.value !== null || selectedType.value !== null || page.value !== 1

  selectedCategory.value = null
  selectedType.value = null
  page.value = 1

  if (!hasFilters) loadOffers()
}

async function loadOffers() {
  const requestId = ++offersRequestId
  loading.value = true
  error.value = ''

  try {
    const response = await api.get<PaginatedResponse<Offer>>('/offers', {
      params: {
        page: page.value,
        category: selectedCategory.value ?? undefined,
        type: selectedType.value ?? undefined,
      },
    })

    if (requestId === offersRequestId) {
      offers.value = response.data.data
      lastPage.value = response.data.meta.last_page
    }
  } catch (exception) {
    if (requestId === offersRequestId) {
      error.value = extractErrorMessage(exception, 'Impossible de charger les annonces.')
    }
  } finally {
    if (requestId === offersRequestId) loading.value = false
  }
}

async function loadShowcasePool() {
  try {
    const response = await api.get<PaginatedResponse<Offer>>('/offers', {
      params: { per_page: 80 },
    })
    showcasePool.value = response.data.data
  } catch {
    // Les blocs éditoriaux sont secondaires : la grille principale reste utilisable.
  }
}

async function loadCategories() {
  categoriesLoading.value = true
  categoriesError.value = ''

  try {
    categories.value = await fetchCategories()
  } catch (exception) {
    categoriesError.value = extractErrorMessage(exception, 'Impossible de charger les catégories.')
  } finally {
    categoriesLoading.value = false
  }
}

watch([selectedCategory, selectedType, page], loadOffers)

onMounted(() => {
  Promise.all([loadCategories(), loadOffers(), loadShowcasePool()])
})
</script>

<template>
  <div class="bg-surface text-ink">
    <section class="border-b border-ink/10 bg-ground">
      <div
        class="mx-auto grid max-w-7xl gap-10 px-5 py-12 sm:px-8 sm:py-16 md:grid-cols-[1.08fr_0.92fr] md:items-center md:gap-10 lg:gap-14 lg:px-10 lg:py-20"
      >
        <div>
          <div
            class="mb-5 inline-flex items-center gap-2 rounded-full border border-primary/15 bg-surface px-3 py-1.5 font-mono text-[0.68rem] tracking-[0.16em] text-primary uppercase"
          >
            <SparklesIcon class="h-4 w-4 text-accent" aria-hidden="true" />
            Assistant de demande
          </div>

          <h1
            class="max-w-3xl font-display text-4xl leading-[1.05] font-semibold tracking-[-0.035em] text-primary sm:text-5xl lg:text-[4.3rem]"
          >
            Décris ton besoin.<br />
            <span class="text-ink">Les bons prestataires viennent à toi.</span>
          </h1>

          <p class="mt-6 max-w-2xl text-base leading-7 text-ink/65 sm:text-lg">
            Qrib transforme ta demande en brief clair et prévient les professionnels compatibles. Tu
            compares leurs propositions avant de choisir, sans réservation automatique.
          </p>

          <form
            class="mt-8 rounded-2xl border border-ink/10 bg-surface p-3 shadow-[0_18px_55px_rgba(20,73,90,0.09)]"
            @submit.prevent="startRequest"
          >
            <label for="home-request" class="sr-only">Décrivez votre besoin</label>
            <div class="flex items-start gap-3 px-2 pt-2">
              <PencilSquareIcon class="mt-1 h-5 w-5 shrink-0 text-primary" aria-hidden="true" />
              <textarea
                id="home-request"
                v-model="requestQuery"
                rows="3"
                maxlength="2000"
                placeholder="Ex. Je cherche un professeur d’arabe à Essaouira vendredi entre 14 h et 18 h, budget 200 DH…"
                class="min-h-24 w-full resize-none bg-transparent text-sm leading-6 text-ink outline-none placeholder:text-ink/40 sm:text-base"
              />
            </div>
            <div
              class="mt-2 flex flex-col gap-3 border-t border-ink/10 px-1 pt-3 sm:flex-row sm:items-center sm:justify-between"
            >
              <span class="px-2 text-xs text-ink/45"
                >Tu confirmeras chaque information avant publication.</span
              >
              <button
                type="submit"
                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-accent px-5 text-sm font-semibold text-ink transition hover:-translate-y-0.5 hover:shadow-md"
              >
                Structurer ma demande
                <ArrowRightIcon class="h-4 w-4" aria-hidden="true" />
              </button>
            </div>
          </form>

          <div class="mt-5 flex flex-wrap gap-2" aria-label="Exemples de demandes">
            <button
              v-for="example in requestExamples"
              :key="example"
              type="button"
              class="rounded-full border border-ink/10 bg-surface px-3 py-2 text-left text-xs text-ink/65 transition hover:border-primary/30 hover:text-primary"
              @click="setRequestExample(example)"
            >
              {{ example }}
            </button>
          </div>
        </div>

        <div
          class="relative hidden min-h-[500px] md:block lg:min-h-[530px]"
          aria-label="Prestataires et services Qribly"
        >
          <div class="absolute -top-4 right-4 h-36 w-36 rounded-full bg-accent/20 blur-3xl" />
          <RouterLink
            v-for="(offer, index) in heroOffers"
            :key="offer.id"
            :to="{ name: 'offer-details', params: { id: offer.id } }"
            class="group absolute overflow-hidden rounded-[1.8rem] border-4 border-surface bg-primary shadow-[0_25px_70px_rgba(20,73,90,0.18)]"
            :class="[
              index === 0 ? 'top-0 left-0 h-72 w-[62%]' : '',
              index === 1 ? 'top-24 right-0 h-72 w-[48%]' : '',
              index === 2 ? 'bottom-0 left-16 h-56 w-[58%]' : '',
            ]"
          >
            <img
              v-if="offerImage(offer)"
              :src="offerImage(offer)!"
              :alt="offer.title"
              class="h-full w-full object-cover transition duration-500 group-hover:scale-105"
            />
            <div
              class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-ink/80 via-ink/25 to-transparent p-5 pt-16 text-surface"
            >
              <p class="font-mono text-[0.62rem] tracking-[0.16em] uppercase opacity-80">
                {{ offer.category?.name ?? 'Annonce locale' }}
              </p>
              <p class="mt-1 line-clamp-2 font-semibold">{{ offer.title }}</p>
            </div>
          </RouterLink>

          <div
            v-if="heroOffers.length === 0"
            class="absolute inset-8 flex items-center justify-center rounded-[2rem] bg-primary text-center text-surface"
          >
            <div class="max-w-xs p-8">
              <UserGroupIcon class="mx-auto h-12 w-12 text-accent" aria-hidden="true" />
              <p class="mt-4 font-display text-2xl font-semibold">La communauté Qribly</p>
              <p class="mt-2 text-sm leading-6 text-surface/70">
                Des services utiles, proposés tout près de chez toi.
              </p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="border-b border-ink/10 bg-primary text-surface">
      <div
        class="mx-auto grid max-w-7xl divide-y divide-surface/15 px-5 sm:px-8 md:grid-cols-3 md:divide-x md:divide-y-0 lg:px-10"
      >
        <div class="flex gap-4 py-6 md:px-7 md:first:pl-0">
          <span class="font-display text-4xl text-accent">01</span>
          <div>
            <p class="font-semibold">Explique ton besoin</p>
            <p class="mt-1 text-sm leading-5 text-surface/65">
              En une phrase, avec ton lieu et ton moment idéal.
            </p>
          </div>
        </div>
        <div class="flex gap-4 py-6 md:px-7">
          <span class="font-display text-4xl text-accent">02</span>
          <div>
            <p class="font-semibold">Reçois des propositions</p>
            <p class="mt-1 text-sm leading-5 text-surface/65">
              Les prestataires compatibles répondent avec leur prix.
            </p>
          </div>
        </div>
        <div class="flex gap-4 py-6 md:px-7 md:last:pr-0">
          <span class="font-display text-4xl text-accent">03</span>
          <div>
            <p class="font-semibold">Choisis sereinement</p>
            <p class="mt-1 text-sm leading-5 text-surface/65">
              Discute, compare puis accepte la proposition qui te convient.
            </p>
          </div>
        </div>
      </div>
    </section>

    <div class="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:px-10 lg:py-24">
      <section class="mb-24">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <p class="font-mono text-xs tracking-[0.18em] text-primary uppercase">
              Explorer librement
            </p>
            <h2 class="mt-2 font-display text-3xl font-semibold text-primary sm:text-4xl">
              Tu préfères parcourir les annonces ?
            </h2>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-ink/60 sm:text-base">
              Choisis une ville ou utilise ta position pour découvrir les offres disponibles autour
              de toi.
            </p>
          </div>
          <div class="grid gap-2 sm:grid-cols-[16rem_auto_auto]">
            <CityCombobox
              v-model="exploreCity"
              id="home-city"
              label="Ville à explorer"
              placeholder="Choisir une ville…"
            />
            <button
              type="button"
              class="rounded-xl bg-primary px-4 py-3 text-sm font-semibold text-surface transition hover:opacity-90"
              @click="exploreSelectedCity"
            >
              Voir la ville
            </button>
            <RouterLink
              :to="{ name: 'nearby' }"
              class="inline-flex items-center justify-center gap-2 rounded-xl border border-ink/15 bg-surface px-4 py-3 text-sm font-semibold text-primary transition hover:border-primary"
            >
              <MapPinIcon class="h-4 w-4" aria-hidden="true" /> Autour de moi
            </RouterLink>
          </div>
        </div>

        <div v-if="exploreOffers.length" class="mt-8 grid gap-5 sm:grid-cols-2 md:grid-cols-4">
          <RouterLink
            v-for="offer in exploreOffers"
            :key="offer.id"
            :to="{ name: 'offer-details', params: { id: offer.id } }"
            class="group min-w-0"
          >
            <div class="relative aspect-[4/3] overflow-hidden rounded-2xl bg-ground">
              <img
                v-if="offerImage(offer)"
                :src="offerImage(offer)!"
                :alt="offer.title"
                class="h-full w-full object-cover transition duration-500 group-hover:scale-105"
              />
              <div
                v-else
                class="flex h-full flex-col items-center justify-center bg-ground text-primary"
              >
                <component
                  :is="offer.category ? categoryIcon(offer.category) : Squares2X2Icon"
                  class="h-9 w-9"
                  aria-hidden="true"
                />
                <span class="mt-3 font-mono text-[0.65rem] tracking-wide text-ink/45 uppercase"
                  >Photo non fournie</span
                >
              </div>
              <div class="absolute top-3 right-3"><FavoriteButton :offer-id="offer.id" /></div>
            </div>
            <div class="mt-3 flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="truncate font-semibold text-ink group-hover:text-primary">
                  {{ offer.title }}
                </p>
                <p class="mt-1 flex items-center gap-1 text-xs text-ink/50">
                  <MapPinIcon class="h-3.5 w-3.5" aria-hidden="true" />{{
                    offer.city ?? 'Localisation à confirmer'
                  }}
                </p>
              </div>
              <p class="shrink-0 font-mono text-sm font-semibold text-primary">
                {{ formatPrice(offer.price) }} DH
              </p>
            </div>
          </RouterLink>
        </div>
      </section>

      <section class="mb-24 rounded-[2rem] bg-ground p-6 sm:p-10 lg:p-14">
        <div class="mx-auto max-w-2xl text-center">
          <p class="font-mono text-xs tracking-[0.18em] text-primary uppercase">
            Une demande, plusieurs réponses
          </p>
          <h2 class="mt-3 font-display text-3xl font-semibold text-primary sm:text-4xl">
            Des besoins réels, des réponses concrètes
          </h2>
          <p class="mt-3 text-sm leading-6 text-ink/60 sm:text-base">
            La demande Qrib complète la recherche classique quand tu ne veux pas contacter chaque
            annonce une par une.
          </p>
        </div>
        <div class="mt-10 grid gap-5 md:grid-cols-2">
          <article class="overflow-hidden rounded-2xl border border-ink/10 bg-surface">
            <div class="grid sm:grid-cols-[11rem_1fr]">
              <img
                v-if="heroOffers[0] && offerImage(heroOffers[0])"
                :src="offerImage(heroOffers[0])!"
                :alt="heroOffers[0].title"
                class="h-52 w-full object-cover sm:h-full"
              />
              <div class="p-6">
                <span class="font-mono text-[0.65rem] tracking-[0.16em] text-accent uppercase"
                  >Besoin</span
                >
                <p class="mt-3 font-display text-xl font-semibold text-primary">
                  « Je cherche une aide fiable près de chez moi, au bon moment. »
                </p>
                <p class="mt-4 text-sm leading-6 text-ink/60">
                  Qrib extrait la ville, la disponibilité et le budget, puis présente la demande aux
                  prestataires compatibles.
                </p>
              </div>
            </div>
          </article>
          <article class="overflow-hidden rounded-2xl border border-ink/10 bg-surface">
            <div class="grid sm:grid-cols-[11rem_1fr]">
              <img
                v-if="heroOffers[1] && offerImage(heroOffers[1])"
                :src="offerImage(heroOffers[1])!"
                :alt="heroOffers[1].title"
                class="h-52 w-full object-cover sm:h-full"
              />
              <div class="p-6">
                <span
                  class="font-mono text-[0.65rem] tracking-[0.16em] text-status-active uppercase"
                  >Réponse</span
                >
                <p class="mt-3 font-display text-xl font-semibold text-primary">
                  Prix, horaire et message réunis au même endroit.
                </p>
                <p class="mt-4 text-sm leading-6 text-ink/60">
                  Tu peux échanger en privé avec chaque prestataire avant d’accepter une proposition
                  et créer la réservation.
                </p>
              </div>
            </div>
          </article>
        </div>
      </section>

      <section v-if="providers.length" class="mb-24">
        <div class="flex items-end justify-between gap-5">
          <div>
            <p class="font-mono text-xs tracking-[0.18em] text-primary uppercase">
              Communauté locale
            </p>
            <h2 class="mt-2 font-display text-3xl font-semibold text-primary sm:text-4xl">
              Prestataires actifs sur Qribly
            </h2>
          </div>
          <RouterLink
            :to="{ name: 'nearby' }"
            class="hidden items-center gap-2 text-sm font-semibold text-primary hover:underline sm:inline-flex"
            >Voir près de moi <ArrowRightIcon class="h-4 w-4"
          /></RouterLink>
        </div>
        <div class="mt-8 grid gap-5 md:grid-cols-3">
          <article
            v-for="provider in providers"
            :key="provider.id"
            class="rounded-2xl border border-ink/10 bg-surface p-6 transition hover:-translate-y-1 hover:shadow-lg"
          >
            <div class="flex items-center gap-4">
              <div
                class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-primary font-display text-xl font-semibold text-surface"
              >
                {{ provider.initials }}
              </div>
              <div class="min-w-0">
                <div class="flex items-center gap-1.5">
                  <h3 class="truncate font-semibold text-ink">{{ provider.name }}</h3>
                  <CheckBadgeIcon class="h-5 w-5 shrink-0 text-status-active" aria-hidden="true" />
                </div>
                <p class="mt-1 truncate text-sm text-ink/55">
                  {{ provider.city ?? 'Prestataire Qribly' }}
                </p>
              </div>
            </div>
            <p class="mt-5 line-clamp-2 text-sm leading-6 text-ink/65">{{ provider.service }}</p>
            <p class="mt-4 inline-flex items-center gap-2 font-mono text-xs text-primary">
              <span class="h-2 w-2 rounded-full bg-status-active" /> Annonce active
            </p>
          </article>
        </div>
      </section>

      <section class="mb-24">
        <div class="mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <p class="font-mono text-xs tracking-[0.18em] text-primary uppercase">
              Tout près de toi
            </p>
            <h2 class="mt-2 font-display text-3xl font-semibold text-primary sm:text-4xl">
              Explore par catégorie
            </h2>
          </div>
          <button
            v-if="selectedCategory !== null"
            type="button"
            class="text-sm font-semibold text-primary hover:underline"
            @click="selectCategory(null)"
          >
            Afficher toutes les catégories
          </button>
        </div>

        <div v-if="categoriesLoading" class="grid grid-cols-2 gap-3 sm:grid-cols-4 md:grid-cols-8">
          <span
            v-for="index in 8"
            :key="index"
            class="h-28 animate-pulse rounded-2xl bg-ground motion-reduce:animate-none"
          />
        </div>
        <div
          v-else-if="categoriesError"
          class="rounded-xl border border-status-reserved/20 bg-status-reserved/5 px-4 py-3 text-sm text-status-reserved"
          role="alert"
        >
          {{ categoriesError }}
          <button type="button" class="ml-2 font-semibold underline" @click="loadCategories">
            Réessayer
          </button>
        </div>
        <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-4 md:grid-cols-8">
          <button
            v-for="category in categoryPreview"
            :key="category.id"
            type="button"
            class="group flex min-h-28 flex-col items-center justify-center rounded-2xl border px-3 py-5 text-center transition"
            :class="
              selectedCategory === category.id
                ? 'border-primary bg-primary text-surface shadow-md'
                : 'border-ink/10 bg-ground text-ink hover:-translate-y-1 hover:border-primary/30'
            "
            @click="selectCategory(category.id)"
          >
            <component
              :is="categoryIcon(category)"
              class="h-7 w-7"
              :class="selectedCategory === category.id ? 'text-accent' : 'text-primary'"
              aria-hidden="true"
            /><span class="mt-3 text-xs font-semibold">{{ category.name }}</span>
          </button>
        </div>
      </section>

      <section>
        <div
          class="flex flex-col gap-5 border-b border-ink/10 pb-6 sm:flex-row sm:items-end sm:justify-between"
        >
          <div>
            <p class="font-mono text-xs tracking-[0.18em] text-primary uppercase">Nouveautés</p>
            <h2 class="mt-2 font-display text-3xl font-semibold text-primary sm:text-4xl">
              Dernières annonces
            </h2>
            <p class="mt-2 text-sm text-ink/55">
              Produits et services récemment publiés sur Qribly.
            </p>
          </div>
          <div class="flex gap-1 rounded-full border border-ink/10 bg-ground p-1">
            <button
              v-for="option in [
                { label: 'Tout', value: null },
                { label: 'Produits', value: 'product' },
                { label: 'Services', value: 'service' },
              ]"
              :key="option.label"
              type="button"
              class="rounded-full px-4 py-2 text-sm font-semibold transition"
              :class="
                selectedType === option.value
                  ? 'bg-primary text-surface'
                  : 'text-ink/55 hover:text-primary'
              "
              @click="selectType(option.value as 'product' | 'service' | null)"
            >
              {{ option.label }}
            </button>
          </div>
        </div>

        <AsyncStatePanel
          v-if="error"
          class="mt-6"
          variant="error"
          title="Les annonces ne sont pas disponibles"
          :message="error"
          action-label="Réessayer"
          compact
          @action="loadOffers"
        />
        <OfferGridSkeleton v-if="loading && offers.length === 0" class="mt-7" />
        <AsyncStatePanel
          v-else-if="!error && offers.length === 0"
          class="mt-7"
          variant="empty"
          title="Aucune annonce trouvée"
          message="Essaie une autre catégorie ou affiche de nouveau toutes les annonces."
          action-label="Réinitialiser les filtres"
          @action="resetOfferFilters"
        />

        <div
          v-if="offers.length"
          class="mt-7 grid gap-x-5 gap-y-9 sm:grid-cols-2 md:grid-cols-3"
          :class="loading ? 'pointer-events-none opacity-60' : ''"
          :aria-busy="loading"
        >
          <RouterLink
            v-for="offer in offers"
            :key="offer.id"
            :to="{ name: 'offer-details', params: { id: offer.id } }"
            class="group min-w-0"
          >
            <div class="relative aspect-[4/3] overflow-hidden rounded-2xl bg-ground">
              <img
                v-if="offerImage(offer)"
                :src="offerImage(offer)!"
                :alt="offer.title"
                class="h-full w-full object-cover transition duration-500 group-hover:scale-105"
              />
              <div
                v-else
                class="flex h-full flex-col items-center justify-center bg-ground text-primary"
              >
                <component
                  :is="offer.category ? categoryIcon(offer.category) : Squares2X2Icon"
                  class="h-10 w-10"
                  aria-hidden="true"
                />
                <span class="mt-3 font-mono text-[0.65rem] tracking-wide text-ink/45 uppercase"
                  >Photo non fournie</span
                >
              </div>
              <div class="absolute top-3 right-3"><FavoriteButton :offer-id="offer.id" /></div>
              <span
                class="absolute bottom-3 left-3 rounded-full bg-surface/95 px-3 py-1 font-mono text-[0.65rem] tracking-wide text-primary uppercase"
                >{{ offer.type === 'service' ? 'Service' : 'Produit' }}</span
              >
            </div>
            <div class="mt-3 flex items-start justify-between gap-4">
              <div class="min-w-0">
                <p class="font-semibold text-ink group-hover:text-primary">{{ offer.title }}</p>
                <p class="mt-1 flex items-center gap-1 text-xs text-ink/50">
                  <MapPinIcon class="h-3.5 w-3.5" />{{ offer.city ?? 'Localisation à confirmer' }}
                </p>
              </div>
              <p
                class="shrink-0 rounded-lg bg-accent px-2.5 py-1 font-mono text-sm font-semibold text-ink"
              >
                {{ formatPrice(offer.price) }} DH
              </p>
            </div>
          </RouterLink>
        </div>

        <div
          v-if="lastPage > 1 && offers.length"
          class="mt-10 flex items-center justify-center gap-3 font-mono text-sm"
        >
          <button
            type="button"
            class="rounded-lg border border-ink/15 px-4 py-2 disabled:opacity-40"
            :disabled="page === 1 || loading"
            @click="page--"
          >
            ← Précédent</button
          ><span class="text-ink/50">{{ page }} / {{ lastPage }}</span
          ><button
            type="button"
            class="rounded-lg border border-ink/15 px-4 py-2 disabled:opacity-40"
            :disabled="page === lastPage || loading"
            @click="page++"
          >
            Suivant →
          </button>
        </div>
      </section>
    </div>

    <section class="overflow-hidden bg-ground">
      <div class="mx-auto grid max-w-7xl lg:grid-cols-[1.05fr_0.95fr]">
        <div class="px-5 py-14 sm:px-8 lg:px-10 lg:py-20">
          <p class="font-mono text-xs tracking-[0.18em] text-primary uppercase">
            Confiance & proximité
          </p>
          <h2 class="mt-3 max-w-xl font-display text-3xl font-semibold text-primary sm:text-4xl">
            Des échanges simples, un choix qui reste toujours entre tes mains.
          </h2>
          <div class="mt-8 grid gap-6 sm:grid-cols-3">
            <div>
              <ShieldCheckIcon class="h-7 w-7 text-accent" />
              <p class="mt-3 font-semibold">Profils identifiés</p>
              <p class="mt-1 text-sm leading-6 text-ink/55">Tu sais avec qui tu échanges.</p>
            </div>
            <div>
              <ChatBubbleLeftRightIcon class="h-7 w-7 text-accent" />
              <p class="mt-3 font-semibold">Discussion privée</p>
              <p class="mt-1 text-sm leading-6 text-ink/55">
                Clarifie les détails avant de décider.
              </p>
            </div>
            <div>
              <LockClosedIcon class="h-7 w-7 text-accent" />
              <p class="mt-3 font-semibold">Aucun engagement forcé</p>
              <p class="mt-1 text-sm leading-6 text-ink/55">
                Tu acceptes seulement ce qui te convient.
              </p>
            </div>
          </div>
        </div>
        <div class="relative min-h-72 bg-primary md:min-h-full">
          <img
            v-if="heroOffers[2] && offerImage(heroOffers[2])"
            :src="offerImage(heroOffers[2])!"
            :alt="heroOffers[2].title"
            class="absolute inset-0 h-full w-full object-cover opacity-75 mix-blend-luminosity"
          />
          <div class="absolute inset-0 bg-gradient-to-r from-primary/85 to-primary/20" />
          <div class="relative flex h-full items-end p-8 text-surface sm:p-12">
            <div>
              <CalendarDaysIcon class="h-8 w-8 text-accent" />
              <p class="mt-4 max-w-sm font-display text-2xl font-semibold">
                Réserver au bon moment, avec les bonnes informations.
              </p>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>
