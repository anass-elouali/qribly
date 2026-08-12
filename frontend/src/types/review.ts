export interface Review {
  id: number
  rating: number
  comment: string | null
  user?: { id: number; name: string }
  offer?: { id: number; title: string }
  reservation_id: number
  created_at: string
  updated_at: string
}
