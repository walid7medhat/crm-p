import { ref } from 'vue';
import { isPlainLeftClick } from '@/utils/isPlainLeftClick';

/**
 * Global "project details" popup. The single <ProjectDetailsModal> instance
 * lives in App.vue; any page opens it with openProjectDetails(id).
 * Same API as usePropertyDetailsModal.
 */
const isOpen = ref(false);
const projectId = ref(null);
let callbacks = {};

/**
 * @param {number|string} id
 * @param {{ onClose?: Function, onDeleted?: Function }} [options]
 *   onClose   – called once when the popup closes (e.g. refresh the list).
 *   onDeleted – called when the project is deleted from inside the popup.
 */
export function openProjectDetails(id, options = {}) {
  if (!id) return;
  callbacks = options || {};
  projectId.value = id;
  isOpen.value = true;
}

/** Click handler for <a href="/projects/:id"> — ctrl/middle click still opens a new tab. */
export function openProjectDetailsFromClick(event, id, options = {}) {
  if (!isPlainLeftClick(event)) return;
  event?.preventDefault();
  openProjectDetails(id, options);
}

export function closeProjectDetails() {
  if (!isOpen.value) return;
  isOpen.value = false;
  const { onClose } = callbacks;
  callbacks = {};
  if (typeof onClose === 'function') onClose();
}

export function notifyProjectDeleted(id) {
  const { onDeleted } = callbacks;
  if (typeof onDeleted === 'function') onDeleted(id);
}

export function useProjectDetailsModal() {
  return {
    isOpen,
    projectId,
    openProjectDetails,
    openProjectDetailsFromClick,
    closeProjectDetails,
    notifyProjectDeleted,
  };
}
