import type { Review } from '@/types/review'

export type ProviderReservationAction = 'confirm' | 'cancel' | 'complete'

export interface Reservation {
  id: number
  scheduled_at: string
  duration_minutes: number
  status: 'pending' | 'confirmed' | 'cancelled' | 'completed'
  notes: string | null
  user?: { id: number; name: string; email: string; created_at: string }
  offer?: {
    id: number
    title: string
    type: 'product' | 'service'
    price: string
    service_duration_minutes?: number | null
  }
  review?: Review | null
  created_at: string
  updated_at: string
}

export interface ProviderAvailabilityDay {
  day_of_week: number
  start_time: string
  end_time: string
}

export interface ProviderAvailabilityResponse {
  configured: boolean
  timezone: string
  days: ProviderAvailabilityDay[]
}

export interface OfferAvailabilitySlot {
  starts_at: string
  time: string
}

export interface OfferAvailabilityDay {
  date: string
  slots: OfferAvailabilitySlot[]
}

export interface OfferAvailabilityResponse {
  configured: boolean
  timezone: string
  duration_minutes: number
  days: OfferAvailabilityDay[]
}
