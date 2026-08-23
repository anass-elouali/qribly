<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import dayjs from 'dayjs'
import {
  BriefcaseBusiness,
  CalendarDays,
  Clock3,
  ChevronLeft,
  ChevronRight,
  Images,
  LockKeyhole,
  MapPin,
  Package,
  X,
} from 'lucide-vue-next'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { useChatStore } from '@/stores/chat'
import type { Offer } from '@/types/offer'
import type { Conversation } from '@/types/conversation'
import { statusLabel, statusColor, formatPrice } from '@/utils/offer'
import { resolveStorageUrl } from '@/utils/url'
import { extractErrorMessage, isNotFoundError } from '@/utils/errors'
import { initials } from '@/utils/user'
import FavoriteButton from '@/components/offers/FavoriteButton.vue'
import OfferDetailsSkeleton from '@/components/offers/OfferDetailsSkeleton.vue'
import OfferLocationMap from '@/components/offers/OfferLocationMap.vue'
import OfferReviews from '@/components/reviews/OfferReviews.vue'
import AsyncStatePanel from '@/components/ui/AsyncStatePanel.vue'
import BookingSlotPicker from '@/components/reservations/BookingSlotPicker.vue'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const chatStore = useChatStore()
const offer = ref<Offer | null>(null)
const loading = ref(true)
const error = ref('')
const selectedImageIndex = ref(0)
const galleryOpen = ref(false)
const galleryDialog = ref<HTMLElement | null>(null)
const galleryCloseButton = ref<HTMLButtonElement | null>(null)
const galleryTrigger = ref<HTMLElement | null>(null)
let previousBodyOverflow = ''
const contacting = ref(false)
const contactError = ref('')

const scheduledAt = ref('')
const notes = ref('')
const booking = ref(false)
const bookingError = ref('')
const bookingSuccess = ref(false)
const scheduledAtTouched = ref(false)
const slotPicker = ref<{ refresh: () => Promise<void> } | null>(null)

const NOTES_MAX_LENGTH = 1000

function nextReservableMinute() {
  return dayjs().add(1, 'minute').startOf('minute').format('YYYY-MM-DDTHH:mm')
}

const minScheduledAt = ref(nextReservableMinute())

const scheduledAtError = computed(() => {
  if (!scheduledAtTouched.value) {
    return ''
  }

  if (!scheduledAt.value) {
    return 'Choisis une date et une heure.'
  }

  const selectedDate = dayjs(scheduledAt.value)

  if (!selectedDate.isValid() || !selectedDate.isAfter(dayjs())) {
    return 'Choisis une date et une heure à venir.'
  }

  return ''
})

const remainingNotesCharacters = computed(() => NOTES_MAX_LENGTH - notes.value.length)

function refreshMinimumDate() {
  minScheduledAt.value = nextReservableMinute()
}

async function loadOffer() {
  loading.value = true
  error.value = ''
  selectedImageIndex.value = 0

  try {
    const response = await api.get<{ data: Offer }>(`/offers/${route.params.id}`)
    offer.value = response.data.data
  } catch (exception) {
    error.value = isNotFoundError(exception)
      ? "Cette annonce n'existe pas ou plus."
      : extractErrorMessage(exception, "Impossible de charger l'annonce.")
  } finally {
    loading.value = false
  }
}

const galleryImages = computed(() =>
  (offer.value?.images ?? []).map((image, index) => ({
    ...image,
    index,
    resolvedUrl: resolveStorageUrl(image.url),
  })),
)

const primaryGalleryImage = computed(() => galleryImages.value[0] ?? null)

const activeGalleryImage = computed(() => galleryImages.value[selectedImageIndex.value] ?? null)

const secondaryGalleryImages = computed(() => galleryImages.value.slice(1, 3))

const hiddenGalleryImageCount = computed(() => Math.max(0, galleryImages.value.length - 3))

const offerTypeLabel = computed(() => {
  return offer.value?.type === 'service' ? 'Service' : 'Produit'
})

function formatServiceDuration(minutes: number | null | undefined) {
  const duration = minutes ?? 60

  if (duration < 60) {
    return `${duration} min`
  }

  const hours = Math.floor(duration / 60)
  const remainingMinutes = duration % 60

  return remainingMinutes ? `${hours} h ${remainingMinutes}` : `${hours} h`
}

