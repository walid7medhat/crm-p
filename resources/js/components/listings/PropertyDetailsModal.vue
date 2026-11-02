<template>
  <Teleport to="body">
    <div v-if="isOpen && listingId" class="pdm-backdrop" @click.self="closePropertyDetails">
      <div class="pdm-dialog" role="dialog" aria-modal="true" aria-label="Property details">
        <div class="pdm-header">
          <h5 class="pdm-title mb-0">Property Details</h5>
          <div class="pdm-header-actions">
            <!-- <a
              :href="`/property-details/${listingId}`"
              target="_blank"
              rel="noopener"
              class="pdm-header-btn"
              title="Open in new tab"
            >
              <i class="ri-external-link-line"></i>
            </a> -->
            <button type="button" class="pdm-header-btn" aria-label="Close" title="Close" @click="closePropertyDetails">
              <i class="ri-close-line"></i>
            </button>
          </div>
        </div>
        <div class="pdm-body">
          <div class="row">
            <BlogOne :key="listingId" :listing-id="listingId" @deleted="onDeleted" />
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
// Mounted once in App.vue — open it from anywhere with openPropertyDetails(id)
// from '@/composables/usePropertyDetailsModal'.
import { watch, onBeforeUnmount, defineAsyncComponent } from 'vue';
import { useRoute, useRouter } from 'vue-router';

// Lazy: this modal is mounted in App.vue; a static import pulls BlogOne (+ html2pdf) into the main bundle.
const BlogOne = defineAsyncComponent(() => import('@/components/alllisting/PropertyDetails/BlogOne.vue'));
import {
  usePropertyDetailsModal,
  initPropertyDetailsModal,
  syncPropertyDetailsWithUrl,
  LISTING_QUERY_KEY,
} from '@/composables/usePropertyDetailsModal';

const { isOpen, listingId, closePropertyDetails, notifyPropertyDeleted } = usePropertyDetailsModal();

const route = useRoute();
initPropertyDetailsModal(useRouter(), route);

const onDeleted = (id) => {
  notifyPropertyDeleted(id);
  closePropertyDetails();
};

const onKeydown = (event) => {
  if (event.key !== 'Escape') return;
  // Let the gallery lightbox / inner dialogs handle Escape first.
  if (document.querySelector('.lightbox-overlay, .swal2-container, .owner-details-modal-overlay, .profile-panel-backdrop')) return;
  closePropertyDetails();
};

const lockBody = (locked) => {
  document.body.classList.toggle('property-details-modal-open', locked);
  if (locked) {
    document.addEventListener('keydown', onKeydown);
  } else {
    document.removeEventListener('keydown', onKeydown);
  }
};

watch(() => isOpen.value && !!listingId.value, lockBody, { immediate: true });

// Actions inside the details (edit, agent profile, ...) navigate to another page — close
// the popup. Only the path: query changes (filters, page, our own ?listing=) must not close it.
// Defined before the ?listing= watcher so a new page that carries ?listing= reopens it.
watch(() => route.path, () => closePropertyDetails({ syncUrl: false }));

// ?listing=<id> in the URL ⇄ popup: shared link / refresh opens it, Back closes it.
watch(() => route.query[LISTING_QUERY_KEY], (value) => syncPropertyDetailsWithUrl(value), { immediate: true });

onBeforeUnmount(() => lockBody(false));
</script>

<style>
/* Class instead of inline overflow so BlogOne's lightbox (which resets body overflow) can't unlock it. */
body.property-details-modal-open {
  overflow: hidden !important;
}

/* Mobile tab bar (z-index 12060) would sit on top of the popup — hide it while open. */
body.property-details-modal-open .mobile-tab-bar {
  visibility: hidden;
  pointer-events: none;
}
</style>

<style scoped>
.pdm-backdrop {
  position: fixed;
  inset: 0;
  /* Above View Lead (#view-lead-modal 12200) and stage overlays (~14000).
     Keep below owner sheets / SweetAlert (105000+). */
  z-index: 15050 !important;
  background: rgba(15, 23, 42, 0.6);
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 1.5rem 1rem;
  overflow: hidden;
}

.pdm-dialog {
  background: #fff;
  border-radius: 16px;
  width: min(1400px, 100%);
  max-height: calc(100dvh - 3rem);
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25);
  overflow: hidden;
}

.pdm-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.75rem 1.25rem;
  background: #fff;
  border-bottom: 1px solid #e9ecef;
  flex-shrink: 0;
  /* Above BlogOne's pinned agent box (z-index 45) */
  position: relative;
  z-index: 50;
}

.pdm-title {
  font-size: 1rem !important;
  font-weight: 600;
  color: #0B0736;
}

.pdm-header-actions {
  display: flex;
  gap: 0.5rem;
}

.pdm-header-btn {
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
  text-decoration: none;
  cursor: pointer;
  transition: background 0.2s ease;
}

.pdm-header-btn:hover {
  background: #f1f2f6;
}

/* ONE scrollbar for the whole property modal */
.pdm-body {
  overflow-y: auto;
  overflow-x: hidden;
  padding: 1rem 1.25rem;
  flex: 1 1 auto;
  min-height: 0;
}

@media (min-width: 992px) {
  .pdm-body :deep(.ps-page),
  .pdm-body :deep(.ps-shell),
  .pdm-body :deep(.ps-layout),
  .pdm-body :deep(.ps-main),
  .pdm-body :deep(.ps-aside),
  .pdm-body :deep(.property-content) {
    overflow: visible !important;
    max-height: none !important;
  }

  .pdm-body :deep(.ps-layout:not(.ps-layout--mobile)) {
    display: grid !important;
    grid-template-columns: minmax(0, 1fr) minmax(340px, 420px) !important;
    gap: 12px !important;
    align-items: stretch !important;
  }

  .pdm-body :deep(.ps-aside),
  .pdm-body :deep(.property-show-sidebar-col) {
    display: flex !important;
    flex-direction: column !important;
    align-self: stretch !important;
    overflow: visible !important;
    max-height: none !important;
  }

  /* Stick flush under modal header — no gap, no second scrollbar */
  /* BlogOne pins this with a transform while .pdm-body scrolls (not position: sticky) */
  .pdm-body :deep(.sidebar-sticky-container) {
    position: relative !important;
    top: 0 !important;
    z-index: 2 !important;
    margin-top: 0 !important;
    padding-top: 0 !important;
    /* max-height is set inline by BlogOne (capped to the visible height) */
    overflow: visible !important;
    background: #fff !important;
  }

  .pdm-body :deep(.property-sidebar-spacer) {
    display: none !important;
    height: 0 !important;
  }

  .pdm-body :deep(.ps-aside .agent-sidebar-card),
  .pdm-body :deep(.sidebar-sticky-container .agent-sidebar-card) {
    margin-top: 0 !important;
    max-height: none !important;
    overflow: visible !important;
  }
}

@media (max-width: 768px) {
  .pdm-backdrop {
    padding: 0;
  }

  .pdm-dialog {
    border-radius: 0;
    max-height: 100dvh;
    height: 100dvh;
  }

  .pdm-header {
    padding: 0.5rem 0.75rem;
    padding-top: max(0.5rem, env(safe-area-inset-top));
  }

  .pdm-dialog {
    background: #fff;
  }

  /* Same as .property-show-page > .row on the full mobile page */
  .pdm-body > .row {
    margin: 0;
    --bs-gutter-x: 0;
    --bs-gutter-y: 0;
  }

  .pdm-body {
    padding: 0 0 calc(150px + env(safe-area-inset-bottom, 0)); /* room for BlogOne's fixed mobile agent bar */
  }
}
</style>
