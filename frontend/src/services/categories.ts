import api from '@/services/api'
import type { Category } from '@/types/offer'

export async function fetchCategories(): Promise<Category[]> {
  const response = await api.get<{ data: Category[] }>('/categories')
  return response.data.data
}
