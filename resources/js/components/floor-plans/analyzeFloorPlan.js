// Reads a floor-plan image and returns wall centerlines in the original
// pixel coordinates. The uploaded image is never modified.
//
// A dark edge becomes a wall only when it is a long, thin, neutral stroke
// that meets the outer shell. Furniture, labels, and tile lines fail that
// test. Incomplete sides are left open instead of being sealed with a guess.
// Ambiguous strokes are returned disabled so they stay visible for review
// and are not built until someone turns them on.

const MAX_SIDE = 780

function luminance(r, g, b) {
  return 0.2126 * r + 0.7152 * g + 0.0722 * b
}

function saturation(r, g, b) {
  return Math.max(r, g, b) - Math.min(r, g, b)
}

function pause() {
  return new Promise((resolve) => setTimeout(resolve, 0))
}

function segmentLength(wall) {
  return Math.max(Math.abs(wall.x1 - wall.x0), Math.abs(wall.y1 - wall.y0))
}

function endpoints(wall) {
  if (wall.axis === 'h') {
    const y = (wall.y0 + wall.y1) / 2
    return [[wall.x0, y], [wall.x1, y]]
  }
  const x = (wall.x0 + wall.x1) / 2
  return [[x, wall.y0], [x, wall.y1]]
}

function hits(point, wall, pad) {
  return point[0] >= wall.x0 - pad && point[0] <= wall.x1 + pad
    && point[1] >= wall.y0 - pad && point[1] <= wall.y1 + pad
}

function connects(a, b, pad) {
  const aEnds = endpoints(a)
  const bEnds = endpoints(b)
  return hits(aEnds[0], b, pad) || hits(aEnds[1], b, pad) || hits(bEnds[0], a, pad) || hits(bEnds[1], a, pad)
}

function dominantNeutral(pixels, width, height) {
  const buckets = new Map()
  const keyOf = (r, g, b) => `${r >> 3},${g >> 3},${b >> 3}`
  const consider = (r, g, b, length) => {
    if (length < 42 || saturation(r, g, b) > 16 || luminance(r, g, b) >= 206) return
    const key = keyOf(r, g, b)
    const slot = buckets.get(key) || { r: 0, g: 0, b: 0, n: 0, length: 0 }
    slot.r += r
    slot.g += g
    slot.b += b
    slot.n += 1
    slot.length += length
    buckets.set(key, slot)
  }
  const scan = (horizontal) => {
    const outer = horizontal ? height : width
    const inner = horizontal ? width : height
    for (let a = 0; a < outer; a++) {
      let b = 0
      while (b < inner) {
        const x = horizontal ? b : a
        const y = horizontal ? a : b
        const index = (y * width + x) * 4
        const r = pixels[index]
        const g = pixels[index + 1]
        const bl = pixels[index + 2]
        if (saturation(r, g, bl) > 16 || luminance(r, g, bl) >= 206) {
          b += 1
          continue
        }
        const key = keyOf(r, g, bl)
        let b1 = b + 1
        while (b1 < inner) {
          const x2 = horizontal ? b1 : a
          const y2 = horizontal ? a : b1
          const index2 = (y2 * width + x2) * 4
          const r2 = pixels[index2]
          const g2 = pixels[index2 + 1]
          const b2 = pixels[index2 + 2]
          if (saturation(r2, g2, b2) > 16 || luminance(r2, g2, b2) >= 206 || keyOf(r2, g2, b2) !== key) break
          b1 += 1
        }
        consider(r, g, bl, b1 - b)
        b = b1
      }
    }
  }
  scan(true)
  scan(false)
  let best = null
  buckets.forEach((slot) => {
    if (!best || slot.length > best.length) best = slot
  })
  if (!best) return { mode: 'line', color: [0, 0, 0] }
  const color = [Math.round(best.r / best.n), Math.round(best.g / best.n), Math.round(best.b / best.n)]
  return luminance(...color) < 42
    ? { mode: 'line', color }
    : { mode: 'render', color }
}

