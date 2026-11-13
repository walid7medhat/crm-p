import { useBrand } from '@/composables/useBrand'

/**
 * Chart / sparkline colors from trusted host brand.
 * OIA keeps the existing purple palette values.
 */
export function useBrandChartColors() {
  const { isLuxvant, colors } = useBrand()

  if (isLuxvant) {
    const primary = colors.primary || '#003c38'
    const bright = colors.primary_bright || '#0a6b63'
    const accent = colors.accent || '#c9a227'
    return {
      primary,
      secondary: bright,
      accent,
      palette: [primary, '#16a34a', accent, '#2563eb', bright],
    }
  }

  return {
    primary: '#7c5cbf',
    secondary: '#6b21a8',
    accent: '#d4af37',
    palette: ['#7c5cbf', '#16a34a', '#f59e0b', '#2563eb', '#6b21a8'],
  }
}

export default useBrandChartColors
