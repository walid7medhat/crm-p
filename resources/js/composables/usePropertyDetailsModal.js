import { ref } from 'vue';
import { isPlainLeftClick } from '@/utils/isPlainLeftClick';

/**
 * Global "property details" popup. The single <PropertyDetailsModal> instance
 * lives in App.vue; any page opens it with openPropertyDetails(id).
 */
const isOpen = ref(false);
const listingId = ref(null);
let callbacks = {};

// Shareable URL for the open popup: /alllisting?listing=<id>. The popup opens from many
// personal pages (My Requests, My Orders, My Listings, owner profile…) whose content
// differs per user, so the link always points at All Properties, which then reopens the
// popup. On other pages only the address bar is rewritten (history.replaceState, the page
// stays mounted) and closing the popup puts the page's own link back.
export const LISTING_QUERY_KEY = 'listing';
export const LISTING_SHARE_PATH = '/alllisting';
let router = null;
let route = null;
/** The page's own URL while the address bar shows the share link (null when not swapped). */
let returnUrl = null;

export function initPropertyDetailsModal(r, rt) {
  router = r;
  route = rt;
}

const currentUrl = () => `${window.location.pathname}${window.location.search}${window.location.hash}`;

function setListingQuery(id) {
  if (!router || !route) return;

  if (route.path === LISTING_SHARE_PATH) {
    // Already on All Properties: use the real router query.
    if (String(route.query[LISTING_QUERY_KEY] || '') === String(id)) return;
    router.replace({ query: { ...route.query, [LISTING_QUERY_KEY]: String(id) } }).catch(() => {});
    return;
  }

  if (typeof window === 'undefined') return;
  if (returnUrl === null) returnUrl = currentUrl();
  const shareUrl = router.resolve({ path: LISTING_SHARE_PATH, query: { [LISTING_QUERY_KEY]: String(id) } }).href;
  // Keep vue-router's history.state so its back/forward bookkeeping is untouched.
  window.history.replaceState(window.history.state, '', shareUrl);
}

function clearListingQuery() {
  if (returnUrl !== null) {
    if (typeof window !== 'undefined') window.history.replaceState(window.history.state, '', returnUrl);
    returnUrl = null;
    // Usually the page's own URL has no ?listing=. If it does (e.g. a redirect to
    // /my-listing?listing=<id> after saving), fall through and drop it so a refresh
    // doesn't reopen the popup.
  }
  if (!router || !route || route.query[LISTING_QUERY_KEY] == null) return;
  const query = { ...route.query };
  delete query[LISTING_QUERY_KEY];
  router.replace({ query }).catch(() => {});
}

/** The share link for a listing (e.g. for a "Copy link" button). */
export function getListingShareUrl(id) {
  const path = router
    ? router.resolve({ path: LISTING_SHARE_PATH, query: { [LISTING_QUERY_KEY]: String(id) } }).href
    : `${LISTING_SHARE_PATH}?${LISTING_QUERY_KEY}=${encodeURIComponent(id)}`;
  return typeof window !== 'undefined' ? `${window.location.origin}${path}` : path;
}

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
  setListingQuery(id);
}

/** Click handler for <a href="/property-details/:id"> — ctrl/middle click still opens a new tab. */
export function openPropertyDetailsFromClick(event, id, options = {}) {
  if (!isPlainLeftClick(event)) return;
  event?.preventDefault();
  openPropertyDetails(id, options);
}

/**
 * @param {{ syncUrl?: boolean }} [opts] syncUrl=false leaves ?listing= alone (used when
 *   the page itself changed — the new URL decides whether the popup reopens).
 */
export function closePropertyDetails({ syncUrl = true } = {}) {
  if (!isOpen.value) return;
  isOpen.value = false;
  if (syncUrl) {
    clearListingQuery();
  } else {
    // The page changed through the router, which already wrote the new page's URL;
    // don't restore the old one over it.
    returnUrl = null;
  }
  const { onClose } = callbacks;
  callbacks = {};
  if (typeof onClose === 'function') onClose();
}

/** Keep the popup in step with ?listing= (shared link, refresh, browser back/forward). */
export function syncPropertyDetailsWithUrl(queryValue) {
  const raw = Array.isArray(queryValue) ? queryValue[0] : queryValue;
  const id = Number(raw);
  if (raw != null && raw !== '' && Number.isFinite(id) && id > 0) {
    if (!isOpen.value || String(listingId.value) !== String(id)) openPropertyDetails(id);
  } else if (isOpen.value) {
    closePropertyDetails({ syncUrl: false });
  }
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