function mergeAxis(walls, axis) {
  let group = walls.filter((wall) => wall.axis === axis).map((wall) => ({ ...wall }))
  const rest = walls.filter((wall) => wall.axis !== axis)
  let changed = true
  let guard = 0
  while (changed && guard < 20) {
    guard += 1
    changed = false
    const used = new Array(group.length).fill(false)
    const next = []
    for (let i = 0; i < group.length; i++) {
      if (used[i]) continue
      let wall = group[i]
      let center = axis === 'h' ? (wall.y0 + wall.y1) / 2 : (wall.x0 + wall.x1) / 2
      for (let j = i + 1; j < group.length; j++) {
        if (used[j]) continue
        const other = group[j]
        const otherCenter = axis === 'h' ? (other.y0 + other.y1) / 2 : (other.x0 + other.x1) / 2
        if (Math.abs(center - otherCenter) > 8) continue
        const gap = axis === 'h'
          ? (wall.x0 <= other.x1 + 1 && other.x0 <= wall.x1 + 1 ? 0 : Math.min(Math.abs(other.x0 - wall.x1), Math.abs(wall.x0 - other.x1)))
          : (wall.y0 <= other.y1 + 1 && other.y0 <= wall.y1 + 1 ? 0 : Math.min(Math.abs(other.y0 - wall.y1), Math.abs(wall.y0 - other.y1)))
        if (gap > 4) continue
        wall = {
          axis,
          x0: Math.min(wall.x0, other.x0),
          y0: Math.min(wall.y0, other.y0),
          x1: Math.max(wall.x1, other.x1),
          y1: Math.max(wall.y1, other.y1),
          confident: true,
          enabled: true
        }
        center = axis === 'h' ? (wall.y0 + wall.y1) / 2 : (wall.x0 + wall.x1) / 2
        used[j] = true
        changed = true
      }
      next.push(wall)
    }
    group = next
  }
  return rest.concat(group)
}

function classify(walls, width, height) {
  if (!walls.length) return []
  const span = Math.max(width, height)
  const long = walls.filter((wall) => segmentLength(wall) >= span * 0.14)
  const base = long.length ? long : walls
  const frame = {
    x0: Math.min(...base.map((wall) => wall.x0)),
    y0: Math.min(...base.map((wall) => wall.y0)),
    x1: Math.max(...base.map((wall) => wall.x1)),
    y1: Math.max(...base.map((wall) => wall.y1))
  }
  const onFrame = (wall) => wall.x0 <= frame.x0 + 14 || wall.y0 <= frame.y0 + 14 || wall.x1 >= frame.x1 - 14 || wall.y1 >= frame.y1 - 14
  const seed = new Set()
  walls.forEach((wall, index) => {
    if (onFrame(wall) && segmentLength(wall) >= span * 0.05) seed.add(index)
  })
  let changed = true
  while (changed) {
    changed = false
    walls.forEach((wall, index) => {
      if (seed.has(index) || segmentLength(wall) < 32) return
      for (const other of seed) {
        if (!connects(wall, walls[other], 7)) continue
        seed.add(index)
        changed = true
        break
      }
    })
  }
  const kept = walls.filter((_, index) => seed.has(index))
  kept.forEach((wall) => {
    wall.confident = true
    wall.enabled = true
  })
  const loose = kept.filter((wall) => !onFrame(wall))
  const parent = loose.map((_, index) => index)
  const find = (index) => {
    while (parent[index] !== index) {
      parent[index] = parent[parent[index]]
      index = parent[index]
    }
    return index
  }
  for (let i = 0; i < loose.length; i++) {
    for (let j = i + 1; j < loose.length; j++) {
      if (!connects(loose[i], loose[j], 8)) continue
      const ri = find(i)
      const rj = find(j)
      if (ri !== rj) parent[rj] = ri
    }
  }
  const groups = new Map()
  loose.forEach((wall, index) => {
    const root = find(index)
    if (!groups.has(root)) groups.set(root, [])
    groups.get(root).push(wall)
  })
  const planArea = Math.max(1, (frame.x1 - frame.x0) * (frame.y1 - frame.y0))
  groups.forEach((group) => {
    const x0 = Math.min(...group.map((wall) => wall.x0))
    const y0 = Math.min(...group.map((wall) => wall.y0))
    const x1 = Math.max(...group.map((wall) => wall.x1))
    const y1 = Math.max(...group.map((wall) => wall.y1))
    const inset = x0 > frame.x0 + 28 && y0 > frame.y0 + 28 && x1 < frame.x1 - 28 && y1 < frame.y1 - 28
    const small = (x1 - x0) * (y1 - y0) < planArea * 0.12
    if (inset && small) {
      group.forEach((wall) => {
        wall.confident = false
        wall.enabled = false
      })
    }
  })
  return kept
}

