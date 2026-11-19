<template>
  <div class="mk-ts">
    <!-- Category tabs: Blogs / OIA / All -->
    <div class="mk-ts__tabs" role="tablist" aria-label="Tracking sheet category">
      <button
        v-for="tab in CATEGORY_TABS"
        :key="tab.id"
        type="button"
        role="tab"
        class="mk-ts__tab"
        :class="{ 'is-on': category === tab.id }"
        :style="chipStyle(tab)"
        @click="setCategory(tab.id)"
      >
        <iconify-icon :icon="tab.icon" />
        <span>{{ tab.label }}</span>
        <em v-if="groupTotals[tab.id] != null">{{ groupTotals[tab.id] }}</em>
      </button>
    </div>

    <!-- Toolbar -->
    <div class="mk-ts__toolbar">
      <div class="mk-ts__groups" role="tablist" aria-label="Sheet group">
        <button
          v-for="g in visibleGroups"
          :key="g.id"
          type="button"
          class="mk-ts__pill"
          :class="{ 'is-on': sheetGroup === g.id }"
          @click="sheetGroup = g.id"
        >
          <iconify-icon :icon="g.icon" />
          {{ g.label }}
          <span v-if="groups[g.id]">{{ groups[g.id] }}</span>
        </button>
      </div>

      <div class="mk-ts__filters">
        <template v-if="category !== 'blogs'">
          <button
            v-for="r in REGION_PILLS"
            :key="r.id"
            type="button"
            class="mk-ts__pill mk-ts__pill--soft"
            :class="{ 'is-on': region === r.id }"
            @click="region = region === r.id ? '' : r.id"
          >{{ r.label }}</button>
        </template>
        <template v-else>
          <button type="button" class="mk-ts__pill mk-ts__pill--soft" :class="{ 'is-on': published === '1' }" @click="published = published === '1' ? '' : '1'">Published</button>
          <button type="button" class="mk-ts__pill mk-ts__pill--soft" :class="{ 'is-on': published === '0' }" @click="published = published === '0' ? '' : '0'">Draft</button>
        </template>

        <button type="button" class="mk-btn mk-btn--primary" @click="openCreate">
          <iconify-icon icon="lucide:plus" /> New entry
        </button>
      </div>
    </div>

    <!-- Stats strip -->
    <div class="mk-ts__stats">
      <div class="mk-ts__stat">
        <iconify-icon icon="lucide:rows-3" />
        <div><small>Showing</small><b>{{ entries.length }}</b></div>
      </div>
      <div class="mk-ts__stat">
        <iconify-icon :icon="activeCategoryMeta.icon" />
        <div><small>{{ activeCategoryMeta.label }}</small><b>{{ activeCategoryMeta.hint }}</b></div>
      </div>
      <div v-if="publishedCount != null" class="mk-ts__stat">
        <iconify-icon icon="lucide:badge-check" />
        <div><small>Published</small><b>{{ publishedCount }}</b></div>
      </div>
      <div v-if="withLinksCount != null" class="mk-ts__stat">
        <iconify-icon icon="lucide:link-2" />
        <div><small>With links</small><b>{{ withLinksCount }}</b></div>
      </div>
    </div>

    <!-- Loading / empty -->
    <div v-if="loading && !entries.length" class="mk-empty"><iconify-icon icon="lucide:loader-2" class="mk-spin" /></div>
    <div v-else-if="!entries.length" class="mk-ts__empty">
      <iconify-icon icon="lucide:sheet" />
      <h4>No entries yet</h4>
      <p>Import is done — add your first {{ activeCategoryMeta.label.toLowerCase() }} row.</p>
      <button type="button" class="mk-btn mk-btn--primary" @click="openCreate"><iconify-icon icon="lucide:plus" /> New entry</button>
    </div>

    <!-- Card grid -->
    <div v-else class="mk-ts__grid" :class="{ 'is-loading': loading }">
      <article v-for="(entry, idx) in entries" :key="entry.id" class="mk-ts-card" :style="{ '--i': idx }">
        <header class="mk-ts-card__head">
          <div class="mk-ts-card__title">
            <span class="mk-ts-card__badge" :data-region="entry.region || 'none'">
              {{ regionLabel(entry.region) || groupLabel(entry.sheet_group) }}
            </span>
            <h4>{{ entry.name }}</h4>
            <p v-if="entry.description">{{ entry.description }}</p>
          </div>
          <div class="mk-ts-card__actions">
            <button type="button" class="mk-btn mk-btn--ghost mk-btn--icon mk-btn--sm" title="Edit" @click="openEdit(entry)">
              <iconify-icon icon="lucide:pencil" />
            </button>
            <button type="button" class="mk-btn mk-btn--ghost mk-btn--icon mk-btn--sm" title="Delete" @click="remove(entry)">
              <iconify-icon icon="lucide:trash-2" />
            </button>
          </div>
        </header>

        <div v-if="entry.area || entry.developer || entry.done_by || entry.is_published != null" class="mk-ts-card__meta">
          <span v-if="entry.area"><iconify-icon icon="lucide:map-pin" /> {{ entry.area }}</span>
          <span v-if="entry.developer"><iconify-icon icon="lucide:building-2" /> {{ entry.developer }}</span>
          <span v-if="entry.done_by"><iconify-icon icon="lucide:user" /> {{ entry.done_by }}</span>
          <span v-if="entry.is_published === true" class="is-live"><iconify-icon icon="lucide:check-circle-2" /> Published</span>
          <span v-else-if="entry.is_published === false" class="is-draft"><iconify-icon icon="lucide:circle-dashed" /> Draft</span>
        </div>

        <div class="mk-ts-card__links">
          <a v-if="entry.content_url" :href="entry.content_url" target="_blank" rel="noopener" class="mk-ts-link mk-ts-link--doc">
            <iconify-icon icon="mdi:google-drive" />
            <span>{{ entry.content_label || 'Content' }}</span>
            <iconify-icon icon="lucide:arrow-up-right" class="mk-ts-link__go" />
          </a>
          <a v-if="entry.page_url" :href="entry.page_url" target="_blank" rel="noopener" class="mk-ts-link mk-ts-link--page">
            <iconify-icon icon="lucide:globe" />
            <span>{{ entry.page_label || 'Live page' }}</span>
            <iconify-icon icon="lucide:arrow-up-right" class="mk-ts-link__go" />
          </a>
          <a v-if="entry.linkedin_url" :href="entry.linkedin_url" target="_blank" rel="noopener" class="mk-ts-link mk-ts-link--li">
            <iconify-icon icon="mdi:linkedin" />
            <span>{{ entry.linkedin_label || 'LinkedIn' }}</span>
            <iconify-icon icon="lucide:arrow-up-right" class="mk-ts-link__go" />
          </a>
          <a v-if="entry.instagram_url" :href="entry.instagram_url" target="_blank" rel="noopener" class="mk-ts-link mk-ts-link--ig">
            <iconify-icon icon="mdi:instagram" />
            <span>{{ entry.instagram_label || 'Instagram' }}</span>
            <iconify-icon icon="lucide:arrow-up-right" class="mk-ts-link__go" />
          </a>
          <span v-if="!hasAnyLink(entry)" class="mk-ts-card__nolink">No links yet</span>
        </div>
      </article>
    </div>

    <!-- Create / edit modal -->
    <MkModal
      v-model="formOpen"
      :title="editing ? 'Edit entry' : 'New tracking entry'"
      :subtitle="activeCategoryMeta.label"
      icon="lucide:sheet"
      width="680px"
    >
      <div class="mk-row">
        <div class="mk-field">
          <label>Name</label>
          <input v-model="form.name" class="mk-input" :class="{ 'mk-input--error': errors.name }" placeholder="Project / blog title" />
          <span v-if="errors.name" class="mk-error">{{ errors.name }}</span>
        </div>
        <div v-if="category !== 'blogs'" class="mk-field">
          <label>Group</label>
          <select v-model="form.sheet_group" class="mk-input">
            <option v-for="g in editableGroups" :key="g.id" :value="g.id">{{ g.label }}</option>
          </select>
        </div>
      </div>

      <div class="mk-field">
        <label>Description / notes</label>
        <textarea v-model="form.description" class="mk-input" rows="2" placeholder="Optional notes" />
      </div>

      <div v-if="category !== 'blogs'" class="mk-row">
        <div class="mk-field">
          <label>Area</label>
          <input v-model="form.area" class="mk-input" placeholder="e.g. Saadiyat Island" />
        </div>
        <div class="mk-field">
          <label>Developer</label>
          <input v-model="form.developer" class="mk-input" placeholder="e.g. Aldar Properties" />
        </div>
      </div>

      <div v-if="category !== 'blogs'" class="mk-row">
        <div class="mk-field">
          <label>Region</label>
          <select v-model="form.region" class="mk-input">
            <option value="">—</option>
            <option value="abu_dhabi">Abu Dhabi</option>
            <option value="dubai">Dubai</option>
          </select>
        </div>
        <div class="mk-field">
          <label>Done by</label>
          <input v-model="form.done_by" class="mk-input" placeholder="Optional" />
        </div>
      </div>

      <div v-else class="mk-field">
        <label>Status</label>
        <div class="mk-chips">
          <button type="button" class="mk-chip" :class="{ 'is-on': form.is_published === true }" @click="form.is_published = true">Published</button>
          <button type="button" class="mk-chip" :class="{ 'is-on': form.is_published === false }" @click="form.is_published = false">Draft</button>
        </div>
      </div>

      <div class="mk-ts-form-block">
        <h6><iconify-icon icon="mdi:google-drive" /> Content document</h6>
        <div class="mk-row">
          <div class="mk-field"><label>Label</label><input v-model="form.content_label" class="mk-input" placeholder="Content" /></div>
          <div class="mk-field"><label>Link</label><input v-model="form.content_url" class="mk-input" placeholder="https://…" /></div>
        </div>
      </div>

      <div class="mk-ts-form-block">
        <h6><iconify-icon icon="lucide:globe" /> {{ category === 'blogs' ? 'Blog page' : 'Live page' }}</h6>
        <div class="mk-row">
          <div class="mk-field"><label>Label</label><input v-model="form.page_label" class="mk-input" :placeholder="category === 'blogs' ? 'Blog' : 'Landing page'" /></div>
          <div class="mk-field"><label>Link</label><input v-model="form.page_url" class="mk-input" placeholder="https://…" /></div>
        </div>
      </div>

      <div v-if="category === 'blogs'" class="mk-ts-form-block">
        <h6><iconify-icon icon="mdi:linkedin" /> LinkedIn</h6>
        <div class="mk-row">
          <div class="mk-field"><label>Label</label><input v-model="form.linkedin_label" class="mk-input" placeholder="LinkedIn" /></div>
          <div class="mk-field"><label>Link</label><input v-model="form.linkedin_url" class="mk-input" placeholder="https://…" /></div>
        </div>
      </div>

      <div v-if="category === 'blogs'" class="mk-ts-form-block">
        <h6><iconify-icon icon="mdi:instagram" /> Instagram</h6>
        <div class="mk-row">
          <div class="mk-field"><label>Label</label><input v-model="form.instagram_label" class="mk-input" placeholder="Instagram" /></div>
          <div class="mk-field"><label>Link</label><input v-model="form.instagram_url" class="mk-input" placeholder="https://…" /></div>
        </div>
      </div>

      <template #footer>
        <button type="button" class="mk-btn mk-btn--ghost" :disabled="saving" @click="formOpen = false">Cancel</button>
        <button type="button" class="mk-btn mk-btn--primary" :disabled="saving" @click="save">
          <iconify-icon :icon="saving ? 'lucide:loader-2' : 'lucide:check'" :class="{ 'mk-spin': saving }" />
          {{ editing ? 'Save changes' : 'Create entry' }}
        </button>
      </template>
    </MkModal>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import Swal from 'sweetalert2'
