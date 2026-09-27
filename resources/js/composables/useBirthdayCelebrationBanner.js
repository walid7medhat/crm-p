/**
 * Shared birthday greeting banner state for the navbar strip.
 * Written by BirthdayCelebrationLayer; read by the layout navbar.
 */
import { ref } from 'vue'

const birthdayBannerVisible = ref(false)
const birthdayBannerTitle = ref('HAPPY BIRTHDAY!')
const birthdayBackgroundActive = ref(false)

export function setBirthdayBackgroundActive(on) {
  birthdayBackgroundActive.value = !!on
}

export function setBirthdayBanner({ visible, title }) {
  birthdayBannerVisible.value = !!visible
  if (typeof title === 'string' && title.trim()) {
    birthdayBannerTitle.value = title.trim()
  } else if (!visible) {
    birthdayBannerTitle.value = 'HAPPY BIRTHDAY!'
  }
}

export function useBirthdayCelebrationBanner() {
  return {
    birthdayBannerVisible,
    birthdayBannerTitle,
    birthdayBackgroundActive,
    setBirthdayBanner,
    setBirthdayBackgroundActive,
  }
}
