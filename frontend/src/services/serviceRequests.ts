import api from '@/services/api'
import type {
  PublishServiceRequestPayload,
  ServiceRequest,
  ServiceRequestInterpretationResponse,
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

export async function fetchServiceRequest(id: number): Promise<ServiceRequest> {
  const response = await api.get<{ data: ServiceRequest }>(`/service-requests/${id}`)

  return response.data.data
}
