export interface Category {
  id: number
  name: string
}

export interface Offer {
  id: number
  title: string
  description: string
  type: 'product' | 'service'
  price: string
  is_negotiable: boolean
  status: 'active' | 'reserved' | 'sold' | 'inactive'
  service_duration_minutes: number | null
  at_customer_location: boolean
  at_provider_location: boolean
  city: string | null
  location: { latitude: number; longitude: number } | null
  category?: Category
  owner?: { id: number; name: string }
  images?: { id: number; url: string }[]
  distance: number | null
  semantic_score?: number | null
  created_at: string
  updated_at: string
}

export interface PaginatedResponse<T> {
  data: T[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}
