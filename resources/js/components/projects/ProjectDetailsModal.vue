<template>
  <Teleport to="body">
    <div v-if="isOpen && projectId" class="pjm-backdrop" @click.self="closeProjectDetails">
      <div class="pjm-dialog" role="dialog" aria-modal="true" aria-label="Project details">
        <div class="pjm-header">
          <h5 class="pjm-title mb-0">Project Details</h5>
          <button type="button" class="pjm-header-btn" aria-label="Close" title="Close" @click="closeProjectDetails">
            <i class="ri-close-line"></i>
          </button>
        </div>
        <div class="pjm-body">
          <ProjectShow :key="projectId" :project-id="projectId" @deleted="onDeleted" />
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
// Mounted once in App.vue — open it from anywhere with openProjectDetails(id)
// from '@/composables/useProjectDetailsModal'. Mirrors PropertyDetailsModal.
import { watch, onBeforeUnmount, defineAsyncComponent } from 'vue';
import { useRoute } from 'vue-router';
import { useProjectDetailsModal } from '@/composables/useProjectDetailsModal';

// Lazy so the project page isn't pulled into the main bundle.
const ProjectShow = defineAsyncComponent(() => import('@/pages/projects/show.vue'));

const { isOpen, projectId, closeProjectDetails, notifyProjectDeleted } = useProjectDetailsModal();

const route = useRoute();

const onDeleted = (id) => {
  notifyProjectDeleted(id);
  closeProjectDetails();
};

const onKeydown = (event) => {
  if (event.key !== 'Escape') return;
  // Let the project image lightbox / dialogs handle Escape first.
  if (document.querySelector('.swal2-container, .lightbox-overlay')) return;
  closeProjectDetails();
};

const lockBody = (locked) => {
  document.body.classList.toggle('project-details-modal-open', locked);
  if (locked) {
    document.addEventListener('keydown', onKeydown);
  } else {
    document.removeEventListener('keydown', onKeydown);
  }
};

watch(() => isOpen.value && !!projectId.value, lockBody, { immediate: true });

// Actions inside the details (edit, ...) navigate away — close the popup.
watch(() => route.fullPath, () => closeProjectDetails());

onBeforeUnmount(() => lockBody(false));
</script>

<style>
/* Class instead of inline overflow so the project lightbox (which resets body overflow) can't unlock it. */
body.project-details-modal-open {
  overflow: hidden !important;
}

/* Mobile tab bar (z-index 12060) would sit on top of the popup — hide it while open
   (same approach as html.lead-search-sheet-lock). */
body.project-details-modal-open .mobile-tab-bar {
  visibility: hidden;
  pointer-events: none;
}

/* Centre the page inside the popup: crm-background.css gives the full page an uneven
   `padding: 0 1rem 1.5rem 0 !important` (room for the app sidebar), which pushed the
   content left here. */
.pjm-body .dashboard-main-body.project-show-page,
body.app-has-video-bg .pjm-body .dashboard-main-body.project-show-page {
  margin: 0 auto !important;
  padding: 0 !important;
  width: 100% !important;
  max-width: 100% !important;
}
</style>

<style scoped>
.pjm-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1055;
  background: rgba(15, 23, 42, 0.6);
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 1.5rem 1rem;
  overflow: hidden;
}

.pjm-dialog {
  background: #f5f6fa;
  border-radius: 16px;
  width: min(1400px, 100%);
  max-height: calc(100dvh - 3rem);
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25);
  overflow: hidden;
}

.pjm-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.75rem 1.25rem;
  background: #fff;
  border-bottom: 1px solid #e9ecef;
  flex-shrink: 0;
  position: relative;
  z-index: 50;
}

.pjm-title {
  font-size: 1rem !important;
  font-weight: 600;
  color: #0B0736;
}

.pjm-header-btn {
  width: 34px;
  height: 34px;
  border-radius: 8px;
  border: 1px solid #e2e5ec;
  background: #fff;
  color: #0B0736;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
  cursor: pointer;
  transition: background 0.2s ease;
}

.pjm-header-btn:hover {
  background: #f1f2f6;
}

.pjm-body {
  overflow-y: auto;
  overflow-x: hidden;
  padding: 1rem 1.25rem;
  flex: 1 1 auto;
}

@media (max-width: 768px) {
  .pjm-backdrop {
    padding: 0;
  }

  .pjm-dialog {
    border-radius: 0;
    max-height: 100dvh;
    height: 100dvh;
    background: #fff;
  }

  .pjm-header {
    padding: 0.5rem 0.75rem;
    padding-top: max(0.5rem, env(safe-area-inset-top));
  }

  .pjm-body {
    padding: 10px 10px calc(16px + env(safe-area-inset-bottom, 0px));
  }
}
</style>
