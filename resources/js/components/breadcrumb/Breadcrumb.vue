<template>

</template>

<script setup>
defineProps({
    title: {
        type: String,
        required: true
    },
    breadcrumbs: {
        type: Array,
        default: () => []
    }
})
</script>