import api from '@/plugins/axios'
import MkModal from '@/components/marketing/shared/MkModal.vue'
import { apiError, chipStyle } from '@/config/marketingConfig.js'

const props = defineProps({
  search: { type: String, default: '' },
})

const CATEGORY_TABS = [
  { id: 'blogs', label: 'Blogs', icon: 'lucide:newspaper', color: '#0d9488', grad: 'linear-gradient(135deg, #2FB5B7 0%, #0B6E70 100%)', hint: 'Blog tracking' },
  { id: 'oia', label: 'OIA', icon: 'lucide:building-2', color: '#733E87', grad: 'linear-gradient(135deg, #9562AA 0%, #5C2F70 100%)', hint: 'Oia Properties pages' },
  { id: 'all', label: 'All', icon: 'lucide:layers', color: '#B8892B', grad: 'linear-gradient(135deg, #DDB052 0%, #A97B1F 100%)', hint: 'All Properties pages' },
]

const ALL_GROUPS = [
  { id: 'blogs', label: 'Blogs', icon: 'lucide:newspaper' },
  { id: 'projects', label: 'Projects', icon: 'lucide:map' },
  { id: 'developers', label: 'Developers', icon: 'lucide:building' },
  { id: 'new_domains', label: 'New Domains', icon: 'lucide:globe-2' },
]

