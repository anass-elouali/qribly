export interface CityOption {
  name: string
  latitude: number
  longitude: number
}

export const moroccanCities: CityOption[] = [
  { name: 'Agadir', latitude: 30.4278, longitude: -9.5981 },
  { name: 'Casablanca', latitude: 33.5731, longitude: -7.5898 },
  { name: 'Essaouira', latitude: 31.5085, longitude: -9.7595 },
  { name: 'Fès', latitude: 34.0181, longitude: -5.0078 },
  { name: 'Marrakech', latitude: 31.6295, longitude: -7.9811 },
  { name: 'Meknès', latitude: 33.8935, longitude: -5.5473 },
  { name: 'Oujda', latitude: 34.6814, longitude: -1.9086 },
  { name: 'Ourika', latitude: 31.3742, longitude: -7.7778 },
  { name: 'Rabat', latitude: 34.0209, longitude: -6.8416 },
  { name: 'Tanger', latitude: 35.7595, longitude: -5.834 },
  { name: 'Tétouan', latitude: 35.5889, longitude: -5.3626 },
]

export function normalizeCity(value: string): string {
  return value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .trim()
    .toLowerCase()
}

export function cityByName(name: string | null | undefined): CityOption | null {
  if (!name) return null

  const normalizedName = normalizeCity(name)

  return moroccanCities.find((city) => normalizeCity(city.name) === normalizedName) ?? null
}

export function nearestCity(latitude: number, longitude: number): CityOption {
  const longitudeScale = Math.cos((latitude * Math.PI) / 180)

  return moroccanCities.reduce((nearest, city) => {
    const nearestLatitudeDelta = latitude - nearest.latitude
    const nearestLongitudeDelta = (longitude - nearest.longitude) * longitudeScale
    const cityLatitudeDelta = latitude - city.latitude
    const cityLongitudeDelta = (longitude - city.longitude) * longitudeScale
    const nearestDistance = nearestLatitudeDelta ** 2 + nearestLongitudeDelta ** 2
    const cityDistance = cityLatitudeDelta ** 2 + cityLongitudeDelta ** 2

    return cityDistance < nearestDistance ? city : nearest
  })
}
