/**
 * Deal pipeline stage header / pill gradients per deal type (Primary, Secondary, Rental).
 * Spec from design — applied in kanban columns and deal modals.
 */

const GRADIENTS = {
  new: 'linear-gradient(90deg, rgba(57, 168, 239, 1) 39%, rgba(42, 143, 208, 1) 100%)',
  eoi: 'linear-gradient(90deg, rgba(170, 233, 252, 1) 39%, rgba(74, 205, 245, 1) 100%)',
  booking: 'linear-gradient(90deg, rgba(0, 255, 0, 1) 39%, rgba(0, 202, 0, 1) 100%)',
  spaSigned: 'linear-gradient(90deg, rgba(0, 166, 76, 1) 39%, rgba(13, 222, 109, 1) 100%)',
  dealWon: 'linear-gradient(90deg, rgba(123, 213, 0, 1) 100%, rgba(123, 213, 0, 1) 68%)',
  dealLost: 'linear-gradient(90deg, rgba(241, 23, 22, 1) 100%, rgba(241, 23, 22, 1) 68%)',
  securityDeposit: 'linear-gradient(90deg, rgba(170, 233, 252, 1) 0%, rgba(74, 205, 245, 1) 60%)',
  mouSigned: 'linear-gradient(90deg, rgba(0, 166, 76, 1) 0%, rgba(3, 198, 92, 1) 71%)',
  noc: 'linear-gradient(90deg, rgba(71, 228, 194, 1) 0%, rgba(35, 236, 193, 1) 100%)',
  leaseOffer: 'linear-gradient(90deg, rgba(47, 198, 246, 1) 0%, rgba(166, 234, 255, 1) 77%)',
  guarantee: 'linear-gradient(90deg, rgba(0, 166, 76, 1) 68%, rgba(0, 128, 59, 1) 100%)',
  internalContract: 'linear-gradient(90deg, rgba(71, 228, 194, 1) 44%, rgba(0, 191, 150, 1) 100%)',
  ejari: 'linear-gradient(90deg, rgba(0, 0, 255, 1) 41%, rgba(7, 7, 146, 1) 100%)',
  tenantMovedIn: 'linear-gradient(90deg, rgba(170, 233, 252, 1) 39%, rgba(74, 205, 245, 1) 100%)',
}

function style(gradient, dotColor) {
  return { gradient, dotColor, color: dotColor }
}

/** @type {Record<string, Record<number, { gradient: string, dotColor: string, color: string }>>} */
const BY_DEAL_TYPE_ORDER = {
  primary: {
    1: style(GRADIENTS.new, '#39A8EF'),
    2: style(GRADIENTS.eoi, '#AAE9FC'),
    3: style(GRADIENTS.booking, '#00FF00'),
    4: style(GRADIENTS.spaSigned, '#00A64C'),
    5: style(GRADIENTS.dealWon, '#7BD500'),
    6: style(GRADIENTS.dealLost, '#F11716'),
  },
  secondary: {
    1: style(GRADIENTS.new, '#39A8EF'),
    2: style(GRADIENTS.securityDeposit, '#AAE9FC'),
    3: style(GRADIENTS.mouSigned, '#00A64C'),
    4: style(GRADIENTS.noc, '#47E4C2'),
    5: style(GRADIENTS.dealWon, '#7BD500'),
    6: style(GRADIENTS.dealLost, '#F11716'),
  },
  rental: {
    1: style(GRADIENTS.new, '#39A8EF'),
    2: style(GRADIENTS.leaseOffer, '#2FC6F6'),
    3: style(GRADIENTS.guarantee, '#00A64C'),
    4: style(GRADIENTS.internalContract, '#47E4C2'),
    5: style(GRADIENTS.ejari, '#0000FF'),
    6: style(GRADIENTS.tenantMovedIn, '#AAE9FC'),
    7: style(GRADIENTS.dealWon, '#7BD500'),
    8: style(GRADIENTS.dealLost, '#F11716'),
  },
}

const DEFAULT_STYLE = style(GRADIENTS.new, '#39A8EF')

export function normalizeDealType(dealType) {
  const t = String(dealType || 'primary').toLowerCase()
  if (t === 'primary' || t === 'secondary' || t === 'rental') return t
  return 'primary'
}

