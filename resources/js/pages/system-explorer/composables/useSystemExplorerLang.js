import { ref, computed } from 'vue'
import { catalog } from '../data/catalog.js'

export const SE_LANG_KEY = 'oia.systemExplorer.lang'

function loadLang() {
  try {
    const v = localStorage.getItem(SE_LANG_KEY)
    if (v === 'en' || v === 'ar') return v
  } catch {
    /* ignore */
  }
  return 'en'
}

const lang = ref(loadLang())

export function useSystemExplorerLang() {
  function setLang(next) {
    if (next !== 'en' && next !== 'ar') return
    lang.value = next
    try {
      localStorage.setItem(SE_LANG_KEY, next)
    } catch {
      /* ignore */
    }
  }

  const t = computed(() => catalog.ui[lang.value] || catalog.ui.en)

  function L(obj) {
    if (obj == null) return ''
    if (typeof obj === 'string') return obj
    return obj[lang.value] || obj.en || ''
  }

  return { lang, setLang, t, L }
}
