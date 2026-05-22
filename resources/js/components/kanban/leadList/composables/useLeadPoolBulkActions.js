import { ref } from 'vue'
import api from '@/plugins/axios'

const ASSIGN_REQUEST_TIMEOUT_MS = 45000

let cachedAssignedStageId = null

function isApiSuccess(response) {
  const status = response?.status
  if (status != null && (status < 200 || status >= 300)) return false
  const body = response?.data
  if (body && typeof body === 'object' && body.success === false) return false
  return true
}

/**
 * Resolve CRM "Assigned" stage (same logic as Lead Assignment Engine settings).
 */
async function resolveAssignedStageId() {
  if (cachedAssignedStageId) return cachedAssignedStageId

  try {
    const settingsRes = await api.get('/lead-assignment/settings', {
      timeout: ASSIGN_REQUEST_TIMEOUT_MS,
    })
    const fromSettings = settingsRes?.data?.data?.assigned_stage_id
    if (fromSettings) {
      cachedAssignedStageId = Number(fromSettings)
      return cachedAssignedStageId
    }
  } catch {
    /* fall through to stages list */
  }

  try {
    const stagesRes = await api.get('/stages', {
      params: { stage_type: 'lead' },
      timeout: ASSIGN_REQUEST_TIMEOUT_MS,
    })
    const rows = stagesRes?.data?.data?.data ?? stagesRes?.data?.data ?? []
    const list = Array.isArray(rows) ? rows : []

    const byName = list.find(
      (s) => String(s?.name || '').trim().toLowerCase() === 'assigned',
    )
    if (byName?.id) {
      cachedAssignedStageId = Number(byName.id)
      return cachedAssignedStageId
    }

    const afterNew = list
      .filter((s) => Number(s.order) > 1)
      .sort((a, b) => Number(a.order) - Number(b.order))
    if (afterNew[0]?.id) {
      cachedAssignedStageId = Number(afterNew[0].id)
      return cachedAssignedStageId
    }
  } catch {
    /* ignore */
  }

  return null
}

/**
 * Bulk actions for Lead Pool — assign to current user and move to Assigned stage.
 */
export function useLeadPoolBulkActions() {
  const isAssigning = ref(false)

  function getCurrentUserId() {
    try {
      const raw = localStorage.getItem('user')
      if (!raw) return null
      const user = JSON.parse(raw)
      return user?.id ?? null
    } catch {
      return null
    }
  }

  /**
   * Assign leads to current user and move from Lead Pool → Assigned stage (normal kanban).
   * @param {number[]} leadIds
   * @returns {Promise<{ ok: number[], failed: { id: number, message: string }[] }>}
   */
  async function assignToMe(leadIds) {
    if (isAssigning.value) {
      return { ok: [], failed: [] }
    }

    const userId = getCurrentUserId()
    if (!userId) {
      throw new Error('You must be logged in to assign leads.')
    }
    if (!leadIds?.length) {
      return { ok: [], failed: [] }
    }

    isAssigning.value = true
    const ok = []
    const failed = []

    try {
      const assignedStageId = await resolveAssignedStageId()
      if (!assignedStageId) {
        cachedAssignedStageId = null
        throw new Error('Assigned stage is not configured. Set it in Lead Assignment settings.')
      }

      const results = await Promise.allSettled(
        leadIds.map((id) =>
          api.post(
            `/leads/${id}/change-stage`,
            {
              stage_id: assignedStageId,
              responsible_person_id: userId,
            },
            { timeout: ASSIGN_REQUEST_TIMEOUT_MS },
          ),
        ),
      )

      results.forEach((result, index) => {
        const id = leadIds[index]
        if (result.status === 'fulfilled' && isApiSuccess(result.value)) {
          ok.push(id)
        } else {
          const message =
            result.reason?.response?.data?.message ||
            result.reason?.message ||
            result.value?.data?.message ||
            'Could not assign lead'
          failed.push({ id, message })
        }
      })

      if (ok.length) {
        window.dispatchEvent(
          new CustomEvent('kanban-leads-board-refresh', {
            detail: { leadIds: ok, assignedStageId },
          }),
        )
      }

      return { ok, failed }
    } finally {
      isAssigning.value = false
    }
  }

  return {
    isAssigning,
    assignToMe,
    getCurrentUserId,
    resolveAssignedStageId,
  }
}
