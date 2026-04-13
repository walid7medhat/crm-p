/**
 * Dev-server only: live project scan + JSON endpoint + SSE notifications.
 * Not included in production builds (apply: 'serve').
 */
import path from 'path'
import { fileURLToPath } from 'url'
import chokidar from 'chokidar'
import { scanProjectMap } from './project-map-scan.mjs'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const repoRoot = path.join(__dirname, '../..')

export function viteProjectMapPlugin() {
  let cache = null
  const sseClients = new Set()
  let debounceTimer = null

  function rescan() {
    try {
      cache = scanProjectMap(repoRoot)
    } catch (err) {
      console.error('[project-map] scan failed:', err)
      cache = {
        error: String(err?.message || err),
        generatedAt: new Date().toISOString(),
      }
    }
  }

  function broadcast() {
    const payload = JSON.stringify({
      type: 'update',
      generatedAt: cache?.generatedAt,
    })
    for (const res of sseClients) {
      try {
        res.write(`data: ${payload}\n\n`)
      } catch {
        sseClients.delete(res)
      }
    }
  }

  return {
    name: 'vite-plugin-project-map',
    apply: 'serve',
    configureServer(server) {
      rescan()

      const watchPath = path.join(repoRoot, 'resources/js')
      const watcher = chokidar.watch(watchPath, {
        ignored: /(^|[\\/])node_modules([\\/]|$)/,
        ignoreInitial: true,
        persistent: true,
        // Safer for editor save/rename (temp file → replace)
        awaitWriteFinish: { stabilityThreshold: 200, pollInterval: 100 },
        atomic: true,
      })

      watcher.on('all', () => {
        clearTimeout(debounceTimer)
        // Renames often emit unlink+add in quick succession — one debounced rescan
        debounceTimer = setTimeout(() => {
          rescan()
          broadcast()
        }, 550)
      })

      /** Laravel (or any app) on another port is a different origin — browsers require CORS for /@project-map/* */
      function setCors(req, res) {
        const origin = req.headers.origin
        if (origin) {
          res.setHeader('Access-Control-Allow-Origin', origin)
          res.setHeader('Vary', 'Origin')
        } else {
          res.setHeader('Access-Control-Allow-Origin', '*')
        }
      }

      server.middlewares.use((req, res, next) => {
        const url = (req.url || '').split('?')[0]
        const isProjectMap = url === '/@project-map/data' || url === '/@project-map/stream'
        if (!isProjectMap) {
          next()
          return
        }

        if (req.method === 'OPTIONS') {
          setCors(req, res)
          res.setHeader('Access-Control-Allow-Methods', 'GET, OPTIONS')
          res.setHeader('Access-Control-Allow-Headers', 'Cache-Control, Content-Type, Last-Event-ID')
          res.statusCode = 204
          res.end()
          return
        }

        if (url === '/@project-map/data') {
          setCors(req, res)
          if (!cache) rescan()
          res.setHeader('Content-Type', 'application/json; charset=utf-8')
          res.setHeader('Cache-Control', 'no-store')
          res.end(JSON.stringify(cache))
          return
        }
        if (url === '/@project-map/stream') {
          setCors(req, res)
          res.setHeader('Content-Type', 'text/event-stream; charset=utf-8')
          res.setHeader('Cache-Control', 'no-cache, no-transform')
          res.setHeader('Connection', 'keep-alive')
          if (typeof res.flushHeaders === 'function') res.flushHeaders()
          sseClients.add(res)
          const hello = JSON.stringify({
            type: 'connected',
            generatedAt: cache?.generatedAt,
          })
          res.write(`data: ${hello}\n\n`)
          req.on('close', () => sseClients.delete(res))
          return
        }
        next()
      })

      return () => {
        clearTimeout(debounceTimer)
        watcher.close().catch(() => {})
      }
    },
  }
}
