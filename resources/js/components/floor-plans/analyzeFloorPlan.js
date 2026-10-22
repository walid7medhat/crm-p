import { findFurniture } from './furnishPlan.js'

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

function dominantNeutral(pixels, width, height, wide) {
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
  const ranked = [...buckets.values()].sort((a, b) => b.length - a.length)
  const best = ranked[0]
  if (!best) return { mode: 'line', color: [0, 0, 0], colors: [[0, 0, 0]] }
  const toColor = (slot) => [Math.round(slot.r / slot.n), Math.round(slot.g / slot.n), Math.round(slot.b / slot.n)]
  const color = toColor(best)
  const colors = [color]
  if (wide && luminance(...color) >= 42) {
    ranked.slice(1).forEach((slot) => {
      if (colors.length >= 3 || slot.length < best.length * 0.28) return
      const next = toColor(slot)
      const lum = luminance(...next)
      if (lum < 48 || lum >= 200) return
      const apart = colors.every((current) => {
        const dr = next[0] - current[0]
        const dg = next[1] - current[1]
        const db = next[2] - current[2]
        return dr * dr + dg * dg + db * db > 26 * 26
      })
      if (apart) colors.push(next)
    })
  }
  return luminance(...color) < 42
    ? { mode: 'line', color, colors }
    : { mode: 'render', color, colors }
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

function quietFurniture(walls, frame) {
  if (!walls.length) return walls
  walls.forEach((wall) => {
    wall.confident = true
    wall.enabled = true
  })
  const onFrame = (wall) => wall.x0 <= frame.x0 + 18 || wall.y0 <= frame.y0 + 18 || wall.x1 >= frame.x1 - 18 || wall.y1 >= frame.y1 - 18
  const loose = walls.filter((wall) => !onFrame(wall))
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
    const span = Math.min(frame.x1 - frame.x0, frame.y1 - frame.y0)
    const longest = Math.max(...group.map((wall) => segmentLength(wall)))
    const small = longest < span * 0.12 && (x1 - x0) * (y1 - y0) < planArea * 0.12
    if (inset && small) {
      group.forEach((wall) => {
        wall.confident = false
        wall.enabled = false
      })
    }
  })
  return walls
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

