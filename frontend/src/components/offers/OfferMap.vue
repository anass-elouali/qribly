<script setup lang="ts">
import { onMounted, onUnmounted, ref, watch } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import type { Offer } from '@/types/offer'
import { formatPrice } from '@/utils/offer'

const props = defineProps<{
  center: { latitude: number; longitude: number }
  radiusKm: number
  offers: Offer[]
}>()

const emit = defineEmits<{
  select: [offerId: number]
}>()

const mapEl = ref<HTMLDivElement | null>(null)
let map: L.Map | null = null
let radiusCircle: L.Circle | null = null
let markersLayer: L.LayerGroup | null = null

function priceIcon(offer: Offer) {
  return L.divIcon({
    className: '',
    html: `<div class="whitespace-nowrap rounded bg-accent px-2 py-1 font-mono text-xs font-bold text-ink shadow-md">${formatPrice(offer.price)} DH</div>`,
    iconAnchor: [20, 12],
  })
}

function renderMarkers() {
  if (!markersLayer) return
  markersLayer.clearLayers()

  for (const offer of props.offers) {
    if (!offer.location) continue

    const marker = L.marker([offer.location.latitude, offer.location.longitude], {
      icon: priceIcon(offer),
    })
    marker.on('click', () => emit('select', offer.id))
    marker.addTo(markersLayer)
  }
}

onMounted(() => {
  if (!mapEl.value) return

  map = L.map(mapEl.value).setView([props.center.latitude, props.center.longitude], 13)

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors',
    maxZoom: 19,
  }).addTo(map)

  radiusCircle = L.circle([props.center.latitude, props.center.longitude], {
    radius: props.radiusKm * 1000,
    color: '#14495A',
    fillColor: '#14495A',
    fillOpacity: 0.08,
  }).addTo(map)

  markersLayer = L.layerGroup().addTo(map)
  renderMarkers()
})

watch(() => props.offers, renderMarkers)

watch(
  () => props.radiusKm,
  (newRadius) => {
    radiusCircle?.setRadius(newRadius * 1000)
  },
)

onUnmounted(() => {
  map?.remove()
})
</script>

<template>
  <div ref="mapEl" class="h-[500px] w-full rounded-md"></div>
</template>