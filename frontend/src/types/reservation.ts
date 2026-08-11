export interface Reservation {
  id: number
  scheduled_at: string
  status: 'pending' | 'confirmed' | 'cancelled' | 'completed'
  notes: string | null
  user?: { id: number; name: string }
  offer?: { id: number; title: string; type: 'product' | 'service'; price: string }
  created_at: string
  updated_at: string
}
