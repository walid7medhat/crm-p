<template>
  <Teleport to="body">
    <Transition name="theme-fade">
      <div v-if="modelValue" class="theme-overlay" @click="onCancel">
        <div class="theme-dialog" role="dialog" aria-labelledby="theme-dialog-title" @click.stop>
          <header class="theme-dialog__head">
            <h3 id="theme-dialog-title" class="theme-dialog__title">THEMES</h3>
            <button type="button" class="theme-dialog__close" aria-label="Close" @click="onCancel">
              <iconify-icon icon="lucide:x" />
            </button>
          </header>

          <div class="theme-dialog__body">
            <div v-if="loading" class="theme-dialog__loading">
              <span class="theme-dialog__spinner" />
              <span class="theme-dialog__loading-text">Loading themes…</span>
            </div>

            <div v-else class="theme-grid">
              <button
                type="button"
                class="theme-tile theme-tile--system"
                :class="{ 'is-selected': pendingId === null }"
                @click="pick(null)"
              >
                <span class="theme-tile__system-icon">
                  <iconify-icon icon="lucide:rotate-ccw" />
                </span>
                <span v-if="pendingId === null" class="theme-tile__check">
                  <iconify-icon icon="lucide:check" />
                </span>
                <span class="theme-tile__label">System</span>
              </button>

              <button
                v-for="bg in visibleBackgrounds"
                :key="bg.id"
                type="button"
                class="theme-tile"
                :class="{ 'is-selected': pendingId === bg.id, 'is-dim': !bg.is_active }"
                :style="{ backgroundImage: `url('${thumb(bg.url)}')` }"
                @click="pick(bg.id)"
              >
                <span v-if="bg.is_default" class="theme-tile__badge theme-tile__badge--default">Default</span>
                <span v-if="pendingId === bg.id" class="theme-tile__check">
                  <iconify-icon icon="lucide:check" />
                </span>
                <span v-if="bg.name" class="theme-tile__caption">{{ bg.name }}</span>
              </button>
            </div>
          </div>

          <footer class="theme-dialog__foot">
            <button
              type="button"
              class="theme-btn theme-btn--save"
              :disabled="!dirty || saving"
              @click="onSave"
            >
              {{ saving ? 'Saving…' : 'Save' }}
            </button>
            <button type="button" class="theme-btn theme-btn--cancel" :disabled="saving" @click="onCancel">
              Cancel
            </button>
          </footer>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import backgroundsApi from '@/services/backgroundsApi'
import { useBackground } from '@/composables/useBackground'
import { normalizePublicStorageUrl } from '@/composables/usePublicStorageUrl'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const { syncFromUser } = useBackground()

const loading = ref(false)
const saving = ref(false)
const backgrounds = ref([])
const savedId = ref(null)
const pendingId = ref(null)
const dirty = computed(() => pendingId.value !== savedId.value)

const visibleBackgrounds = computed(() =>
  backgrounds.value.filter((bg) => bg.is_active !== false),
)

function thumb(url) {
  return normalizePublicStorageUrl(url) || url
}

function pick(id) {
  pendingId.value = id
}

function closeAndEmit() {
  emit('update:modelValue', false)
}

function onCancel() {
  if (saving.value) return
  pendingId.value = savedId.value
  closeAndEmit()
}

async function loadThemes() {
  loading.value = true
  try {
    const res = await backgroundsApi.list()
    const data = res.data?.data || {}
    backgrounds.value = data.backgrounds || []
    savedId.value = data.selected_id ?? null
    pendingId.value = savedId.value
  } catch (e) {
    console.error('Failed to load themes', e)
    backgrounds.value = []
  } finally {
    loading.value = false
  }
}

async function onSave() {
  if (!dirty.value || saving.value) return
  saving.value = true
  try {
    const res = await backgroundsApi.select(pendingId.value)
    syncFromUser(res.data?.data)
    savedId.value = pendingId.value
    emit('saved', pendingId.value)
    closeAndEmit()
  } catch (e) {
    console.error('Failed to save theme', e)
  } finally {
    saving.value = false
  }
}

watch(
  () => props.modelValue,
  (open) => {
    if (open) loadThemes()
  },
)
</script>

<style scoped>
.theme-overlay {
  position: fixed;
  inset: 0;
  z-index: 60000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  background: rgba(15, 23, 42, 0.45);
  backdrop-filter: blur(4px);
}

