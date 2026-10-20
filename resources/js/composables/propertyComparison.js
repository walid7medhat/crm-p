/**
 * Side-by-side listing comparison built from an existing listing detail payload
 * (GET /listings/properties/{id} → ListingResource). No extra queries.
 */

export const NOT_SET = 'Not set'
export const NOT_AVAILABLE = 'Not available'

function blank(value) {
  return value == null || value === '' || value === NOT_SET || value === NOT_AVAILABLE
}

function present(value) {
  if (value === NOT_AVAILABLE) return NOT_AVAILABLE
  if (value == null || value === '') return NOT_SET
  return String(value)
}

/** Mutual absence is not a match. One side missing is a difference. */
export function rowState(left, right) {
  const leftBlank = blank(left)
  const rightBlank = blank(right)
  if (leftBlank && rightBlank) return 'missing'
  if (leftBlank || rightBlank) return 'different'
  if (String(left).trim().toLowerCase() === String(right).trim().toLowerCase()) return 'same'
  return 'different'
}

function row(id, label, leftRaw, rightRaw, { hideWhenBothBlank = false } = {}) {
  const left = present(leftRaw)
  const right = present(rightRaw)
  const state = rowState(left, right)
  if (hideWhenBothBlank && state === 'missing') return null
  return { id, label, left, right, state }
}

function money(value) {
  if (value == null || value === '') return null
  const num = Number(value)
  if (!Number.isFinite(num)) return null
  return `AED ${new Intl.NumberFormat('en-AE', { maximumFractionDigits: 0 }).format(num)}`
}

function unitRate(price, size) {
  const amount = Number(price)
  const measure = Number(size)
  if (!Number.isFinite(amount) || amount <= 0 || !Number.isFinite(measure) || measure <= 0) return null
  const rate = amount / measure
  const digits = rate >= 100 ? 0 : 2
  return `AED ${new Intl.NumberFormat('en-AE', { minimumFractionDigits: digits, maximumFractionDigits: digits }).format(rate)}`
}

