import { createApp } from 'vue'
import { createPinia } from 'pinia'
import dayjs from 'dayjs'
import relativeTime from 'dayjs/plugin/relativeTime'
import 'dayjs/locale/fr'
import App from './App.vue'
import router from './router'
import { useAuthStore } from '@/stores/auth'
import './style.css'

dayjs.extend(relativeTime)
dayjs.locale('fr')


const app = createApp(App)

const pinia = createPinia()

app.use(pinia)
app.use(router)

const authStore = useAuthStore(pinia)

await authStore.restoreAuthentication()

app.mount('#app')