function normalizeStageName(name) {
  return String(name || '')
    .toLowerCase()
    .replace(/[()]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
}

/** Match stage name to a palette key when order is missing or mismatched. */
function matchStageByName(name) {
  const n = normalizeStageName(name)
  if (!n) return null
  if (n === 'new') return 'new'
  if (n.includes('deal won')) return 'dealWon'
  if (n.includes('deal lost')) return 'dealLost'
  if (n.includes('eoi')) return 'eoi'
  if (n.includes('booking')) return 'booking'
  if (n.includes('spa') || n.includes('deal done')) return 'spaSigned'
  if (n.includes('security deposit')) return 'securityDeposit'
  if (n.includes('mou') || n.includes('contract f')) return 'mouSigned'
  if (n === 'noc' || n.startsWith('noc ')) return 'noc'
  if (n.includes('lease') && (n.includes('offer') || n.includes('latter') || n.includes('letter'))) {
    return 'leaseOffer'
  }
  if (n.includes('guarantee') || n.includes('chequ')) return 'guarantee'
  if (n.includes('internal contract')) return 'internalContract'
  if (n.includes('ejari') || n.includes('tawtheq')) return 'ejari'
  if (n.includes('tenant') && n.includes('mov')) return 'tenantMovedIn'
  return null
}

const NAME_TO_STYLE = {
  new: BY_DEAL_TYPE_ORDER.primary[1],
  eoi: BY_DEAL_TYPE_ORDER.primary[2],
  booking: BY_DEAL_TYPE_ORDER.primary[3],
  spaSigned: BY_DEAL_TYPE_ORDER.primary[4],
  dealWon: BY_DEAL_TYPE_ORDER.primary[5],
  dealLost: BY_DEAL_TYPE_ORDER.primary[6],
  securityDeposit: BY_DEAL_TYPE_ORDER.secondary[2],
  mouSigned: BY_DEAL_TYPE_ORDER.secondary[3],
  noc: BY_DEAL_TYPE_ORDER.secondary[4],
  leaseOffer: BY_DEAL_TYPE_ORDER.rental[2],
  guarantee: BY_DEAL_TYPE_ORDER.rental[3],
  internalContract: BY_DEAL_TYPE_ORDER.rental[4],
  ejari: BY_DEAL_TYPE_ORDER.rental[5],
  tenantMovedIn: BY_DEAL_TYPE_ORDER.rental[6],
}

/**
 * @param {string} dealType primary | secondary | rental
 * @param {{ order?: number, name?: string, stage_name?: string }} stage
 */
export function resolveDealStageStyle(dealType, stage = {}) {
  const type = normalizeDealType(dealType)
  const order = Number(stage.order) || 0
  const name = stage.name ?? stage.stage_name ?? ''
  const byOrder = BY_DEAL_TYPE_ORDER[type]?.[order]
  if (byOrder) return byOrder
  const key = matchStageByName(name)
  if (key && NAME_TO_STYLE[key]) return NAME_TO_STYLE[key]
  return DEFAULT_STYLE
}

export function enrichDealStage(dealType, stage) {
  if (!stage) return stage
  const resolved = resolveDealStageStyle(dealType, stage)
  return {
    ...stage,
    gradient: resolved.gradient,
    dotColor: resolved.dotColor,
    color: resolved.color,
    bg: resolved.gradient,
  }
}

export function enrichDealStages(dealType, stages) {
  return (stages || []).map((s) => enrichDealStage(dealType, s))
}

/** Kanban column header */
export function getDealColumnHeaderStyle(column, dealType) {
  const resolved = resolveDealStageStyle(dealType, {
    order: column?.order,
    name: column?.title ?? column?.stage_name,
  })
  return { background: resolved.gradient }
}

/**
 * Pipeline pill (create / view deal modals)
 * @param {number} index pill index
 * @param {number} selectedIndex current stage index
 */
export function getDealStagePillStyle(dealType, stage, index, selectedIndex) {
  const resolved = resolveDealStageStyle(dealType, stage)
  if (index > selectedIndex) {
    return { backgroundColor: 'transparent', borderColor: '#E2E8F0' }
  }
  return {
    background: resolved.gradient,
    borderColor: resolved.dotColor,
  }
}

/** DB / API solid color fallback (first stop of gradient) */
export function getDealStageColorForDb(dealType, order, name) {
  return resolveDealStageStyle(dealType, { order, name }).dotColor
}
