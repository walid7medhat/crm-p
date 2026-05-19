<template>
  <section class="auth-landing">
    <video
      ref="bgVideoRef"
      class="auth-landing__video"
      autoplay
      muted
      loop
      playsinline
      preload="auto"
      aria-hidden="true"
      tabindex="-1"
    >
      <source :src="backgroundVideoSrc" type="video/mp4" />
    </video>
    <div class="auth-landing__overlay" aria-hidden="true" />
    <div class="auth-landing__mesh" aria-hidden="true" />
    <div class="auth-landing__glow auth-landing__glow--one" aria-hidden="true" />
    <div class="auth-landing__glow auth-landing__glow--two" aria-hidden="true" />

    <div class="auth-landing__grid">
      <aside class="auth-landing__marketing">
        <div class="auth-landing__brand-row">
          <img :src="altcrmLogo" alt="altcrm" class="auth-landing__brand-logo auth-landing__brand-logo--altcrm" />
          <span class="auth-landing__brand-divider" aria-hidden="true" />
          <img :src="oiaLogo" alt="Oia Properties" class="auth-landing__brand-logo auth-landing__brand-logo--oia" />
        </div>

        <h6 class="auth-landing__headline">
          A Powerful Digital Workspace for Real Estate Professionals and Growing Businesses
        </h6>

        <div
          ref="cardsTrackRef"
          class="auth-landing__cards-track"
          @scroll.passive="onCardsScroll"
        >
          <article
            v-for="(card, index) in featureCards"
            :key="card.id"
            class="auth-landing__feature-card"
          >
            <h6 class="auth-landing__feature-title">{{ card.title }}</h6>
            <div class="auth-landing__feature-media">
              <img :src="card.image" :alt="card.title" class="auth-landing__feature-image" loading="lazy" />
            </div>
          </article>
        </div>

        <div class="auth-landing__dots" role="tablist" aria-label="Feature highlights">
          <button
            v-for="(_, index) in carouselDots"
            :key="index"
            type="button"
            class="auth-landing__dot"
            :class="{ 'is-active': activeCardIndex === index }"
            :aria-label="`Slide ${index + 1}`"
            @click="scrollToCard(index)"
          />
        </div>
      </aside>

      <main class="auth-landing__auth-panel">
        <slot />
      </main>
    </div>
  </section>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const altcrmLogo = '/assets/images/auth/altcrm-logo.png';
const oiaLogo = '/assets/images/auth/oia-properties-logo.png';
const backgroundVideoSrc = '/videos/vibecode.mp4';
const bgVideoRef = ref(null);

const featureCards = [
  {
    id: 'pipeline',
    title: 'Your sales pipeline, simplified',
    image: '/assets/images/auth/mockup-pipeline.png',
  },
  {
    id: 'analytics',
    title: 'Analyze sales and team performance easily',
    image: '/assets/images/auth/mockup-analytics.png',
  },
  {
    id: 'mobile',
    title: 'Mobile CRM built for faster teamwork',
    image: '/assets/images/auth/mockup-mobile.png',
  },
];

const carouselDots = [0, 1, 2, 3, 4];
const cardsTrackRef = ref(null);
const activeCardIndex = ref(0);
let carouselTimer = null;

function scrollToCard(index) {
  activeCardIndex.value = index;
  const track = cardsTrackRef.value;
  if (!track) return;

  if (index >= featureCards.length) {
    track.scrollTo({ left: 0, behavior: 'smooth' });
    return;
  }

  const card = track.children[index];
  if (card) {
    track.scrollTo({ left: card.offsetLeft - 12, behavior: 'smooth' });
  }
}

function onCardsScroll() {
  const track = cardsTrackRef.value;
  if (!track || !track.children.length) return;

  const scrollLeft = track.scrollLeft;
  let nearest = 0;
  let nearestDistance = Number.POSITIVE_INFINITY;

  Array.from(track.children).forEach((child, index) => {
    const distance = Math.abs(child.offsetLeft - scrollLeft - 12);
    if (distance < nearestDistance) {
      nearestDistance = distance;
      nearest = index;
    }
  });

  activeCardIndex.value = nearest;
}

