import { ref } from 'vue'
import { messages, SYSTEM_LANG_STORAGE_KEY } from '@/i18n/systemOverview.js'

function getPath(obj, path) {
  if (!obj || !path) return undefined
  const parts = path.split('.')
  let cur = obj
  for (const p of parts) {
    if (cur == null) return undefined
    cur = cur[p]
  }
  return cur
}

function loadStoredLang() {
  try {
    const v = localStorage.getItem(SYSTEM_LANG_STORAGE_KEY)
    if (v === 'en' || v === 'ar') return v
  } catch {
    /* ignore */
  }
  return 'en'
}

/** Singleton UI language for System Overview + navbar toggle (instant, no reload). */
const systemLang = ref(loadStoredLang())

export function useSystemOverviewLang() {
  function setLang(lang) {
    if (lang !== 'en' && lang !== 'ar') return
    systemLang.value = lang
    try {
      localStorage.setItem(SYSTEM_LANG_STORAGE_KEY, lang)
    } catch {
      /* ignore */
    }
  }

  /**
   * Translate by dot path into messages[locale].
   * @param {string} path e.g. 'page.hero.title'
   * @param {'en'|'ar'} [locale] defaults to current systemLang
   */
  function t(path, locale) {
    const loc = locale ?? systemLang.value
    const v = getPath(messages[loc], path)
    if (v !== undefined) return v
    return path
  }

  return {
    lang: systemLang,
    setLang,
    t,
    messages,
  }
}