const REGION_PILLS = [
  { id: 'abu_dhabi', label: 'Abu Dhabi' },
  { id: 'dubai', label: 'Dubai' },
]

const category = ref('blogs')
const sheetGroup = ref('blogs')
const region = ref('')
const published = ref('')
const entries = ref([])
const groups = ref({})
const groupTotals = reactive({ blogs: null, oia: null, all: null })
const loading = ref(false)
const formOpen = ref(false)
const saving = ref(false)
const editing = ref(null)
const errors = reactive({})

const emptyForm = () => ({
  category: 'blogs',
  sheet_group: 'blogs',
  region: '',
  name: '',
  description: '',
  area: '',
  developer: '',
  done_by: '',
  is_published: true,
  content_label: '',
  content_url: '',
  page_label: '',
  page_url: '',
  linkedin_label: '',
  linkedin_url: '',
  instagram_label: '',
  instagram_url: '',
})
const form = reactive(emptyForm())

const activeCategoryMeta = computed(() => CATEGORY_TABS.find((t) => t.id === category.value) || CATEGORY_TABS[0])
const visibleGroups = computed(() => {
  if (category.value === 'blogs') return ALL_GROUPS.filter((g) => g.id === 'blogs')
  return ALL_GROUPS.filter((g) => g.id !== 'blogs')
})
const editableGroups = computed(() => visibleGroups.value)
const publishedCount = computed(() => {
  if (category.value !== 'blogs') return null
  return entries.value.filter((e) => e.is_published === true).length
})
const withLinksCount = computed(() => entries.value.filter(hasAnyLink).length)

