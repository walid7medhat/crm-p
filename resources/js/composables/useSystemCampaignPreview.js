import { ref } from 'vue'

const previewCampaign = ref(null)

export function openSystemCampaignPreview(campaign) {
  if (!campaign?.desktop_image_url || !campaign?.mobile_image_url) return
  previewCampaign.value = {
    ...campaign,
    preview: true,
  }
}

export function closeSystemCampaignPreview() {
  previewCampaign.value = null
}

export function useSystemCampaignPreview() {
  return {
    previewCampaign,
    openSystemCampaignPreview,
    closeSystemCampaignPreview,
  }
}
