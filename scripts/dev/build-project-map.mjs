/**
 * Writes resources/js/pages/dev/projectMapData.json (offline fallback for the dev dashboard).
 * Run: node scripts/dev/build-project-map.mjs  |  npm run map:build
 */
import path from 'path'
import { fileURLToPath } from 'url'
import { writeProjectMapJson, getRepoRoot } from './project-map-scan.mjs'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const repoRoot = getRepoRoot(__dirname)
const outFile = path.join(repoRoot, 'resources/js/pages/dev/projectMapData.json')

writeProjectMapJson(repoRoot, outFile)
console.log('Wrote', outFile)
