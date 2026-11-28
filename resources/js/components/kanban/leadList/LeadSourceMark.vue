<template>
    <span v-if="mark && existingClient" class="lead-source-mark-referral" :title="referralTitle">
        <span class="lead-source-mark lead-source-mark--icon" :style="{ background: mark.bg }">
            <iconify-icon :icon="mark.icon" width="16" height="16" />
        </span>
        <span
            v-if="referrerMark"
            class="lead-source-mark"
            :class="referrerMark.badge ? 'lead-source-mark--badge' : 'lead-source-mark--icon'"
            :style="referrerMark.bg ? { background: referrerMark.bg } : null"
        >
            <iconify-icon v-if="referrerMark.icon" :icon="referrerMark.icon" width="14" height="14" />
            <span v-else>{{ referrerMark.badge }}</span>
        </span>
    </span>
    <span
        v-else-if="mark"
        class="lead-source-mark"
        :class="mark.badge ? 'lead-source-mark--badge' : 'lead-source-mark--icon'"
        :style="mark.bg ? { background: mark.bg } : null"
        :title="mark.title"
    >
        <iconify-icon v-if="mark.icon" :icon="mark.icon" width="16" height="16" />
        <span v-else>{{ mark.badge }}</span>
    </span>
</template>

<script setup>
import { computed } from 'vue'
import { leadSourceMark, isExistingClientReferral } from './leadSourceMark.js'

const props = defineProps({
    source: { type: String, default: '' },
    /** Optional lead: referral from an existing client shows one person + the referrer's name. */
    lead: { type: Object, default: null },
})

const existingClient = computed(() => isExistingClientReferral(props.lead))
const referrerName = computed(() => String(props.lead?.source_client_name || '').trim())

const mark = computed(() => leadSourceMark(props.source, {
    existingClient: existingClient.value,
    referrer: referrerName.value,
}))

/** Icon of the existing client's own lead source, shown under the referral icon. */
const referrerMark = computed(() => (
    existingClient.value ? leadSourceMark(props.lead?.referrer_lead_source) : null
))

const referralTitle = computed(() => {
    const parts = [mark.value?.title]
    if (referrerMark.value) parts.push(`Client source: ${referrerMark.value.title}`)
    return parts.filter(Boolean).join(' · ')
})
</script>

<style scoped>
.lead-source-mark {
    width: 20px;
    height: 20px;
    border-radius: 5px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    overflow: hidden;
    line-height: 1;
    color: #fff;
}

.lead-source-mark--icon:not([style]) {
    background: transparent;
    width: 20px;
    height: 20px;
}

.lead-source-mark--badge {
    font-size: 8px;
    font-weight: 800;
    letter-spacing: -0.2px;
}

.lead-source-mark :deep(svg) {
    width: 16px;
    height: 16px;
}

/* Referral icon with the client's source icon overlapping it (avatar-group style).
   Width: 20 + 20 - 7 = 33px. */
.lead-source-mark-referral {
    display: inline-flex;
    flex-direction: row;
    align-items: center;
    flex-shrink: 0;
}

.lead-source-mark-referral > .lead-source-mark {
    position: relative;
    z-index: 1;
}

.lead-source-mark-referral > .lead-source-mark + .lead-source-mark {
    z-index: 2;
    margin-left: -7px;
    box-shadow: 0 0 0 1.5px #fff;
}

/* Brand logos (Facebook, Meta, WhatsApp…) have no colour box: white so they cover cleanly. */
.lead-source-mark-referral > .lead-source-mark + .lead-source-mark--icon:not([style]) {
    background: #fff;
}

.lead-source-mark-referral > .lead-source-mark + .lead-source-mark :deep(svg) {
    width: 14px;
    height: 14px;
}

.lead-source-mark-referral > .lead-source-mark--badge {
    font-size: 7px;
}
</style>