async function openGallery(index = 0, event?: MouseEvent) {
  if (!galleryImages.value[index]) {
    return
  }

  galleryTrigger.value = event?.currentTarget as HTMLElement | null
  selectedImageIndex.value = index
  galleryOpen.value = true
  previousBodyOverflow = document.body.style.overflow
  document.body.style.overflow = 'hidden'
  await nextTick()
  galleryCloseButton.value?.focus()
}

function closeGallery() {
  galleryOpen.value = false
  document.body.style.overflow = previousBodyOverflow
  nextTick(() => galleryTrigger.value?.focus())
}

function stepGallery(direction: number) {
  if (galleryImages.value.length < 2) {
    return
  }

  selectedImageIndex.value =
    (selectedImageIndex.value + direction + galleryImages.value.length) % galleryImages.value.length
}

function handleGalleryKeydown(event: KeyboardEvent) {
  if (!galleryOpen.value) {
    return
  }

  if (event.key === 'Tab') {
    const focusableElements = Array.from(
      galleryDialog.value?.querySelectorAll<HTMLElement>(
        'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
      ) ?? [],
    )
    const firstElement = focusableElements[0]
    const lastElement = focusableElements.at(-1)

    if (event.shiftKey && document.activeElement === firstElement) {
      event.preventDefault()
      lastElement?.focus()
    } else if (!event.shiftKey && document.activeElement === lastElement) {
      event.preventDefault()
      firstElement?.focus()
    }
  } else if (event.key === 'Escape') {
    closeGallery()
  } else if (event.key === 'ArrowLeft') {
    stepGallery(-1)
  } else if (event.key === 'ArrowRight') {
    stepGallery(1)
  }
}

async function submitReservation() {
  if (!offer.value) {
    return
  }

  scheduledAtTouched.value = true
  refreshMinimumDate()

  if (scheduledAtError.value) {
    return
  }

  bookingError.value = ''
  booking.value = true

  try {
    await api.post(`/offers/${offer.value.id}/reservations`, {
      scheduled_at: dayjs(scheduledAt.value).toISOString(),
      notes: notes.value || undefined,
    })

    bookingSuccess.value = true
    scheduledAt.value = ''
    notes.value = ''
    scheduledAtTouched.value = false
  } catch (err) {
    bookingError.value = extractErrorMessage(err, 'Impossible de réserver ce service.')
    await slotPicker.value?.refresh()
  } finally {
    booking.value = false
  }
}

async function contactOwner() {
  if (!offer.value?.owner) {
    return
  }

  contacting.value = true
  contactError.value = ''

  try {
    const response = await api.post<Conversation>('/conversations', {
      user_id: offer.value.owner.id,
    })
    chatStore.upsertConversation(response.data)
    router.push({ name: 'conversation', params: { id: response.data.id } })
  } catch {
    contactError.value = 'Impossible de contacter le vendeur pour le moment.'
  } finally {
    contacting.value = false
  }
}

onMounted(() => {
  loadOffer()
  window.addEventListener('keydown', handleGalleryKeydown)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleGalleryKeydown)
  document.body.style.overflow = previousBodyOverflow
})

watch(
  () => route.params.id,
  () => {
    closeGallery()
    offer.value = null
    loadOffer()
    bookingSuccess.value = false
    bookingError.value = ''
    scheduledAt.value = ''
    notes.value = ''
    scheduledAtTouched.value = false
    refreshMinimumDate()
  },
)
</script>

