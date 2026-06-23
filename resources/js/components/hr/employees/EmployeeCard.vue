<template>
  <article class="emp-card" @click="$emit('view', employee)">
    <div class="emp-card__head">
      <img :src="employee.avatar" :alt="employee.name" class="emp-card__avatar" loading="lazy" />
      <div class="emp-card__meta">
        <h3 class="emp-card__name">{{ employee.name }}</h3>
        <p class="emp-card__code">{{ employee.employeeCode }}</p>
      </div>
      <span class="emp-card__badge" :class="badgeClass">{{ statusLabel }}</span>
    </div>

    <div>
      <p class="emp-card__role">{{ employee.designation }}</p>
      <p class="emp-card__dept">{{ employee.department }}</p>
    </div>

    <div class="emp-card__details">
      <div class="emp-card__detail">
        <iconify-icon icon="lucide:mail" />
        <span>{{ employee.email }}</span>
      </div>
      <div class="emp-card__detail">
        <iconify-icon icon="lucide:phone" />
        <span>{{ employee.phone }}</span>
      </div>
      <div class="emp-card__detail">
        <iconify-icon icon="lucide:calendar" />
        <span>{{ formatDate(employee.joiningDate) }}</span>
      </div>
      <div v-if="employee.manager !== '—'" class="emp-card__detail">
        <iconify-icon icon="lucide:user-round" />
        <span>{{ employee.manager }}</span>
      </div>
    </div>

    <div class="emp-card__actions" @click.stop>
      <button type="button" class="emp-card__action" @click="$emit('view', employee)">
        <iconify-icon icon="lucide:eye" /> View
      </button>
      <button type="button" class="emp-card__action" @click="$emit('edit', employee)">
        <iconify-icon icon="lucide:pencil" /> Edit
      </button>
      <button type="button" class="emp-card__action" @click="$emit('assets', employee)">
        <iconify-icon icon="lucide:laptop" /> Assets
      </button>
      <button type="button" class="emp-card__action" @click="$emit('attendance', employee)">
        <iconify-icon icon="lucide:clock" /> Attendance
      </button>
      <button type="button" class="emp-card__action" @click="$emit('leave', employee)">
        <iconify-icon icon="lucide:calendar-days" /> Leave
      </button>
      <button type="button" class="emp-card__action emp-card__action--danger" @click="$emit('delete', employee)">
        <iconify-icon icon="lucide:trash-2" /> Delete
      </button>
    </div>
  </article>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  employee: { type: Object, required: true },
})

defineEmits(['view', 'edit', 'assets', 'attendance', 'leave', 'delete'])

const statusLabel = computed(() => {
  const map = {
    active: 'Active',
    on_leave: 'On Leave',
    terminated: 'Terminated',
    suspended: 'Suspended',
  }
  return map[props.employee.employmentStatus] || props.employee.employmentStatus || 'Active'
})

const badgeClass = computed(() => {
  if (props.employee.status !== 'active') return 'emp-card__badge--inactive'
  return `emp-card__badge--${props.employee.employmentStatus || 'active'}`
})

function formatDate(value) {
  if (!value || value === '—') return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return value
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}
</script>
