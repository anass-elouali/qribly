<script setup lang="ts">
import { onMounted, onUnmounted, ref, watch } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import type { Offer } from '@/types/offer'
import { formatPrice } from '@/utils/offer'
import { resolveStorageUrl } from '@/utils/url'

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
    html: `<div class="whitespace-nowrap rounded-full border-2 border-surface bg-accent px-2.5 py-1 font-mono text-xs font-bold text-ink shadow-md">${formatPrice(offer.price)} DH</div>`,
    iconAnchor: [22, 14],
  })
}

function escapeHtml(value: string) {
  const div = document.createElement('div')
  div.textContent = value
  return div.innerHTML
}

function previewHtml(offer: Offer) {
  const image = offer.images?.[0]
  const thumb = image
    ? `<img src="${escapeHtml(resolveStorageUrl(image.url))}" alt="" class="h-16 w-24 rounded object-cover" />`
    : `<div class="flex h-16 w-24 items-center justify-center rounded bg-primary font-mono text-[0.6rem] tracking-wide text-surface/70 uppercase">Photo</div>`

  const category = offer.category
    ? `<p class="truncate font-mono text-[0.6rem] tracking-wide text-ink/50 uppercase">${escapeHtml(offer.category.name)}</p>`
    : ''

  return `
    <div class="flex w-56 items-center gap-2 rounded-md border border-ink/10 bg-surface p-2 shadow-lg">
      ${thumb}
      <div class="min-w-0 flex-1">
        ${category}
        <p class="truncate font-body text-sm font-semibold text-ink">${escapeHtml(offer.title)}</p>
      </div>
    </div>
  `
}

function clusterIcon(count: number) {
  return L.divIcon({
    className: '',
    html: `<div class="flex h-10 w-10 items-center justify-center rounded-full border-2 border-surface bg-primary font-mono text-sm font-bold text-surface shadow-md">${count}</div>`,
    iconAnchor: [20, 20],
  })
}

const CLUSTER_PIXEL_RADIUS = 64

type LocatedOffer = Offer & { location: NonNullable<Offer['location']> }

function clusterOffers(offers: LocatedOffer[]): LocatedOffer[][] {
  if (!map) return offers.map((offer) => [offer])

  const points = offers.map((offer) => ({
    offer,
    point: map!.latLngToContainerPoint([offer.location.latitude, offer.location.longitude]),
  }))

  const clusters: LocatedOffer[][] = []
  const used = new Set<number>()

  for (let i = 0; i < points.length; i++) {
    if (used.has(i)) continue
    const seed = points[i]
    if (!seed) continue

    const cluster = [seed.offer]
    used.add(i)

    for (let j = i + 1; j < points.length; j++) {
      if (used.has(j)) continue
      const candidate = points[j]
      if (!candidate) continue

      if (seed.point.distanceTo(candidate.point) < CLUSTER_PIXEL_RADIUS) {
        cluster.push(candidate.offer)
        used.add(j)
      }
    }

    clusters.push(cluster)
  }

  return clusters
}

function renderMarkers() {
  if (!markersLayer || !map) return
  markersLayer.clearLayers()

  const located = props.offers.filter((offer): offer is LocatedOffer => offer.location != null)

  for (const cluster of clusterOffers(located)) {
    if (cluster.length === 1) {
      const offer = cluster[0]
      if (!offer) continue

      const marker = L.marker([offer.location.latitude, offer.location.longitude], {
        icon: priceIcon(offer),
      })
      marker.bindTooltip(previewHtml(offer), {
        direction: 'top',
        offset: [0, -16],
        opacity: 1,
        className: 'offer-preview-tooltip',
      })
      marker.on('click', () => emit('select', offer.id))
      marker.addTo(markersLayer)
      continue
    }

    const lat = cluster.reduce((sum, offer) => sum + offer.location.latitude, 0) / cluster.length
    const lng = cluster.reduce((sum, offer) => sum + offer.location.longitude, 0) / cluster.length

    const clusterMarker = L.marker([lat, lng], { icon: clusterIcon(cluster.length) })
    clusterMarker.on('click', () => {
      map!.setView([lat, lng], Math.min(map!.getZoom() + 3, 18))
    })
    clusterMarker.addTo(markersLayer)
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

  map.on('zoomend', renderMarkers)
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

<style scoped>
:deep(.offer-preview-tooltip) {
  background: transparent;
  border: none;
  box-shadow: none;
  padding: 0;
}

:deep(.offer-preview-tooltip::before) {
  display: none;
}
</style>