function humanize(value) {
  if (value == null || value === '') return null
  return String(value)
    .replace(/[_-]+/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
    .replace(/\b\w/g, (char) => char.toUpperCase())
}

function hierarchy(listing) {
  const chain = listing?.area?.hierarchy
  return Array.isArray(chain) ? chain : []
}

function areaByType(listing, type) {
  const wanted = String(type).toLowerCase()
  const node = hierarchy(listing).find((item) => String(item?.type || '').toLowerCase() === wanted)
  return node?.name || null
}

function locationLabel(listing) {
  const area = listing?.area
  if (!area) return null
  if (typeof area === 'string') return area
  return area.title || area.name || area.area_title || null
}

function propertyName(listing) {
  return listing?.project?.title || listing?.project?.name || listing?.reference_number || null
}

function propertyTypeName(listing) {
  const type = listing?.property_type
  if (!type) return null
  if (typeof type === 'string') return type
  return type.name || null
}

function bedrooms(listing) {
  const value = listing?.number_of_bedrooms
  if (value == null || value === '') return null
  const count = Number(value)
  if (!Number.isFinite(count)) return null
  if (count === 0) return 'Studio'
  return String(count)
}

function countLabel(value, suffix) {
  if (value == null || value === '') return null
  const count = Number(value)
  if (!Number.isFinite(count) || count <= 0) return null
  return `${new Intl.NumberFormat('en-AE').format(count)} ${suffix}`
}

function purpose(listing) {
  const raw = String(listing?.listing_status || '').toLowerCase()
  if (raw.includes('rent')) return 'Rent'
  if (raw.includes('sale')) return 'Sale'
  return null
}

function availability(listing) {
  if (!listing) return null
  if (listing.is_archived) return 'Archived'
  if (listing.status === 'converted') return 'Sold'
  if (listing.status === 'rented') return 'Rented'
  if (listing.status === 'draft') return 'Draft'
  if (listing.is_active === false) return 'Inactive'
  if (listing.status === 'published' || listing.is_active === true) return 'Available'
  return null
}

function unitNumber(listing) {
  if (listing?.canShowUnitNumber === false) return NOT_AVAILABLE
  return listing?.unit_number || null
}

function developerName(listing) {
  return listing?.developer?.name
    || listing?.project?.developer_name
    || listing?.project?.developerData?.name
    || null
}

function featureNames(listing) {
  const features = listing?.project?.features
  if (!Array.isArray(features)) return []
  return features.map((feature) => feature?.name).filter(Boolean)
}

function amenityRows(left, right) {
  const labels = {
    ...(left?.additional_features_labels || {}),
    ...(right?.additional_features_labels || {}),
  }
  const rows = Object.keys(labels)
    .map((key) => {
      const leftOn = Boolean(left?.additional_features?.[key])
      const rightOn = Boolean(right?.additional_features?.[key])
      if (!leftOn && !rightOn) return null
      return row(`amenity-${key}`, labels[key] || humanize(key), leftOn ? 'Yes' : 'No', rightOn ? 'Yes' : 'No')
    })
    .filter(Boolean)
    .sort((a, b) => a.label.localeCompare(b.label))

  if (!rows.length) {
    return [row('amenities', 'Amenities', null, null)]
  }
  return rows
}

function projectAmenityRows(left, right) {
  const leftNames = featureNames(left)
  const rightNames = featureNames(right)
  const labels = new Map()
  for (const name of [...leftNames, ...rightNames]) {
    const key = name.trim().toLowerCase()
    if (key && !labels.has(key)) labels.set(key, name.trim())
  }
  if (!labels.size) {
    return [row('project-amenities', 'Project amenities', null, null)]
  }
  return [...labels.entries()]
    .sort((a, b) => a[1].localeCompare(b[1]))
    .map(([key, label]) => row(
      `project-${key}`,
      label,
      leftNames.some((name) => name.trim().toLowerCase() === key) ? 'Yes' : 'No',
      rightNames.some((name) => name.trim().toLowerCase() === key) ? 'Yes' : 'No',
    ))
}

function pushGroup(groups, title, rows) {
  const kept = rows.filter(Boolean)
  if (kept.length) groups.push({ title, rows: kept })
}

export function listingImages(listing) {
  const gallery = Array.isArray(listing?.gallery_images) ? listing.gallery_images : []
  const urls = gallery
    .map((image) => image?.image_url_final || image?.image_url)
    .filter(Boolean)
  if (!urls.length && listing?.main_image) urls.push(listing.main_image)
  if (!urls.length && listing?.hero_image) urls.push(listing.hero_image)
  if (!urls.length && listing?.project?.image) urls.push(listing.project.image)
  return urls.slice(0, 4)
}

export function listingHeadline(listing) {
  return {
    name: propertyName(listing) || 'Untitled listing',
    reference: listing?.reference_number || null,
    price: money(listing?.price),
    type: propertyTypeName(listing),
    bedrooms: bedrooms(listing),
    image: listingImages(listing)[0] || null,
  }
}

export function compareProperties(left, right) {
  const groups = []
  const eitherRent = purpose(left) === 'Rent' || purpose(right) === 'Rent'

  pushGroup(groups, 'Price', [
    row('price', 'Price', money(left?.price), money(right?.price)),
    row('price-sqft', 'Price per sqft', unitRate(left?.price, left?.size_sqft), unitRate(right?.price, right?.size_sqft)),
    row('price-sqm', 'Price per sqm', unitRate(left?.price, left?.size_sqmt), unitRate(right?.price, right?.size_sqmt), { hideWhenBothBlank: true }),
    row('purpose', 'Sale or rent', purpose(left), purpose(right)),
    row('original-price', 'Original price', money(left?.original_price), money(right?.original_price), { hideWhenBothBlank: true }),
    row('selling-price', 'Selling price', money(left?.selling_price), money(right?.selling_price), { hideWhenBothBlank: true }),
    row('rent-amount', 'Rent amount', money(left?.rent_amount), money(right?.rent_amount), { hideWhenBothBlank: !eitherRent }),
  ])

  pushGroup(groups, 'Property', [
    row('type', 'Property type', propertyTypeName(left), propertyTypeName(right)),
    row('bedrooms', 'Bedrooms', bedrooms(left), bedrooms(right)),
    row('bathrooms', 'Bathrooms', left?.number_of_bathrooms == null || left?.number_of_bathrooms === '' ? null : String(left.number_of_bathrooms), right?.number_of_bathrooms == null || right?.number_of_bathrooms === '' ? null : String(right.number_of_bathrooms)),
    row('size-sqft', 'Size', countLabel(left?.size_sqft, 'sqft'), countLabel(right?.size_sqft, 'sqft')),
    row('size-sqm', 'Size (sqm)', countLabel(left?.size_sqmt, 'sqm'), countLabel(right?.size_sqmt, 'sqm')),
    row('plot', 'Plot size', countLabel(left?.plot_size, 'sqft'), countLabel(right?.plot_size, 'sqft'), { hideWhenBothBlank: true }),
    row('layout', 'Layout', left?.layout_type || null, right?.layout_type || null),
    row('view', 'Unit view', left?.unit_view || null, right?.unit_view || null),
    row('furnishing', 'Furnishing', humanize(left?.furnished_status), humanize(right?.furnished_status)),
    row('ownership', 'Ownership', humanize(left?.ownership_type), humanize(right?.ownership_type)),
    row('unit', 'Unit number', unitNumber(left), unitNumber(right)),
  ])

  pushGroup(groups, 'Location', [
    row('location', 'Location', locationLabel(left), locationLabel(right)),
    row('city', 'City', areaByType(left, 'city'), areaByType(right, 'city')),
    row('community', 'Community', areaByType(left, 'community'), areaByType(right, 'community')),
    row('subcommunity', 'Subcommunity', areaByType(left, 'sub_community'), areaByType(right, 'sub_community')),
    row('district', 'Area', areaByType(left, 'area'), areaByType(right, 'area'), { hideWhenBothBlank: true }),
  ])

  pushGroup(groups, 'Status', [
    row('availability', 'Availability', availability(left), availability(right)),
    row('completion', 'Completion', left?.completion_status || null, right?.completion_status || null),
    row('handover', 'Handover', left?.handover_date || null, right?.handover_date || null),
    row('occupancy', 'Occupancy', left?.occupancy_status || null, right?.occupancy_status || null),
    row('mortgage', 'Mortgage', humanize(left?.mortgage_status), humanize(right?.mortgage_status), { hideWhenBothBlank: true }),
    row('hot-deal', 'Hot deal', left?.is_hot_deal === 'Yes' ? 'Yes' : null, right?.is_hot_deal === 'Yes' ? 'Yes' : null, { hideWhenBothBlank: true }),
    row('rent-until', 'Rent until', left?.rented_until || left?.rent_expiry_date || null, right?.rented_until || right?.rent_expiry_date || null, { hideWhenBothBlank: !eitherRent }),
  ])

  pushGroup(groups, 'People', [
    row('developer', 'Developer', developerName(left), developerName(right)),
    row('agent', 'Listing agent', left?.agent?.name || null, right?.agent?.name || null),
  ])

  pushGroup(groups, 'Amenities', amenityRows(left, right))
  pushGroup(groups, 'Project amenities', projectAmenityRows(left, right))

  const counts = { same: 0, different: 0, missing: 0 }
  for (const group of groups) {
    for (const item of group.rows) counts[item.state] += 1
  }

  return { groups, counts }
}
