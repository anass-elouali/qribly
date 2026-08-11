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