function startCarouselAutoplay() {
  carouselTimer = window.setInterval(() => {
    const next = (activeCardIndex.value + 1) % carouselDots.length;
    scrollToCard(next);
  }, 6000);
}

onMounted(() => {
  startCarouselAutoplay();
  const video = bgVideoRef.value;
  if (video) {
    video.play().catch(() => {});
  }
});

onUnmounted(() => {
  if (carouselTimer) {
    clearInterval(carouselTimer);
  }
});
</script>

<style scoped>
.auth-landing {
  --auth-bg: #0b0736;
  --auth-bg-mid: #1a0a42;
  --auth-bg-light: #2b1458;
  position: relative;
  flex: 1 1 auto;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  max-height: 100vh;
  max-height: 100dvh;
  overflow: hidden;
  background: transparent;
  color: #fff;
  font-family: Montserrat, Inter, system-ui, sans-serif;
  box-sizing: border-box;
}

.auth-landing__video {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  min-width: 100%;
  min-height: 100%;
  object-fit: cover;
  object-position: center center;
  z-index: 0;
  pointer-events: none;
}

.auth-landing__overlay {
  position: absolute;
  inset: 0;
  z-index: 0;
  pointer-events: none;
  background: linear-gradient(
    125deg,
    rgba(11, 7, 54, 0.82) 0%,
    rgba(26, 10, 66, 0.78) 42%,
    rgba(43, 20, 88, 0.72) 68%,
    rgba(30, 13, 74, 0.8) 100%
  );
}

.auth-landing__mesh {
  position: absolute;
  inset: 0;
  z-index: 0;
  pointer-events: none;
  background:
    radial-gradient(ellipse 55% 45% at 8% 92%, rgba(192, 38, 211, 0.45) 0%, transparent 70%),
    radial-gradient(ellipse 40% 35% at 22% 88%, rgba(115, 62, 135, 0.35) 0%, transparent 65%);
}

.auth-landing__glow {
  position: absolute;
  z-index: 0;
  border-radius: 50%;
  pointer-events: none;
  filter: blur(90px);
}

.auth-landing__glow--one {
  width: 580px;
  height: 580px;
  left: -200px;
  bottom: -280px;
  background: radial-gradient(circle, rgba(192, 38, 211, 0.65) 0%, rgba(115, 62, 135, 0.25) 40%, transparent 70%);
}

.auth-landing__glow--two {
  width: 400px;
  height: 400px;
  left: 60px;
  bottom: -120px;
  background: radial-gradient(circle, rgba(147, 51, 234, 0.4) 0%, transparent 68%);
}

.auth-landing__grid {
  position: relative;
  z-index: 1;
  display: grid;
  grid-template-columns: minmax(0, 1.2fr) minmax(340px, 44vw);
  gap: clamp(28px, 5vw, 72px);
  align-items: center;
  width: 100%;
  max-width: none;
  max-height: 100%;
  height: 100%;
  margin: 0 auto;
  padding: clamp(12px, 2vh, 24px) clamp(40px, 6vw, 100px);
  box-sizing: border-box;
}

.auth-landing__marketing {
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: clamp(10px, 1.8vh, 20px);
  min-width: 0;
  min-height: 0;
  max-height: 100%;
  width: 100%;
  overflow: hidden;
}

.auth-landing__brand-row {
  display: flex;
  align-items: center;
  gap: 22px;
  margin-top: 0;
  margin-bottom: 4px;
}

.auth-landing__brand-logo {
  display: block;
  height: auto;
  object-fit: contain;
}

.auth-landing__brand-logo--altcrm {
  width: clamp(140px, 14vw, 200px);
  max-height: clamp(64px, 8vh, 96px);
}

.auth-landing__brand-logo--oia {
  width: clamp(160px, 16vw, 220px);
  max-height: clamp(60px, 7.5vh, 90px);
}

