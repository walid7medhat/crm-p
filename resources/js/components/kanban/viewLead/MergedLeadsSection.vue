<template>
    <div v-if="rows.length" class="ml-section">
        <div class="ml-title">Merged Leads ({{ rows.length }})</div>
        <button v-for="r in rows" :key="r.id" type="button" class="ml-row" @click="open(r.id)">
            <span class="ml-name">{{ r.lead_name || 'Untitled lead' }}</span>
            <span class="ml-person">
                <img v-if="r.responsible_person?.avatar" :src="r.responsible_person.avatar" alt="" />
                <span v-else class="ml-avatar"><iconify-icon icon="lucide:user" /></span>
                {{ r.responsible_person?.name || '—' }}
            </span>
        </button>
    </div>

    <MergedLeadViewModal v-model="showView" :parent-id="leadId" :merged-id="selectedId" />
</template>

<script setup>
import { ref, watch } from 'vue'
import api from '@/plugins/axios'
import MergedLeadViewModal from './MergedLeadViewModal.vue'

const props = defineProps({ leadId: { type: Number, required: true } })

const rows = ref([])
const showView = ref(false)
const selectedId = ref(null)

function open(id) {
    selectedId.value = id
    showView.value = true
}

async function load() {
    rows.value = []
    if (!props.leadId) return
    try {
        const res = await api.get(`/leads/${props.leadId}/merged-leads`)
        rows.value = res.data?.data || []
    } catch {
        rows.value = []
    }
}

watch(() => props.leadId, load, { immediate: true })
defineExpose({ reload: load })
</script>

<style scoped>
.ml-section { margin: 0 0 8px; border: 1px solid #efe4f3; border-radius: 10px; background: #fff; overflow: hidden; }
.ml-title { padding: 6px 10px; background: linear-gradient(90deg, #f7eef9 0%, #fff 68%);
    border-bottom: 1px solid #f3eaf6; font-size: 11px; font-weight: 700; letter-spacing: .06em;
    text-transform: uppercase; color: var(--color-primary); }
.ml-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; width: 100%;
    padding: 8px 10px; border: 0; border-top: 1px solid #f1f5f9; background: #fff; text-align: left; cursor: pointer; }
.ml-row:first-of-type { border-top: 0; }
.ml-row:hover { background: #f8fafc; }
.ml-name { font-size: 12px; font-weight: 700; color: #0B0736; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ml-person { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: #475569; flex-shrink: 0; }
.ml-person img, .ml-avatar { width: 22px; height: 22px; border-radius: 50%; object-fit: cover; }
.ml-avatar { display: inline-grid; place-items: center; background: #f1f5f9; color: #94a3b8; font-size: 12px; }
</style>