.theme-dialog {
  width: min(720px, 100%);
  max-height: min(88vh, 640px);
  display: flex;
  flex-direction: column;
  background: #fff;
  border-radius: 14px;
  box-shadow: 0 24px 48px rgba(15, 23, 42, 0.18);
  overflow: hidden;
}

.theme-dialog__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1.15rem;
  border-bottom: 1px solid #f1f5f9;
}

.theme-dialog__title {
  margin: 0;
  font-size: 1.75rem;
  font-weight: 800;
  color: #0f172a;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  line-height: 1.1;
}

.theme-dialog__close {
  width: 28px;
  height: 28px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: none;
  border-radius: 8px;
  background: transparent;
  color: #64748b;
  font-size: 16px;
  cursor: pointer;
  transition: background 0.15s ease;
}

.theme-dialog__close:hover {
  background: #f1f5f9;
  color: #0f172a;
}

.theme-dialog__body {
  flex: 1;
  overflow-y: auto;
  padding: 0.85rem 1rem 1rem;
}

.theme-dialog__loading {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 2.5rem 1rem;
  color: #64748b;
  font-size: 0.75rem;
}

.theme-dialog__spinner {
  width: 16px;
  height: 16px;
  border: 2px solid #e2e8f0;
  border-top-color: #2a1548;
  border-radius: 50%;
  animation: theme-spin 0.7s linear infinite;
}

@keyframes theme-spin {
  to { transform: rotate(360deg); }
}

.theme-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.65rem;
}

@media (max-width: 640px) {
  .theme-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

.theme-tile {
  position: relative;
  aspect-ratio: 16 / 10;
  padding: 0;
  border: 2px solid transparent;
  border-radius: 10px;
  background-size: cover;
  background-position: center;
  background-color: #e2e8f0;
  cursor: pointer;
  overflow: hidden;
  transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
}

.theme-tile:hover {
  transform: translateY(-1px);
}

.theme-tile.is-selected {
  border-color: #2a1548;
  box-shadow: 0 0 0 2px rgba(42, 21, 72, 0.15);
}

.theme-tile.is-dim {
  opacity: 0.55;
}

.theme-tile--system {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.2rem;
  background: linear-gradient(145deg, #f8fafc, #eef2ff);
  border: 2px solid #e2e8f0;
}

.theme-tile--system.is-selected {
  border-color: #2a1548;
}

.theme-tile__system-icon {
  font-size: 1rem;
  color: #64748b;
}

.theme-tile__label {
  font-size: 0.625rem;
  font-weight: 600;
  color: #475569;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.theme-tile__badge {
  position: absolute;
  top: 6px;
  right: 6px;
  padding: 0.1rem 0.35rem;
  border-radius: 4px;
  font-size: 0.5625rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.theme-tile__badge--default {
  background: rgba(42, 21, 72, 0.88);
  color: #fff;
}

.theme-tile__check {
  position: absolute;
  top: 6px;
  left: 6px;
  width: 18px;
  height: 18px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: #2a1548;
  color: #fff;
  font-size: 11px;
}

.theme-tile__caption {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  padding: 0.3rem 0.4rem;
  font-size: 0.5625rem;
  font-weight: 600;
  color: #fff;
  text-align: left;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  background: linear-gradient(to top, rgba(0, 0, 0, 0.65), transparent);
}

.theme-dialog__foot {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 1rem;
  border-top: 1px solid #f1f5f9;
  background: #fafbfc;
}

.theme-btn {
  border: none;
  background: none;
  cursor: pointer;
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  text-decoration: none;
  transition: opacity 0.15s ease, transform 0.15s ease;
}

.theme-btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.theme-btn--save {
  padding: 0.45rem 1.1rem;
  border-radius: 6px;
  background: #f5c518;
  color: #1a1030;
}

.theme-btn--save:hover:not(:disabled) {
  background: #e6b800;
}

.theme-btn--cancel {
  color: #64748b;
}

.theme-btn--cancel:hover:not(:disabled) {
  color: #0f172a;
}

.theme-fade-enter-active,
.theme-fade-leave-active {
  transition: opacity 0.2s ease;
}

.theme-fade-enter-active .theme-dialog,
.theme-fade-leave-active .theme-dialog {
  transition: transform 0.2s ease, opacity 0.2s ease;
}

.theme-fade-enter-from,
.theme-fade-leave-to {
  opacity: 0;
}

.theme-fade-enter-from .theme-dialog,
.theme-fade-leave-to .theme-dialog {
  transform: scale(0.96) translateY(8px);
  opacity: 0;
}
</style>