<template>
  <div class="mx-auto max-w-6xl px-4 py-5 sm:px-6 lg:py-1">
    <OfferDetailsSkeleton v-if="loading" />

    <AsyncStatePanel
      v-else-if="error"
      variant="error"
      title="Impossible d’afficher cette annonce"
      :message="error"
      action-label="Réessayer"
      @action="loadOffer"
    />

    <div v-else-if="offer">
      <section
        class="offer-gallery relative overflow-hidden rounded-lg bg-ink/10"
        :class="galleryImages.length > 1 ? 'has-secondary-images' : 'single-image'"
        aria-label="Photos de l’annonce"
      >
        <button
          v-if="primaryGalleryImage"
          type="button"
          class="primary-image group relative min-h-0 overflow-hidden text-left focus-visible:z-10 focus-visible:outline-2 focus-visible:outline-offset-[-3px] focus-visible:outline-surface"
          aria-label="Ouvrir la galerie à partir de la photo principale"
          @click="openGallery(primaryGalleryImage.index, $event)"
        >
          <img
            :src="primaryGalleryImage.resolvedUrl"
            :alt="offer.title"
            class="h-full w-full object-cover transition duration-300 group-hover:brightness-95"
          />
        </button>

        <div
          v-else
          class="primary-image flex items-center justify-center font-mono text-xs tracking-wide text-surface/70 uppercase"
        >
          Aucune photo
        </div>

        <button
          v-for="(image, supportIndex) in secondaryGalleryImages"
          :key="image.id"
          type="button"
          class="secondary-image group relative min-h-0 overflow-hidden text-left"
          :aria-label="`Ouvrir la galerie à partir de la photo ${image.index + 1}`"
          @click="openGallery(image.index, $event)"
        >
          <img
            :src="image.resolvedUrl"
            :alt="`Photo ${image.index + 1} de ${offer.title}`"
            class="h-full w-full object-cover transition duration-300 group-hover:brightness-95"
          />
          <span
            v-if="supportIndex === secondaryGalleryImages.length - 1 && hiddenGalleryImageCount > 0"
            class="absolute right-3 bottom-3 rounded-md bg-ink/80 px-3 py-1.5 font-body text-xs font-semibold text-surface"
          >
            +{{ hiddenGalleryImageCount }} photo<span v-if="hiddenGalleryImageCount > 1">s</span>
          </span>
        </button>

        <button
          v-if="galleryImages.length"
          type="button"
          class="absolute right-3 bottom-3 z-10 inline-flex items-center gap-2 rounded-md border border-ink/20 bg-surface px-3 py-2 font-body text-xs font-semibold text-ink shadow-md transition hover:bg-mist focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
          @click="openGallery(0, $event)"
        >
          <Images :size="16" aria-hidden="true" />
          <span class="hidden sm:inline">Afficher toutes les photos</span>
          <span class="sm:hidden"
            >{{ galleryImages.length }} photo<span v-if="galleryImages.length > 1">s</span></span
          >
        </button>
      </section>

      <div class="detail-layout mt-3" :class="{ 'has-booking': offer.type === 'service' }">
        <article class="offer-information min-w-0">
          <nav
            class="mb-3 flex flex-wrap items-center gap-1.5 font-mono text-[0.65rem] tracking-wide text-ink/45 uppercase"
            aria-label="Fil d’Ariane"
          >
            <RouterLink :to="{ name: 'home' }" class="transition hover:text-primary"
              >Accueil</RouterLink
            >
            <span aria-hidden="true">›</span>
            <span>{{ offerTypeLabel }}</span>
            <template v-if="offer.category">
              <span aria-hidden="true">›</span>
              <span>{{ offer.category.name }}</span>
            </template>
          </nav>

          <div class="flex items-start justify-between gap-4">
            <h1 class="max-w-3xl font-display text-3xl leading-tight font-bold text-ink">
              {{ offer.title }}
            </h1>
            <FavoriteButton
              :offer-id="offer.id"
              :size="21"
              class="shrink-0 rounded-lg border border-ink/10 bg-surface shadow-sm"
            />
          </div>

          <div class="mt-3 flex flex-wrap items-center gap-2">
            <span class="offer-detail-chip">
              <BriefcaseBusiness v-if="offer.type === 'service'" :size="15" aria-hidden="true" />
              <Package v-else :size="15" aria-hidden="true" />
              {{ offerTypeLabel }}
            </span>
            <span v-if="offer.category" class="offer-detail-chip">{{ offer.category.name }}</span>
            <a
              v-if="offer.location"
              href="#localisation"
              class="offer-detail-chip transition hover:border-primary hover:text-primary"
            >
              <MapPin :size="15" aria-hidden="true" />
              Zone de l’offre
            </a>
            <span class="offer-detail-chip">
              <CalendarDays :size="15" aria-hidden="true" />
              Publié {{ dayjs(offer.created_at).fromNow() }}
            </span>
            <span
              class="rounded-md px-2.5 py-1 font-mono text-xs tracking-wide text-surface"
              :class="statusColor[offer.status]"
            >
              {{ statusLabel[offer.status] }}
            </span>
            <span v-if="offer.is_negotiable" class="offer-detail-chip">Prix négociable</span>
            <span v-if="offer.type === 'service'" class="offer-detail-chip">
              <Clock3 :size="15" aria-hidden="true" />
              {{ formatServiceDuration(offer.service_duration_minutes) }}
            </span>
          </div>

          <p class="mt-3 font-mono text-2xl font-bold text-accent">
            {{ formatPrice(offer.price) }} DH
          </p>

          <p class="mt-1 max-w-3xl whitespace-pre-line font-body leading-6 text-ink/80">
            {{ offer.description }}
          </p>

          <div
            v-if="offer.owner"
            class="mt-4 flex flex-col gap-2 rounded-lg border border-ink/10 bg-surface p-3.5"
          >
            <div class="flex flex-wrap items-center justify-between gap-3">
              <div class="flex items-center gap-3">
                <span
                  class="flex h-11 w-11 items-center justify-center rounded-full bg-primary font-mono text-xs font-bold text-surface"
                >
                  {{ initials(offer.owner.name) }}
                </span>
                <div>
                  <p class="font-body text-xs text-ink/45">Annonce proposée par</p>
                  <p class="font-body font-semibold text-ink">{{ offer.owner.name }}</p>
                </div>
              </div>

              <button
                v-if="authStore.isAuthenticated && offer.owner.id !== authStore.user?.id"
                type="button"
                :disabled="contacting"
                class="shrink-0 rounded-md border border-primary px-4 py-2 text-sm font-semibold text-primary transition hover:bg-primary/5 disabled:cursor-not-allowed disabled:opacity-60"
                @click="contactOwner"
              >
                {{ contacting ? 'Ouverture…' : 'Contacter' }}
              </button>
            </div>

            <p v-if="contactError" class="font-body text-sm text-status-reserved" role="alert">
              {{ contactError }}
            </p>
          </div>
        </article>

        <aside
          v-if="offer.type === 'service'"
          class="booking-panel rounded-xl border border-ink/10 bg-surface p-4 shadow-sm lg:sticky lg:top-4"
        >
          <h2 class="font-display text-xl font-bold text-primary">Réserver ce service</h2>
          <p class="mt-1 font-mono text-2xl font-bold text-accent">
            {{ formatPrice(offer.price) }} DH
          </p>

          <p class="mt-1 flex items-center gap-1.5 font-body text-xs text-ink/50">
            <Clock3 :size="14" aria-hidden="true" />
            Durée prévue : {{ formatServiceDuration(offer.service_duration_minutes) }}
          </p>

          <p v-if="!authStore.isAuthenticated" class="mt-5 font-body text-sm text-ink/60">
            <RouterLink :to="{ name: 'login' }" class="font-semibold text-primary hover:underline">
              Connecte-toi
            </RouterLink>
            pour réserver.
          </p>

          <p
            v-else-if="offer.owner && offer.owner.id === authStore.user?.id"
            class="mt-5 rounded-md bg-primary/5 px-4 py-3 font-body text-sm text-ink/60"
          >
            C'est ta propre annonce.
          </p>

          <p
            v-else-if="offer.status !== 'active'"
            class="mt-5 rounded-md bg-status-reserved/10 px-4 py-3 font-body text-sm text-status-reserved"
          >
            Cette annonce n'est pas disponible à la réservation.
          </p>

          <p
            v-else-if="bookingSuccess"
            class="mt-5 rounded-md bg-status-active/10 px-4 py-3 text-sm text-status-active"
          >
            Réservation envoyée ! Retrouve-la dans
            <RouterLink :to="{ name: 'profile' }" class="font-semibold underline"
              >ton profil</RouterLink
            >.
          </p>

          <form
            v-else
            class="mt-4 flex flex-col gap-3"
            novalidate
            @submit.prevent="submitReservation"
          >
            <BookingSlotPicker
              ref="slotPicker"
              v-model="scheduledAt"
              :offer-id="offer.id"
              :min-scheduled-at="minScheduledAt"
              :error-message="scheduledAtError"
              @touch="scheduledAtTouched = true"
            />

            <div>
              <label for="resa-notes" class="mb-1.5 block font-body text-sm font-medium text-ink">
                Notes pour le prestataire (optionnel)
              </label>
              <textarea
                id="resa-notes"
                v-model="notes"
                rows="2"
                :maxlength="NOTES_MAX_LENGTH"
                aria-describedby="notes-counter"
                class="h-16 w-full rounded-lg border border-ink/15 bg-ground px-3 py-2 font-body outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
              ></textarea>
              <p id="notes-counter" class="mt-1 text-right font-mono text-xs text-ink/40">
                {{ remainingNotesCharacters }} caractère(s) restant(s)
              </p>
            </div>

            <p
              v-if="bookingError"
              class="rounded-md bg-status-reserved/10 px-3 py-2 text-sm text-status-reserved"
              role="alert"
            >
              {{ bookingError }}
            </p>

            <button
              type="submit"
              :disabled="booking"
              class="rounded-lg bg-accent px-4 py-2.5 font-semibold text-ink transition hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary disabled:cursor-not-allowed disabled:opacity-60"
            >
              {{ booking ? 'Envoi…' : 'Réserver' }}
            </button>
          </form>

          <p
            v-if="!offer.owner || offer.owner.id !== authStore.user?.id"
            class="mt-4 flex items-start gap-2 font-body text-xs leading-5 text-ink/50"
          >
            <LockKeyhole :size="15" class="mt-0.5 shrink-0" aria-hidden="true" />
            Aucun paiement ne sera demandé maintenant.
          </p>
        </aside>

        <div class="offer-supporting-content min-w-0">
          <section
            v-if="offer.location"
            id="localisation"
            class="scroll-mt-6 border-t border-ink/10 pt-5"
          >
            <div class="mb-3">
              <div class="flex items-center gap-2 text-primary">
                <MapPin :size="20" aria-hidden="true" />
                <h2 class="font-display text-xl font-bold">Localisation</h2>
              </div>
              <p class="mt-1 font-body text-sm font-semibold text-ink">Zone de l’offre</p>
              <p class="font-body text-xs leading-5 text-ink/55">
                Position approximative — l’adresse exacte est partagée après confirmation.
              </p>
            </div>

            <div class="overflow-hidden rounded-lg border border-ink/10 bg-surface">
              <OfferLocationMap :location="offer.location" />
            </div>
          </section>

          <section class="mt-7 border-t border-ink/10 pt-5">
            <OfferReviews :offer-id="offer.id" />
          </section>
        </div>
      </div>
    </div>

    <Teleport to="body">
      <Transition name="gallery-fade">
        <div
          v-if="galleryOpen && activeGalleryImage"
          ref="galleryDialog"
          class="fixed inset-0 z-[2000] grid h-[100dvh] grid-rows-[auto_minmax(0,1fr)_auto] bg-ink text-surface"
          role="dialog"
          aria-modal="true"
          aria-label="Galerie photos"
        >
          <header class="grid grid-cols-[1fr_auto_1fr] items-center px-4 py-3 sm:px-6">
            <button
              ref="galleryCloseButton"
              type="button"
              class="inline-flex h-10 w-10 items-center justify-center rounded-full transition hover:bg-surface/15 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-surface"
              aria-label="Fermer la galerie"
              @click="closeGallery"
            >
              <X :size="24" aria-hidden="true" />
            </button>
            <p class="font-body text-sm font-semibold tabular-nums" aria-live="polite">
              {{ selectedImageIndex + 1 }} / {{ galleryImages.length }}
            </p>
            <FavoriteButton
              :offer-id="offer?.id ?? 0"
              :size="20"
              class="justify-self-end border-surface/25 bg-ink text-surface hover:bg-surface/15"
            />
          </header>

          <div class="relative flex min-h-0 items-center justify-center px-4 sm:px-20">
            <button
              v-if="galleryImages.length > 1"
              type="button"
              class="absolute left-3 z-10 inline-flex h-11 w-11 items-center justify-center rounded-full border border-surface/20 bg-surface/10 transition hover:bg-surface/20 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-surface sm:left-6"
              aria-label="Photo précédente"
              @click="stepGallery(-1)"
            >
              <ChevronLeft :size="26" aria-hidden="true" />
            </button>

            <img
              :src="activeGalleryImage.resolvedUrl"
              :alt="`Photo ${selectedImageIndex + 1} de ${offer?.title ?? 'l’annonce'}`"
              data-full-gallery-image
              class="max-h-full max-w-full object-contain"
            />

            <button
              v-if="galleryImages.length > 1"
              type="button"
              class="absolute right-3 z-10 inline-flex h-11 w-11 items-center justify-center rounded-full border border-surface/20 bg-surface/10 transition hover:bg-surface/20 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-surface sm:right-6"
              aria-label="Photo suivante"
              @click="stepGallery(1)"
            >
              <ChevronRight :size="26" aria-hidden="true" />
            </button>
          </div>

          <div
            v-if="galleryImages.length > 1"
            class="mx-auto flex max-w-full gap-2 overflow-x-auto px-4 py-4 sm:gap-3"
            aria-label="Choisir une photo"
          >
            <button
              v-for="image in galleryImages"
              :key="image.id"
              type="button"
              class="h-14 w-20 shrink-0 overflow-hidden rounded-md border-2 transition sm:h-16 sm:w-24"
              :class="
                image.index === selectedImageIndex
                  ? 'border-surface opacity-100'
                  : 'border-transparent opacity-55 hover:opacity-90'
              "
              :aria-pressed="image.index === selectedImageIndex"
              :aria-label="`Afficher la photo ${image.index + 1}`"
              @click="selectedImageIndex = image.index"
            >
              <img
                :src="image.resolvedUrl"
                :alt="`Miniature de la photo ${image.index + 1}`"
                class="h-full w-full object-cover"
              />
            </button>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
