import api from '@/services/api'
import type { Category } from '@/types/offer'

export async function fetchCategories(): Promise<Category[]> {
  try {
    const response = await api.get<{ data: Category[] }>('/categories')
    return response.data.data
  } catch {
    return []
  }
}
