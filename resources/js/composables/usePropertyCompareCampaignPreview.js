import { ref } from 'vue'

export const propertyCompareCampaignOpen = ref(false)
export const propertyCompareCampaignMode = ref('preview')
export const propertyCompareCampaignLaunchId = ref(null)

let pendingLaunch = null

function reopen() {
  if (propertyCompareCampaignOpen.value) {
    propertyCompareCampaignOpen.value = false
    queueMicrotask(() => {
      propertyCompareCampaignOpen.value = true
    })
    return
  }
  propertyCompareCampaignOpen.value = true
}

export function startPropertyCompareCampaignPreview() {
  pendingLaunch = null
  propertyCompareCampaignMode.value = 'preview'
  propertyCompareCampaignLaunchId.value = null
  reopen()
}

export function startPropertyCompareCampaignLive(launchId) {
  if (!launchId) return
  if (
    propertyCompareCampaignOpen.value
    && propertyCompareCampaignMode.value === 'live'
    && propertyCompareCampaignLaunchId.value === launchId
  ) {
    return
  }
  if (pendingLaunch === launchId && propertyCompareCampaignOpen.value) return
  pendingLaunch = launchId
  propertyCompareCampaignMode.value = 'live'
  propertyCompareCampaignLaunchId.value = launchId
  reopen()
}

export function closePropertyCompareCampaignPreview() {
  pendingLaunch = null
  propertyCompareCampaignOpen.value = false
}