.auth-landing__brand-divider {
  width: 1px;
  height: clamp(40px, 6vh, 56px);
  background: rgba(255, 255, 255, 0.2);
  flex-shrink: 0;
}

.auth-landing__headline {
  margin: 0;
  max-width: 100%;
  font-size: clamp(1.1rem, 2vw, 1.85rem);
  font-weight: 700;
  line-height: 1.2;
  letter-spacing: -0.02em;
  color: #fff;
  flex-shrink: 0;
}

.auth-landing__cards-track {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: clamp(10px, 1.2vw, 14px);
  min-width: 0;
  min-height: 0;
  width: 100%;
  flex: 1 1 auto;
  align-items: stretch;
  overflow: hidden;
}

.auth-landing__feature-card {
  display: flex;
  flex-direction: column;
  gap: 3px;
  padding: 6px 6px 5px;
  min-height: 0;
  max-height: 100%;
  border-radius: clamp(14px, 1.2vw, 18px);
  border: 1px solid rgba(255, 255, 255, 0.18);
  background: rgba(255, 255, 255, 0.08);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  box-shadow: 0 16px 36px rgba(0, 0, 0, 0.2);
  min-width: 0;
}

.auth-landing__feature-title {
  margin: 0;
  flex: 0 0 auto;
  font-size: clamp(0.44rem, 0.48vw, 0.5rem);
  font-weight: 500;
  line-height: 1.15;
  letter-spacing: 0.02em;
  color: rgba(255, 255, 255, 0.8);
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  overflow: hidden;
}

.auth-landing__feature-media {
  flex: 1 1 auto;
  width: 100%;
  min-height: 0;
  border-radius: 10px;
  overflow: hidden;
  background: rgba(11, 7, 54, 0.25);
  display: flex;
  align-items: stretch;
}

.auth-landing__feature-image {
  display: block;
  width: 100%;
  height: 100%;
  min-height: 0;
  max-height: 100%;
  object-fit: contain;
  object-position: center center;
}

.auth-landing__dots {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  padding-top: 2px;
}

.auth-landing__dot {
  width: 7px;
  height: 7px;
  padding: 0;
  border: none;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.25);
  cursor: pointer;
  transition: width 0.25s ease, background 0.25s ease;
}

.auth-landing__dot.is-active {
  width: 20px;
  background: #fff;
}

.auth-landing__auth-panel {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  min-width: 0;
  align-self: stretch;
}

.auth-landing__auth-panel > * {
  width: 100%;
  max-width: min(560px, 100%);
  height: auto;
  flex: 0 0 auto;
}

@media (max-width: 1023px) {
  .auth-landing {
    align-items: center;
    height: 100%;
    max-height: 100dvh;
    overflow: hidden;
    padding: 16px 0;
  }

  .auth-landing__grid {
    grid-template-columns: 1fr;
    align-items: center;
    height: auto;
    max-height: 100%;
    padding: 0 20px;
    max-width: 100%;
    overflow-y: auto;
    overflow-x: hidden;
    scrollbar-width: none;
  }

  .auth-landing__grid::-webkit-scrollbar {
    display: none;
  }

  .auth-landing__auth-panel {
    justify-content: center;
    width: 100%;
  }

  .auth-landing__cards-track {
    display: flex;
    gap: 10px;
    overflow-x: auto;
    scroll-snap-type: x mandatory;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
  }

  .auth-landing__cards-track::-webkit-scrollbar {
    display: none;
  }

  .auth-landing__feature-card {
    flex: 0 0 min(72vw, 260px);
    min-height: 0;
    max-height: 200px;
    scroll-snap-align: start;
  }

  .auth-landing__feature-image {
    min-height: 0;
    max-height: 160px;
  }
}

@media (max-width: 639px) {
  .auth-landing__brand-logo--altcrm {
    width: 110px;
    max-height: 56px;
  }

  .auth-landing__brand-logo--oia {
    width: 128px;
    max-height: 52px;
  }

  .auth-landing__headline {
    font-size: 1.05rem;
  }
}
</style>
