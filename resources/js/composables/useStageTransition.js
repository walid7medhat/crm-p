import axios from '@/plugins/axios'
import { useDealValidation } from './useDealValidation'

export function useStageTransition() {
  const { normalizeMissingPayload } = useDealValidation()

  function normalizeExistingPropertyDocs(rawDocs = [], fallbackType) {
    if (!Array.isArray(rawDocs)) return []
    return rawDocs
      .map((doc) => {
        if (!doc || typeof doc !== 'object') return null
        // Existing docs should be preserved in properties payload, but never send File blobs here.
        if (doc.file instanceof File) return null
        const path = doc.path || doc.file_path || null
        const url = doc.url || doc.file_url || null
        const originalName = doc.original_name || doc.file_name || doc.name || null
        if (!path && !url && !originalName) return null
        return {
          id: doc.id || null,
          original_name: originalName || 'Document',
          path,
          url,
          file_url: doc.file_url || path || null,
          mime_type: doc.mime_type || doc.type || null,
          size: doc.size || doc.file_size || 0,
          document_type: doc.document_type || fallbackType,
          document_category: 'property',
        }
      })
      .filter(Boolean)
  }

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

  function appendRootPropertyDocFiles(payload, formData) {
    ;['payment_proof', 'spa_document'].forEach((key) => {
      const arr = payload[key]
      if (!Array.isArray(arr)) return
      arr.forEach((doc, idx) => {
        if (doc?.file instanceof File) {
          formData.append(`${key}[${idx}]`, doc.file)
        }
      })
    })
  }

  function buildUpdateAndStageFormData({ payload = {}, documents = [], stageId }) {
    const formData = new FormData()
    let paymentProofUploadIndex = 0
    let spaDocumentUploadIndex = 0

    Object.keys(payload).forEach((key) => {
      const value = payload[key]
      if (key.includes('_documents')) return
      if (key === 'payment_proof' || key === 'spa_document') return
      if (key === 'properties') {
        if (Array.isArray(value) && value.length > 0) {
          // Send only lightweight whitelisted property fields.
          // Do not include nested docs/files here (they are uploaded separately).
          const allowedPropertyKeys = [
            'sort_order',
            'unit_no',
            'property_type_id',
            'bedrooms',
            'unit_size',
            'area_id',
            'project_id',
            'developer_id',
            'developer_name',
            'developer_phone',
            'purchase_price',
            'rental_price',
            'budget_from',
            'budget_to',
            'commission',
            'payment_proof',
            'spa_document',
          ]

          const sanitized = value.map((property) => {
            if (!property || typeof property !== 'object') return property
            const lightweight = {}
            allowedPropertyKeys.forEach((k) => {
              if (Object.prototype.hasOwnProperty.call(property, k)) {
                lightweight[k] = property[k]
              }
            })
            // Keep existing persisted property docs so backend syncProperties doesn't wipe them.
            lightweight.payment_proof = normalizeExistingPropertyDocs(property.payment_proof, 'payment_proof')
            lightweight.spa_document = normalizeExistingPropertyDocs(property.spa_document, 'spa')
            return lightweight
          })
          formData.append('properties', JSON.stringify(sanitized))
        }
        return
      }
      if (value !== null && value !== undefined && value !== '') {
        formData.append(key, value)
      }
    })

    appendRootPropertyDocFiles(payload, formData)

    documents.forEach((doc, index) => {
      if (!doc?.file) return

      // Property docs must also be sent as root keys so backend can merge
      // into deal_properties payment_proof / spa_document.
      if (doc.category === 'property') {
        const docType = String(doc.document_type || '').toLowerCase()
        if (docType === 'payment_proof' || docType === 'payment' || docType.includes('payment')) {
          formData.append(`payment_proof[${paymentProofUploadIndex}]`, doc.file)
          paymentProofUploadIndex += 1
          return
        }
        if (docType === 'spa' || docType === 'spa_document' || docType.includes('spa')) {
          formData.append(`spa_document[${spaDocumentUploadIndex}]`, doc.file)
          spaDocumentUploadIndex += 1
          return
        }
      }

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

