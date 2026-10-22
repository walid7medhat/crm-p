import * as THREE from 'three'

const EYE = 1.62
const BODY = 0.28
const HOME_FOV = 42

function clampByte(value) {
  return value < 0 ? 0 : value > 255 ? 255 : value
}

function sharpen(source) {
  const canvas = document.createElement('canvas')
  canvas.width = source.width * 2
  canvas.height = source.height * 2
  const context = canvas.getContext('2d', { willReadFrequently: true })
  context.imageSmoothingEnabled = true
  context.imageSmoothingQuality = 'high'
  context.drawImage(source, 0, 0, canvas.width, canvas.height)
  const image = context.getImageData(0, 0, canvas.width, canvas.height)
  const pixels = image.data
  for (let i = 0; i < pixels.length; i += 4) {
    pixels[i] = clampByte((pixels[i] - 128) * 1.08 + 132)
    pixels[i + 1] = clampByte((pixels[i + 1] - 128) * 1.08 + 132)
    pixels[i + 2] = clampByte((pixels[i + 2] - 128) * 1.08 + 132)
  }
  context.putImageData(image, 0, 0)
  return canvas
}

export function mountFloorPlanScene(canvas, sourceCanvas, onChange) {
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

  scene.add(new THREE.HemisphereLight(0xfffaf4, 0xc8bfb4, 0.55))
  scene.add(new THREE.AmbientLight(0xffffff, 0.38))
  const sun = new THREE.DirectionalLight(0xfff6ec, 0.72)
  sun.position.set(8, 16, 6)
  sun.castShadow = true
  sun.shadow.mapSize.set(2048, 2048)
  sun.shadow.camera.left = -16
  sun.shadow.camera.right = 16
  sun.shadow.camera.top = 16
  sun.shadow.camera.bottom = -16
  sun.shadow.camera.near = 1
  sun.shadow.camera.far = 48
  scene.add(sun)
  scene.add(sun.target)

  const plaster = new THREE.MeshStandardMaterial({ color: 0xf7f4ef, roughness: 0.72 })
  const plinthMat = new THREE.MeshStandardMaterial({ color: 0x2a242c, roughness: 0.94 })
  const solids = []
  const metres = 12 / Math.max(sourceCanvas.width, sourceCanvas.height)
  const width = sourceCanvas.width * metres
  const depth = sourceCanvas.height * metres
  const wallH = 1.12
  const thickness = 0.16
  const door = Math.min(1.2, Math.max(0.85, width * 0.18))
  const gapStart = (width - door) / 2

  const studio = new THREE.Mesh(
    new THREE.CircleGeometry(Math.max(width, depth) + 4, 72),
    new THREE.MeshStandardMaterial({ color: 0x221e24, roughness: 1 })
  )
  studio.rotation.x = -Math.PI / 2
  studio.position.y = -0.2
  studio.receiveShadow = true
  scene.add(studio)

  const shape = new THREE.Shape()
  shape.moveTo(0, 0)
  shape.lineTo(width, 0)
  shape.lineTo(width, -depth)
  shape.lineTo(0, -depth)
  shape.closePath()
  const geo = new THREE.ShapeGeometry(shape)
  const position = geo.attributes.position
  const uv = geo.attributes.uv
  for (let i = 0; i < position.count; i++) {
    const px = position.getX(i) / metres
    const py = (-position.getY(i)) / metres
    uv.setXY(i, px / sourceCanvas.width, 1 - py / sourceCanvas.height)
  }

  const plinth = new THREE.Mesh(geo, plinthMat)
  plinth.rotation.x = -Math.PI / 2
  plinth.position.y = -0.045
  plinth.receiveShadow = true
  scene.add(plinth)

  const map = new THREE.CanvasTexture(sharpen(sourceCanvas))
  if (THREE.SRGBColorSpace) map.colorSpace = THREE.SRGBColorSpace
  map.generateMipmaps = true
  map.minFilter = THREE.LinearMipmapLinearFilter
  map.magFilter = THREE.LinearFilter
  map.anisotropy = renderer.capabilities.getMaxAnisotropy()
  const floor = new THREE.Mesh(geo.clone(), new THREE.MeshBasicMaterial({ map, side: THREE.DoubleSide }))
  floor.rotation.x = -Math.PI / 2
  floor.position.y = 0.012
  scene.add(floor)

  function addBox(x0, z0, x1, z1, height, collide) {
    const minX = Math.min(x0, x1)
    const maxX = Math.max(x0, x1)
    const minZ = Math.min(z0, z1)
    const maxZ = Math.max(z0, z1)
    const mesh = new THREE.Mesh(
      new THREE.BoxGeometry(Math.max(0.04, maxX - minX), height, Math.max(0.04, maxZ - minZ)),
      plaster
    )
    mesh.position.set((minX + maxX) / 2, height / 2, (minZ + maxZ) / 2)
    mesh.castShadow = true
    mesh.receiveShadow = true
    scene.add(mesh)
    if (collide) solids.push({ minX, maxX, minZ, maxZ })
  }

  addBox(0, 0, thickness, depth, wallH, true)
  addBox(width - thickness, 0, width, depth, wallH, true)
  addBox(0, 0, width, thickness, wallH, true)
  addBox(0, depth - thickness, gapStart, depth, wallH, true)
  addBox(gapStart + door, depth - thickness, width, depth, wallH, true)

  const player = { x: width / 2, z: depth - 1.15, yaw: 0, pitch: -0.28 }
  const orbit = {
    azimuth: 0.32,
    polar: 0.58,
    radius: Math.max(width, depth) * 1.35,
    targetX: width / 2,
    targetY: 0,
    targetZ: depth * 0.46
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
    document.exitPointerLock?.()
    notify()
  }
  function enterWalk() {
    mode = 'walk'
    player.x = width / 2
    player.z = Math.max(depth * 0.55, depth - 1.4)
    player.yaw = 0
    player.pitch = -0.28
    if (overlaps(player.x, player.z)) player.z = depth / 2
    document.exitPointerLock?.()
    notify()
  }
  function reset() {
    orbit.azimuth = 0.32
    orbit.polar = 0.58
    orbit.radius = fitRadius
    camera.fov = HOME_FOV
    camera.updateProjectionMatrix()
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
    orbit.polar = Math.max(0.38, Math.min(1.05, orbit.polar - (event.clientY - pointerY) * 0.004))
    pointerX = event.clientX
    pointerY = event.clientY
  }
  function onWheel(event) {
    event.preventDefault()
    const step = Math.max(-48, Math.min(48, event.deltaY))
    if (mode === 'walk') {
      camera.fov = Math.max(34, Math.min(52, camera.fov + step * 0.018))
      camera.updateProjectionMatrix()
      return
    }
    orbit.radius = Math.max(fitRadius * 0.62, Math.min(fitRadius * 1.22, orbit.radius + step * 0.012))
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