export async function analyzeFloorPlan(source, onProgress, crop) {
  const report = async (message) => {
    if (onProgress) await onProgress(message)
    else await pause()
  }
  await report(crop ? 'Reading the area you drew…' : 'Finding the floor-plan region…')
  const locked = crop && crop.x1 - crop.x0 > 48 && crop.y1 - crop.y0 > 48
  const originX = locked ? Math.max(0, Math.min(source.width - 2, crop.x0)) : 0
  const originY = locked ? Math.max(0, Math.min(source.height - 2, crop.y0)) : 0
  const sourceWidth = locked ? Math.min(source.width - originX, crop.x1 - originX) : source.width
  const sourceHeight = locked ? Math.min(source.height - originY, crop.y1 - originY) : source.height
  const scale = Math.min(1, (locked ? 1100 : MAX_SIDE) / Math.max(sourceWidth, sourceHeight))
  const width = Math.max(1, Math.round(sourceWidth * scale))
  const height = Math.max(1, Math.round(sourceHeight * scale))
  const canvas = document.createElement('canvas')
  canvas.width = width
  canvas.height = height
  const context = canvas.getContext('2d', { willReadFrequently: true })
  context.drawImage(source, originX, originY, sourceWidth, sourceHeight, 0, 0, width, height)
  const pixels = context.getImageData(0, 0, width, height).data
  const inkStyle = dominantNeutral(pixels, width, height, locked)
  const maxThickness = inkStyle.mode === 'line' ? 16 : 26
  const minThickness = inkStyle.mode === 'line' ? 4 : 1
  const isInk = (r, g, b) => {
    if (inkStyle.mode === 'line') return luminance(r, g, b) < 46 && saturation(r, g, b) < 36
    if (saturation(r, g, b) >= 24) return false
    return inkStyle.colors.some((color) => {
      const dr = r - color[0]
      const dg = g - color[1]
      const db = b - color[2]
      const band = (luminance(...color) > 150 ? 18 : 15) + (locked ? 8 : 0)
      return dr * dr + dg * dg + db * db <= band * band
    })
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

  await report(locked ? 'Tracing every room inside that frame…' : 'Tracing architectural lines…')
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
    if (locked && y > 0 && y % 140 === 0) await report('Tracing every room inside that frame…')
  }
  await report(locked ? 'Following the walls between the rooms…' : 'Tracing architectural lines…')
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
    if (locked && x > 0 && x % 140 === 0) await report('Following the walls between the rooms…')
  }
  walls = mergeAxis(mergeAxis(walls, 'h'), 'v')
  walls = walls.filter((wall) => segmentLength(wall) >= 36)

  await report(locked ? 'Lining the walls up with the whole plan…' : 'Setting aside furniture, labels, and open space…')
  const kept = locked
    ? quietFurniture(walls, { x0: 0, y0: 0, x1: width, y1: height })
    : classify(walls, width, height)
  const toSheet = (region) => ({
    bounds: locked
      ? { x0: originX, y0: originY, x1: originX + sourceWidth, y1: originY + sourceHeight }
      : {
        x0: region.bounds.x0 / scale + originX,
        y0: region.bounds.y0 / scale + originY,
        x1: region.bounds.x1 / scale + originX,
        y1: region.bounds.y1 / scale + originY
      },
    walls: region.walls.map((wall) => ({
      x0: wall.x0 / scale + originX,
      y0: wall.y0 / scale + originY,
      x1: wall.x1 / scale + originX,
      y1: wall.y1 / scale + originY,
      axis: wall.axis,
      confident: wall.confident !== false,
      enabled: wall.enabled !== false
    }))
  })
  const regions = locked
    ? [toSheet({ bounds: { x0: 0, y0: 0, x1: width, y1: height }, walls: kept })]
    : clusterRegions(kept, width, height).map(toSheet)
  for (let index = 0; index < regions.length; index++) {
    regions[index].furniture = await findFurniture(source, regions[index].bounds, regions[index].walls, report)
  }
  const built = regions.reduce((sum, region) => sum + region.walls.filter((wall) => wall.enabled !== false).length, 0)
  const held = regions.reduce((sum, region) => sum + region.walls.filter((wall) => wall.enabled === false).length, 0)
  const cropped = regions.some((region) => (region.bounds.x1 - region.bounds.x0) * (region.bounds.y1 - region.bounds.y0) < source.width * source.height * 0.72)
  let note = 'Drag a box around the whole floor plan, including every room. Then trace that area.'
  if (locked && built > 0) {
    note = 'Walls are red. Purple marks are furniture that will be built as 3D pieces. Click a mark, with Add wall selected, to drop it.'
  } else if (!regions.length || built === 0) {
    note = 'No reliable walls were found inside the frame. The drawing stays as the floor. Drag the walls you want, then build.'
  } else if (regions.length > 1) {
    note = 'Only part of the sheet was picked. Drag one box around the entire floor plan, then trace that area.'
  } else if (cropped) {
    note = 'The gold frame may be only part of the plan. Drag a box around every room, then trace that area.'
  } else if (held > 0) {
    note = 'Gold lines are left out until you click them. If the frame misses a room, drag a new box around the whole plan.'
  }
  return { regions, note }
}

const FURNITURE_TYPES = ['bed', 'sofa', 'armchair', 'table', 'dining', 'rug', 'plant', 'wardrobe', 'counter', 'chair', 'toilet', 'sink']

export function sceneFromWalls(bounds, walls, furniture) {
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
      })),
    furniture: (furniture || [])
      .filter((piece) => piece && piece.enabled !== false)
      .slice(0, 36)
      .map((piece) => ({
        type: FURNITURE_TYPES.includes(piece.type) ? piece.type : 'table',
        x: Number(piece.x) || 0,
        y: Number(piece.y) || 0,
        w: Number(piece.w) || 0,
        d: Number(piece.d) || 0,
        color: typeof piece.color === 'string' ? piece.color.slice(0, 7) : '#c4b8a8',
        confident: piece.confident !== false
      }))
  }
}
