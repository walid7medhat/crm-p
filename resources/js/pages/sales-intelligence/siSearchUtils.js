/** Shared helpers for Sales Intelligence lead search (no API changes). */

export function unwrapLeadsResponse(response) {
  const body = response?.data
  if (body?.status === true) return body.data
  if (body?.success === true) return body.data
  return null
}

export function flattenLeadsFromStages(stages) {
  if (!Array.isArray(stages)) return []
  const out = []
  for (const block of stages) {
    const leads = block?.leads || []
    for (const item of leads) {
      const L = item?.data ?? item
      if (L?.id) {
        out.push({
          id: L.id,
          label: L.lead_name || L.name || `Lead #${L.id}`,
          subtitle: L.lead_number || '',
        })
      }
    }
  }
  return out
}
