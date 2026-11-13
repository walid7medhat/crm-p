const KEY = 'crm.loginWelcome'
const MAX_AGE_MS = 2 * 60 * 1000

/** Called once, right after a successful sign-in, before the app reloads. */
export function armLoginWelcome(name) {
  try {
    sessionStorage.setItem(
      KEY,
      JSON.stringify({
        name: String(name || '').trim(),
        at: Date.now(),
      }),
    )
  } catch {
    /* private mode */
  }
}

/** Read and clear the flag so a refresh does not play the greeting again. */
export function takeLoginWelcome() {
  try {
    const raw = sessionStorage.getItem(KEY)
    sessionStorage.removeItem(KEY)
    if (!raw) return null
    const data = JSON.parse(raw)
    if (!data || typeof data.at !== 'number') return null
    if (Date.now() - data.at > MAX_AGE_MS) return null
    return { name: typeof data.name === 'string' ? data.name : '' }
  } catch {
    return null
  }
}

export function welcomeCopy(name, options = {}) {
  const hour = new Date().getHours()
  const first = String(name || '').trim().split(/\s+/)[0] || ''
  const company = String(options.companyName || '').trim()
  let hello = 'Welcome'
  let line = 'Have a great day and good work.'

  if (hour >= 5 && hour < 12) {
    hello = 'Good morning'
    line = company
      ? `Have a great day at ${company}. Your workspace is ready.`
      : 'Have a great day. Your workspace is ready.'
  } else if (hour >= 12 && hour < 17) {
    hello = 'Good afternoon'
    line = company
      ? `Wishing you a smooth day with ${company}.`
      : 'Wishing you a smooth day and good work.'
  } else if (hour >= 17 && hour < 22) {
    hello = 'Good evening'
    line = company
      ? `Welcome back to ${company}. Here's to a strong finish.`
      : 'Welcome back. Here\'s to a strong finish.'
  } else {
    hello = 'Welcome back'
    line = company
      ? `${company} is ready whenever you are.`
      : 'Your workspace is ready whenever you are.'
  }

  return { hello, first, line }
}
