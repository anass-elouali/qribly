import DefaultLayout from '@/layouts/DefaultLayout.vue'
import HomeView from '@/views/HomeView.vue'
import { createRouter, createWebHistory } from 'vue-router'

const LoginView = () => import('@/views/LoginView.vue')
const RegisterView = () => import('@/views/RegisterView.vue')
const OfferDetailsView = () => import('@/views/OfferDetailsView.vue')

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
          path: 'offers/:id',
          name: 'offer-details',
          component: OfferDetailsView,
        },
      ]
    },
     {
      path: '/login',
      name: 'login',
      component: LoginView,
    },
    {
      path: '/register',
      name: 'register',
      component: RegisterView,
    },
   
    
  ],
})

export default router
