import { ref } from 'vue';
import { isPlainLeftClick } from '@/utils/isPlainLeftClick';

/**
 * Global "property details" popup. The single <PropertyDetailsModal> instance
 * lives in App.vue; any page opens it with openPropertyDetails(id).
 */
const isOpen = ref(false);
const listingId = ref(null);
let callbacks = {};

/**
 * @param {number|string} id
 * @param {{ onClose?: Function, onDeleted?: Function }} [options]
 *   onClose   – called once when the popup closes (e.g. refresh the list).
 *   onDeleted – called when the listing is deleted from inside the popup.
 */
export function openPropertyDetails(id, options = {}) {
  if (!id) return;
  callbacks = options || {};
  listingId.value = id;
  isOpen.value = true;
}

/** Click handler for <a href="/property-details/:id"> — ctrl/middle click still opens a new tab. */
export function openPropertyDetailsFromClick(event, id, options = {}) {
  if (!isPlainLeftClick(event)) return;
  event?.preventDefault();
  openPropertyDetails(id, options);
}

export function closePropertyDetails() {
  if (!isOpen.value) return;
  isOpen.value = false;
  const { onClose } = callbacks;
  callbacks = {};
  if (typeof onClose === 'function') onClose();
}

export function notifyPropertyDeleted(id) {
  const { onDeleted } = callbacks;
  if (typeof onDeleted === 'function') onDeleted(id);
}

export function usePropertyDetailsModal() {
  return {
    isOpen,
    listingId,
    openPropertyDetails,
    openPropertyDetailsFromClick,
    closePropertyDetails,
    notifyPropertyDeleted,
  };
}
