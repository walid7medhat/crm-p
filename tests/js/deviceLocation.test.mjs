import assert from 'node:assert/strict'
import test from 'node:test'
import {
  captureDeviceLocation,
  chooseBetterFix,
  deviceLocationMapUrls,
  deviceLocationView,
  normalizeDeviceFix,
} from '../../resources/js/utils/deviceLocation.mjs'

const position = (latitude, longitude, accuracy, timestamp = 1_700_000_000_000) => ({
  coords: { latitude, longitude, accuracy },
  timestamp,
})

test('rejects coordinates that are not finite or in range', () => {
  assert.equal(normalizeDeviceFix(position(Number.NaN, 55, 10)), null)
  assert.equal(normalizeDeviceFix(position(91, 55, 10)), null)
  assert.equal(normalizeDeviceFix(position(25, 181, 10)), null)
  assert.equal(normalizeDeviceFix(position(25, Number.POSITIVE_INFINITY, 10)), null)
})

test('keeps the tighter of two fresh readings', () => {
  const coarse = normalizeDeviceFix(position(25.2, 55.2, 4000, 1_000))
  const precise = normalizeDeviceFix(position(25.2048493, 55.2707825, 18, 2_000))
  const chosen = chooseBetterFix(coarse, precise)
  assert.equal(chosen.accuracy, 18)
  assert.equal(chooseBetterFix(precise, coarse).accuracy, 18)
  assert.equal(precise.precise, true)
  assert.equal(coarse.precise, false)
})

test('high-accuracy watch reports enableHighAccuracy and does not reuse a cached position', async () => {
  const calls = []
  let cleared = false
  const geolocation = {
    watchPosition(success, error, options) {
      calls.push(options)
      success(position(25.2048493, 55.2707825, 18))
      return 4
    },
    getCurrentPosition() {
      throw new Error('low-accuracy request should not start after a precise fix')
    },
    clearWatch(id) {
      cleared = id === 4
    },
  }

  const fix = await captureDeviceLocation(geolocation, { maxWait: 5000 })
  assert.equal(fix.latitude, 25.2048493)
  assert.equal(fix.longitude, 55.2707825)
  assert.equal(fix.accuracy, 18)
  assert.equal(calls[0].enableHighAccuracy, true)
  assert.equal(calls[0].maximumAge, 0)
  assert.equal(cleared, true)
})

test('a precise watch reading wins over an earlier approximate reading', async () => {
  const geolocation = {
    watchPosition(success) {
      success(position(25.2048493, 55.2707825, 12))
      return 1
    },
    getCurrentPosition() {},
    clearWatch() {},
  }

  const fix = await captureDeviceLocation(geolocation, { maxWait: 50 })
  assert.equal(fix.accuracy, 12)
  assert.equal(fix.precise, true)
})

test('permission denial resolves with no coordinates', async () => {
  let lowAccuracyUsed = false
  const geolocation = {
    watchPosition(success, error) {
      error({ code: 1, message: 'denied' })
      return 1
    },
    getCurrentPosition(success) {
      lowAccuracyUsed = true
      success(position(25, 55, 20))
    },
    clearWatch() {},
  }

  await assert.rejects(
    () => captureDeviceLocation(geolocation, { maxWait: 50 }),
    (error) => error.code === 1,
  )
  assert.equal(lowAccuracyUsed, false)
})

test('timeout keeps the best device reading and does not invent one', async () => {
  const geolocation = {
    watchPosition(success, error, options) {
      assert.equal(options.maximumAge, 0)
      success(position(200, 55, 10))
      return 1
    },
    getCurrentPosition(success, error, options) {
      assert.equal(options.enableHighAccuracy, false)
      assert.equal(options.maximumAge, 0)
      success(position(25.31, 55.39, 2200))
    },
    clearWatch() {},
  }

  const fix = await captureDeviceLocation(geolocation, { maxWait: 30 })
  assert.equal(fix.latitude, 25.31)
  assert.equal(fix.longitude, 55.39)
  assert.equal(fix.accuracy, 2200)
  assert.equal(fix.precise, false)
})

test('timeout with no reading rejects instead of returning a fallback', async () => {
  const geolocation = {
    watchPosition() {
      return 1
    },
    getCurrentPosition(success, error) {
      error({ code: 3, message: 'timeout' })
    },
    clearWatch() {},
  }

  await assert.rejects(
    () => captureDeviceLocation(geolocation, { maxWait: 20 }),
    (error) => error.code === 3,
  )
})

test('the map marker uses the saved coordinates and not the address', () => {
  const address = 'Nearby Street, Abu Dhabi'
  const view = deviceLocationView({
    latitude: '25.2048493',
    longitude: '55.2707825',
    accuracy: 18,
    capturedAt: '2026-10-18T14:59:00Z',
    address,
    source: 'device',
  })

  assert.equal(view.state, 'device')
  assert.equal(view.quality, 'precise')
  assert.equal(view.address, address)
  assert.equal(view.map.embed.includes('25.2048493'), true)
  assert.equal(view.map.embed.includes('55.2707825'), true)
  assert.equal(view.map.external.includes('25.2048493'), true)
  assert.equal(view.map.external.includes('55.2707825'), true)
  assert.equal(view.map.embed.includes('Nearby'), false)
  assert.equal(view.map.external.includes('Abu'), false)
  assert.deepEqual(deviceLocationMapUrls(25.2048493, 55.2707825), view.map)
})

test('an approximate device fix stays distinct from a precise one', () => {
  const view = deviceLocationView({
    latitude: 25.31,
    longitude: 55.39,
    accuracy: 2200,
    source: 'device',
  })
  assert.equal(view.state, 'device')
  assert.equal(view.quality, 'approximate')
  assert.equal(view.map.embed.includes('25.3100000'), true)
})

test('a network estimate and a missing fix are not plotted', () => {
  const network = deviceLocationView({
    latitude: 25.2,
    longitude: 55.27,
    address: '(approx. by IP) Dubai',
    source: 'ip',
  })
  const missing = deviceLocationView({})

  assert.equal(network.state, 'unavailable')
  assert.equal(network.unavailableReason, 'network')
  assert.equal(network.map, null)
  assert.equal(missing.state, 'unavailable')
  assert.equal(missing.unavailableReason, 'missing')
  assert.equal(missing.map, null)
})