.detail-layout {
  display: grid;
  grid-template-areas:
    'information'
    'supporting';
  gap: 2rem;
  align-items: start;
}

.detail-layout.has-booking {
  grid-template-areas:
    'information'
    'booking'
    'supporting';
}

.offer-information {
  grid-area: information;
}

.booking-panel {
  grid-area: booking;
  min-width: 0;
}

.offer-supporting-content {
  grid-area: supporting;
}

.offer-gallery {
  display: grid;
  grid-template-columns: minmax(0, 2fr) minmax(14rem, 1fr);
  grid-template-rows: repeat(2, minmax(0, 1fr));
  gap: 4px;
  height: clamp(18rem, 22vw, 19rem);
}

.offer-gallery .primary-image {
  grid-row: 1 / -1;
}

.offer-gallery.single-image {
  grid-template-columns: 1fr;
  grid-template-rows: 1fr;
}

.offer-detail-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  border: 1px solid color-mix(in srgb, var(--color-ink) 14%, transparent);
  border-radius: 0.375rem;
  padding: 0.25rem 0.625rem;
  background: var(--color-surface);
  color: color-mix(in srgb, var(--color-ink) 75%, transparent);
  font-family: var(--font-body);
  font-size: 0.75rem;
  line-height: 1.25rem;
}

.gallery-fade-enter-active,
.gallery-fade-leave-active {
  transition: opacity 180ms ease;
}

.gallery-fade-enter-from,
.gallery-fade-leave-to {
  opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
  .gallery-fade-enter-active,
  .gallery-fade-leave-active {
    transition: none;
  }
}

@media (max-width: 639px) {
  .offer-gallery {
    grid-template-columns: 1fr;
    grid-template-rows: 1fr;
    height: 19rem;
  }

  .offer-gallery .secondary-image {
    display: none;
  }
}

@media (min-width: 1024px) {
  .detail-layout.has-booking {
    grid-template-columns: minmax(0, 1fr) 23rem;
    grid-template-areas:
      'information booking'
      'supporting booking';
    column-gap: 2rem;
    row-gap: 1rem;
  }
}
</style>