function hasAnyLink(e) {
  return !!(e.content_url || e.page_url || e.linkedin_url || e.instagram_url)
}
function regionLabel(r) {
  if (r === 'abu_dhabi') return 'Abu Dhabi'
  if (r === 'dubai') return 'Dubai'
  return ''
}
function groupLabel(g) {
  return ALL_GROUPS.find((x) => x.id === g)?.label || g
}

function setCategory(id) {
  if (category.value === id) return
  category.value = id
  sheetGroup.value = id === 'blogs' ? 'blogs' : 'projects'
  region.value = ''
  published.value = ''
}

function openCreate() {
  editing.value = null
  Object.assign(form, emptyForm(), {
    category: category.value,
    sheet_group: sheetGroup.value || (category.value === 'blogs' ? 'blogs' : 'projects'),
  })
  Object.keys(errors).forEach((k) => delete errors[k])
  formOpen.value = true
}

function openEdit(entry) {
  editing.value = entry
  Object.assign(form, emptyForm(), {
    ...entry,
    region: entry.region || '',
    is_published: entry.is_published,
  })
  Object.keys(errors).forEach((k) => delete errors[k])
  formOpen.value = true
}

async function save() {
  Object.keys(errors).forEach((k) => delete errors[k])
  if (!String(form.name || '').trim()) {
    errors.name = 'Name is required'
    return
  }
  saving.value = true
  try {
    const body = {
      ...form,
      name: form.name.trim(),
      region: form.region || null,
      category: category.value,
      sheet_group: category.value === 'blogs' ? 'blogs' : form.sheet_group,
    }
    if (editing.value) {
      const { data } = await api.put(`/marketing/tracking-sheets/${editing.value.id}`, body)
      const i = entries.value.findIndex((e) => e.id === data.entry.id)
      if (i !== -1) entries.value.splice(i, 1, data.entry)
      else await load()
    } else {
      await api.post('/marketing/tracking-sheets', body)
      await load()
      await refreshTotals()
    }
    formOpen.value = false
  } catch (err) {
    const fieldErrors = err?.response?.data?.errors
    if (fieldErrors) {
      Object.entries(fieldErrors).forEach(([k, v]) => { errors[k] = Array.isArray(v) ? v[0] : v })
    } else {
      Swal.fire({ icon: 'error', title: 'Could not save', text: apiError(err) })
    }
  } finally {
    saving.value = false
  }
}

