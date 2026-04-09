<template>
  <ListingsSearchBar
    ref="innerRef"
    :initial-filters="initialFilters"
    :result-count="resultCount"
    :show-status-tabs="showStatusTabs"
    :active-status="activeStatus"
    @filters-changed="$emit('filters-changed', $event)"
    @status-changed="$emit('status-changed', $event)"
  />
</template>

<script setup>
import { ref } from "vue";
import ListingsSearchBar from "@/pages/listings/SearchBar.vue";

defineOptions({ name: "AllListingSearchBar" });

defineProps({
  initialFilters: {
    type: Object,
    default: null,
  },
  resultCount: {
    type: Number,
    default: null,
  },
  showStatusTabs: {
    type: Boolean,
    default: false,
  },
  activeStatus: {
    type: String,
    default: "all",
  },
});

defineEmits(["filters-changed", "status-changed"]);

const innerRef = ref(null);

/** Parent refs (e.g. AllLsting agent filter) expect the inner SearchBar API. */
defineExpose(
  new Proxy(
    {},
    {
      get(_target, prop, receiver) {
        const inst = innerRef.value;
        if (!inst) return undefined;
        return Reflect.get(inst, prop, receiver);
      },
      set(_target, prop, value, receiver) {
        const inst = innerRef.value;
        if (!inst) return false;
        return Reflect.set(inst, prop, value, receiver);
      },
      has(_target, prop) {
        const inst = innerRef.value;
        return inst ? Reflect.has(inst, prop) : false;
      },
    }
  )
);
</script>
