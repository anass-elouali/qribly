<script setup lang="ts">
import { onMounted, onUnmounted, ref, watch } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import type { Offer } from '@/types/offer'

const props = defineProps<{
  location: NonNullable<Offer['location']>
}>()

const mapEl = ref<HTMLDivElement | null>(null)

let map: L.Map | null = null
let areaCircle: L.Circle | null = null
let centerMarker: L.Marker | null = null

const APPROXIMATE_RADIUS_METERS = 450

function approximatePosition() {
  return L.latLng(
    Math.round(props.location.latitude * 1000) / 1000,
    Math.round(props.location.longitude * 1000) / 1000,
  )
}

function drawApproximateArea() {
  if (!map) return

  const position = approximatePosition()

  areaCircle?.remove()
  centerMarker?.remove()

  map.setView(position, 14, {
    animate: false,
  })

  areaCircle = L.circle(position, {
    radius: APPROXIMATE_RADIUS_METERS,
    color: '#14495A',
    fillColor: '#14495A',
    fillOpacity: 0.14,
    opacity: 0.65,
    weight: 2,
  }).addTo(map)

  centerMarker = L.marker(position, {
    keyboard: false,
    interactive: false,
  }).addTo(map)

  map.fitBounds(areaCircle.getBounds(), {
    animate: false,
    padding: [24, 24],
  })
}

onMounted(() => {
  if (!mapEl.value) return

  map = L.map(mapEl.value, {
    scrollWheelZoom: false,
    zoomControl: true,
  })

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors',
    maxZoom: 19,
  }).addTo(map)

  drawApproximateArea()

  requestAnimationFrame(() => map?.invalidateSize())
})

watch(() => [props.location.latitude, props.location.longitude], drawApproximateArea)

onUnmounted(() => {
  map?.remove()
  map = null
})
</script>

<template>
  <div
    ref="mapEl"
    class="h-48 w-full bg-primary/5 sm:h-36"
    role="img"
    aria-label="Carte de la zone approximative de l’annonce"
  ></div>
</template>

<style scoped>
:deep(.leaflet-control-zoom a) {
  color: var(--color-primary);
}

:deep(.leaflet-control-attribution) {
  color: color-mix(in srgb, var(--color-ink) 55%, transparent);
  font-family: var(--font-body);
  font-size: 0.6rem;
}
</style>
