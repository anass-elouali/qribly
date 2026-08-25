import type { Category } from '@/types/offer'

export type ServiceRequestMissingField = 'category_id' | 'city' | 'desired_period' | 'at_home'

export interface ServiceRequestInterpretation {
  summary: string
  category_id: number | null
  category_name: string | null
  city: string | null
  desired_start_at: string | null
  desired_end_at: string | null
  budget_max: string | number | null
  at_home: boolean | null
  missing_fields: ServiceRequestMissingField[]
  questions: string[]
}

export interface ServiceRequestInterpretationResponse {
  data: ServiceRequestInterpretation
  meta: {
    interpreter: 'local' | 'openai' | 'groq'
  }
}

export interface ServiceRequest {
  id: number
  raw_text: string
  summary: string
  city: string
  desired_start_at: string
  desired_end_at: string
  budget_max: string | null
  at_home: boolean
  status: 'open' | 'fulfilled' | 'cancelled'
  expires_at: string
  category: Category
  proposals?: ServiceRequestProposal[]
  proposals_count?: number
  created_at: string
  updated_at: string
}

export interface ServiceRequestProposal {
  id: number
  service_request_id: number
  proposed_price: string
  scheduled_at: string
  message: string | null
  status: 'pending' | 'accepted' | 'declined' | 'withdrawn'
  provider?: {
    id: number
    name: string
  }
  offer?: {
    id: number
    title: string
    price: string
    city: string | null
    service_duration_minutes: number | null
  }
  created_at: string
  updated_at: string
}

export interface PublishServiceRequestPayload {
  raw_text: string
  summary: string
  category_id: number
  city: string
  desired_start_at: string
  desired_end_at: string
  budget_max: number | null
  at_home: boolean
}
