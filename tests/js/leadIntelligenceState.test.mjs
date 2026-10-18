import assert from 'node:assert/strict'
import test from 'node:test'
import { leadIntelligenceViewState } from '../../resources/js/components/kanban/viewLead/leadIntelligenceState.mjs'

test('idle before a scan exists', () => {
  assert.equal(leadIntelligenceViewState({ scan: null, loading: false, error: '' }), 'idle')
})

test('loading while the first request is in flight', () => {
  assert.equal(leadIntelligenceViewState({ scan: null, loading: true, error: '' }), 'loading')
})

test('loading while a scan is queued or processing', () => {
  assert.equal(leadIntelligenceViewState({ scan: { status: 'queued', candidates: [] }, loading: false, error: '' }), 'loading')
  assert.equal(leadIntelligenceViewState({ scan: { status: 'processing', candidates: [] }, loading: false, error: '' }), 'loading')
})

test('failed, partial, empty, and completed stay distinct', () => {
  assert.equal(leadIntelligenceViewState({ scan: null, loading: false, error: 'no' }), 'failed')
  assert.equal(leadIntelligenceViewState({ scan: { status: 'failed', candidates: [] }, loading: false, error: '' }), 'failed')
  assert.equal(leadIntelligenceViewState({ scan: { status: 'partial', candidates: [{ id: 1 }] }, loading: false, error: '' }), 'partial')
  assert.equal(leadIntelligenceViewState({ scan: { status: 'completed', candidates: [] }, loading: false, error: '' }), 'empty')
  assert.equal(leadIntelligenceViewState({ scan: { status: 'completed', candidates: [{ id: 1 }] }, loading: false, error: '' }), 'completed')
})
