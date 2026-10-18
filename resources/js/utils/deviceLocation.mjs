/** Device-reported accuracy at or below this is labeled precise. It is not a guarantee. */
export const PRECISE_ACCURACY_METERS = 100

export function isValidCoordinate(latitude, longitude) {
  return Number.isFinite(latitude)
    && Number.isFinite(longitude)
    && latitude >= -90
    && latitude <= 90
    && longitude >= -180
    && longitude <= 180
}

/**
 * Stable 7-decimal token, matching the stored coordinate scale (about 1 cm).
 * Returns null when the value is not a usable coordinate component.
 */
export function coordinateToken(value) {
  const number = Number(value)
  if (!Number.isFinite(number)) return null
  return number.toFixed(7)
}

export function normalizeDeviceFix(position) {
  const latitude = Number(position?.coords?.latitude)
  const longitude = Number(position?.coords?.longitude)
  if (!isValidCoordinate(latitude, longitude)) return null

  const rawAccuracy = Number(position?.coords?.accuracy)
  const accuracy = Number.isFinite(rawAccuracy) && rawAccuracy >= 0 ? rawAccuracy : null
  const timestamp = Number(position?.timestamp)
  const capturedAt = new Date(Number.isFinite(timestamp) ? timestamp : Date.now()).toISOString()

  return {
    latitude,
    longitude,
    accuracy,
    capturedAt,
    precise: accuracy !== null && accuracy <= PRECISE_ACCURACY_METERS,
  }
}

/** Keep the reading with the smaller reported error. A missing accuracy loses to a reported one. */
export function chooseBetterFix(current, next) {
  if (!next) return current || null
  if (!current) return next
  if (current.accuracy == null && next.accuracy == null) {
    return next.capturedAt >= current.capturedAt ? next : current
  }
  if (current.accuracy == null) return next
  if (next.accuracy == null) return current
  return next.accuracy < current.accuracy ? next : current
}

/**
 * Ask the browser for one device position.
 * A high-accuracy watch is the reading we prefer. A single low-accuracy
 * reading can fill in when GPS never arrives, and it stays labeled by its
 * reported accuracy. The watch is cleared when this settles. Cached positions
 * are not accepted (maximumAge: 0).
 */
export function captureDeviceLocation(geolocation, options = {}) {
  const maxWait = options.maxWait ?? 12000
  const goodEnough = options.goodEnough ?? PRECISE_ACCURACY_METERS

  return new Promise((resolve, reject) => {
    if (!geolocation || typeof geolocation.watchPosition !== 'function') {
      reject({ code: 0, message: 'Geolocation is not supported' })
      return
    }

    let best = null
    let watchId = null
    let settled = false

    const stop = () => {
      if (watchId != null && typeof geolocation.clearWatch === 'function') {
        geolocation.clearWatch(watchId)
      }
      watchId = null
    }

    const finish = (error) => {
      if (settled) return
      settled = true
      clearTimeout(guard)
      stop()
      if (best) resolve(best)
      else reject(error || { code: 3, message: 'Location request timed out' })
    }

    const consider = (position, canFinish) => {
      if (settled) return
      const fix = normalizeDeviceFix(position)
      if (!fix) return
      best = chooseBetterFix(best, fix)
      if (canFinish && best?.accuracy != null && best.accuracy <= goodEnough) finish()
    }

    const onError = (error) => {
      if (settled) return
      if (error?.code === 1) {
        best = null
        finish(error)
      }
    }

    const guard = setTimeout(() => finish({ code: 3, message: 'Location request timed out' }), maxWait)

    try {
      // The success callback can run before watchPosition returns its id.
      // Clear again afterwards so a precise fix does not keep watching.
      watchId = geolocation.watchPosition(
        (position) => consider(position, true),
        onError,
        { enableHighAccuracy: true, maximumAge: 0, timeout: maxWait },
      )
    } catch (error) {
      finish({ code: 0, message: error?.message || 'Geolocation is not supported' })
      return
    }

    if (settled) stop()

    if (!settled && typeof geolocation.getCurrentPosition === 'function') {
      geolocation.getCurrentPosition(
        (position) => consider(position, false),
        onError,
        { enableHighAccuracy: false, maximumAge: 0, timeout: maxWait },
      )
    }
  })
}

export function deviceLocationMapUrls(latitude, longitude) {
  const lat = coordinateToken(latitude)
  const lng = coordinateToken(longitude)
  if (!lat || !lng || !isValidCoordinate(Number(lat), Number(lng))) return null

  const latitudeNumber = Number(lat)
  const longitudeNumber = Number(lng)
  const pad = 0.01
  const bbox = [
    longitudeNumber - pad,
    latitudeNumber - pad,
    longitudeNumber + pad,
    latitudeNumber + pad,
  ].join(',')
  const point = `${lat},${lng}`

  return {
    embed: `https://www.openstreetmap.org/export/embed.html?bbox=${encodeURIComponent(bbox)}&layer=mapnik&marker=${encodeURIComponent(point)}`,
    external: `https://www.google.com/maps?q=${encodeURIComponent(point)}&ll=${encodeURIComponent(point)}&z=17`,
  }
}

/**
 * What View User / View Profile should render.
 * Coordinates are the source of truth. An IP-derived point is not plotted.
 * The address is a label only and is never used to place the marker.
 */
export function deviceLocationView(input = {}) {
  const source = input?.source || null
  const latitude = Number(input?.latitude)
  const longitude = Number(input?.longitude)
  const hasCoords = isValidCoordinate(latitude, longitude)

  if (source === 'ip' || !hasCoords) {
    return {
      state: 'unavailable',
      unavailableReason: source === 'ip' ? 'network' : 'missing',
      latitude: null,
      longitude: null,
      accuracy: null,
      quality: null,
      capturedAt: null,
      address: null,
      map: null,
    }
  }

  const rawAccuracy = Number(input?.accuracy)
  const accuracy = Number.isFinite(rawAccuracy) && rawAccuracy >= 0 ? Math.round(rawAccuracy) : null
  let quality = 'unrated'
  if (accuracy !== null) {
    quality = accuracy <= PRECISE_ACCURACY_METERS ? 'precise' : 'approximate'
  }

  return {
    state: 'device',
    unavailableReason: null,
    latitude: Number(coordinateToken(latitude)),
    longitude: Number(coordinateToken(longitude)),
    accuracy,
    quality,
    capturedAt: input?.capturedAt || null,
    address: input?.address ? String(input.address) : null,
    map: deviceLocationMapUrls(latitude, longitude),
  }
}
