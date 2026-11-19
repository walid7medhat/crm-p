// Deal-level unit/type fields are often empty on multi-property deals: the
// values live on deal.properties[] (or on the listing a property links to).
// These mirror what PropertyCardReadonly shows, so the completeness summary
// doesn't flag fields the user can see filled in.

const filled = (v) => v !== null && v !== undefined && String(v).trim() !== ''

const firstFilled = (...values) => values.find(filled) ?? null

export function dealUnitNo(deal) {
  const d = deal || {}
  if (filled(d.unit_no)) return d.unit_no
  for (const p of d.properties || []) {
    const v = firstFilled(p?.unit_no, p?.listing?.unit_number, p?.listing_unit_number)
    if (v) return v
  }
  return null
}

export function dealPropertyType(deal) {
  const d = deal || {}
  const own = firstFilled(d.property_type?.name, d.property_type_name, d.property_type_id)
  if (own) return own
  for (const p of d.properties || []) {
    const v = firstFilled(
      p?.property_type_name,
      p?.property_type?.name,
      p?.property_type_id,
      p?.listing?.property_type?.name,
      p?.listing?.property_type_name,
      p?.listing?.property_type_id,
      p?.listing_property_type_name,
    )
    if (v) return v
  }
  return null
}
