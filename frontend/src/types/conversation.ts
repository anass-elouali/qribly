export interface Message {
  id: number
  conversation_id: number
  sender_id: number
  body: string
  read_at: string | null
  created_at: string
  updated_at: string
  sender?: { id: number; name: string }
}

export interface Conversation {
  id: number
  user_one_id: number
  user_two_id: number
  user_one?: { id: number; name: string }
  user_two?: { id: number; name: string }
  messages?: Message[]
  proposal_context?: {
    proposal_id: number
    service_request_id: number
    request_summary: string
    offer_title: string
    proposed_price: string
    scheduled_at: string
    message: string | null
    status: 'pending' | 'accepted' | 'declined' | 'withdrawn'
  } | null
  created_at: string
  updated_at: string
}
