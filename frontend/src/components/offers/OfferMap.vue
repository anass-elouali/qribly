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

type LocatedOffer = Offer & {
  location: NonNullable<Offer['location']>
}

const CLUSTER_PIXEL_RADIUS = 64

function escapeHtml(value: string) {
  const div = document.createElement('div')
  div.textContent = value
  return div.innerHTML
}

function offerPreviewIcon(offer: Offer) {
  const image = offer.images?.[0]

  const imageHtml = image
    ? `
      <img
        src="${escapeHtml(resolveStorageUrl(image.url))}"
        alt=""
        class="offer-map-card-image"
      />
    `
    : `
      <div class="offer-map-card-image offer-map-card-image-placeholder">
        Photo
      </div>
    `

  const title = escapeHtml(offer.title)

  const price = `${formatPrice(offer.price)} DH`

  return L.divIcon({
    className: 'offer-map-card-icon',
    html: `
      <div
        class="offer-map-card"
        data-offer-id="${offer.id}"
      >
        ${imageHtml}

        <div class="offer-map-card-content">
          <p class="offer-map-card-title">
            ${title}
          </p>

          <p class="offer-map-card-price">
            ${escapeHtml(price)}
          </p>
        </div>
      </div>
    `,
    iconSize: [108, 132],
    iconAnchor: [54, 132],
  })
}

function clusterIcon(count: number) {
  return L.divIcon({
    className: 'offer-cluster-icon',
    html: `
      <div class="offer-cluster">
        ${count}
      </div>
    `,
    iconSize: [40, 40],
    iconAnchor: [20, 20],
  })
}

