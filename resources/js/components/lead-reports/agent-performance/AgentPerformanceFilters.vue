<template>
  <section class="ap-filters">
    <div class="ap-filters__head">
      <span class="ap-filters__title">Filters</span>
      <button type="button" class="ap-link" @click="$emit('reset')">Reset filters</button>
    </div>
    <div class="ap-filters__grid">
      <div class="ap-field">
        <label for="ap-from-date">From</label>
        <input id="ap-from-date" type="date" :value="filters.from_date" @change="update('from_date', $event.target.value)" />
      </div>
      <div class="ap-field">
        <label for="ap-to-date">To</label>
        <input id="ap-to-date" type="date" :value="filters.to_date" @change="update('to_date', $event.target.value)" />
      </div>
      <div class="ap-field">
        <label for="ap-agent">Agent</label>
        <select id="ap-agent" :value="filters.agent_id" @change="update('agent_id', $event.target.value)">
          <option value="">All agents</option>
          <option v-for="a in agentOptions" :key="a.agent_id" :value="a.agent_id">
            {{ a.agent_name }}
          </option>
        </select>
      </div>
      <div class="ap-field">
        <label for="ap-location">Location</label>
        <select id="ap-location" :value="filters.area_id" @change="update('area_id', $event.target.value)">
          <option value="">All locations</option>
          <option v-for="area in areaOptions" :key="area.id" :value="area.id">
            {{ area.name }}
          </option>
        </select>
      </div>
    </div>
  </section>
</template>

<script setup>
const props = defineProps({
  filters: { type: Object, required: true },
  agentOptions: { type: Array, default: () => [] },
  areaOptions: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:filters', 'change', 'reset'])

function update(key, value) {
  emit('update:filters', { ...props.filters, [key]: value })
  emit('change')
}
</script>
