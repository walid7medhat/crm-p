/**
 * Normalize Vue SFC templates: replace <h1>–<h5> with <h6> + utility classes.
 * Run: node scripts/normalize-vue-headings.mjs
 */
import { readdirSync, readFileSync, statSync, writeFileSync } from 'node:fs'
import { join } from 'node:path'

const ROOT = join(process.cwd(), 'resources', 'js')

const LEVEL_CLASS = {
  1: 'ui-h-page',
  2: 'ui-h-section',
  3: 'ui-h-sub',
  4: 'ui-h-mini',
  5: 'ui-h-mini',
}

function walk(dir, out = []) {
  for (const name of readdirSync(dir)) {
    if (name === 'node_modules' || name === 'dist') continue
    const p = join(dir, name)
    const st = statSync(p)
    if (st.isDirectory()) walk(p, out)
    else if (p.endsWith('.vue')) out.push(p)
  }
  return out
}

function mergeClass(attrs, levelClass) {
  const m = attrs.match(/\sclass\s*=\s*(["'])([^"']*)\1/i)
  if (!m) {
    return `${attrs} class="${levelClass}"`.trim()
  }
  const q = m[1]
  const existing = m[2].trim()
  const next = `${levelClass} ${existing}`.trim()
  return attrs.replace(/\sclass\s*=\s*(["'])([^"']*)\1/i, ` class=${q}${next}${q}`)
}

function transform(content) {
  let s = content
  s = s.replace(/<\/h[1-5]\b/gi, '</h6>')

  s = s.replace(/<h([1-5])(\s[^>]*)?>/gi, (full, level, attrs = '') => {
    const cls = LEVEL_CLASS[level] || 'ui-h-mini'
    const rest = attrs.trim()
    if (!rest) {
      return `<h6 class="${cls}">`
    }
    if (/^\s*class\s*=/i.test(rest)) {
      const inner = mergeClass(rest, cls)
      return `<h6 ${inner}>`
    }
    return `<h6 class="${cls}" ${rest}>`
  })

  return s
}

let changed = 0
for (const file of walk(ROOT)) {
  const before = readFileSync(file, 'utf8')
  const after = transform(before)
  if (after !== before) {
    writeFileSync(file, after, 'utf8')
    changed++
  }
}

console.log(`Normalized headings in ${changed} Vue files.`)
