<template>
  <div class="hr-oc-node" :class="{ 'hr-oc-node--root': depth === 0 }">
    <div class="hr-oc-node__cardwrap">
      <article class="hr-oc-card" :class="{ 'hr-oc-card--root': depth === 0 }" @click="onCardClick">
        <button
          v-if="hasChildren"
          type="button"
          class="hr-oc-expand"
          :aria-expanded="expanded"
          @click.stop="expanded = !expanded"
        >
          <iconify-icon :icon="expanded ? 'lucide:chevron-up' : 'lucide:chevron-down'" width="13" />
        </button>

        <div class="hr-oc-main">
          <img :src="node.avatar || defaultAvatar" alt="" class="hr-oc-avatar" @error="onImgError" />
          <div class="hr-oc-copy">
            <h6 class="hr-oc-name">{{ node.name || 'Unnamed' }}</h6>
            <p class="hr-oc-role">{{ node.role_name || '—' }}</p>
            <div class="hr-oc-meta-row">
              <span class="hr-oc-meta">ID {{ node.employee_id_display || '—' }}</span>
              <span
                v-if="node.hr_attendance"
                class="hr-oc-status"
                :class="`hr-oc-status--${statusKey}`"
              >
                {{ statusLabel }}
              </span>
            </div>
            <p class="hr-oc-time">{{ node.hr_attendance ? `${formatHrTime(node.hr_attendance.check_in)} - ${formatHrTime(node.hr_attendance.check_out)}` : 'No punches' }}</p>
          </div>
        </div>
      </article>
    </div>

    <Transition name="hr-branch">
      <div v-if="hasChildren && expanded" class="hr-oc-tree">
        <div class="hr-oc-stem" aria-hidden="true" />
        <HrTeamTreeNodeChildren :children="node.children" :depth="depth + 1" @open-sales="emit('open-sales', $event)" />
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import HrTeamTreeNodeChildren from './HrTeamTreeNodeChildren.vue'

const props = defineProps({
  node: { type: Object, required: true },
  depth: { type: Number, default: 0 },
})
const emit = defineEmits(['open-sales'])

const defaultAvatar = '/assets/images/user.png'
const expanded = ref(false)

const hasChildren = computed(() => Array.isArray(props.node.children) && props.node.children.length > 0)
const roleLower = computed(() => String(props.node.role_name || '').toLowerCase())
const isSales = computed(() => roleLower.value.includes('sales'))
const isTeamLead = computed(() => roleLower.value.includes('lead') || roleLower.value.includes('team'))
const salesChildren = computed(() =>
  (props.node.children || []).filter((c) => String(c.role_name || '').toLowerCase().includes('sales'))
)

const statusKey = computed(() => {
  const s = String(props.node.hr_attendance?.status || 'absent').toLowerCase()
  if (s === 'present' || s === 'late' || s === 'absent') return s
  return 'absent'
})

const statusLabel = computed(() => {
  const k = statusKey.value
  return k ? k.charAt(0).toUpperCase() + k.slice(1) : 'Absent'
})

function formatHrTime(value) {
  if (!value) return '—'
  try {
    const d = new Date(value)
    if (Number.isNaN(d.getTime())) return '—'
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
  } catch {
    return '—'
  }
}

function onImgError(e) {
  e.target.src = defaultAvatar
}

function onCardClick() {
  if (isSales.value) return
  if (isTeamLead.value && salesChildren.value.length) {
    emit('open-sales', {
      lead: props.node,
      sales: salesChildren.value,
    })
    return
  }
  if (hasChildren.value) {
    expanded.value = !expanded.value
  }
}
</script>

<style scoped>
.hr-oc-node {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  max-width: 100%;
  margin-left: auto;
  margin-right: auto;
  font-family: var(--hr-tree-font, 'Inter', 'Montserrat', system-ui, sans-serif);
}

.hr-oc-node__cardwrap {
  width: min(var(--hr-oc-card-w, 248px), 100%);
  display: flex;
  justify-content: center;
}

.hr-oc-card {
  position: relative;
  box-sizing: border-box;
  width: min(var(--hr-oc-card-w, 248px), 100%);
  border-radius: var(--hr-tree-radius-card, 14px);
  background: #fff;
  border: 1px solid var(--hr-tree-line-soft, #d9d9e4);
  box-shadow: var(--hr-tree-card-shadow, 0 2px 10px rgba(17, 24, 39, 0.06));
  padding: 10px 12px;
}

.hr-oc-card--root {
  border-color: var(--hr-tree-accent-border, rgba(99, 102, 241, 0.35));
  box-shadow: var(--hr-tree-card-shadow-root, 0 0 0 1px rgba(99, 102, 241, 0.18), 0 5px 18px rgba(79, 70, 229, 0.12));
}

.hr-oc-expand {
  position: absolute;
  right: 6px;
  top: 6px;
  width: 22px;
  height: 22px;
  border: none;
  border-radius: 8px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: transparent;
  color: var(--hr-tree-muted, #737373);
}

.hr-oc-expand:hover {
  background: var(--hr-tree-accent-weak, #eef2ff);
  color: var(--hr-tree-accent, #4f46e5);
}

.hr-oc-main {
  display: flex;
  gap: 10px;
  align-items: center;
}

.hr-oc-avatar {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  object-fit: cover;
  flex-shrink: 0;
  border: 1px solid #e5e7eb;
}

.hr-oc-copy {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.hr-oc-name {
  margin: 0;
  font-size: var(--hr-oc-font-title, 20px);
  line-height: 1.15;
  font-weight: 700;
  color: var(--hr-tree-text-strong, #111827);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.hr-oc-role {
  margin: 0;
  font-size: var(--hr-oc-font-role, 15px);
  line-height: 1.2;
  font-weight: 500;
  color: var(--hr-tree-text, #4b5563);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.hr-oc-meta-row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 2px;
}

.hr-oc-meta {
  font-size: var(--hr-oc-font-meta, 12px);
  color: var(--hr-tree-muted, #6b7280);
  font-weight: 500;
}

.hr-oc-time {
  margin: 0;
  font-size: var(--hr-oc-font-time, 12px);
  color: #6b7280;
  font-weight: 500;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.hr-oc-status {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
  padding: 1px 8px;
  font-size: 11px;
  font-weight: 700;
}

.hr-oc-status--present { background: #dcfce7; color: #166534; }
.hr-oc-status--late { background: #ffedd5; color: #c2410c; }
.hr-oc-status--absent { background: #fee2e2; color: #b91c1c; }

.hr-oc-tree {
  display: flex;
  flex-direction: column;
  align-items: center;
  width: max-content;
  max-width: 100%;
}

.hr-oc-stem {
  width: 2px;
  height: var(--hr-oc-stem-h, 22px);
  background: var(--hr-tree-line, #c7c9da);
  border-radius: 99px;
}

.hr-branch-enter-active,
.hr-branch-leave-active {
  transition: opacity 0.18s ease, transform 0.18s ease;
}

.hr-branch-enter-from,
.hr-branch-leave-to {
  opacity: 0;
  transform: translateY(-5px);
}

@media (max-width: 900px) {
  .hr-oc-node__cardwrap,
  .hr-oc-card {
    width: min(100%, var(--hr-oc-card-w-mobile, 220px));
  }
  .hr-oc-avatar { width: 40px; height: 40px; }
}
</style>
