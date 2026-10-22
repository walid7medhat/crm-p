// Finds colored furniture footprints inside a floor-plan crop.
// This is browser image analysis, not a trained vision model. Line drawings
// with no filled color often return little or nothing.

function luminance(r, g, b) {
  return 0.2126 * r + 0.7152 * g + 0.0722 * b
}

function saturation(r, g, b) {
  return Math.max(r, g, b) - Math.min(r, g, b)
}

function classifyPiece(r, g, b, width, height, areaRatio) {
  const lum = luminance(r, g, b)
  const sat = saturation(r, g, b)
  const aspect = Math.max(width, height) / Math.max(1, Math.min(width, height))
  const green = g > r + 8 && g > b
  const wood = r > g + 6 && g > b && sat > 18 && lum < 165
  const colorful = sat >= 22
  if (!green && !wood && !colorful && lum > 58) return null
  if (green && areaRatio < 0.02 && aspect < 1.75) return { type: 'plant', confident: true }
  if (green && aspect >= 1.45) return { type: 'sofa', confident: true }
  if (green) return { type: 'armchair', confident: true }
  if (lum < 58 && areaRatio < 0.04) return { type: aspect > 1.55 ? 'dining' : 'table', confident: true }
  if (wood && areaRatio > 0.012 && aspect < 2.4) return { type: 'bed', confident: true }
  if (colorful && aspect < 1.7 && areaRatio < 0.03) return { type: 'armchair', confident: true }
  if (colorful && aspect >= 1.4) return { type: 'sofa', confident: false }
  return null
}