function clusterRegions(walls, width, height) {
  const parent = walls.map((_, index) => index)
  const find = (index) => {
    while (parent[index] !== index) {
      parent[index] = parent[parent[index]]
      index = parent[index]
    }
    return index
  }
  for (let i = 0; i < walls.length; i++) {
    for (let j = i + 1; j < walls.length; j++) {
      if (!connects(walls[i], walls[j], 22)) continue
      const ri = find(i)
      const rj = find(j)
      if (ri !== rj) parent[rj] = ri
    }
  }
  const groups = new Map()
  walls.forEach((wall, index) => {
    const root = find(index)
    if (!groups.has(root)) groups.set(root, [])
    groups.get(root).push(wall)
  })
  const bbox = (group) => ({
    x0: Math.min(...group.map((wall) => Math.min(wall.x0, wall.x1))),
    y0: Math.min(...group.map((wall) => Math.min(wall.y0, wall.y1))),
    x1: Math.max(...group.map((wall) => Math.max(wall.x0, wall.x1))),
    y1: Math.max(...group.map((wall) => Math.max(wall.y0, wall.y1)))
  })
  let regions = [...groups.values()].filter((group) => group.length >= 4 || Math.max(...group.map(segmentLength)) > 0.2 * Math.max(width, height))
  let changed = true
  while (changed) {
    changed = false
    for (let i = 0; i < regions.length; i++) {
      for (let j = i + 1; j < regions.length; j++) {
        const a = bbox(regions[i])
        const b = bbox(regions[j])
        const gapX = a.x1 >= b.x0 && b.x1 >= a.x0 ? 0 : Math.min(Math.abs(b.x0 - a.x1), Math.abs(a.x0 - b.x1))
        const gapY = a.y1 >= b.y0 && b.y1 >= a.y0 ? 0 : Math.min(Math.abs(b.y0 - a.y1), Math.abs(a.y0 - b.y1))
        if (Math.max(gapX, gapY) >= 0.16 * Math.min(width, height)) continue
        regions[i] = regions[i].concat(regions[j])
        regions.splice(j, 1)
        changed = true
        break
      }
      if (changed) break
    }
  }
  regions.sort((a, b) => {
    const aa = bbox(a)
    const bb = bbox(b)
    return (bb.x1 - bb.x0) * (bb.y1 - bb.y0) - (aa.x1 - aa.x0) * (aa.y1 - aa.y0)
  })
  if (regions.length > 1) {
    const largest = bbox(regions[0])
    const largestArea = (largest.x1 - largest.x0) * (largest.y1 - largest.y0)
    regions = regions.filter((group, index) => {
      if (index === 0) return true
      const box = bbox(group)
      return (box.x1 - box.x0) * (box.y1 - box.y0) > largestArea * 0.28 && group.filter((wall) => wall.enabled !== false).length >= 4
    })
  }
  return regions.map((group) => {
    const box = bbox(group)
    return {
      bounds: {
        x0: Math.max(0, box.x0 - 12),
        y0: Math.max(0, box.y0 - 12),
        x1: Math.min(width, box.x1 + 12),
        y1: Math.min(height, box.y1 + 12)
      },
      walls: group
    }
  })
}

