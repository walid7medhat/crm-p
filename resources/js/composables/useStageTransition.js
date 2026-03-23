import axios from '@/plugins/axios'
import { useDealValidation } from './useDealValidation'

export function useStageTransition() {
  const { normalizeMissingPayload } = useDealValidation()

  async function checkStageRequirements({ dealId, targetStageId, dealType }) {
    const { data } = await axios.post('/deals/check-stage-requirements', {
      deal_id: dealId,
      target_stage_id: targetStageId,
      deal_type: dealType,
    })

    return normalizeMissingPayload(data)
  }

  async function changeStage({ dealId, stageId, reason }) {
    const payload = { stage_id: stageId }
    if (reason) payload.reason = reason
    return axios.post(`/deals/${dealId}/change-stage`, payload)
  }

  function buildUpdateAndStageFormData({ payload = {}, documents = [], stageId }) {
    const formData = new FormData()

    Object.keys(payload).forEach((key) => {
      const value = payload[key]
      if (!key.includes('_documents') && value !== null && value !== undefined && value !== '') {
        formData.append(key, value)
      }
    })

    documents.forEach((doc, index) => {
      if (!doc?.file) return
      formData.append(`documents[${index}]`, doc.file)
      formData.append(`document_types[${index}]`, doc.document_type)
      formData.append(`categories[${index}]`, doc.category)
      formData.append(`party_types[${index}]`, doc.party_type)
    })

    formData.append('stage_id', stageId)
    return formData
  }

  async function updateAndChangeStage({ dealId, payload, documents, stageId }) {
    const formData = buildUpdateAndStageFormData({ payload, documents, stageId })

    return axios.post(`/deals/${dealId}/update-and-change-stage`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  }

  function normalizeMissingFromError(error) {
    if (error?.response?.status !== 422 || !error?.response?.data) {
      return null
    }
    return normalizeMissingPayload(error.response.data)
  }

  return {
    checkStageRequirements,
    changeStage,
    updateAndChangeStage,
    normalizeMissingFromError,
  }
}