export async function findFurniture(source, bounds, walls, report) {
  if (!source || !bounds) return []
  if (report) await report('Finding furniture, fixtures, and built-in pieces…')
  const originX = Math.max(0, Math.min(source.width - 2, bounds.x0))
  const originY = Math.max(0, Math.min(source.height - 2, bounds.y0))
  const sourceWidth = Math.min(source.width - originX, bounds.x1 - originX)
  const sourceHeight = Math.min(source.height - originY, bounds.y1 - originY)
  if (sourceWidth < 48 || sourceHeight < 48) return []
  const scale = Math.min(1, 560 / Math.max(sourceWidth, sourceHeight))
  const width = Math.max(1, Math.round(sourceWidth * scale))
  const height = Math.max(1, Math.round(sourceHeight * scale))
  const canvas = document.createElement('canvas')
  canvas.width = width
  canvas.height = height
  const context = canvas.getContext('2d', { willReadFrequently: true })
  context.drawImage(source, originX, originY, sourceWidth, sourceHeight, 0, 0, width, height)
  const pixels = context.getImageData(0, 0, width, height).data
  const wallMask = new Uint8Array(width * height)
  const paint = (x, y) => {
    if (x < 0 || y < 0 || x >= width || y >= height) return
    wallMask[y * width + x] = 1
  }
  ;(walls || []).forEach((wall) => {
    const horizontal = Math.abs(wall.x1 - wall.x0) >= Math.abs(wall.y1 - wall.y0)
    if (horizontal) {
      const y = Math.round((((wall.y0 + wall.y1) / 2) - originY) * scale)
      const x0 = Math.round((Math.min(wall.x0, wall.x1) - originX) * scale)
      const x1 = Math.round((Math.max(wall.x0, wall.x1) - originX) * scale)
      for (let x = x0 - 6; x <= x1 + 6; x++) {
        for (let band = -6; band <= 6; band++) paint(x, y + band)
      }
    } else {
      const x = Math.round((((wall.x0 + wall.x1) / 2) - originX) * scale)
      const y0 = Math.round((Math.min(wall.y0, wall.y1) - originY) * scale)
      const y1 = Math.round((Math.max(wall.y0, wall.y1) - originY) * scale)
      for (let y = y0 - 6; y <= y1 + 6; y++) {
        for (let band = -6; band <= 6; band++) paint(x + band, y)
      }
    }
  })

  let floorR = 0
  let floorG = 0
  let floorB = 0
  let floorN = 0
  for (let i = 0, p = 0; i < width * height; i++, p += 4) {
    if (wallMask[i]) continue
    const r = pixels[p]
    const g = pixels[p + 1]
    const b = pixels[p + 2]
    if (saturation(r, g, b) > 18 || luminance(r, g, b) < 170 || luminance(r, g, b) > 242) continue
    floorR += r
    floorG += g
    floorB += b
    floorN += 1
  }
  const floor = floorN ? [floorR / floorN, floorG / floorN, floorB / floorN] : [210, 204, 194]
  const mask = new Uint8Array(width * height)
  for (let i = 0, p = 0; i < mask.length; i++, p += 4) {
    if (wallMask[i]) continue
    const r = pixels[p]
    const g = pixels[p + 1]
    const b = pixels[p + 2]
    const lum = luminance(r, g, b)
    const sat = saturation(r, g, b)
    if (lum > 236 && sat < 12) continue
    if (sat < 18 && lum > 70 && lum < 230) continue
    const green = g > r + 8 && g > b
    const wood = r > g + 6 && g > b && sat > 18
    if (green || wood || sat >= 22 || lum < 52) mask[i] = 1
  }

  if (report) await report('Classifying beds, seating, tables, and fittings…')
  const seen = new Uint8Array(width * height)
  const pieces = []
  const planArea = width * height
  for (let start = 0; start < mask.length; start++) {
    if (!mask[start] || seen[start]) continue
    const stack = [start]
    seen[start] = 1
    let count = 0
    let minX = width
    let minY = height
    let maxX = 0
    let maxY = 0
    let red = 0
    let green = 0
    let blue = 0
    while (stack.length) {
      const pixel = stack.pop()
      const x = pixel % width
      const y = (pixel - x) / width
      count += 1
      if (x < minX) minX = x
      if (y < minY) minY = y
      if (x > maxX) maxX = x
      if (y > maxY) maxY = y
      const index = pixel * 4
      red += pixels[index]
      green += pixels[index + 1]
      blue += pixels[index + 2]
      const neighbors = [pixel - 1, pixel + 1, pixel - width, pixel + width]
      neighbors.forEach((next) => {
        if (next < 0 || next >= mask.length || seen[next] || !mask[next]) return
        if (next === pixel - 1 && x === 0) return
        if (next === pixel + 1 && x === width - 1) return
        seen[next] = 1
        stack.push(next)
      })
    }
    const boxW = maxX - minX + 1
    const boxH = maxY - minY + 1
    const areaRatio = count / planArea
    if (count < 160 || areaRatio > 0.16) continue
    if (boxW < 10 || boxH < 10) continue
    if (count / (boxW * boxH) < 0.28) continue
    if (minX < 2 && maxX > width - 3 && minY < 2 && maxY > height - 3) continue
    let samples = 0
    let touching = 0
    for (let x = minX; x <= maxX; x += 4) {
      samples += 2
      if (wallMask[minY * width + x] || wallMask[maxY * width + x]) touching += 1
    }
    for (let y = minY; y <= maxY; y += 4) {
      samples += 2
      if (wallMask[y * width + minX] || wallMask[y * width + maxX]) touching += 1
    }
    if (samples && touching / samples > 0.42 && areaRatio > 0.03) continue
    const kind = classifyPiece(red / count, green / count, blue / count, boxW, boxH, areaRatio)
    if (!kind) continue
    const sourceW = boxW / scale
    const sourceH = boxH / scale
    if (sourceW < 22 || sourceH < 22) continue
    const hex = (value) => Math.max(0, Math.min(255, Math.round(value))).toString(16).padStart(2, '0')
    pieces.push({
      type: kind.type,
      x: originX + (minX + maxX) / 2 / scale,
      y: originY + (minY + maxY) / 2 / scale,
      w: sourceW,
      d: sourceH,
      color: `#${hex(red / count)}${hex(green / count)}${hex(blue / count)}`,
      confident: kind.confident,
      enabled: true
    })
  }
  pieces.sort((a, b) => b.w * b.d - a.w * a.d)
  const kept = []
  pieces.forEach((piece) => {
    const overlap = kept.some((other) => {
      const dx = Math.abs(piece.x - other.x)
      const dy = Math.abs(piece.y - other.y)
      return dx < (piece.w + other.w) * 0.3 && dy < (piece.d + other.d) * 0.3
    })
    if (!overlap) kept.push(piece)
  })
  return kept.slice(0, 36)
}
