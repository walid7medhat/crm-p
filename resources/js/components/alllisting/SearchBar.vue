<template>
  <ListingsSearchBar
    :initial-filters="initialFilters"
    :result-count="resultCount"
    @filters-changed="$emit('filters-changed', $event)"
  />
</template>

<script>
import ListingsSearchBar from "@/pages/listings/SearchBar.vue";

export default {
  name: "AllListingSearchBar",
  components: { ListingsSearchBar },
  emits: ["filters-changed"],
  props: {
    initialFilters: {
      type: Object,
      default: null,
    },
    resultCount: {
      type: Number,
      default: null,
    },
  },
};
</script>
