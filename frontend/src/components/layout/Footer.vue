<script setup lang="ts">
import { RouterLink } from 'vue-router'
import QriblyLogo from '@/components/branding/QriblyLogo.vue'
</script>

<template>
  <footer class="border-t border-surface/10 bg-primary text-surface">
    <div
      class="mx-auto grid max-w-7xl gap-10 px-6 py-12 md:grid-cols-[1.2fr_0.8fr_0.8fr] lg:px-10 lg:py-16"
    >
      <div>
        <div class="inline-flex rounded-xl bg-surface px-2 py-1">
          <QriblyLogo :animated="false" :show-tagline="false" class="w-36" />
        </div>
        <p class="mt-4 max-w-sm text-sm leading-6 text-surface/65">
          Le lien simple entre les besoins du quotidien et les personnes capables d’y répondre,
          partout au Maroc.
        </p>
      </div>

      <nav aria-label="Découvrir Qribly">
        <p class="font-mono text-xs tracking-[0.16em] text-accent uppercase">Découvrir</p>
        <ul class="mt-4 space-y-3 text-sm text-surface/75">
          <li>
            <RouterLink :to="{ name: 'home' }" class="hover:text-surface">Accueil</RouterLink>
          </li>
          <li>
            <RouterLink :to="{ name: 'nearby' }" class="hover:text-surface">Près de moi</RouterLink>
          </li>
          <li>
            <RouterLink :to="{ name: 'service-request-create' }" class="hover:text-surface">
              Demander à Qrib
            </RouterLink>
          </li>
        </ul>
      </nav>

      <nav aria-label="Publier et gérer">
        <p class="font-mono text-xs tracking-[0.16em] text-accent uppercase">Participer</p>
        <ul class="mt-4 space-y-3 text-sm text-surface/75">
          <li>
            <RouterLink :to="{ name: 'offer-create' }" class="hover:text-surface">
              Publier une annonce
            </RouterLink>
          </li>
          <li>
            <RouterLink :to="{ name: 'conversations' }" class="hover:text-surface">
              Mes messages
            </RouterLink>
          </li>
          <li>
            <RouterLink :to="{ name: 'profile' }" class="hover:text-surface">Mon profil</RouterLink>
          </li>
        </ul>
      </nav>
    </div>

    <div class="border-t border-surface/10">
      <div
        class="mx-auto flex max-w-7xl flex-col gap-2 px-6 py-5 text-xs text-surface/55 sm:flex-row sm:items-center sm:justify-between lg:px-10"
      >
        <p>© 2026 Qribly — Tout près de vous</p>
        <p>Produits, services et demandes locales.</p>
      </div>
    </div>
  </footer>
</template>
