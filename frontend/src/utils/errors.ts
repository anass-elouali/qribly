import { isAxiosError } from 'axios'

export function extractErrorMessage(error: unknown, fallback: string): string {
  if (isAxiosError(error)) {
    if (error.code === 'ECONNABORTED' || error.code === 'ETIMEDOUT') {
      return 'Le serveur met trop de temps à répondre. Réessaie dans un instant.'
    }

    if (!error.response) {
      return 'Connexion au serveur impossible. Vérifie ta connexion puis réessaie.'
    }

    const data = error.response?.data as
      { message?: string; errors?: Record<string, string[]> } | undefined

    const firstFieldError = data?.errors ? Object.values(data.errors)[0]?.[0] : undefined

    return firstFieldError ?? data?.message ?? fallback
  }

  return fallback
}

export function isNotFoundError(error: unknown): boolean {
  return isAxiosError(error) && error.response?.status === 404
}
