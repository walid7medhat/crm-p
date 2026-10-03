import { reactive } from 'vue'
import api from '@/plugins/axios'

const ARABIC_RE = /[؀-ۿݐ-ݿࢠ-ࣿﭐ-﷿ﹰ-﻿]/

export const hasArabic = (text) => ARABIC_RE.test(String(text || ''))

/**
 * Per-comment translation state for comment lists.
 * State is keyed by comment id: { loading, text, visible, error }.
 */
export function useCommentTranslation() {
    const translations = reactive({})

    const stateFor = (comment) => translations[comment.id] || null

    const toggleTranslation = async (comment) => {
        const key = comment.id
        const current = translations[key]

        // Already translated → just show/hide it
        if (current?.text) {
            current.visible = !current.visible
            return
        }
        if (current?.loading) return

        // First request, or retry after an error
        translations[key] = { loading: true, text: '', visible: true, error: '' }
        try {
            const { data } = await api.post('/translate', { text: comment.comment, target: 'en' })
            translations[key].text = data?.translated || ''
            if (!translations[key].text) translations[key].error = 'No translation returned.'
        } catch (error) {
            translations[key].error = error.response?.data?.message || 'Translation failed. Please try again.'
        } finally {
            translations[key].loading = false
        }
    }

    return { translations, stateFor, toggleTranslation, hasArabic }
}
