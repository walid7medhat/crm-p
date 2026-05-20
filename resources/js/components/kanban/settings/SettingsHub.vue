<template>
  <div class="settings-hub">
    <div class="settings-hub-top">
      <div>
        <h6 class="settings-hub-title">Settings System</h6>
        <p class="settings-hub-subtitle">Choose a section to configure your Kanban experience.</p>
      </div>

      <button
        type="button"
        class="settings-hub-close"
        aria-label="Close settings"
        @click="emit('close')"
      >
        <iconify-icon icon="lucide:x" class="settings-hub-close-icon" />
      </button>
    </div>

    <div class="settings-hub-layout">
      <aside class="settings-hub-nav">
        <button
          v-for="s in visibleSections"
          :key="s.id"
          type="button"
          class="settings-nav-item"
          :class="{ active: s.id === activeSectionId }"
          @click="activeSectionId = s.id"
        >
          <iconify-icon :icon="s.icon" class="settings-nav-icon" />
          <span class="settings-nav-label">{{ s.label }}</span>
        </button>
      </aside>

      <section class="settings-hub-content">
        <transition name="settings-switch" mode="out-in">
          <component :is="activeComponent" :key="activeSectionId" />
        </transition>
      </section>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch, defineAsyncComponent } from 'vue'
import KanbanSettings from '../KanbanSettings.vue'
import LeadScoringSettings from '../LeadScoringSettings.vue'
import StageVisibility from '../stage/StageVisibility.vue'
import DealsSettings from './DealsSettings.vue'
import LeadAssignmentEngine from './LeadAssignmentEngine.vue'

const IntegrationPanel = defineAsyncComponent(() =>
  import('../integration/Integration.vue')
)

const props = defineProps({
  /** Open directly to a settings section (e.g. "integrations"). */
  initialSection: {
    type: String,
    default: null,
  },
})

const emit = defineEmits(['close'])

function getUserFromStorage() {
  try {
    const userData = localStorage.getItem('user')
    return userData ? JSON.parse(userData) : null
  } catch {
    return null
  }
}

const user = ref(getUserFromStorage())

const isSuperAdmin = computed(() => user.value?.roles?.includes('super_admin') ?? false)

const baseSections = [
  { id: 'leads', label: 'Leads Settings', icon: 'lucide:layout-template', component: KanbanSettings },
  { id: 'stages', label: 'Lead Stages', icon: 'lucide:eye', component: StageVisibility },
  { id: 'deals', label: 'Deal Stage Settings', icon: 'lucide:badge-dollar-sign', component: DealsSettings },
  { id: 'lead-scoring', label: 'Lead Scoring Engine', icon: 'lucide:brain-circuit', component: LeadScoringSettings },
  { id: 'lead-assignment', label: 'Lead Assignment Engine', icon: 'lucide:git-branch-plus', component: LeadAssignmentEngine },
]

const visibleSections = computed(() => {
  const sections = [...baseSections]
  if (isSuperAdmin.value) {
    sections.push({
      id: 'integrations',
      label: 'Integrations',
      icon: 'lucide:plug',
      component: IntegrationPanel,
    })
  }
  return sections
})

const activeSectionId = ref(baseSections[0].id)

const activeComponent = computed(() => {
  return visibleSections.value.find((s) => s.id === activeSectionId.value)?.component || null
})

function applyInitialSection(sectionId) {
  if (!sectionId) return
  if (visibleSections.value.some((s) => s.id === sectionId)) {
    activeSectionId.value = sectionId
  }
}

watch(
  () => props.initialSection,
  (sectionId) => applyInitialSection(sectionId),
  { immediate: true }
)

watch(visibleSections, () => {
  if (!visibleSections.value.some((s) => s.id === activeSectionId.value)) {
    activeSectionId.value = visibleSections.value[0]?.id ?? baseSections[0].id
  }
  applyInitialSection(props.initialSection)
})
</script>

<style scoped>
.settings-hub {
  height: 100%;
  min-height: min(90vh, 920px);
  max-height: 94vh;
  display: flex;
  flex-direction: column;
  background: #ffffff;
}

.settings-hub-top {
  padding: 14px 16px 12px;
  border-bottom: 1px solid #eef2f7;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  position: relative;
}

.settings-hub-title {
  margin: 0;
  font-size: 13px;
  font-weight: 800;
  color: #0f172a;
  letter-spacing: 0.2px;
}

.settings-hub-subtitle {
  margin: 6px 0 0;
  font-size: 12.5px;
  color: #64748b;
  line-height: 1.4;
}

.settings-hub-close {
  border: 1px solid #e2e8f0;
  background: #fff;
  width: 32px;
  height: 32px;
  border-radius: 10px;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: all 0.15s ease;
}

.settings-hub-close:hover {
  background: #f8fafc;
  border-color: #cbd5e1;
}

.settings-hub-close-icon {
  font-size: 18px;
  color: #64748b;
}

.settings-hub-layout {
  display: flex;
  min-height: 0;
  flex: 1;
}

.settings-hub-nav {
  width: 270px;
  padding: 12px;
  border-right: 1px solid #eef2f7;
  background: #fbfdff;
  overflow: auto;
}

.settings-nav-item {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-radius: 12px;
  border: 1px solid transparent;
  background: transparent;
  cursor: pointer;
  transition: all 0.18s ease;
  color: #0f172a;
}

.settings-nav-item:hover {
  background: #f1f5f9;
  border-color: #e2e8f0;
}

.settings-nav-item.active {
  background: #eaf3ff;
  border-color: #cfe4ff;
}

.settings-nav-icon {
  font-size: 18px;
  color: #64748b;
}

.settings-nav-item.active .settings-nav-icon {
  color: #1d4ed8;
}

.settings-nav-label {
  font-weight: 800;
  font-size: 12.5px;
}

.settings-hub-content {
  flex: 1;
  overflow: auto;
  padding: 12px 14px;
}

.settings-switch-enter-active,
.settings-switch-leave-active {
  transition: opacity 0.18s ease, transform 0.18s ease;
}

.settings-switch-enter-from {
  opacity: 0;
  transform: translateY(6px);
}

.settings-switch-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}
</style>

<!-- Modal shell is rendered outside scoped tree; sizes the Bootstrap dialog -->
<style>
.settings-hub-bootstrap-modal .modal-dialog {
  max-width: min(1540px, 99vw) !important;
  width: 99vw;
  margin: 0.65rem auto;
}

.settings-hub-bootstrap-modal-content {
  border: none;
  border-radius: 18px;
  overflow: hidden;
  box-shadow: 0 28px 80px rgba(15, 23, 42, 0.22);
  min-height: min(92vh, 940px);
  max-height: 96vh;
}

.settings-hub-bootstrap-modal .modal-body {
  max-height: 96vh;
  overflow: hidden;
  padding: 0 !important;
}
</style>
