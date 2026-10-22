import * as THREE from 'three'

const EYE = 1.62
const BODY = 0.28
const HOME_FOV = 38
const HOME_POLAR = 0.74

function knockOutPaper(source, bounds) {
  const x0 = Math.max(0, Math.floor(bounds.x0))
  const y0 = Math.max(0, Math.floor(bounds.y0))
  const x1 = Math.min(source.width, Math.ceil(bounds.x1))
  const y1 = Math.min(source.height, Math.ceil(bounds.y1))
  const w = Math.max(1, x1 - x0)
  const h = Math.max(1, y1 - y0)
  const canvas = document.createElement('canvas')
  canvas.width = source.width
  canvas.height = source.height
  const context = canvas.getContext('2d')
  context.drawImage(source, 0, 0)
  const image = context.getImageData(x0, y0, w, h)
  const data = image.data
  const paper = (index) => {
    const r = data[index]
    const g = data[index + 1]
    const b = data[index + 2]
    const max = Math.max(r, g, b)
    const min = Math.min(r, g, b)
    return min >= 236 && max - min <= 5
  }
  const seen = new Uint8Array(w * h)
  const stack = []
  const visit = (x, y) => {
    if (x < 0 || y < 0 || x >= w || y >= h) return
    const pixel = y * w + x
    if (seen[pixel]) return
    seen[pixel] = 1
    if (!paper(pixel * 4)) return
    stack.push(pixel)
  }
  for (let x = 0; x < w; x++) {
    visit(x, 0)
    visit(x, h - 1)
  }
  for (let y = 0; y < h; y++) {
    visit(0, y)
    visit(w - 1, y)
  }
  let cleared = 0
  while (stack.length) {
    const pixel = stack.pop()
    data[pixel * 4 + 3] = 0
    cleared += 1
    const x = pixel % w
    const y = (pixel - x) / w
    visit(x + 1, y)
    visit(x - 1, y)
    visit(x, y + 1)
    visit(x, y - 1)
  }
  if (cleared > w * h * 0.42) return source
  context.putImageData(image, x0, y0)
  return canvas
}

function contentBox(canvas, bounds) {
  const x0 = Math.max(0, Math.floor(bounds.x0))
  const y0 = Math.max(0, Math.floor(bounds.y0))
  const x1 = Math.min(canvas.width, Math.ceil(bounds.x1))
  const y1 = Math.min(canvas.height, Math.ceil(bounds.y1))
  const w = Math.max(1, x1 - x0)
  const h = Math.max(1, y1 - y0)
  const data = canvas.getContext('2d').getImageData(x0, y0, w, h).data
  let minX = w
  let minY = h
  let maxX = -1
  let maxY = -1
  for (let y = 0; y < h; y++) {
    for (let x = 0; x < w; x++) {
      if (data[(y * w + x) * 4 + 3] < 20) continue
      if (x < minX) minX = x
      if (y < minY) minY = y
      if (x > maxX) maxX = x
      if (y > maxY) maxY = y
    }
  }
  if (maxX < 0) return bounds
  const pad = 6
  return {
    x0: Math.max(bounds.x0, x0 + minX - pad),
    y0: Math.max(bounds.y0, y0 + minY - pad),
    x1: Math.min(bounds.x1, x0 + maxX + 1 + pad),
    y1: Math.min(bounds.y1, y0 + maxY + 1 + pad)
  }
}

