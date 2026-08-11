import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import api from '@/services/api'
import { useFavoritesStore } from '@/stores/favorites'

interface User {
  id: number
  name: string
  email: string
  email_verified_at: string | null
  created_at: string
  updated_at: string
}

interface LoginResponse {
  message: string
  user: User
  token: string
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)

  const token = ref<string | null>(
    localStorage.getItem('qribly_token')
  )

  const isAuthenticated = computed(() => {
    return token.value !== null
  })

  async function login(email: string, password: string) {
    const response = await api.post<LoginResponse>('/login', {
      email,
      password,
    })

    user.value = response.data.user
    token.value = response.data.token

    localStorage.setItem(
      'qribly_token',
      response.data.token
    )
  }

  async function register(name: string, email: string, password: string, passwordConfirmation: string) {
    const response = await api.post<LoginResponse>('/register', {
      name,
      email,
      password,
      password_confirmation: passwordConfirmation,
    })

    user.value = response.data.user
    token.value = response.data.token

    localStorage.setItem(
      'qribly_token',
      response.data.token
    )
  }

  async function restoreAuthentication() {
    if (!token.value) {
      return
    }

    try {
      const response = await api.get<User>('/user')

      user.value = response.data
    } catch (error) {
      token.value = null
      user.value = null

      localStorage.removeItem('qribly_token')
    }
  }

  async function logout() {
    try {
      if (token.value) {
        await api.post('/logout')
      }
    } finally {
      token.value = null
      user.value = null

      localStorage.removeItem('qribly_token')
      useFavoritesStore().reset()
    }
  }

  return {
    user,
    token,
    isAuthenticated,
    login,
    register,
    restoreAuthentication,
    logout,
  }
})