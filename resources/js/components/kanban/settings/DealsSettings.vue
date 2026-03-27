<template>
  <div class="deals-settings-container">
    <div class="settings-empty-header">
      <h6 class="settings-empty-title">Deals Settings</h6>
      <p class="settings-empty-subtitle">
        This section is prepared in the unified Settings system. If you want specific deal settings to be configurable,
        tell me what fields/rules you need and we’ll wire them to the backend.
      </p>
    </div>

    <div class="settings-empty-card">
      <div class="settings-empty-row">
        <iconify-icon icon="lucide:construction" class="settings-empty-icon" />
        <div>
          <div class="settings-empty-row-title">Coming soon</div>
          <div class="settings-empty-row-desc">
            For now, Leads / Stages / Cards settings are fully available. Deals settings UI will be enabled when the backend endpoints are ready.
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
// Placeholder component for future deals settings.
</script>

<style scoped>
.deals-settings-container {
  padding: 14px 6px;
}

.settings-empty-header {
  padding: 6px 6px 12px;
}

.settings-empty-title {
  margin: 0;
  font-size: 14px;
  font-weight: 800;
  color: #0f172a;
}

.settings-empty-subtitle {
  margin: 6px 0 0;
  font-size: 12.5px;
  color: #64748b;
  line-height: 1.5;
}

.settings-empty-card {
  background: #fbfdff;
  border: 1px solid #edf2f7;
  border-radius: 14px;
  padding: 14px;
  box-shadow: 0 6px 20px rgba(15, 23, 42, 0.04);
}

.settings-empty-row {
  display: flex;
  gap: 12px;
  align-items: flex-start;
}

.settings-empty-icon {
  font-size: 28px;
  color: #94a3b8;
}

.settings-empty-row-title {
  font-weight: 800;
  color: #0f172a;
  font-size: 13px;
  margin-bottom: 4px;
}

.settings-empty-row-desc {
  font-size: 12.5px;
  color: #64748b;
  line-height: 1.45;
}
</style>

