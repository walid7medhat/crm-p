export function normalizeLanguageSelection(value) {
  if (Array.isArray(value)) {
    return value
      .map((item) => String(item || '').trim())
      .filter(Boolean)
  }

  if (typeof value === 'string') {
    const trimmed = value.trim()
    if (!trimmed) return []
    return trimmed
      .split(',')
      .map((item) => item.trim())
      .filter(Boolean)
  }

  return []
}

export function hasLanguageSelection(value) {
  return normalizeLanguageSelection(value).length > 0
}

export function formatLanguageSelection(value) {
  const items = normalizeLanguageSelection(value)
  return items.length ? items.join(', ') : '----'
}
