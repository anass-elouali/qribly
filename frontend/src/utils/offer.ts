export const statusLabel: Record<string, string> = {
  active: 'Actif',
  reserved: 'Réservé',
  sold: 'Vendu',
  inactive: 'Inactif',
}

export const statusColor: Record<string, string> = {
  active: 'bg-status-active',
  reserved: 'bg-status-reserved',
  sold: 'bg-status-sold',
  inactive: 'bg-ink/40',
}

export function formatPrice(value: string | number) {
  return new Intl.NumberFormat('fr-MA').format(Number(value))
}

export function formatDistance(meters: number) {
  return meters < 1000 ? `${Math.round(meters)} m` : `${(meters / 1000).toFixed(1)} km`
}
