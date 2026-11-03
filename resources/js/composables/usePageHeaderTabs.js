import { ref, shallowRef } from 'vue';

/**
 * Lets a page show its own section tabs in the top navbar (desktop layout).
 * The page keeps owning the state: it publishes the tab list and the active id,
 * and the navbar calls back into the page when a tab is picked.
 *
 * Tab shape: { id, label, count? }
 */
const tabs = ref([]);
const activeId = ref(null);
const selectHandler = shallowRef(null);
let currentOwner = null;

export function publishPageHeaderTabs(owner, { tabs: list, active, select }) {
  currentOwner = owner;
  tabs.value = list;
  activeId.value = active;
  selectHandler.value = select;
}

export function clearPageHeaderTabs(owner) {
  // The next page may have published already (route change order), keep its tabs.
  if (currentOwner !== owner) return;
  currentOwner = null;
  tabs.value = [];
  activeId.value = null;
  selectHandler.value = null;
}

export function selectPageHeaderTab(id) {
  selectHandler.value?.(id);
}

export function usePageHeaderTabs() {
  return { pageHeaderTabs: tabs, activePageHeaderTab: activeId };
}
