/**
 * Side-by-side listing comparison built from an existing listing detail payload
 * (GET /listings/properties/{id} → ListingResource). No extra queries.
 */

import { LISTING_FEATURE_OPTIONS } from '../config/listingFeatures.js'
import { paymentPlanSelectionLabel } from './listingPaymentPlanPresets.js'

export const NOT_SET = 'Not set'
export const NOT_AVAILABLE = 'Not available'
export const MAX_COMPARE = 3

const FEATURE_CATEGORY = new Map(LISTING_FEATURE_OPTIONS.map((feature) => [feature.key, feature.category]))
const OUTDOOR_KEYS = new Set([
  ...LISTING_FEATURE_OPTIONS.filter((feature) => feature.category === 'spaces').map((feature) => feature.key),
  'private_pool',
  'private_gym',
])

const ADVANTAGE_LABEL = {
  price: 'Lower price',
  'price-sqft': 'Lower per sqft',
  'rent-amount': 'Lower rent',
  'size-sqft': 'Larger area',
}

function blank(value) {
  return value == null || value === '' || value === NOT_SET || value === NOT_AVAILABLE
}

function present(value) {
  if (value === NOT_AVAILABLE) return NOT_AVAILABLE
  if (value == null || value === '') return NOT_SET
  return String(value)
}

function asList(left, right) {
  if (right === undefined && Array.isArray(left)) return left
  return [left, right]
}

/** Mutual absence is not a match. One side missing is a difference. */
export function rowState(left, right) {
  const values = asList(left, right)
  const blanks = values.map(blank)
  if (blanks.every(Boolean)) return 'missing'
  if (blanks.some(Boolean)) return 'different'
  const first = String(values[0]).trim().toLowerCase()
  if (values.every((value) => String(value).trim().toLowerCase() === first)) return 'same'
  return 'different'
}

function finiteNumber(value) {
  if (value == null || value === '') return null
  const num = Number(value)
  return Number.isFinite(num) ? num : null
}

function row(id, label, rawValues, { hideWhenAllBlank = false, hideWhenBothBlank = false, advantage = null, numbers = null } = {}) {
  const values = rawValues.map(present)
  const state = rowState(values)
  if ((hideWhenAllBlank || hideWhenBothBlank) && state === 'missing') return null
  let best = []
  if (advantage && state === 'different' && Array.isArray(numbers) && numbers.length === values.length) {
    if (numbers.every((num) => num != null)) {
      const target = advantage === 'lower' ? Math.min(...numbers) : Math.max(...numbers)
      const winners = numbers.map((num, index) => (num === target ? index : -1)).filter((index) => index >= 0)
      if (winners.length === 1) best = winners
    }
  }
  return {
    id,
    label,
    values,
    left: values[0] ?? NOT_SET,
    right: values[1] ?? NOT_SET,
    state,
    best,
    advantageLabel: best.length ? (ADVANTAGE_LABEL[id] || null) : null,
  }
}

function money(value) {
  if (value == null || value === '') return null
  const num = Number(value)
  if (!Number.isFinite(num)) return null
  return `AED ${new Intl.NumberFormat('en-AE', { maximumFractionDigits: 0 }).format(num)}`
}

function unitRate(price, size) {
  const rate = rateNumber(price, size)
  if (rate == null) return null
  const digits = rate >= 100 ? 0 : 2
  return `AED ${new Intl.NumberFormat('en-AE', { minimumFractionDigits: digits, maximumFractionDigits: digits }).format(rate)}`
}

