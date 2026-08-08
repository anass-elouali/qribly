import { defineStore } from "pinia";
import { ref,computed } from "vue";
import api from "@/services/api";

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
    const token = ref<string | null>(null)
    
    
    const isAuthenticated = computed(()=>{
        return token.value !== null
    })

    async function login(email: string, password: string){
        const response = await api.post<LoginResponse>('/login',{
            email,
            password,
        })
        
        user.value = response.data.user
        token.value = response.data.token
    }

    function logout() {
        user.value = null
        token.value = null
    }

    return {
        user,
        token,
        isAuthenticated,
        login,
        logout,
    }
})