async function remove(entry) {
  const res = await Swal.fire({
    icon: 'warning',
    title: 'Delete this entry?',
    text: entry.name,
    showCancelButton: true,
    confirmButtonText: 'Delete',
    confirmButtonColor: '#dc2626',
  })
  if (!res.isConfirmed) return
  try {
    await api.delete(`/marketing/tracking-sheets/${entry.id}`)
    entries.value = entries.value.filter((e) => e.id !== entry.id)
    await refreshTotals()
  } catch (err) {
    Swal.fire({ icon: 'error', title: 'Could not delete', text: apiError(err) })
  }
}

let seq = 0
async function load() {
  const mine = ++seq
  loading.value = true
  try {
    const { data } = await api.get('/marketing/tracking-sheets', {
      params: {
        category: category.value,
        sheet_group: sheetGroup.value || undefined,
        region: region.value || undefined,
        published: category.value === 'blogs' && published.value !== '' ? published.value : undefined,
        search: props.search?.trim() || undefined,
      },
    })
    if (mine !== seq) return
    entries.value = data.entries || []
    groups.value = data.groups || {}
    groupTotals[category.value] = Object.values(data.groups || {}).reduce((a, b) => a + Number(b), 0)
  } catch (err) {
    if (mine === seq) Swal.fire({ icon: 'error', title: 'Could not load tracking sheets', text: apiError(err) })
  } finally {
    if (mine === seq) loading.value = false
  }
}

async function refreshTotals() {
  for (const tab of CATEGORY_TABS) {
    try {
      const { data } = await api.get('/marketing/tracking-sheets', { params: { category: tab.id } })
      groupTotals[tab.id] = Object.values(data.groups || {}).reduce((a, b) => a + Number(b), 0)
    } catch {
      /* ignore */
    }
  }
}

let searchTimer = null
watch(() => [category.value, sheetGroup.value, region.value, published.value], load)
watch(() => props.search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(load, 280)
})

onMounted(async () => {
  await load()
  refreshTotals()
})
</script>

<style scoped>
.mk-ts { display: flex; flex-direction: column; gap: 16px; }

