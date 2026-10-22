// Reads a floor-plan image and returns wall segments in the original pixel
// coordinates. The original image is never modified.
//
// Strokes are kept when they are thin, axis-aligned, and meet the outer
// shell. Door-sized gaps are left open. A side of the plan with almost no
// detected wall is closed with an uncertain segment so the model is not
// open to the outside.

const MAX_SIDE = 780

function luminance(r, g, b) {
  return 0.2126 * r + 0.7152 * g + 0.0722 * b
}

function boxBlur(source, width, height, radius) {
  const temp = new Float64Array(width * height)
  const output = new Float64Array(width * height)
  const divisor = radius * 2 + 1
  for (let y = 0; y < height; y++) {
    const row = y * width
    let acc = 0
    for (let x = 0; x < width; x++) {
      if (x === 0) {
        acc = 0
        for (let k = 0; k < Math.min(width, radius + 1); k++) acc += source[row + k]
      } else {
        if (x - radius - 1 >= 0) acc -= source[row + x - radius - 1]
        acc += source[row + Math.min(width - 1, x + radius)]
      }
      temp[row + x] = acc / divisor
    }
  }
  for (let x = 0; x < width; x++) {
    let acc = 0
    for (let y = 0; y < height; y++) {
      if (y === 0) {
        acc = 0
        for (let k = 0; k < Math.min(height, radius + 1); k++) acc += temp[k * width + x]
      } else {
        if (y - radius - 1 >= 0) acc -= temp[(y - radius - 1) * width + x]
        acc += temp[Math.min(height - 1, y + radius) * width + x]
      }
      output[y * width + x] = acc / divisor
    }
  }
  return output
}

function segmentLength(wall) {
  return Math.max(wall.x1 - wall.x0, wall.y1 - wall.y0)
}