function rateNumber(price, size) {
  const amount = Number(price)
  const measure = Number(size)
  if (!Number.isFinite(amount) || amount <= 0 || !Number.isFinite(measure) || measure <= 0) return null
  return amount / measure
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
  return listing?.project?.title
    || listing?.project?.name
    || listing?.project_name
    || listing?.title
    || listing?.reference_number
    || null
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

function listingsOf(left, right, third) {
  const source = Array.isArray(left) ? left : [left, right, third]
  return source.filter((listing) => listing != null).slice(0, MAX_COMPARE)
}

function columnValues(listings, read) {
  return listings.map((listing) => read(listing))
}

function expenseLines(listing) {
  const raw = listing?.assignment_expense_lines
  if (Array.isArray(raw)) return raw
  if (typeof raw === 'string') {
    try {
      const parsed = JSON.parse(raw)
      return Array.isArray(parsed) ? parsed : []
    } catch {
      return []
    }
  }
  return []
}

function lineText(line) {
  const label = String(line?.label || '').trim()
  if (!label) return null
  const value = Number(line?.value ?? line?.amount)
  const type = String(line?.calcType || line?.type || '')
  if (!Number.isFinite(value) || value <= 0) return label
  if (type === 'percentage') return `${label} ${value}%`
  return `${label} ${money(value)}`
}

function matchingCost(listing, pattern) {
  const lines = expenseLines(listing).map(lineText).filter(Boolean)
  const hit = lines.find((text) => pattern.test(text))
  return hit || null
}

function otherCosts(listing) {
  const parts = expenseLines(listing)
    .map(lineText)
    .filter((text) => text && !/service\s*charge/i.test(text))
  if (listing?.noc_percentage) parts.push(`NOC ${listing.noc_percentage}%`)
  else if (listing?.noc_fixed_amount) {
    const formatted = money(listing.noc_fixed_amount)
    if (formatted) parts.push(`NOC ${formatted}`)
  }
  if (listing?.mortgage_amount) {
    const formatted = money(listing.mortgage_amount)
    if (formatted) parts.push(`Mortgage ${formatted}`)
  }
  return parts.length ? parts.join(' · ') : null
}

function paymentPlan(listing) {
  const label = paymentPlanSelectionLabel(listing?.payment_plan)
  return label ? String(label) : null
}

function floorPlanLabel(listing) {
  const plans = Array.isArray(listing?.floor_plans) ? listing.floor_plans.filter((plan) => plan?.image_url || plan?.name) : []
  if (!plans.length) return null
  return plans.length === 1 ? '1 plan' : `${plans.length} plans`
}

function mentionsParking(listing) {
  return featureNames(listing).some((name) => /parking|garage|car park/i.test(name))
}

function featureRows(listings, includeKey) {
  const labels = {}
  for (const listing of listings) {
    Object.assign(labels, listing?.additional_features_labels || {})
  }
  for (const feature of LISTING_FEATURE_OPTIONS) {
    if (!labels[feature.key]) labels[feature.key] = feature.label
  }
  return Object.keys(labels)
    .filter((key) => includeKey(key))
    .map((key) => {
      const flags = listings.map((listing) => Boolean(listing?.additional_features?.[key]))
      if (!flags.some(Boolean)) return null
      return row(
        `amenity-${key}`,
        labels[key] || humanize(key),
        flags.map((on) => (on ? 'Yes' : 'No')),
      )
    })
    .filter(Boolean)
    .sort((a, b) => a.label.localeCompare(b.label))
}

function amenityRows(listings) {
  const rows = featureRows(listings, (key) => {
    if (OUTDOOR_KEYS.has(key)) return false
    return FEATURE_CATEGORY.get(key) !== 'furnishing'
  })
  if (!rows.length) return [row('amenities', 'Amenities', listings.map(() => null))]
  return rows
}

function outdoorRows(listings) {
  const rows = featureRows(listings, (key) => OUTDOOR_KEYS.has(key))
  if (!rows.length) return [row('outdoor', 'Outdoor features', listings.map(() => null))]
  return rows
}

function projectAmenityRows(listings) {
  const names = listings.map(featureNames)
  const labels = new Map()
  for (const name of names.flat()) {
    const key = name.trim().toLowerCase()
    if (key && !/parking|garage|car park/i.test(name) && !labels.has(key)) labels.set(key, name.trim())
  }
  if (!labels.size) return [row('project-amenities', 'Project amenities', listings.map(() => null))]
  return [...labels.entries()]
    .sort((a, b) => a[1].localeCompare(b[1]))
    .map(([key, label]) => row(
      `project-${key}`,
      label,
      names.map((list) => (list.some((name) => name.trim().toLowerCase() === key) ? 'Yes' : 'No')),
    ))
}

function pushGroup(groups, title, rows) {
  const kept = rows.filter(Boolean)
  if (kept.length) groups.push({ title, rows: kept })
}

export function selectionIdsFromQuery(query = {}) {
  const fromIds = String(query.ids || '')
    .split(',')
    .map((part) => part.trim())
    .filter(Boolean)
  const legacy = [query.left, query.right]
    .map((value) => (value == null ? '' : String(value).trim()))
    .filter(Boolean)
  return [...new Set([...fromIds, ...legacy])].slice(0, MAX_COMPARE)
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

export function floorPlanImages(listing) {
  const plans = Array.isArray(listing?.floor_plans) ? listing.floor_plans : []
  return plans.map((plan) => plan?.image_url).filter(Boolean).slice(0, 2)
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

export function compareProperties(left, right, third) {
  const listings = listingsOf(left, right, third)
  const groups = []
  const purposes = listings.map(purpose)
  const anyRent = purposes.includes('Rent')
  const samePurpose = purposes.every((item) => item && item === purposes[0])
  const priceAdvantage = samePurpose ? 'lower' : null

  pushGroup(groups, 'Price', [
    row('price', 'Price', columnValues(listings, (listing) => money(listing?.price)), {
      advantage: priceAdvantage,
      numbers: columnValues(listings, (listing) => finiteNumber(listing?.price)),
    }),
    row('price-sqft', 'Price per sqft', columnValues(listings, (listing) => unitRate(listing?.price, listing?.size_sqft)), {
      advantage: priceAdvantage,
      numbers: columnValues(listings, (listing) => rateNumber(listing?.price, listing?.size_sqft)),
    }),
    row('price-sqm', 'Price per sqm', columnValues(listings, (listing) => unitRate(listing?.price, listing?.size_sqmt)), { hideWhenAllBlank: true }),
    row('purpose', 'Sale or rent', purposes),
    row('original-price', 'Original price', columnValues(listings, (listing) => money(listing?.original_price)), { hideWhenAllBlank: true }),
    row('selling-price', 'Selling price', columnValues(listings, (listing) => money(listing?.selling_price)), { hideWhenAllBlank: true }),
    row('rent-amount', 'Rent amount', columnValues(listings, (listing) => money(listing?.rent_amount)), {
      hideWhenAllBlank: !anyRent,
      advantage: purposes.every((item) => item === 'Rent') ? 'lower' : null,
      numbers: columnValues(listings, (listing) => finiteNumber(listing?.rent_amount)),
    }),
  ])

  pushGroup(groups, 'Property', [
    row('type', 'Property type', columnValues(listings, propertyTypeName)),
    row('bedrooms', 'Bedrooms', columnValues(listings, bedrooms)),
    row('bathrooms', 'Bathrooms', columnValues(listings, (listing) => (
      listing?.number_of_bathrooms == null || listing?.number_of_bathrooms === ''
        ? null
        : String(listing.number_of_bathrooms)
    ))),
    row('size-sqft', 'Size', columnValues(listings, (listing) => countLabel(listing?.size_sqft, 'sqft')), {
      advantage: 'higher',
      numbers: columnValues(listings, (listing) => {
        const size = finiteNumber(listing?.size_sqft)
        return size != null && size > 0 ? size : null
      }),
    }),
    row('size-sqm', 'Size (sqm)', columnValues(listings, (listing) => countLabel(listing?.size_sqmt, 'sqm'))),
    row('plot', 'Plot size', columnValues(listings, (listing) => countLabel(listing?.plot_size, 'sqft')), { hideWhenAllBlank: true }),
    row('layout', 'Layout', columnValues(listings, (listing) => listing?.layout_type || null)),
    row('view', 'Unit view', columnValues(listings, (listing) => listing?.unit_view || null)),
    row('furnishing', 'Furnishing', columnValues(listings, (listing) => humanize(listing?.furnished_status))),
    row('ownership', 'Ownership', columnValues(listings, (listing) => humanize(listing?.ownership_type))),
    row('unit', 'Unit number', columnValues(listings, unitNumber)),
  ])

  pushGroup(groups, 'Location', [
    row('location', 'Location', columnValues(listings, locationLabel)),
    row('city', 'City', columnValues(listings, (listing) => areaByType(listing, 'city'))),
    row('community', 'Community', columnValues(listings, (listing) => areaByType(listing, 'community'))),
    row('subcommunity', 'Subcommunity', columnValues(listings, (listing) => areaByType(listing, 'sub_community'))),
    row('district', 'Area', columnValues(listings, (listing) => areaByType(listing, 'area')), { hideWhenAllBlank: true }),
  ])

  pushGroup(groups, 'Status', [
    row('availability', 'Availability', columnValues(listings, availability)),
    row('completion', 'Completion', columnValues(listings, (listing) => listing?.completion_status || null)),
    row('handover', 'Handover', columnValues(listings, (listing) => listing?.handover_date || null)),
    row('occupancy', 'Occupancy', columnValues(listings, (listing) => listing?.occupancy_status || null)),
    row('mortgage', 'Mortgage', columnValues(listings, (listing) => humanize(listing?.mortgage_status)), { hideWhenAllBlank: true }),
    row('hot-deal', 'Hot deal', columnValues(listings, (listing) => (listing?.is_hot_deal === 'Yes' ? 'Yes' : null)), { hideWhenAllBlank: true }),
    row('rent-until', 'Rent until', columnValues(listings, (listing) => listing?.rented_until || listing?.rent_expiry_date || null), { hideWhenAllBlank: !anyRent }),
  ])

  pushGroup(groups, 'People', [
    row('developer', 'Developer', columnValues(listings, developerName)),
    row('agent', 'Listing agent', columnValues(listings, (listing) => listing?.agent?.name || null)),
  ])

  pushGroup(groups, 'Costs', [
    row('payment-plan', 'Payment plan', columnValues(listings, paymentPlan)),
    row('service-charge', 'Service charge', columnValues(listings, (listing) => matchingCost(listing, /service\s*charge/i)), { hideWhenAllBlank: true }),
    row('other-costs', 'Other costs', columnValues(listings, otherCosts), { hideWhenAllBlank: true }),
    row('floor-plans', 'Floor plans', columnValues(listings, floorPlanLabel)),
    row('parking', 'Parking', columnValues(listings, (listing) => (mentionsParking(listing) ? 'Yes' : null)), { hideWhenAllBlank: true }),
  ])

  pushGroup(groups, 'Outdoor', outdoorRows(listings))
  pushGroup(groups, 'Amenities', amenityRows(listings))
  pushGroup(groups, 'Project amenities', projectAmenityRows(listings))

  const counts = { same: 0, different: 0, missing: 0 }
  const names = listings.map((listing, index) => propertyName(listing) || `Property ${index + 1}`)
  const highlights = []
  for (const group of groups) {
    for (const item of group.rows) {
      counts[item.state] += 1
      if (item.advantageLabel && item.best.length === 1) {
        highlights.push({
          id: item.id,
          column: item.best[0],
          text: `${item.advantageLabel}: ${names[item.best[0]]}`,
        })
      }
    }
  }

  return { groups, counts, highlights, columnCount: listings.length }
}
