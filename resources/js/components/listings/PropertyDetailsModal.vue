<template>
  <Teleport to="body">
    <div v-if="isOpen && listingId" class="pdm-backdrop" @click.self="closePropertyDetails">
      <div class="pdm-dialog" role="dialog" aria-modal="true" aria-label="Property details">
        <div class="pdm-header">
          <h5 class="pdm-title mb-0">Property Details</h5>
          <div class="pdm-header-actions">
            <a
              :href="`/property-details/${listingId}`"
              target="_blank"
              rel="noopener"
              class="pdm-header-btn"
              title="Open in new tab"
            >
              <i class="ri-external-link-line"></i>
            </a>
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
import { watch, onBeforeUnmount } from 'vue';
import { useRoute } from 'vue-router';
import BlogOne from '@/components/alllisting/PropertyDetails/BlogOne.vue';
import { usePropertyDetailsModal } from '@/composables/usePropertyDetailsModal';

const { isOpen, listingId, closePropertyDetails, notifyPropertyDeleted } = usePropertyDetailsModal();

const route = useRoute();

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

// Actions inside the details (edit, agent profile, ...) navigate away — close the popup.
watch(() => route.fullPath, () => closePropertyDetails());

onBeforeUnmount(() => lockBody(false));
</script>

<style>
/* Class instead of inline overflow so BlogOne's lightbox (which resets body overflow) can't unlock it. */
body.property-details-modal-open {
  overflow: hidden !important;
}
</style>

<style scoped>
.pdm-backdrop {
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

.pdm-dialog {
  background: #f5f6fa;
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

.pdm-body {
  overflow-y: auto;
  overflow-x: hidden;
  padding: 1rem 1.25rem;
  flex: 1 1 auto;
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