.mk-ts__tabs {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}
.mk-ts__tab {
  --chip: var(--color-primary);
  --chip-grad: var(--gradient-primary);
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  border-radius: 14px;
  border: 1.5px solid var(--border-color);
  background: var(--white);
  color: var(--text-primary-light);
  font-size: 14px !important;
  font-weight: 700 !important;
  cursor: pointer;
  transition: all 0.18s ease;
}
.mk-ts__tab iconify-icon { font-size: 18px; color: var(--chip); }
.mk-ts__tab em {
  font-style: normal;
  font-size: 12px !important;
  font-weight: 700 !important;
  padding: 2px 8px;
  border-radius: 999px;
  background: color-mix(in srgb, var(--chip) 12%, var(--white));
  color: var(--chip);
}
.mk-ts__tab:hover {
  border-color: color-mix(in srgb, var(--chip) 45%, var(--border-color));
  transform: translateY(-1px);
}
.mk-ts__tab.is-on {
  color: #fff;
  border-color: transparent;
  background: var(--chip-grad);
  box-shadow: 0 10px 24px -12px color-mix(in srgb, var(--chip) 70%, transparent);
}
.mk-ts__tab.is-on iconify-icon,
.mk-ts__tab.is-on em { color: #fff; background: rgba(255,255,255,0.18); }

.mk-ts__toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  align-items: center;
  justify-content: space-between;
}
.mk-ts__groups,
.mk-ts__filters {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}
.mk-ts__pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 34px;
  padding: 6px 12px;
  border-radius: 999px;
  border: 1px solid var(--border-color);
  background: var(--white);
  color: var(--text-primary-light);
  font-size: 13px !important;
  font-weight: 600 !important;
  cursor: pointer;
  transition: all 0.15s;
}
.mk-ts__pill span {
  font-size: 11px !important;
  padding: 1px 7px;
  border-radius: 999px;
  background: #f3eef8;
  color: var(--color-primary);
}
.mk-ts__pill.is-on {
  background: color-mix(in srgb, var(--color-primary) 12%, var(--white));
  border-color: color-mix(in srgb, var(--color-primary) 40%, var(--border-color));
  color: var(--color-primary);
}
.mk-ts__pill--soft.is-on {
  background: color-mix(in srgb, #B8892B 14%, var(--white));
  border-color: color-mix(in srgb, #B8892B 45%, var(--border-color));
  color: #8a6818;
}

.mk-ts__stats {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
}
@media (max-width: 900px) {
  .mk-ts__stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
.mk-ts__stat {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border-radius: 16px;
  border: 1px solid var(--border-color);
  background:
    radial-gradient(120% 80% at 0% 0%, color-mix(in srgb, var(--color-primary) 10%, transparent), transparent 55%),
    var(--white);
}
.mk-ts__stat iconify-icon {
  width: 36px;
  height: 36px;
  display: grid;
  place-items: center;
  border-radius: 12px;
  background: color-mix(in srgb, var(--color-primary) 12%, var(--white));
  color: var(--color-primary);
  font-size: 18px;
}
.mk-ts__stat small {
  display: block;
  font-size: 11px !important;
  color: var(--text-secondary-light);
  font-weight: 600 !important;
}
.mk-ts__stat b {
  font-size: 14px !important;
  font-weight: 700 !important;
  color: var(--text-primary-light);
}

.mk-ts__empty {
  display: grid;
  place-items: center;
  gap: 8px;
  padding: 60px 20px;
  border-radius: 20px;
  border: 1px dashed var(--border-color);
  background: color-mix(in srgb, var(--color-primary) 4%, var(--white));
  text-align: center;
}
.mk-ts__empty iconify-icon { font-size: 42px; color: var(--color-primary); opacity: 0.7; }
.mk-ts__empty h4 { margin: 0; font-size: 18px !important; }
.mk-ts__empty p { margin: 0 0 8px; color: var(--text-secondary-light); font-size: 14px !important; }

.mk-ts__grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 14px;
}
.mk-ts__grid.is-loading { opacity: 0.65; }

.mk-ts-card {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 16px;
  border-radius: 18px;
  border: 1px solid var(--border-color);
  background:
    linear-gradient(160deg, color-mix(in srgb, var(--color-primary) 7%, var(--white)) 0%, var(--white) 42%),
    var(--white);
  box-shadow: 0 10px 28px -22px rgba(60, 30, 90, 0.45);
  animation: mk-ts-in 0.35s ease both;
  animation-delay: calc(var(--i) * 18ms);
  transition: transform 0.15s, box-shadow 0.15s, border-color 0.15s;
}
.mk-ts-card:hover {
  transform: translateY(-2px);
  border-color: color-mix(in srgb, var(--color-primary) 35%, var(--border-color));
  box-shadow: 0 16px 36px -20px rgba(60, 30, 90, 0.5);
}
@keyframes mk-ts-in {
  from { opacity: 0; transform: translateY(8px); }
  to { opacity: 1; transform: none; }
}

.mk-ts-card__head {
  display: flex;
  gap: 10px;
  justify-content: space-between;
  align-items: flex-start;
}
.mk-ts-card__title h4 {
  margin: 6px 0 4px;
  font-size: 15px !important;
  font-weight: 700 !important;
  line-height: 1.35;
  color: var(--text-primary-light);
}
.mk-ts-card__title p {
  margin: 0;
  font-size: 12.5px !important;
  color: var(--text-secondary-light);
  line-height: 1.4;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.mk-ts-card__badge {
  display: inline-flex;
  align-items: center;
  padding: 3px 9px;
  border-radius: 999px;
  font-size: 11px !important;
  font-weight: 700 !important;
  letter-spacing: 0.02em;
  background: #f3eef8;
  color: var(--color-primary);
}
.mk-ts-card__badge[data-region="abu_dhabi"] { background: #e8f7f4; color: #0f766e; }
.mk-ts-card__badge[data-region="dubai"] { background: #fff4e5; color: #b45309; }
.mk-ts-card__actions { display: flex; gap: 4px; flex-shrink: 0; }

.mk-ts-card__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.mk-ts-card__meta span {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 9px;
  border-radius: 999px;
  background: #f7f5fa;
  color: #5b5268;
  font-size: 12px !important;
  font-weight: 600 !important;
}
.mk-ts-card__meta .is-live { background: #e8f8ef; color: #15803d; }
.mk-ts-card__meta .is-draft { background: #f3f4f6; color: #6b7280; }

.mk-ts-card__links {
  display: flex;
  flex-direction: column;
  gap: 7px;
  margin-top: auto;
}
.mk-ts-link {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 9px 11px;
  border-radius: 12px;
  text-decoration: none;
  font-size: 13px !important;
  font-weight: 600 !important;
  color: var(--text-primary-light);
  background: #faf8fc;
  border: 1px solid transparent;
  transition: all 0.15s;
}
.mk-ts-link span {
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.mk-ts-link__go { opacity: 0.45; font-size: 14px; }
.mk-ts-link:hover {
  border-color: color-mix(in srgb, var(--color-primary) 25%, var(--border-color));
  background: #fff;
  transform: translateX(2px);
}
.mk-ts-link--doc { --c: #1a73e8; }
.mk-ts-link--page { --c: #733E87; }
.mk-ts-link--li { --c: #1F6FC2; }
.mk-ts-link--ig { --c: #D6336C; }
.mk-ts-link iconify-icon:first-child { color: var(--c); font-size: 16px; }
.mk-ts-card__nolink {
  font-size: 12px !important;
  color: var(--text-secondary-light);
  padding: 4px 2px;
}

.mk-ts-form-block {
  margin-top: 4px;
  margin-bottom: 12px;
  padding: 12px;
  border-radius: 14px;
  background: #faf8fc;
  border: 1px solid var(--border-color);
}
.mk-ts-form-block h6 {
  display: flex;
  align-items: center;
  gap: 7px;
  margin: 0 0 10px;
  font-size: 13px !important;
  font-weight: 700 !important;
  color: var(--color-primary);
}
.mk-ts-form-block .mk-field { margin-bottom: 0; }

.mk-empty {
  display: grid;
  place-items: center;
  min-height: 220px;
  color: var(--color-primary);
  font-size: 28px;
}
.mk-spin { animation: mk-spin 0.8s linear infinite; }
@keyframes mk-spin { to { transform: rotate(360deg); } }
</style>
