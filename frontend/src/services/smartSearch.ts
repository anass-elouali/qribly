import api from '@/services/api'
import type { Offer } from '@/types/offer'

export class LocationUnavailableError extends Error {
  constructor(message: string) {
    super(message)
    this.name = 'LocationUnavailableError'
  }
}

export interface SmartSearchMeta {
  semantic_ranking: boolean
  candidate_count: number
}

interface SmartSearchResponse {
  data: Offer[]
  meta: SmartSearchMeta
}

interface Coordinates {
  latitude: number
  longitude: number
}

function getCurrentPosition(): Promise<Coordinates> {
  if (!navigator.geolocation) {
    return Promise.reject(
      new LocationUnavailableError('La géolocalisation n’est pas disponible sur cet appareil.'),
    )
  }

  return new Promise((resolve, reject) => {
    navigator.geolocation.getCurrentPosition(
      (position) => {
        resolve({
          latitude: position.coords.latitude,
          longitude: position.coords.longitude,
        })
      },
      () => {
        reject(
          new LocationUnavailableError('La position n’a pas été partagée.'),
        )
      },
      {
        enableHighAccuracy: false,
        maximumAge: 60_000,
        timeout: 10_000,
      },
    )
  })
}

export async function smartSearchOffers(query: string): Promise<SmartSearchResponse> {
  const coordinates = await getCurrentPosition()

  const response = await api.get<SmartSearchResponse>('/offers/smart-search', {
    params: {
      query,
      latitude: coordinates.latitude,
      longitude: coordinates.longitude,
      radius: 10,
      limit: 10,
    },
  })

  return response.data
}
