import dayjs from 'dayjs'

import type { Reservation } from '@/types/reservation'
import { inAppTimeZone } from '@/utils/dateTime'

export const reservationStatusLabel: Record<string, string> = {
  pending: 'En attente',
  confirmed: 'Confirmée',
  cancelled: 'Annulée',
  completed: 'Terminée',
}

export const reservationStatusColor: Record<string, string> = {
  pending: 'bg-status-reserved',
  confirmed: 'bg-status-active',
  cancelled: 'bg-ink/40',
  completed: 'bg-primary',
}

export function formatReservationDuration(minutes: number | null | undefined) {
  const duration = minutes ?? 60

  if (duration < 60) {
    return `${duration} min`
  }

  const hours = Math.floor(duration / 60)
  const remainingMinutes = duration % 60

  return remainingMinutes ? `${hours} h ${remainingMinutes}` : `${hours} h`
}

export function reservationEndsAt(
  reservation: Pick<Reservation, 'scheduled_at' | 'duration_minutes'>,
) {
  return inAppTimeZone(reservation.scheduled_at).add(
    reservation.duration_minutes ?? 60,
    'minute',
  )
}

export function canCompleteReservation(
  reservation: Pick<Reservation, 'scheduled_at' | 'duration_minutes'>,
  nowMs = Date.now(),
) {
  return !reservationEndsAt(reservation).isAfter(dayjs(nowMs))
}
