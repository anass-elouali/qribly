import api from '@/services/api'
import type { PaginatedResponse } from '@/types/offer'
import type { Reservation } from '@/types/reservation'
import type {
  PublishServiceRequestPayload,
  ServiceRequest,
  ServiceRequestInterpretationResponse,
  ServiceRequestProposal,
} from '@/types/serviceRequest'

export async function interpretServiceRequest(
  rawText: string,
): Promise<ServiceRequestInterpretationResponse> {
  const response = await api.post<ServiceRequestInterpretationResponse>(
    '/assistant/interpret-service-request',
    { raw_text: rawText },
  )

  return response.data
}

export async function publishServiceRequest(
  payload: PublishServiceRequestPayload,
): Promise<ServiceRequest> {
  const response = await api.post<{ data: ServiceRequest }>('/service-requests', payload)

  return response.data.data
}

export async function fetchServiceRequests(): Promise<ServiceRequest[]> {
  const response = await api.get<PaginatedResponse<ServiceRequest>>('/service-requests')

  return response.data.data
}

export async function fetchServiceRequest(id: number): Promise<ServiceRequest> {
  const response = await api.get<{ data: ServiceRequest }>(`/service-requests/${id}`)

  return response.data.data
}

export interface UpsertServiceRequestProposalPayload {
  offer_id: number
  proposed_price: number
  scheduled_at: string
  message?: string
}

export async function upsertServiceRequestProposal(
  serviceRequestId: number,
  payload: UpsertServiceRequestProposalPayload,
): Promise<ServiceRequestProposal> {
  const response = await api.put<{ data: ServiceRequestProposal }>(
    `/provider/service-requests/${serviceRequestId}/proposal`,
    payload,
  )

  return response.data.data
}

export async function withdrawServiceRequestProposal(
  proposalId: number,
): Promise<ServiceRequestProposal> {
  const response = await api.patch<{ data: ServiceRequestProposal }>(
    `/provider/service-request-proposals/${proposalId}/withdraw`,
  )

  return response.data.data
}

export async function acceptServiceRequestProposal(proposalId: number): Promise<Reservation> {
  const response = await api.post<{ data: Reservation }>(
    `/service-request-proposals/${proposalId}/accept`,
  )

  return response.data.data
}

export async function declineServiceRequestProposal(
  proposalId: number,
): Promise<ServiceRequestProposal> {
  const response = await api.patch<{ data: ServiceRequestProposal }>(
    `/service-request-proposals/${proposalId}/decline`,
  )

  return response.data.data
}
