/**
 * Fix artifacts from heading normalization:
 * - </h6>> -> </h6>
 * - <h6 class="a" class="ui-h-*"> -> merged single class attribute
 */
import { readdirSync, readFileSync, statSync, writeFileSync } from 'node:fs'
import { join } from 'node:path'

const ROOT = join(process.cwd(), 'resources', 'js')

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

function fix(content) {
  let s = content.replace(/<\/h6>>/gi, '</h6>')
  // Merge duplicate class on h6: class="foo" class="ui-h-..."
  s = s.replace(
    /<h6\s+class="([^"]*)"\s+class="(ui-h-[^"]+)"/gi,
    '<h6 class="$2 $1"',
  )
  s = s.replace(
    /<h6\s+class="(ui-h-[^"]*)"\s+class="([^"]+)"/gi,
    '<h6 class="$1 $2"',
  )
  return s
}

let n = 0
for (const file of walk(ROOT)) {
  const before = readFileSync(file, 'utf8')
  const after = fix(before)
  if (after !== before) {
    writeFileSync(file, after, 'utf8')
    n++
  }
}
console.log(`Fixed heading artifacts in ${n} files.`)