function segmentThickness(wall) {
  return Math.min(wall.x1 - wall.x0, wall.y1 - wall.y0)
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

function mergeAxis(walls, axis) {
  let group = walls.filter((wall) => wall.axis === axis).map((wall) => ({ ...wall }))
  const rest = walls.filter((wall) => wall.axis !== axis)
  let changed = true
  let guard = 0
  while (changed && guard < 24) {
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
        if (Math.abs(center - otherCenter) > 6) continue
        const gap = axis === 'h'
          ? (wall.x0 <= other.x1 + 1 && other.x0 <= wall.x1 + 1 ? 0 : Math.min(Math.abs(other.x0 - wall.x1), Math.abs(wall.x0 - other.x1)))
          : (wall.y0 <= other.y1 + 1 && other.y0 <= wall.y1 + 1 ? 0 : Math.min(Math.abs(other.y0 - wall.y1), Math.abs(wall.y0 - other.y1)))
        if (gap > 6) continue
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

function neutralStroke(pixels, width, height, wall) {
  const samples = []
  if (wall.axis === 'h') {
    const y0 = Math.max(0, Math.floor(wall.y0))
    const y1 = Math.min(height - 1, Math.ceil(wall.y1))
    for (let i = 0; i < 9; i++) {
      const x = Math.max(0, Math.min(width - 1, Math.round(wall.x0 + (wall.x1 - wall.x0) * i / 8)))
      let best = null
      let bestLum = 999
      for (let y = y0; y <= y1; y++) {
        const index = (y * width + x) * 4
        const lum = luminance(pixels[index], pixels[index + 1], pixels[index + 2])
        if (lum < bestLum) {
          bestLum = lum
          best = [pixels[index], pixels[index + 1], pixels[index + 2]]
        }
      }
      if (best) samples.push(Math.max(...best) - Math.min(...best))
    }
  } else {
    const x0 = Math.max(0, Math.floor(wall.x0))
    const x1 = Math.min(width - 1, Math.ceil(wall.x1))
    for (let i = 0; i < 9; i++) {
      const y = Math.max(0, Math.min(height - 1, Math.round(wall.y0 + (wall.y1 - wall.y0) * i / 8)))
      let best = null
      let bestLum = 999
      for (let x = x0; x <= x1; x++) {
        const index = (y * width + x) * 4
        const lum = luminance(pixels[index], pixels[index + 1], pixels[index + 2])
        if (lum < bestLum) {
          bestLum = lum
          best = [pixels[index], pixels[index + 1], pixels[index + 2]]
        }
      }
      if (best) samples.push(Math.max(...best) - Math.min(...best))
    }
  }
  if (!samples.length) return false
  samples.sort((a, b) => a - b)
  return samples[Math.floor(samples.length / 2)] <= 26
}

function closeOpenSides(walls, bounds) {
  const span = Math.max(bounds.x1 - bounds.x0, bounds.y1 - bounds.y0)
  const sides = [
    { axis: 'h', fixed: bounds.y0, start: bounds.x0, end: bounds.x1, horizontal: true },
    { axis: 'h', fixed: bounds.y1, start: bounds.x0, end: bounds.x1, horizontal: true },
    { axis: 'v', fixed: bounds.x0, start: bounds.y0, end: bounds.y1, horizontal: false },
    { axis: 'v', fixed: bounds.x1, start: bounds.y0, end: bounds.y1, horizontal: false }
  ]
  sides.forEach((side) => {
    const covered = walls
      .filter((wall) => wall.axis === side.axis && Math.abs((side.horizontal ? (wall.y0 + wall.y1) / 2 : (wall.x0 + wall.x1) / 2) - side.fixed) < 22)
      .map((wall) => side.horizontal ? [wall.x0, wall.x1] : [wall.y0, wall.y1])
      .sort((a, b) => a[0] - b[0])
    const merged = []
    covered.forEach(([a, b]) => {
      const last = merged[merged.length - 1]
      if (last && a <= last[1] + 8) last[1] = Math.max(last[1], b)
      else merged.push([a, b])
    })
    const gaps = []
    let cursor = side.start
    merged.forEach(([a, b]) => {
      if (a - cursor > 8) gaps.push([cursor, a])
      cursor = Math.max(cursor, b)
    })
    if (side.end - cursor > 8) gaps.push([cursor, side.end])
    const coveredLength = merged.reduce((sum, span) => sum + Math.max(0, span[1] - span[0]), 0)
    const sideLength = side.end - side.start
    if (coveredLength > sideLength * 0.45) return
    gaps.forEach(([a, b]) => {
      if (b - a < Math.max(36, span * 0.08)) return
      if (merged.length && b - a < 90) return
      const thickness = 8
      walls.push(side.horizontal
        ? { axis: 'h', x0: a, x1: b, y0: side.fixed - thickness / 2, y1: side.fixed + thickness / 2, confident: false, enabled: true }
        : { axis: 'v', x0: side.fixed - thickness / 2, x1: side.fixed + thickness / 2, y0: a, y1: b, confident: false, enabled: true })
    })
  })
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
      if (!connects(walls[i], walls[j], 18)) continue
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
  let regions = [...groups.values()].filter((group) => {
    const longest = Math.max(...group.map(segmentLength))
    return group.length >= 4 || longest > 0.2 * Math.max(width, height)
  })
  const bbox = (group) => ({
    x0: Math.min(...group.map((wall) => wall.x0)),
    y0: Math.min(...group.map((wall) => wall.y0)),
    x1: Math.max(...group.map((wall) => wall.x1)),
    y1: Math.max(...group.map((wall) => wall.y1))
  })
  let changed = true
  while (changed) {
    changed = false
    for (let i = 0; i < regions.length; i++) {
      for (let j = i + 1; j < regions.length; j++) {
        const a = bbox(regions[i])
        const b = bbox(regions[j])
        const gapX = a.x1 >= b.x0 && b.x1 >= a.x0 ? 0 : Math.min(Math.abs(b.x0 - a.x1), Math.abs(a.x0 - b.x1))
        const gapY = a.y1 >= b.y0 && b.y1 >= a.y0 ? 0 : Math.min(Math.abs(b.y0 - a.y1), Math.abs(a.y0 - b.y1))
        if (Math.max(gapX, gapY) >= 0.14 * Math.min(width, height)) continue
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
      return (box.x1 - box.x0) * (box.y1 - box.y0) > largestArea * 0.22
    })
  }
  return regions.map((group) => {
    const box = bbox(group)
    const pad = 10
    const bounds = {
      x0: Math.max(0, box.x0 - pad),
      y0: Math.max(0, box.y0 - pad),
      x1: Math.min(width, box.x1 + pad),
      y1: Math.min(height, box.y1 + pad)
    }
    closeOpenSides(group, bounds)
    return { bounds, walls: group }
  })
}

export function analyzeFloorPlan(source) {
  const scale = Math.min(1, MAX_SIDE / Math.max(source.width, source.height))
  const width = Math.max(1, Math.round(source.width * scale))
  const height = Math.max(1, Math.round(source.height * scale))
  const canvas = document.createElement('canvas')
  canvas.width = width
  canvas.height = height
  const context = canvas.getContext('2d', { willReadFrequently: true })
  context.drawImage(source, 0, 0, width, height)
  const image = context.getImageData(0, 0, width, height)
  const pixels = image.data
  const luminanceMap = new Float64Array(width * height)
  for (let i = 0, p = 0; i < luminanceMap.length; i++, p += 4) {
    luminanceMap[i] = luminance(pixels[p], pixels[p + 1], pixels[p + 2])
  }
  const blur = boxBlur(luminanceMap, width, height, 28)
  const mask = new Uint8Array(width * height)
  for (let i = 0, p = 0; i < mask.length; i++, p += 4) {
    const sat = Math.max(pixels[p], pixels[p + 1], pixels[p + 2]) - Math.min(pixels[p], pixels[p + 1], pixels[p + 2])
    if (sat < 40 && luminanceMap[i] < blur[i] - 16 && luminanceMap[i] < 200) mask[i] = 1
  }
  const ink = (x, y) => x >= 0 && y >= 0 && x < width && y < height && mask[y * width + x] === 1
  const maxThickness = 44
  const spanV = (x, y) => {
    let top = y
    let steps = 0
    while (steps < maxThickness + 4 && ink(x, top - 1)) { top -= 1; steps += 1 }
    let bottom = y
    steps = 0
    while (steps < maxThickness + 4 && ink(x, bottom + 1)) { bottom += 1; steps += 1 }
    return bottom - top + 1
  }
  const spanH = (x, y) => {
    let left = x
    let steps = 0
    while (steps < maxThickness + 4 && ink(left - 1, y)) { left -= 1; steps += 1 }
    let right = x
    steps = 0
    while (steps < maxThickness + 4 && ink(right + 1, y)) { right += 1; steps += 1 }
    return right - left + 1
  }

  let walls = []
  const minRun = 20
  for (let y = 0; y < height; y++) {
    let x = 0
    while (x < width) {
      if (!ink(x, y)) { x += 1; continue }
      let x1 = x + 1
      while (x1 < width && ink(x1, y)) x1 += 1
      if (x1 - x >= minRun) {
        const samples = []
        for (let i = 0; i < 7; i++) {
          const sx = x + Math.floor((x1 - x - 1) * i / 6)
          const thickness = spanV(sx, y)
          if (thickness >= 3 && thickness <= maxThickness) samples.push(thickness)
        }
        if (samples.length >= 4) {
          samples.sort((a, b) => a - b)
          const thickness = samples[Math.floor(samples.length / 2)]
          walls.push({ axis: 'h', x0: x, x1, y0: y - thickness / 2, y1: y + thickness / 2, confident: true, enabled: true })
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
        for (let i = 0; i < 7; i++) {
          const sy = y + Math.floor((y1 - y - 1) * i / 6)
          const thickness = spanH(x, sy)
          if (thickness >= 3 && thickness <= maxThickness) samples.push(thickness)
        }
        if (samples.length >= 4) {
          samples.sort((a, b) => a - b)
          const thickness = samples[Math.floor(samples.length / 2)]
          walls.push({ axis: 'v', x0: x - thickness / 2, x1: x + thickness / 2, y0: y, y1, confident: true, enabled: true })
        }
      }
      y = y1
    }
  }

  walls = mergeAxis(mergeAxis(walls, 'h'), 'v')
  walls = walls.filter((wall) => segmentLength(wall) >= 34 && segmentThickness(wall) >= 3 && segmentThickness(wall) <= maxThickness + 6)
  walls = walls.filter((wall) => neutralStroke(pixels, width, height, wall))

  const span = Math.max(width, height)
  const long = walls.filter((wall) => segmentLength(wall) >= 0.12 * span)
  const base = long.length ? long : walls
  if (!base.length) {
    return {
      regions: [],
      note: 'No architectural lines could be read. The drawing may be too faint, or the plan is mostly a photograph.'
    }
  }
  const frame = {
    x0: Math.min(...base.map((wall) => wall.x0)),
    y0: Math.min(...base.map((wall) => wall.y0)),
    x1: Math.max(...base.map((wall) => wall.x1)),
    y1: Math.max(...base.map((wall) => wall.y1))
  }
  const onFrame = (wall) => wall.x0 <= frame.x0 + 18 || wall.y0 <= frame.y0 + 18 || wall.x1 >= frame.x1 - 18 || wall.y1 >= frame.y1 - 18
  const seed = new Set()
  walls.forEach((wall, index) => {
    if (onFrame(wall) && segmentLength(wall) >= 0.05 * span) seed.add(index)
    else if (segmentLength(wall) >= 0.22 * span && segmentThickness(wall) >= 4) seed.add(index)
  })
  let changed = true
  while (changed) {
    changed = false
    walls.forEach((wall, index) => {
      if (seed.has(index) || segmentLength(wall) < 30 || segmentThickness(wall) < 3.2) return
      for (const other of seed) {
        if (!connects(wall, walls[other], 16)) continue
        seed.add(index)
        changed = true
        break
      }
    })
  }
  const kept = walls.filter((_, index) => seed.has(index))
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

  const cropped = regions.some((region) => {
    const box = region.bounds
    return (box.x1 - box.x0) * (box.y1 - box.y0) < source.width * source.height * 0.72
  })
  return {
    regions,
    note: regions.length > 1
      ? 'More than one plan was found on this sheet. Choose the one to build.'
      : cropped
        ? 'The plan was separated from the rest of the sheet. Check the frame before building.'
        : 'Walls were traced from the drawing. Click any line that is furniture or a wrong guess.'
  }
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
