<template>
  <div class="approved-viewings-panel" :class="panelClasses">
    <div class="approved-viewings-panel__head">
      <div class="approved-viewings-panel__title-row">
        <i class="ri-calendar-check-line"></i>
        <span class="approved-viewings-panel__title">Viewings</span>
        <span v-if="viewings.length" class="approved-viewings-panel__count">{{ viewings.length }}</span>
      </div>
      <p class="approved-viewings-panel__hint">Scheduled visits for this property</p>
    </div>

    <div v-if="loading" class="approved-viewings-panel__state">
      <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
    </div>

    <div v-else-if="!viewings.length" class="approved-viewings-panel__state approved-viewings-panel__state--empty">
      <i class="ri-calendar-line"></i>
      <strong>No viewings yet</strong>
      <span>Scheduled visits for this property will show up here.</span>
    </div>

    <ul v-else class="approved-viewings-panel__list">
      <li
        v-for="viewing in viewings"
        :key="viewing.id"
        class="approved-viewings-panel__item"
      >
        <PersonHoverCard
          v-if="viewing.requested_by?.id"
          :user-id="viewing.requested_by?.id"
          :name="viewing.requested_by?.name"
          :avatar="viewing.requested_by?.avatar"
          class="approved-viewings-panel__hover"
          @click="openUserProfile(viewing.requested_by?.id)"
        >
          <div class="d-flex align-items-center gap-2" style="cursor: pointer;">
            <img
              :src="viewing.requested_by?.avatar || defaultAvatar"
              class="approved-viewings-panel__avatar"
              alt=""
            >
            <div class="approved-viewings-panel__body">
              <span class="approved-viewings-panel__name">{{ viewing.requested_by?.name || 'Agent' }}</span>
              <span class="approved-viewings-panel__time">
                <i class="ri-time-line"></i>
                {{ displayWhen(viewing) }}
              </span>
            </div>
          </div>
        </PersonHoverCard>
        <template v-else>
          <img
            :src="viewing.requested_by?.avatar || defaultAvatar"
            class="approved-viewings-panel__avatar"
            alt=""
          >
          <div class="approved-viewings-panel__body">
            <span class="approved-viewings-panel__name">{{ viewing.requested_by?.name || 'Agent' }}</span>
            <span class="approved-viewings-panel__time">
              <i class="ri-time-line"></i>
              {{ displayWhen(viewing) }}
            </span>
          </div>
        </template>
      </li>
    </ul>

    <ProfilePopup
      v-if="showProfilePopup && profileUserId"
      v-model="showProfilePopup"
      :user-id="profileUserId"
      @update:model-value="closeProfilePopup"
    />
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import PersonHoverCard from '@/components/shared/PersonHoverCard.vue'
import ProfilePopup from '@/components/kanban/shared/ProfilePopup.vue'

const showProfilePopup = ref(false)
const profileUserId = ref(null)
function openUserProfile(userId) {
  if (userId) {
    profileUserId.value = userId
    showProfilePopup.value = true
  }
}
function closeProfilePopup() {
  showProfilePopup.value = false
  profileUserId.value = null
}

const props = defineProps({
  viewings: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  mobile: { type: Boolean, default: false },
  underAgent: { type: Boolean, default: false },
})

const panelClasses = computed(() => ({
  'approved-viewings-panel--mobile': props.mobile,
  'approved-viewings-panel--under-agent': props.underAgent,
}))

const defaultAvatar = 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png'

function displayWhen(viewing) {
  const date = viewing.formatted_date
  const time = viewing.formatted_time
  if (date && time) return `${date} · ${time}`
  if (date) return date
  if (time) return time
  return 'Date not set'
}
</script>

<style scoped>
.approved-viewings-panel {
  background: #fff;
  border: 1px solid #e8e8ee;
  border-radius: 16px;
  padding: 18px;
  margin-top: 0;
  box-shadow: 0 1px 3px rgba(11, 7, 54, 0.04);
}

.approved-viewings-panel--mobile {
  margin-bottom: 12px;
}

.approved-viewings-panel--under-agent {
  box-shadow: 0 1px 3px rgba(11, 7, 54, 0.04);
}

.approved-viewings-panel__head {
  margin-bottom: 8px;
}

.approved-viewings-panel__title-row {
  display: flex;
  align-items: center;
  gap: 10px;
  color: #0b0736;
  font-size: 15px;
  font-weight: 700;
}

.approved-viewings-panel__title-row i {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  color: var(--color-primary);
  background: rgba(var(--color-primary-rgb), 0.1);
}

.approved-viewings-panel__count {
  min-width: 16px;
  height: 16px;
  padding: 0 4px;
  border-radius: 999px;
  background: var(--gradient-primary) !important;
  color: #ffffff !important;
  font-size: 9px;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.approved-viewings-panel__hint {
  margin: 6px 0 0 46px;
  font-size: 12px;
  color: #9ca3af;
  line-height: 1.3;
}

.approved-viewings-panel__state {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 12px 6px;
  color: #94a3b8;
  font-size: 11px;
}

.approved-viewings-panel__state--empty {
  flex-direction: column;
  gap: 8px;
  padding: 28px 12px;
  font-size: 13px;
  text-align: center;
}

.approved-viewings-panel__state--empty strong {
  color: #0b0736;
  font-size: 15px;
  font-weight: 700;
}

.approved-viewings-panel__state--empty span {
  max-width: 240px;
  color: #9ca3af;
  font-size: 12px;
  line-height: 1.4;
}

.approved-viewings-panel__state--empty i {
  font-size: 36px;
  color: rgba(var(--color-primary-rgb), 0.45);
  opacity: 1;
}

.approved-viewings-panel__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
  max-height: none;
  overflow: visible;
  padding-right: 2px;
}

.approved-viewings-panel__list::-webkit-scrollbar {
  width: 3px;
}

.approved-viewings-panel__list::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 4px;
}

.approved-viewings-panel__item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 8px;
  border-radius: 8px;
  background: #f8fafc;
  border: 1px solid #eef2f7;
}

.approved-viewings-panel__hover {
  display: flex !important;
  width: 100%;
  min-width: 0;
}

.approved-viewings-panel__avatar {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  object-fit: cover;
  flex-shrink: 0;
  border: 1px solid #e2e8f0;
}

.approved-viewings-panel__body {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.approved-viewings-panel__name {
  font-size: 11px;
  font-weight: 600;
  color: #1e293b;
  line-height: 1.2;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.approved-viewings-panel__time {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  font-size: 10px;
  color: #64748b;
  line-height: 1.2;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.approved-viewings-panel__time i {
  font-size: 10px;
  color: var(--color-primary);
  flex-shrink: 0;
}
</style>
