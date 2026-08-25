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
const MessagesView = () => import('@/views/messages/MessagesView.vue')
const ServiceRequestView = () => import('@/views/ServiceRequestView.vue')
const ServiceRequestTrackingView = () => import('@/views/ServiceRequestTrackingView.vue')

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      component: DefaultLayout,

      children: [
        {
          path: '',
          name: 'home',
          component: HomeView,
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
          path: 'demander-a-qrib',
          name: 'service-request-create',
          component: ServiceRequestView,
          meta: {
            requiresAuth: true,
          },
        },
        {
          path: 'demandes/:id',
          name: 'service-request-details',
          component: ServiceRequestTrackingView,
          meta: {
            requiresAuth: true,
          },
        },
        {
          path: 'profile',
          name: 'profile',
          component: ProfileView,
          meta: {
            requiresAuth: true,
          },
        },
        {
          path: 'messages',
          name: 'conversations',
          component: MessagesView,
          meta: {
            requiresAuth: true,
          },
        },
        {
          path: 'messages/:id',
          name: 'conversation',
          component: MessagesView,
          meta: {
            requiresAuth: true,
          },
        },
        {
          path: '/search',
          name: 'search',
          component: () => import('@/views/SearchView.vue'),
        },
      ],
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

router.beforeEach((to, from) => {
  const authStore = useAuthStore()

  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    return { name: 'login' }
  }

  if (to.meta.requiresGuest && authStore.isAuthenticated) {
    return { name: 'home' }
  }

  return true
})

export default router
