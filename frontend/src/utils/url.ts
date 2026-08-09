const API_ORIGIN = String(import.meta.env.VITE_API_URL).replace(/\/api\/?$/, '')

export function resolveStorageUrl(path: string): string {
  return `${API_ORIGIN}${path}`
}
