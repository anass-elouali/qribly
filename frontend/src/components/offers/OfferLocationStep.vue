<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

export interface OfferLocationData {
  latitude: number | null
  longitude: number | null
}

const props = defineProps<{
  modelValue: OfferLocationData
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: OfferLocationData): void
  (e: 'back'): void
  (e: 'submit'): void
}>()

const mapContainer = ref<HTMLElement | null>(null)

const locating = ref(false)
const error = ref('')

let map: L.Map | null = null
let marker: L.Marker | null = null

const defaultCenter: L.LatLngExpression = [31.6295, -7.9811]

function updateLocation(latitude: number, longitude: number) {
  error.value = ''

  emit('update:modelValue', {
    latitude,
    longitude,
  })

  if (!map) {
    return
  }

  const position: L.LatLngExpression = [latitude, longitude]

  if (!marker) {
    marker = L.marker(position).addTo(map)
  } else {
    marker.setLatLng(position)
  }

  map.setView(position, 15)
}

function useCurrentLocation() {
  if (!navigator.geolocation) {
    error.value =
      "La géolocalisation n'est pas disponible sur cet appareil."

    return
  }

  error.value = ''
  locating.value = true

  navigator.geolocation.getCurrentPosition(
    (position) => {
      updateLocation(
        position.coords.latitude,
        position.coords.longitude,
      )

      locating.value = false
    },
    () => {
      error.value =
        "Impossible de récupérer votre position. Vérifiez l'autorisation de géolocalisation."

      locating.value = false
    },
    {
      enableHighAccuracy: true,
      timeout: 10000,
      maximumAge: 0,
    },
  )
}

function handleMapClick(event: L.LeafletMouseEvent) {
  updateLocation(
    event.latlng.lat,
    event.latlng.lng,
  )
}

async function initializeMap() {
  await nextTick()

  if (!mapContainer.value) {
    return
  }

  if (map) {
    map.remove()
    map = null
  }

  let center: L.LatLngExpression = defaultCenter
  let zoom = 12

  if (
    props.modelValue.latitude !== null &&
    props.modelValue.longitude !== null
  ) {
    center = [
      props.modelValue.latitude,
      props.modelValue.longitude,
    ]

    zoom = 15
  }

  map = L.map(mapContainer.value, {
    zoomControl: true,
  }).setView(center, zoom)

  L.tileLayer(
    'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
    {
      attribution: '&copy; OpenStreetMap contributors',
      maxZoom: 19,
    },
  ).addTo(map)

  map.on('click', handleMapClick)

  if (
    props.modelValue.latitude !== null &&
    props.modelValue.longitude !== null
  ) {
    marker = L.marker([
      props.modelValue.latitude,
      props.modelValue.longitude,
    ]).addTo(map)
  }

  setTimeout(() => {
    map?.invalidateSize()
  }, 100)
}

watch(
  () => [
    props.modelValue.latitude,
    props.modelValue.longitude,
  ],
  ([latitude, longitude]) => {
    if (
      latitude === null ||
      longitude === null ||
      !map
    ) {
      return
    }

    const position: L.LatLngExpression = [
      latitude,
      longitude,
    ]

    if (!marker) {
      marker = L.marker(position).addTo(map)
    } else {
      marker.setLatLng(position)
    }

    map.setView(position, 15)
  },
)

onMounted(() => {
  initializeMap()
})

onBeforeUnmount(() => {
  if (map) {
    map.remove()
    map = null
  }

  marker = null
})
</script>

<template>
  <section class="space-y-6">

    <!-- Header -->
    <div>
      <p
        class="mb-2 font-mono text-xs tracking-widest
               text-ink/40 uppercase"
      >
        Étape 3 sur 3
      </p>

      <h2
        class="font-display text-2xl font-bold text-primary"
      >
        Où se trouve votre annonce ?
      </h2>

      <p
        class="mt-2 max-w-xl text-sm leading-relaxed text-ink/60"
      >
        Indiquez l'emplacement de votre produit ou service.
        Vous pouvez utiliser votre position actuelle ou
        sélectionner directement un point sur la carte.
      </p>
    </div>

    <!-- Current location -->
    <button
      type="button"
      :disabled="locating"
      class="w-full rounded-xl border border-ink/10
             bg-surface px-5 py-4
             font-semibold text-ink transition
             hover:border-primary hover:bg-primary/5
             disabled:cursor-not-allowed
             disabled:opacity-60"
      @click="useCurrentLocation"
    >
      {{
        locating
          ? 'Recherche de votre position…'
          : 'Utiliser ma position actuelle'
      }}
    </button>

    <!-- Error -->
    <div
      v-if="error"
      class="rounded-xl bg-status-reserved/10
             px-4 py-3 text-sm
             text-status-reserved"
    >
      {{ error }}
    </div>

    <!-- Map -->
    <div
      class="overflow-hidden rounded-2xl
             border border-ink/10
             bg-surface shadow-sm"
    >
      <div
        ref="mapContainer"
        class="h-[420px] w-full"
      ></div>
    </div>

    <!-- Instructions -->
    <div
      class="rounded-xl border border-ink/10
             bg-surface px-4 py-4"
    >
      <p class="text-sm font-semibold text-ink">
        Choisissez un emplacement
      </p>

      <p
        class="mt-1 text-xs leading-relaxed
               text-ink/50"
      >
        Cliquez directement sur la carte pour placer
        l'emplacement de votre annonce.
      </p>
    </div>

    <!-- Selected location -->
    <div
      v-if="
        modelValue.latitude !== null &&
        modelValue.longitude !== null
      "
      class="rounded-xl bg-primary/5 px-4 py-4"
    >
      <p
        class="font-mono text-xs tracking-wide
               text-ink/40 uppercase"
      >
        Emplacement sélectionné
      </p>

      <p
        class="mt-1 font-mono text-sm
               text-primary"
      >
        {{ modelValue.latitude.toFixed(6) }},
        {{ modelValue.longitude.toFixed(6) }}
      </p>
    </div>

    <!-- Navigation -->
    <div
      class="flex items-center
             justify-between pt-2"
    >
      <button
        type="button"
        class="rounded-xl border border-ink/15
               px-5 py-3
               text-sm font-semibold
               text-ink transition
               hover:border-primary
               hover:text-primary"
        @click="emit('back')"
      >
        Retour
      </button>

      <button
        type="button"
        :disabled="
          modelValue.latitude === null ||
          modelValue.longitude === null
        "
        class="rounded-xl bg-accent
               px-6 py-3
               text-sm font-semibold
               text-ink transition
               hover:opacity-90
               disabled:cursor-not-allowed
               disabled:opacity-50"
        @click="emit('submit')"
      >
        Publier l'annonce
      </button>
    </div>

  </section>
</template>