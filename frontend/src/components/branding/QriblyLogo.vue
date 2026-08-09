<script setup lang="ts">
withDefaults(
  defineProps<{
    animated?: boolean
    showTagline?: boolean
  }>(),
  {
    animated: true,
    showTagline: true,
  },
)
</script>

<template>
  <div class="qribly-logo" :class="{ 'is-animated': animated }">
    <svg viewBox="0 0 650 300" role="img" aria-label="Qribly — tout près de vous">
      <g aria-hidden="true">
        <path class="connection connection-one" d="M219 128 C178 102 145 83 111 74" />
        <path class="connection connection-two" d="M217 177 C169 201 139 225 104 240" />
        <path class="connection connection-three" d="M269 119 C303 88 330 69 365 57" />

        <circle class="pulse pulse-one" cx="245" cy="151" r="54" />
        <circle class="pulse pulse-two" cx="245" cy="151" r="76" />

        <!-- Produit -->
        <g class="node node-one">
          <circle class="node-background" cx="100" cy="70" r="28" />
          <path
            class="node-symbol"
            d="
              M89 63
              L100 57
              L111 63
              L111 76
              L100 82
              L89 76
              Z

              M100 69 L111 63
              M100 69 L89 63
              M100 69 L100 82
            "
          />
        </g>

        <!-- Utilisateur / communauté -->
        <g class="node node-two">
          <circle class="node-background" cx="94" cy="245" r="28" />
          <circle class="node-symbol" cx="94" cy="237" r="6" />
          <path class="node-symbol" d="M82 258 C84 248 104 248 106 258" />
        </g>

        <!-- Service -->
        <g class="node node-three">
          <circle class="node-background" cx="376" cy="54" r="28" />
          <path
            class="node-symbol"
            d="
              M364 64 L384 44
              M365 45 L375 55
              M374 63 L383 54
            "
          />
        </g>

        <!-- Repère géographique : le pin fait déjà office de Q -->
        <g class="logo-mark">
          <path
            class="pin"
            d="
              M245 70
              C200 70 173 103 173 142
              C173 198 245 248 245 248
              C245 248 317 198 317 142
              C317 103 290 70 245 70
              Z
            "
          />
          <circle class="q-circle" cx="239" cy="137" r="29" />
          <path class="q-tail" d="M260 158 L282 180" />
          <circle class="user-point" cx="291" cy="188" r="8" />
        </g>

        <g class="word-group">
          <text class="wordmark" x="331" y="184">ribly</text>
        </g>

        <text v-if="showTagline" class="tagline" x="335" y="224">TOUT PRÈS DE VOUS</text>
      </g>
    </svg>
  </div>
</template>

<style scoped>
svg {
  display: block;
  width: 100%;
  height: auto;
  overflow: visible;
}

.connection {
  fill: none;
  stroke: color-mix(in srgb, var(--color-ink) 15%, transparent);
  stroke-width: 2;
  stroke-linecap: round;
  stroke-dasharray: 240;
  stroke-dashoffset: 0;
}

.pulse {
  fill: none;
  stroke: var(--color-accent);
  stroke-width: 2;
  opacity: 0;
  transform-box: fill-box;
  transform-origin: center;
}

.node {
  transform-box: fill-box;
  transform-origin: center;
}

.node-background {
  fill: var(--color-surface);
  stroke: color-mix(in srgb, var(--color-ink) 15%, transparent);
  stroke-width: 2;
}

.node-symbol {
  fill: none;
  stroke: var(--color-accent);
  stroke-width: 3;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.logo-mark {
  transform-box: fill-box;
  transform-origin: center;
}

.pin {
  fill: var(--color-primary);
}

.q-circle,
.q-tail {
  fill: none;
  stroke: var(--color-surface);
  stroke-width: 9;
  stroke-linecap: round;
}

.q-tail {
  stroke-dasharray: 38;
  stroke-dashoffset: 0;
}

.user-point {
  fill: var(--color-accent);
  stroke: var(--color-surface);
  stroke-width: 4;
}

.wordmark {
  fill: var(--color-ink);
  font-family: var(--font-display);
  font-size: 74px;
  font-weight: 600;
  letter-spacing: -1px;
}

.tagline {
  fill: var(--color-ink);
  opacity: 0.55;
  font-family: var(--font-mono);
  font-size: 18px;
  font-weight: 500;
  letter-spacing: 1.8px;
}

.is-animated .logo-mark {
  animation: mark-arrive 700ms cubic-bezier(0.2, 0.9, 0.25, 1.2) both;
}

.is-animated .q-tail {
  animation: tail-draw 500ms 550ms ease both;
}

.is-animated .pulse-one {
  animation: pulse-out 1s 700ms ease-out both;
}

.is-animated .pulse-two {
  animation: pulse-out 1s 980ms ease-out both;
}

.is-animated .connection-one {
  animation: line-draw 650ms 1050ms ease both;
}

.is-animated .connection-two {
  animation: line-draw 650ms 1220ms ease both;
}

.is-animated .connection-three {
  animation: line-draw 650ms 1390ms ease both;
}

.is-animated .node-one {
  animation: node-pop 550ms 1250ms cubic-bezier(0.2, 0.9, 0.25, 1.25) both;
}

.is-animated .node-two {
  animation: node-pop 550ms 1420ms cubic-bezier(0.2, 0.9, 0.25, 1.25) both;
}

.is-animated .node-three {
  animation: node-pop 550ms 1590ms cubic-bezier(0.2, 0.9, 0.25, 1.25) both;
}

.is-animated .word-group {
  animation: word-reveal 650ms 2050ms cubic-bezier(0.2, 0.8, 0.2, 1) both;
}

.is-animated .tagline {
  animation: tagline-reveal 550ms 2450ms ease both;
}

@keyframes mark-arrive {
  0% {
    opacity: 0;
    transform: translateY(-22px) scale(0.75);
  }

  70% {
    opacity: 1;
    transform: translateY(3px) scale(1.04);
  }

  100% {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

@keyframes tail-draw {
  from {
    stroke-dashoffset: 38;
  }

  to {
    stroke-dashoffset: 0;
  }
}

@keyframes pulse-out {
  0% {
    opacity: 0.55;
    transform: scale(0.45);
  }

  100% {
    opacity: 0;
    transform: scale(1.35);
  }
}

@keyframes line-draw {
  from {
    stroke-dashoffset: 240;
  }

  to {
    stroke-dashoffset: 0;
  }
}

@keyframes node-pop {
  0% {
    opacity: 0;
    transform: scale(0.4);
  }

  75% {
    opacity: 1;
    transform: scale(1.08);
  }

  100% {
    opacity: 1;
    transform: scale(1);
  }
}

@keyframes word-reveal {
  from {
    opacity: 0;
    transform: translateX(-24px);
  }

  to {
    opacity: 1;
    transform: translateX(0);
  }
}

@keyframes tagline-reveal {
  from {
    opacity: 0;
    transform: translateY(8px);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@media (prefers-reduced-motion: reduce) {
  .is-animated * {
    animation: none !important;
  }
}
</style>
