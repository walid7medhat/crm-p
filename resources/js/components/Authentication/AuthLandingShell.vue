<template>
  <section class="auth-landing">
    <div class="auth-landing__bg-image" aria-hidden="true" />
    <div class="auth-landing__overlay" aria-hidden="true" />
    <div class="auth-landing__mesh" aria-hidden="true" />
    <div class="auth-landing__glow auth-landing__glow--one" aria-hidden="true" />
    <div class="auth-landing__glow auth-landing__glow--two" aria-hidden="true" />

    <div class="auth-landing__grid">
      <!-- Form first in DOM so it stays visible even if CSS fails to load -->
      <main class="auth-landing__auth-panel">
        <slot />
      </main>

      <aside class="auth-landing__marketing">
        <div class="auth-landing__brand-row">
          <img
            :src="altcrmLogo"
            alt="altcrm"
            class="auth-landing__brand-logo auth-landing__brand-logo--altcrm"
            width="200"
            height="96"
          />
          <span class="auth-landing__brand-divider" aria-hidden="true" />
          <img
            :src="oiaLogo"
            alt="Oia Properties"
            class="auth-landing__brand-logo auth-landing__brand-logo--oia"
            width="220"
            height="90"
          />
        </div>

        <h6 class="auth-landing__headline auth-landing__headline--desktop">
          A Powerful Digital Workspace for Real Estate Professionals and Growing Businesses
        </h6>
        <h6 class="auth-landing__headline auth-landing__headline--mobile">
          Access OIA'S Exclusive Tools to Boost Your Brokerage
        </h6>

        <div
          ref="cardsTrackRef"
          class="auth-landing__cards-track"
          @scroll.passive="onCardsScroll"
        >
          <article
            v-for="(card, index) in loopedCards"
            :key="card.loopKey"
            class="auth-landing__feature-card"
            :data-slide-index="index % slideCount"
          >
            <h6 class="auth-landing__feature-title">{{ card.title }}</h6>
            <div class="auth-landing__feature-media">
              <img
                :src="card.image"
                :alt="card.title"
                class="auth-landing__feature-image"
                width="320"
                height="200"
                loading="lazy"
              />
            </div>
          </article>
        </div>

        <div class="auth-landing__dots" role="tablist" aria-label="Feature highlights">
          <button
            v-for="(_, index) in slideCount"
            :key="index"
            type="button"
            class="auth-landing__dot"
            :class="{ 'is-active': activeCardIndex === index }"
            :aria-label="`Slide ${index + 1}`"
            @click="scrollToCard(index)"
          />
        </div>
      </aside>
    </div>
  </section>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';

const altcrmLogo = '/assets/images/auth/altcrm-logo.png';
const oiaLogo = '/assets/images/auth/oia-properties-logo.png';

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

const LOOP_SETS = 2;
const slideCount = featureCards.length;
const AUTOPLAY_MS = 4500;

const loopedCards = computed(() => {
  const items = [];
  for (let set = 0; set < LOOP_SETS; set += 1) {
    featureCards.forEach((card, index) => {
      items.push({
        ...card,
        loopKey: `${card.id}-set${set}-i${index}`,
      });
    });
  }
  return items;
});

const cardsTrackRef = ref(null);
const activeCardIndex = ref(0);
let carouselTimer = null;
let isResettingScroll = false;

function getPhysicalIndex(logicalIndex) {
  return logicalIndex + slideCount;
}

function getCardScrollLeft(physicalIndex) {
  const track = cardsTrackRef.value;
  if (!track?.children?.length) return 0;
  const child = track.children[physicalIndex];
  if (!child) return 0;
  return Math.max(0, child.offsetLeft - 12);
}

function scrollToPhysical(physicalIndex, behavior = 'smooth') {
  const track = cardsTrackRef.value;
  if (!track) return;
  track.scrollTo({ left: getCardScrollLeft(physicalIndex), behavior });
}

function scrollToCard(logicalIndex, behavior = 'smooth') {
  const normalized =
    ((logicalIndex % slideCount) + slideCount) % slideCount;
  activeCardIndex.value = normalized;
  scrollToPhysical(getPhysicalIndex(normalized), behavior);
}

function normalizeInfiniteScroll() {
  if (isResettingScroll) return;
  const track = cardsTrackRef.value;
  if (!track?.children?.length) return;

  const scrollLeft = track.scrollLeft;
  let nearestPhysical = 0;
  let nearestDistance = Number.POSITIVE_INFINITY;

  Array.from(track.children).forEach((child, index) => {
    const distance = Math.abs(child.offsetLeft - 12 - scrollLeft);
    if (distance < nearestDistance) {
      nearestDistance = distance;
      nearestPhysical = index;
    }
  });

  const logical = nearestPhysical % slideCount;
  activeCardIndex.value = logical;

  if (nearestPhysical < slideCount) {
    isResettingScroll = true;
    scrollToPhysical(nearestPhysical + slideCount, 'auto');
    isResettingScroll = false;
    return;
  }

  if (nearestPhysical >= slideCount * LOOP_SETS) {
    isResettingScroll = true;
    scrollToPhysical(nearestPhysical - slideCount, 'auto');
    isResettingScroll = false;
  }
}

function onCardsScroll() {
  if (isResettingScroll) return;
  normalizeInfiniteScroll();
}

function advanceCarousel() {
  const next = (activeCardIndex.value + 1) % slideCount;
  scrollToCard(next);
}

function startCarouselAutoplay() {
  stopCarouselAutoplay();
  carouselTimer = window.setInterval(advanceCarousel, AUTOPLAY_MS);
}

function stopCarouselAutoplay() {
  if (carouselTimer) {
    clearInterval(carouselTimer);
    carouselTimer = null;
  }
}

onMounted(async () => {
  await nextTick();
  scrollToCard(0, 'auto');
  startCarouselAutoplay();
});

onUnmounted(() => {
  stopCarouselAutoplay();
});
</script>
