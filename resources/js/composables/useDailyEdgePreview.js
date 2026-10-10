import { ref } from 'vue'

const testPayload = ref(null)
const testOpen = ref(false)

export function openDailyEdgeTest(payload) {
  testPayload.value = payload
  testOpen.value = true
}

export function closeDailyEdgeTest() {
  testOpen.value = false
}

export function useDailyEdgeTest() {
  return {
    testPayload,
    testOpen,
    openDailyEdgeTest,
    closeDailyEdgeTest,
  }
}