export async function analyzeFloorPlan(source, onProgress) {
  const report = async (message) => {
    if (onProgress) await onProgress(message)
    else await pause()
  }
  await report('Finding the floor-plan region…')
  const scale = Math.min(1, MAX_SIDE / Math.max(source.width, source.height))
  const width = Math.max(1, Math.round(source.width * scale))
  const height = Math.max(1, Math.round(source.height * scale))
  const canvas = document.createElement('canvas')
  canvas.width = width
  canvas.height = height
  const context = canvas.getContext('2d', { willReadFrequently: true })
  context.drawImage(source, 0, 0, width, height)
  const pixels = context.getImageData(0, 0, width, height).data
  const inkStyle = dominantNeutral(pixels, width, height)
  const maxThickness = inkStyle.mode === 'line' ? 16 : 26
  const minThickness = inkStyle.mode === 'line' ? 4 : 1
  const tolerance = luminance(...inkStyle.color) > 150 ? 18 : 15
  const isInk = (r, g, b) => {
    if (inkStyle.mode === 'line') return luminance(r, g, b) < 46 && saturation(r, g, b) < 36
    const dr = r - inkStyle.color[0]
    const dg = g - inkStyle.color[1]
    const db = b - inkStyle.color[2]
    return dr * dr + dg * dg + db * db <= tolerance * tolerance && saturation(r, g, b) < 24
  }
  const mask = new Uint8Array(width * height)
  for (let i = 0, p = 0; i < mask.length; i++, p += 4) {
    if (isInk(pixels[p], pixels[p + 1], pixels[p + 2])) mask[i] = 1
  }
  const ink = (x, y) => x >= 0 && y >= 0 && x < width && y < height && mask[y * width + x] === 1
  const spanOf = (x, y, horizontal) => {
    let start = horizontal ? x : y
    let steps = 0
    while (steps < maxThickness + 4 && ink(horizontal ? start - 1 : x, horizontal ? y : start - 1)) {
      start -= 1
      steps += 1
    }
    let end = horizontal ? x : y
    steps = 0
    while (steps < maxThickness + 4 && ink(horizontal ? end + 1 : x, horizontal ? y : end + 1)) {
      end += 1
      steps += 1
    }
    return end - start + 1
  }

  await report('Tracing architectural lines…')
  let walls = []
  const minRun = 24
  const half = 3
  for (let y = 0; y < height; y++) {
    let x = 0
    while (x < width) {
      if (!ink(x, y)) { x += 1; continue }
      let x1 = x + 1
      while (x1 < width && ink(x1, y)) x1 += 1
      if (x1 - x >= minRun) {
        const samples = []
        for (let i = 0; i < 7; i++) samples.push(spanOf(x + Math.floor((x1 - x - 1) * i / 6), y, false))
        samples.sort((a, b) => a - b)
        const median = samples[3]
        if (median >= minThickness && median <= maxThickness) {
          walls.push({ axis: 'h', x0: x, x1, y0: y - half, y1: y + half, confident: true, enabled: true })
        }
      }
      x = x1
    }
  }
  for (let x = 0; x < width; x++) {
    let y = 0
    while (y < height) {
      if (!ink(x, y)) { y += 1; continue }
      let y1 = y + 1
      while (y1 < height && ink(x, y1)) y1 += 1
      if (y1 - y >= minRun) {
        const samples = []
        for (let i = 0; i < 7; i++) samples.push(spanOf(x, y + Math.floor((y1 - y - 1) * i / 6), true))
        samples.sort((a, b) => a - b)
        const median = samples[3]
        if (median >= minThickness && median <= maxThickness) {
          walls.push({ axis: 'v', x0: x - half, x1: x + half, y0: y, y1, confident: true, enabled: true })
        }
      }
      y = y1
    }
  }
  walls = mergeAxis(mergeAxis(walls, 'h'), 'v')
  walls = walls.filter((wall) => segmentLength(wall) >= 36)

  await report('Setting aside furniture, labels, and open space…')
  const kept = classify(walls, width, height)
  const regions = clusterRegions(kept, width, height).map((region) => ({
    bounds: {
      x0: region.bounds.x0 / scale,
      y0: region.bounds.y0 / scale,
      x1: region.bounds.x1 / scale,
      y1: region.bounds.y1 / scale
    },
    walls: region.walls.map((wall) => ({
      x0: wall.x0 / scale,
      y0: wall.y0 / scale,
      x1: wall.x1 / scale,
      y1: wall.y1 / scale,
      axis: wall.axis,
      confident: wall.confident !== false,
      enabled: wall.enabled !== false
    }))
  }))
  const built = regions.reduce((sum, region) => sum + region.walls.filter((wall) => wall.enabled !== false).length, 0)
  const held = regions.reduce((sum, region) => sum + region.walls.filter((wall) => wall.enabled === false).length, 0)
  const cropped = regions.some((region) => (region.bounds.x1 - region.bounds.x0) * (region.bounds.y1 - region.bounds.y0) < source.width * source.height * 0.72)
  let note = 'Red lines will be built. Gold lines are uncertain and stay out until you click them. Drag to add a missing wall.'
  if (!regions.length || built === 0) {
    note = 'No reliable walls were found. The drawing stays as the floor. Drag the walls you want, then build.'
  } else if (regions.length > 1) {
    note = 'More than one plan was found. Choose one. Gold lines are left out until you click them.'
  } else if (cropped) {
    note = 'The plan was separated from the rest of the sheet. Red lines will be built. Gold lines stay out until you click them.'
  } else if (held > 0) {
    note = 'Gold lines look like furniture or an uncertain partition. They are left out until you click them.'
  }
  return { regions, note }
}

export function sceneFromWalls(bounds, walls) {
  return {
    bounds: {
      x0: Number(bounds.x0) || 0,
      y0: Number(bounds.y0) || 0,
      x1: Number(bounds.x1) || 0,
      y1: Number(bounds.y1) || 0
    },
    walls: (walls || [])
      .filter((wall) => wall && wall.enabled !== false)
      .slice(0, 500)
      .map((wall) => ({
        x0: Number(wall.x0),
        y0: Number(wall.y0),
        x1: Number(wall.x1),
        y1: Number(wall.y1),
        confident: wall.confident !== false
      }))
  }
}
