import DefaultLayout from '@/layouts/DefaultLayout.vue'
import HomeView from '@/views/HomeView.vue'
import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const LoginView = () => import('@/views/auth/LoginView.vue')
const RegisterView = () => import('@/views/auth/RegisterView.vue')
const OfferDetailsView = () => import('@/views/offers/OfferDetailsView.vue')
const OfferFormView = () => import('@/views/offers/OfferFormView.vue')
const ProfileView = () => import('@/views/profile/ProfileView.vue')
const NearbyView = () => import('@/views/offers/NearbyView.vue')

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      component: DefaultLayout,

      children: [
        {
          path:'',
          name:'home',
          component:HomeView,
        },
        {
          path: 'offers/nouvelle',
          name: 'offer-create',
          component: OfferFormView,
          meta: {
            requiresAuth: true,
          },
        },
        {
          path: 'offers/:id/modifier',
          name: 'offer-edit',
          component: OfferFormView,
          meta: {
            requiresAuth: true,
          },
        },
        {
          path: 'offers/:id',
          name: 'offer-details',
          component: OfferDetailsView,
        },
        {
          path: 'pres-de-moi',
          name: 'nearby',
          component: NearbyView,
        },
        {
          path:'profile',
          name:'profile',
          component:ProfileView,
          meta: {
            requiresAuth:true,
          }
        }
      ]
    },
     {
      path: '/login',
      name: 'login',
      component: LoginView,
      meta: {
        requiresGuest: true,
      },
    },
    {
      path: '/register',
      name: 'register',
      component: RegisterView,
    },
   
    
  ],
})




router.beforeEach((to, from)=>{
  const authStore = useAuthStore()

  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    return {name: 'login'}
  }


  if (to.meta.requiresGuest && authStore.isAuthenticated) {
    return { name: 'home' }
  }

  return true
})

export default router
