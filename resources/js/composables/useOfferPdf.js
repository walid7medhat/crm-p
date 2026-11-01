import { ref } from 'vue'

/**
 * Builds a listing's sales offer PDF with the property page's own generator (BlogOne's
 * "Generate Offer"), without opening the page. OfferPdfHost (mounted in App.vue while
 * jobs exist) renders BlogOne off-screen, one job at a time.
 */
export const offerPdfJobs = ref([])
let seq = 0

/** @returns {Promise<{ blob: Blob, filename: string, offerNumber: string }>} */
export function renderListingOffer(listingId, { clientName = 'Potential Client', onProgress } = {}) {
  return new Promise((resolve, reject) => {
    offerPdfJobs.value = [
      ...offerPdfJobs.value,
      { id: ++seq, listingId: Number(listingId), clientName, onProgress, resolve, reject },
    ]
  })
}
