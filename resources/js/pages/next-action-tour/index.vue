<template>
  <div class="nat-admin">
    <header class="nat-admin__bar">
      <div>
        <p class="nat-admin__title">Next Action</p>
        <p class="nat-admin__status">Campaign tours are turned off</p>
      </div>
    </header>
    <p class="nat-admin__note">
      The Next Actions tour and the Property Compare explanation no longer play.
    </p>
  </div>
</template>

<style scoped>
.nat-admin {
  max-width: 880px;
  margin: 0 auto;
  padding: 24px 20px 48px;
}
.nat-admin__bar {
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
.nat-admin__note {
  margin: 0;
  padding: 12px 14px;
  border-radius: 12px;
  background: #f8f5fb;
  color: #4a4460;
  font-size: 13px;
}
</style>