export function mountFloorPlanScene(canvas, sourceCanvas, onChange, traced) {
  const scene = new THREE.Scene()
  scene.background = new THREE.Color(0x17141a)
  const camera = new THREE.PerspectiveCamera(HOME_FOV, 1, 0.05, 120)
  camera.rotation.order = 'YXZ'
  const renderer = new THREE.WebGLRenderer({
    canvas,
    antialias: true,
    powerPreference: 'high-performance'
  })
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2))
  renderer.shadowMap.enabled = true
  renderer.shadowMap.type = THREE.PCFSoftShadowMap
  renderer.toneMapping = THREE.NoToneMapping
  if ('outputColorSpace' in renderer && THREE.SRGBColorSpace) renderer.outputColorSpace = THREE.SRGBColorSpace

  scene.add(new THREE.HemisphereLight(0xfff8f1, 0x3a343c, 0.72))
  scene.add(new THREE.AmbientLight(0xfff6ee, 0.22))
  const sun = new THREE.DirectionalLight(0xfff3e4, 1.15)
  sun.castShadow = true
  sun.shadow.mapSize.set(2048, 2048)
  sun.shadow.bias = -0.00035
  scene.add(sun)
  scene.add(sun.target)

  const plaster = new THREE.MeshStandardMaterial({ color: 0xf6f2ea, roughness: 0.62, metalness: 0.02 })
  const capMat = new THREE.MeshStandardMaterial({ color: 0xfffdf8, roughness: 0.38 })
  const uncertain = new THREE.MeshStandardMaterial({
    color: 0xd4af37,
    roughness: 0.46,
    transparent: true,
    opacity: 0.45,
    depthWrite: false
  })
  const solids = []
  const plan = traced || {
    walls: [],
    bounds: { x0: 0, y0: 0, x1: sourceCanvas.width, y1: sourceCanvas.height }
  }
  const metres = 11 / Math.max(plan.bounds.x1 - plan.bounds.x0, plan.bounds.y1 - plan.bounds.y0)
  const width = (plan.bounds.x1 - plan.bounds.x0) * metres
  const depth = (plan.bounds.y1 - plan.bounds.y0) * metres
  const wallH = 1.58
  const wallThick = 0.16
  const wx = (px) => (px - plan.bounds.x0) * metres
  const wz = (py) => (py - plan.bounds.y0) * metres

  const sheet = knockOutPaper(sourceCanvas, plan.bounds)
  const content = contentBox(sheet, plan.bounds)
  const floorX0 = wx(content.x0)
  const floorX1 = wx(content.x1)
  const floorZ0 = wz(content.y0)
  const floorZ1 = wz(content.y1)
  const span = Math.max(floorX1 - floorX0, floorZ1 - floorZ0, 1)
  sun.position.set((floorX0 + floorX1) / 2 - span * 0.35, span * 1.45, (floorZ0 + floorZ1) / 2 - span * 0.2)
  sun.shadow.camera.left = -span
  sun.shadow.camera.right = span
  sun.shadow.camera.top = span
  sun.shadow.camera.bottom = -span
  sun.shadow.camera.near = 0.5
  sun.shadow.camera.far = span * 4
  const studio = new THREE.Mesh(
    new THREE.CircleGeometry(span + 6, 72),
    new THREE.MeshStandardMaterial({ color: 0x141218, roughness: 1 })
  )
  studio.rotation.x = -Math.PI / 2
  studio.position.set((floorX0 + floorX1) / 2, -0.04, (floorZ0 + floorZ1) / 2)
  studio.receiveShadow = true
  scene.add(studio)

  const shape = new THREE.Shape()
  shape.moveTo(floorX0, -floorZ0)
  shape.lineTo(floorX1, -floorZ0)
  shape.lineTo(floorX1, -floorZ1)
  shape.lineTo(floorX0, -floorZ1)
  shape.closePath()
  const geo = new THREE.ShapeGeometry(shape)
  const position = geo.attributes.position
  const uv = geo.attributes.uv
  for (let i = 0; i < position.count; i++) {
    const px = plan.bounds.x0 + position.getX(i) / metres
    const py = plan.bounds.y0 + (-position.getY(i)) / metres
    uv.setXY(i, px / sourceCanvas.width, 1 - py / sourceCanvas.height)
  }

  const map = new THREE.CanvasTexture(sheet)
  if (THREE.SRGBColorSpace) map.colorSpace = THREE.SRGBColorSpace
  map.generateMipmaps = true
  map.minFilter = THREE.LinearMipmapLinearFilter
  map.magFilter = THREE.LinearFilter
  map.anisotropy = renderer.capabilities.getMaxAnisotropy()
  const floor = new THREE.Mesh(geo, new THREE.MeshStandardMaterial({
    map,
    roughness: 0.96,
    metalness: 0,
    transparent: true,
    alphaTest: 0.4,
    side: THREE.DoubleSide
  }))
  floor.rotation.x = -Math.PI / 2
  floor.position.y = 0.02
  floor.receiveShadow = true
  scene.add(floor)

  function addBox(x0, z0, x1, z1, height, collide, material) {
    const minX = Math.min(x0, x1)
    const maxX = Math.max(x0, x1)
    const minZ = Math.min(z0, z1)
    const maxZ = Math.max(z0, z1)
    const mesh = new THREE.Mesh(
      new THREE.BoxGeometry(Math.max(0.05, maxX - minX), height, Math.max(0.05, maxZ - minZ)),
      material || plaster
    )
    mesh.position.set((minX + maxX) / 2, height / 2, (minZ + maxZ) / 2)
    mesh.castShadow = true
    mesh.receiveShadow = true
    scene.add(mesh)
    if (material !== uncertain && height > 0.4) {
      const cap = new THREE.Mesh(
        new THREE.BoxGeometry(Math.max(0.05, maxX - minX) + 0.012, 0.05, Math.max(0.05, maxZ - minZ) + 0.012),
        capMat
      )
      cap.position.set((minX + maxX) / 2, height - 0.02, (minZ + maxZ) / 2)
      cap.castShadow = true
      scene.add(cap)
    }
    if (collide) solids.push({ minX, maxX, minZ, maxZ })
  }

  plan.walls.forEach((rect) => {
    const box = Array.isArray(rect) ? rect : [rect.x0, rect.y0, rect.x1, rect.y1]
    const confident = Array.isArray(rect) ? true : rect.confident !== false
    const material = confident ? plaster : uncertain
    const horizontal = Math.abs(box[2] - box[0]) >= Math.abs(box[3] - box[1])
    if (horizontal) {
      const z = wz((box[1] + box[3]) / 2)
      addBox(wx(Math.min(box[0], box[2])), z - wallThick / 2, wx(Math.max(box[0], box[2])), z + wallThick / 2, wallH, true, material)
    } else {
      const x = wx((box[0] + box[2]) / 2)
      addBox(x - wallThick / 2, wz(Math.min(box[1], box[3])), x + wallThick / 2, wz(Math.max(box[1], box[3])), wallH, true, material)
    }
  })

  function findSpawn() {
    for (let gz = 0.22; gz <= 0.78; gz += 0.07) {
      for (let gx = 0.22; gx <= 0.78; gx += 0.07) {
        const x = floorX0 + (floorX1 - floorX0) * gx
        const z = floorZ0 + (floorZ1 - floorZ0) * gz
        if (!overlaps(x, z) && !overlaps(x + 0.35, z) && !overlaps(x, z + 0.35)) return [x, z]
      }
    }
    return [(floorX0 + floorX1) / 2, (floorZ0 + floorZ1) / 2]
  }
  const [spawnX, spawnZ] = findSpawn()
  const player = { x: spawnX, z: spawnZ, yaw: 0, pitch: -0.2 }
  const orbit = {
    azimuth: 0.62,
    polar: HOME_POLAR,
    radius: span * 2.15,
    targetX: (floorX0 + floorX1) / 2,
    targetY: 0.45,
    targetZ: (floorZ0 + floorZ1) / 2
  }
  const fitRadius = orbit.radius
  let mode = 'dollhouse'
  let lookX = 0
  let lookZ = -1
  const keys = new Set()
  let dragging = false
  let pointerX = 0
  let pointerY = 0
  let alive = true
  sun.target.position.set(orbit.targetX, 0, orbit.targetZ)

  function overlaps(x, z) {
    return solids.some((solid) => {
      const cx = Math.max(solid.minX, Math.min(x, solid.maxX))
      const cz = Math.max(solid.minZ, Math.min(z, solid.maxZ))
      return (x - cx) ** 2 + (z - cz) ** 2 < BODY * BODY
    })
  }
  function tryMove(dx, dz) {
    const steps = Math.max(1, Math.ceil(Math.hypot(dx, dz) / 0.08))
    for (let i = 0; i < steps; i++) {
      if (!overlaps(player.x + dx / steps, player.z)) player.x += dx / steps
      if (!overlaps(player.x, player.z + dz / steps)) player.z += dz / steps
    }
  }
  function updateLook() {
    lookX = -Math.sin(player.yaw)
    lookZ = -Math.cos(player.yaw)
    camera.position.set(player.x, EYE, player.z)
    camera.rotation.order = 'YXZ'
    camera.rotation.y = player.yaw
    camera.rotation.x = player.pitch
    camera.rotation.z = 0
  }
  function updateOrbit() {
    const sp = Math.sin(orbit.polar)
    const cp = Math.cos(orbit.polar)
    camera.position.set(
      orbit.targetX + orbit.radius * sp * Math.sin(orbit.azimuth),
      orbit.targetY + orbit.radius * cp,
      orbit.targetZ + orbit.radius * sp * Math.cos(orbit.azimuth)
    )
    camera.lookAt(orbit.targetX, orbit.targetY, orbit.targetZ)
  }
  function notify() {
    onChange?.({ mode })
  }
  function showDollhouse() {
    mode = 'dollhouse'
    orbit.azimuth = 0.62
    orbit.polar = HOME_POLAR
    orbit.radius = fitRadius
    camera.fov = HOME_FOV
    camera.updateProjectionMatrix()
    document.exitPointerLock?.()
    notify()
  }
  function enterWalk() {
    mode = 'walk'
    const [x, z] = findSpawn()
    player.x = x
    player.z = z
    player.yaw = 0
    player.pitch = -0.16
    document.exitPointerLock?.()
    notify()
  }
  function reset() {
    showDollhouse()
  }

  function onPointerDown(event) {
    if (event.button !== 0) return
    dragging = true
    pointerX = event.clientX
    pointerY = event.clientY
    if (mode === 'walk') canvas.requestPointerLock?.()
  }
  function onPointerUp() {
    dragging = false
  }
  function onMouseMove(event) {
    if (mode === 'walk') {
      if (document.pointerLockElement !== canvas && !dragging) return
      player.yaw -= event.movementX * 0.0022
      player.pitch = Math.max(-1.05, Math.min(0.9, player.pitch - event.movementY * 0.0022))
      return
    }
    if (!dragging) return
    orbit.azimuth -= (event.clientX - pointerX) * 0.005
    orbit.polar = Math.max(0.22, Math.min(1.2, orbit.polar - (event.clientY - pointerY) * 0.004))
    pointerX = event.clientX
    pointerY = event.clientY
  }
  function onWheel(event) {
    event.preventDefault()
    const step = Math.max(-80, Math.min(80, event.deltaY))
    if (mode === 'walk') {
      camera.fov = Math.max(28, Math.min(62, camera.fov + step * 0.02))
      camera.updateProjectionMatrix()
      return
    }
    const next = orbit.radius * Math.exp(step * 0.0022)
    orbit.radius = Math.max(fitRadius * 0.18, Math.min(fitRadius * 2.8, next))
  }
  function zoomOut() {
    orbit.radius = Math.min(fitRadius * 2.8, orbit.radius * 1.28)
  }
  function zoomIn() {
    orbit.radius = Math.max(fitRadius * 0.18, orbit.radius * 0.78)
  }
  function onKeyDown(event) {
    keys.add(event.code)
    if (event.code === 'KeyR') reset()
  }
  function onKeyUp(event) {
    keys.delete(event.code)
  }
  function onBlur() {
    keys.clear()
  }

  canvas.addEventListener('pointerdown', onPointerDown)
  window.addEventListener('pointerup', onPointerUp)
  window.addEventListener('mousemove', onMouseMove)
  canvas.addEventListener('wheel', onWheel, { passive: false })
  window.addEventListener('keydown', onKeyDown)
  window.addEventListener('keyup', onKeyUp)
  window.addEventListener('blur', onBlur)

  function resize() {
    const widthPx = canvas.clientWidth || canvas.parentElement?.clientWidth || 1
    const heightPx = canvas.clientHeight || canvas.parentElement?.clientHeight || 1
    camera.aspect = widthPx / Math.max(heightPx, 1)
    camera.updateProjectionMatrix()
    renderer.setSize(widthPx, heightPx, false)
  }

  let last = performance.now()
  function frame(now) {
    if (!alive) return
    const dt = Math.min(0.05, (now - last) / 1000)
    last = now
    if (mode === 'walk') {
      let forward = 0
      let side = 0
      if (keys.has('KeyW') || keys.has('ArrowUp')) forward += 1
      if (keys.has('KeyS') || keys.has('ArrowDown')) forward -= 1
      if (keys.has('KeyA') || keys.has('ArrowLeft')) side -= 1
      if (keys.has('KeyD') || keys.has('ArrowRight')) side += 1
      updateLook()
      if (forward || side) {
        const length = Math.hypot(forward, side) || 1
        const speed = 1.7 * dt
        tryMove((lookX * forward - lookZ * side) / length * speed, (lookZ * forward + lookX * side) / length * speed)
        updateLook()
      }
    } else {
      updateOrbit()
    }
    renderer.render(scene, camera)
    requestAnimationFrame(frame)
  }

  resize()
  notify()
  requestAnimationFrame(frame)

  return {
    showDollhouse,
    enterWalk,
    zoomIn,
    zoomOut,
    reset,
    resize,
    dispose() {
      alive = false
      document.exitPointerLock?.()
      canvas.removeEventListener('pointerdown', onPointerDown)
      window.removeEventListener('pointerup', onPointerUp)
      window.removeEventListener('mousemove', onMouseMove)
      canvas.removeEventListener('wheel', onWheel)
      window.removeEventListener('keydown', onKeyDown)
      window.removeEventListener('keyup', onKeyUp)
      window.removeEventListener('blur', onBlur)
      scene.traverse((object) => {
        if (object.geometry) object.geometry.dispose()
        const materials = object.material ? [].concat(object.material) : []
        materials.forEach((material) => {
          if (material.map) material.map.dispose()
          material.dispose()
        })
      })
      renderer.dispose()
    }
  }
}
