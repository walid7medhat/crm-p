<template>
  <div class="nat-admin">
    <header class="nat-admin__bar">
      <div>
        <p class="nat-admin__title">Next Actions Tour</p>
        <p class="nat-admin__status">Phase A — Preview only (this browser session)</p>
      </div>
      <div class="nat-admin__actions">
        <button type="button" class="nat-admin__btn nat-admin__btn--preview" @click="runPreview">
          Preview
        </button>
        <button type="button" class="nat-admin__btn" disabled title="Available after Preview approval">
          Start Campaign
        </button>
        <button type="button" class="nat-admin__btn" disabled title="Available after Preview approval">
          Stop Campaign
        </button>
      </div>
    </header>

    <p class="nat-admin__note" role="status">{{ notice }}</p>

    <section class="nat-admin__card">
      <p class="nat-admin__card-title">What Preview does</p>
      <ul>
        <li>Starts with a 3D “New Feature” intro</li>
        <li>Opens the real Next Actions tree automatically</li>
        <li>Walks each stage and shows that stage’s exact cards from your data</li>
        <li>Does not open leads, notify other users, or write campaign data</li>
      </ul>
    </section>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { startNextActionTourPreview } from '@/composables/useNextActionTourPreview.js'

const notice = ref('Click Preview — 3D intro, then auto walkthrough of every stage and its cards.')

function runPreview() {
  startNextActionTourPreview()
  notice.value = 'Auto preview running. Watch the intro, then each stage with its real tasks. Close anytime with X / Esc.'
}
</script>

<style scoped>
.nat-admin {
  max-width: 880px;
  margin: 0 auto;
  padding: 24px 20px 48px;
}
.nat-admin__bar {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 16px;
}
.nat-admin__title {
  margin: 0;
  color: #0b0736;
  font-size: 22px;
  font-weight: 800;
}
.nat-admin__status {
  margin: 6px 0 0;
  color: #6b6580;
  font-size: 13px;
}
.nat-admin__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.nat-admin__btn {
  appearance: none;
  border: 0;
  border-radius: 999px;
  padding: 10px 16px;
  font-size: 13px;
  font-weight: 700;
  color: #3b3558;
  background: #f4f1f7;
  cursor: pointer;
}
.nat-admin__btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}
.nat-admin__btn--preview {
  color: #fff;
  background: var(--gradient-primary, linear-gradient(135deg, #733e87, #9b59b6));
  box-shadow: 0 8px 18px rgba(115, 62, 135, 0.25);
}
.nat-admin__note {
  margin: 0 0 18px;
  padding: 12px 14px;
  border-radius: 12px;
  background: #f8f5fb;
  color: #4a4460;
  font-size: 13px;
}
.nat-admin__card {
  padding: 18px 18px 8px;
  border-radius: 16px;
  border: 1px solid #ebecef;
  background: #fff;
  box-shadow: 0 10px 28px rgba(11, 7, 54, 0.06);
}
.nat-admin__card-title {
  margin: 0 0 8px;
  color: #0b0736;
  font-size: 14px;
  font-weight: 750;
}
.nat-admin__card ul {
  margin: 0;
  padding: 0 0 8px 18px;
  color: #5b5572;
  font-size: 14px;
  line-height: 1.55;
}
</style>
