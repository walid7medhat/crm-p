<template>
  <div class="col-12 col-lg-9">
    <div class="card">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
          <div>
            <div class="h5 mb-1">Admin Email Sender</div>
            <div class="text-muted small">Send a dark SaaS notification email to agents.</div>
          </div>
          <AdminEmailBlastModal />
        </div>

        <hr class="my-3" />

        <div class="small text-muted">
          Template: <code>emails/saas-notification-dark.blade.php</code>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import AdminEmailBlastModal from './AdminEmailBlastModal.vue'
</script>