function clusterOffers(offers: LocatedOffer[]): LocatedOffer[][] {
  if (!map) {
    return offers.map((offer) => [offer])
  }

  const points = offers.map((offer) => ({
    offer,
    point: map!.latLngToContainerPoint([
      offer.location.latitude,
      offer.location.longitude,
    ]),
  }))

  const clusters: LocatedOffer[][] = []
  const used = new Set<number>()

  for (let i = 0; i < points.length; i++) {
    if (used.has(i)) continue

    const seed = points[i]

    if (!seed) continue

    const cluster: LocatedOffer[] = [seed.offer]

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

  const located = props.offers.filter(
    (offer): offer is LocatedOffer => offer.location != null,
  )

  for (const cluster of clusterOffers(located)) {
    if (cluster.length === 1) {
      const offer = cluster[0]

      if (!offer) continue

      const marker = L.marker(
        [
          offer.location.latitude,
          offer.location.longitude,
        ],
        {
          icon: offerPreviewIcon(offer),
          zIndexOffset: 100,
        },
      )

      marker.on('mouseover', () => {
        const element = marker.getElement()

        if (!element) return

        const card = element.querySelector(
          '.offer-map-card',
        ) as HTMLElement | null

        if (!card) return

        card.classList.add('is-hovered')

        marker.setZIndexOffset(10000)
      })

      marker.on('mouseout', () => {
        const element = marker.getElement()

        if (!element) return

        const card = element.querySelector(
          '.offer-map-card',
        ) as HTMLElement | null

        if (!card) return

        card.classList.remove('is-hovered')

        marker.setZIndexOffset(100)
      })

      marker.on('click', () => {
        emit('select', offer.id)
      })

      marker.addTo(markersLayer)

      continue
    }

    const lat =
      cluster.reduce(
        (sum, offer) => sum + offer.location.latitude,
        0,
      ) / cluster.length

    const lng =
      cluster.reduce(
        (sum, offer) => sum + offer.location.longitude,
        0,
      ) / cluster.length

    const clusterMarker = L.marker([lat, lng], {
      icon: clusterIcon(cluster.length),
      zIndexOffset: 500,
    })

    clusterMarker.on('click', () => {
      map!.setView(
        [lat, lng],
        Math.min(map!.getZoom() + 3, 18),
        {
          animate: true,
        },
      )
    })

    clusterMarker.addTo(markersLayer)
  }
}

onMounted(() => {
  if (!mapEl.value) return

  map = L.map(mapEl.value).setView(
    [
      props.center.latitude,
      props.center.longitude,
    ],
    13,
  )

  L.tileLayer(
    'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
    {
      attribution: '&copy; OpenStreetMap contributors',
      maxZoom: 19,
    },
  ).addTo(map)

  radiusCircle = L.circle(
    [
      props.center.latitude,
      props.center.longitude,
    ],
    {
      radius: props.radiusKm * 1000,
      color: '#14495A',
      fillColor: '#14495A',
      fillOpacity: 0.08,
      weight: 2,
    },
  ).addTo(map)

  markersLayer = L.layerGroup().addTo(map)

  renderMarkers()

  map.on('moveend zoomend resize', renderMarkers)
})

watch(
  () => props.offers,
  () => {
    renderMarkers()
  },
)

watch(
  () => props.radiusKm,
  (newRadius) => {
    radiusCircle?.setRadius(newRadius * 1000)
  },
)

onUnmounted(() => {
  map?.remove()
  map = null
})
</script>

<template>
  <div
    ref="mapEl"
    class="h-[500px] w-full rounded-md"
  ></div>
</template>

<style scoped>
:deep(.offer-map-card-icon) {
  background: transparent;
  border: 0;
  overflow: visible;
}

:deep(.offer-map-card) {
  width: 108px;
  overflow: hidden;

  border: 2px solid var(--color-surface);
  border-radius: 10px;

  background: var(--color-surface);

  box-shadow:
    0 2px 5px rgb(0 0 0 / 0.12),
    0 5px 12px rgb(0 0 0 / 0.08);

  transform-origin: bottom center;

  transition:
    width 180ms ease,
    transform 180ms ease,
    box-shadow 180ms ease;
}

:deep(.offer-map-card:hover),
:deep(.offer-map-card.is-hovered) {
  width: 132px;

  transform:
    translateY(-8px)
    scale(1.05);

  box-shadow:
    0 8px 20px rgb(0 0 0 / 0.2),
    0 2px 6px rgb(0 0 0 / 0.12);
}

:deep(.offer-map-card-image) {
  display: block;

  width: 100%;
  height: 72px;

  object-fit: cover;

  background: var(--color-primary);
}

:deep(.offer-map-card-image-placeholder) {
  display: flex;

  align-items: center;
  justify-content: center;

  color: color-mix(
    in srgb,
    var(--color-surface) 70%,
    transparent
  );

  font-family: var(--font-mono);
  font-size: 0.55rem;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

:deep(.offer-map-card-content) {
  padding: 6px 8px 7px;
}

:deep(.offer-map-card-title) {
  margin: 0;

  overflow: hidden;

  color: var(--color-ink);

  font-family: var(--font-body);
  font-size: 0.7rem;
  font-weight: 600;

  line-height: 1.1;

  text-overflow: ellipsis;
  white-space: nowrap;
}

:deep(.offer-map-card-price) {
  margin: 3px 0 0;

  color: var(--color-primary);

  font-family: var(--font-mono);
  font-size: 0.65rem;
  font-weight: 700;

  line-height: 1;
}

:deep(.offer-cluster-icon) {
  background: transparent;
  border: 0;
}

:deep(.offer-cluster) {
  display: flex;

  width: 40px;
  height: 40px;

  align-items: center;
  justify-content: center;

  border: 2px solid var(--color-surface);
  border-radius: 9999px;

  background: var(--color-primary);

  color: var(--color-surface);

  font-family: var(--font-mono);
  font-size: 0.75rem;
  font-weight: 700;

  box-shadow:
    0 3px 8px rgb(0 0 0 / 0.18);
}

:deep(.leaflet-marker-icon) {
  overflow: visible;
}

:deep(.leaflet-pane) {
  overflow: visible;
}
</style>