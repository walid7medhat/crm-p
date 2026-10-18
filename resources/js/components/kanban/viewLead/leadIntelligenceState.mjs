export function leadIntelligenceViewState({ scan, loading, error }) {
  if (loading && !scan) return 'loading'
  if (!scan && error) return 'failed'
  if (!scan) return 'idle'
  if (scan.status === 'queued' || scan.status === 'processing') return 'loading'
  if (scan.status === 'failed') return 'failed'
  if (scan.status === 'cancelled') return 'idle'
  if (scan.status === 'partial') return 'partial'
  const candidates = Array.isArray(scan.candidates) ? scan.candidates : []
  if (scan.status === 'completed' && candidates.length === 0) return 'empty'
  if (scan.status === 'completed') return 'completed'
  return 'failed'
}
