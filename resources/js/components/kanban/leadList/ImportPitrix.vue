<template>
  <div class="import-leads-container mt-3">
    <div class="container py-4 ">
      <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10">
                    <!-- ===================== GLOBAL QUEUE STATUS ===================== -->
          <div v-if="queue.active" class="card shadow-sm border-0 rounded-4 mb-4">
            <div class="card-body p-3">

              <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                  <div class="spinner-border spinner-border-sm text-primary"></div>
                  <strong class="small">Bitrix24 Sync Running...</strong>
                </div>

                <span class="badge bg-primary">
                  {{ queue.status }}
                </span>
              </div>

              <!-- Progress -->
              <div class="progress mb-2" style="height: 8px;">
                <div
                  class="progress-bar"
                  :style="{ width: queue.progress + '%' }"
                ></div>
              </div>

              <div class="d-flex justify-content-between small text-muted">
                <div>
                  Processed: <strong>{{ queue.processed }}</strong>
                  / {{ queue.total || '...' }}
                </div>

                <div class="d-flex gap-2">
                  <span class="text-success">New: {{ queue.new }}</span>
                  <span class="text-secondary">Existing: {{ queue.existing }}</span>
                  <span class="text-danger">Errors: {{ queue.errors }}</span>
                </div>
              </div>

              <!-- Optional current message -->
              <div v-if="queue.message" class="mt-2 small text-muted">
                {{ queue.message }}
              </div>

            </div>
          </div>
         

          <!-- Main Card -->
          <div class="card shadow-lg border-0 rounded-4 overflow-hidden p-3">
            <!-- Card Header -->
            <div class="card-header bg-gradient-dark text-white py-3 px-4 border-0">
              <div class="d-flex align-items-center gap-2">
                <div class="header-icon">
                  <i class="ri-file-excel-2-line fs-4"></i>
                </div>
                <div>
                  <h5 class="fw-bold mb-0 text-white">Import Leads</h5>
                  <small class="mb-0 opacity-75">Upload your lead data in bulk</small>
                </div>
              </div>
            </div>

            <!-- Card Body -->
            <div class="card-body p-4">
              
              <!-- File Upload Area -->
              <div class="mb-3">
                <label class="form-label fw-semibold mb-1 small" style="color: #1a2a6c;">
                  <i class="ri-attachment-2 me-1"></i>
                  Excel / CSV File
                </label>
                
                <!-- Custom File Upload -->
                <div 
                  class="file-upload-area"
                  :class="{ 'has-file': file, 'drag-over': isDragOver }"
                  @dragover.prevent="isDragOver = true"
                  @dragleave.prevent="isDragOver = false"
                  @drop.prevent="handleDrop"
                  @click="triggerFileInput"
                >
                  <input
                    ref="fileInput"
                    type="file"
                    class="d-none"
                    @change="handleFile"
                    accept=".xlsx,.xls,.csv"
                  />
                  
                  <div v-if="!file" class="text-center py-4">
                    <i class="ri-upload-cloud-2-line upload-icon mb-2"></i>
                    <p class="mb-1 small fw-semibold">Click or drag file to upload</p>
                    <small class="text-muted" style="font-size: 11px;">Supports .xlsx, .xls, .csv</small>
                  </div>
                  
                  <div v-else class="file-preview py-3">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                      <div class="d-flex align-items-center gap-2">
                        <div class="file-icon-wrapper">
                          <i class="ri-file-excel-2-line file-icon"></i>
                        </div>
                        <div class="text-start">
                          <p class="fw-semibold mb-0 small">{{ file.name }}</p>
                          <small class="text-muted" style="font-size: 10px;">
                            {{ formatFileSize(file.size) }}
                          </small>
                        </div>
                      </div>
                      <button 
                        type="button" 
                        class="btn btn-sm btn-outline-danger rounded-pill"
                        style="padding: 4px 12px; font-size: 12px;"
                        @click.stop="removeFile"
                      >
                        <i class="ri-close-line me-1"></i>
                        Remove
                      </button>
                    </div>
                  </div>
                </div>
                
                <div class="form-text mt-1" style="font-size: 11px;">
                  <i class="ri-information-line me-1"></i>
                  First row should contain column headers
                </div>
              </div>

              <!-- Row Range Section -->
              <div class="mb-3">
                <label class="form-label fw-semibold mb-2 small" style="color: #1a2a6c;">
                  <i class="ri-table-line me-1"></i>
                  Row Range
                </label>
                
                <div class="row g-2">
                  <div class="col-md-6">
                    <div class="range-input-wrapper">
                      <label class="form-label small text-muted mb-1" style="font-size: 11px;">Start Row</label>
                      <div class="input-group">
                        <span class="input-group-text bg-light border-end-0" style="padding: 4px 8px;">
                          <i class="ri-arrow-right-up-line" style="font-size: 12px;"></i>
                        </span>
                        <input
                          type="number"
                          class="form-control border-start-0 ps-0"
                          style="font-size: 13px; padding: 6px 8px;"
                          v-model="start"
                          min="1"
                          :disabled="loading"
                        />
                      </div>
                    </div>
                  </div>

                  <div class="col-md-6">
                    <div class="range-input-wrapper">
                      <label class="form-label small text-muted mb-1" style="font-size: 11px;">End Row</label>
                      <div class="input-group">
                        <span class="input-group-text bg-light border-end-0" style="padding: 4px 8px;">
                          <i class="ri-arrow-right-down-line" style="font-size: 12px;"></i>
                        </span>
                        <input
                          type="number"
                          class="form-control border-start-0 ps-0"
                          style="font-size: 13px; padding: 6px 8px;"
                          v-model="end"
                          min="1"
                          :disabled="loading"
                        />
                      </div>
                    </div>
                  </div>
                </div>
                
                <div class="form-text mt-1" style="font-size: 11px;">
                  <i class="ri-lightbulb-line me-1"></i>
                  Leave empty to import all rows
                </div>
              </div>

              <!-- Info Cards -->
              <div class="row g-2 mb-3">
                <div class="col-md-4">
                  <div class="info-card">
                    <i class="ri-database-2-line info-icon"></i>
                    <div>
                      <div class="info-label">Max per batch</div>
                      <div class="info-value">1,000 rows</div>
                    </div>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="info-card">
                    <i class="ri-file-copy-line info-icon"></i>
                    <div>
                      <div class="info-label">Supported formats</div>
                      <div class="info-value">.xlsx, .csv</div>
                    </div>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="info-card">
                    <i class="ri-time-line info-icon"></i>
                    <div>
                      <div class="info-label">Est. time</div>
                      <div class="info-value">&lt; 30 seconds</div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Action Buttons -->
              <div class="d-flex gap-2">
                <button
                  class="btn btn-primary flex-grow-1"
                  style="padding: 8px 12px; font-size: 13px;"
                  :disabled="loading || !file"
                  @click="uploadFile"
                >
                  <span v-if="loading" class="spinner-border spinner-border-sm me-2"></span>
                  <i v-else class="ri-upload-2-line me-2"></i>
                  {{ loading ? 'Importing...' : 'Start Import' }}
                </button>
                
                <button
                  v-if="file"
                  class="btn btn-outline-secondary px-3"
                  style="padding: 8px 12px;"
                  :disabled="loading"
                  @click="resetForm"
                >
                  <i class="ri-refresh-line"></i>
                </button>
              </div>

              <!-- Success Alert -->
              <transition name="slide-fade">
                <div v-if="success" class="alert alert-success mt-3 border-0 rounded-3 py-2 px-3" style="font-size: 13px;">
                  <div class="d-flex align-items-center gap-2">
                    <i class="ri-checkbox-circle-fill fs-6"></i>
                    <div>
                      <strong>Success!</strong> Leads have been imported successfully.
                    </div>
                  </div>
                </div>
              </transition>

              <!-- Error Alert -->
              <transition name="slide-fade">
                <div v-if="error" class="alert alert-danger mt-3 border-0 rounded-3 py-2 px-3" style="font-size: 13px;">
                  <div class="d-flex align-items-center gap-2">
                    <i class="ri-error-warning-line fs-6"></i>
                    <div>
                      <strong>Import Failed</strong><br>
                      {{ error }}
                    </div>
                  </div>
                </div>
              </transition>

            </div>
          </div>
          
          <!-- ====================== Fetch single Bitrix24 lead Card ====================== -->
          <div class="card shadow-lg border-0 rounded-4 overflow-hidden p-3 mt-4">
            <div class="card-header bg-gradient-dark text-white py-3 px-4 border-0">
              <div class="d-flex align-items-center gap-2">
                <div class="header-icon">
                  <i class="ri-search-eye-line fs-4"></i>
                </div>
                <div>
                  <h5 class="fw-bold mb-0 text-white">Fetch a single Bitrix24 lead</h5>
                  <small class="mb-0 opacity-75">Pull one lead by Bitrix24 ID — useful for testing or one-off imports</small>
                </div>
              </div>
            </div>

            <div class="card-body p-4">
              <label class="form-label fw-semibold mb-1 small" style="color: #1a2a6c;">
                <i class="ri-hashtag me-1"></i>
                Bitrix24 lead ID
              </label>
              <div class="row g-2 align-items-end">
                <div class="col-md-8">
                  <input
                    type="number"
                    class="form-control"
                    style="font-size: 13px;"
                    v-model="b24.singleId"
                    placeholder="e.g. 1389  (open the lead in Bitrix24 — the ID is in the URL)"
                    :disabled="b24Single.running"
                  />
                </div>
                <div class="col-md-4">
                  <button
                    class="btn btn-primary w-100"
                    style="padding: 8px 12px; font-size: 13px;"
                    :disabled="b24Single.running || !b24.singleId"
                    @click="fetchOneBitrix24"
                  >
                    <span v-if="b24Single.running" class="spinner-border spinner-border-sm me-2"></span>
                    <i v-else class="ri-download-cloud-2-line me-1"></i>
                    {{ b24Single.running ? 'Fetching...' : 'Fetch lead' }}
                  </button>
                </div>
              </div>

              <transition name="slide-fade">
                <div v-if="b24Single.result" class="alert mt-3 border-0 rounded-3 py-2 px-3" :class="b24Single.result.created ? 'alert-success' : 'alert-info'" style="font-size: 13px;">
                  <i class="ri-checkbox-circle-fill me-1"></i>
                  <template v-if="b24Single.result.created">
                    Lead created. Local ID #{{ b24Single.result.lead_id }} (Bitrix24 #{{ b24Single.result.bitrix24_id }}).
                  </template>
                  <template v-else>
                    Lead already existed. Local ID #{{ b24Single.result.lead_id }} (Bitrix24 #{{ b24Single.result.bitrix24_id }}). Timeline and stage refreshed.
                  </template>
                </div>
              </transition>

              <transition name="slide-fade">
                <div v-if="b24Single.error" class="alert alert-danger mt-3 border-0 rounded-3 py-2 px-3" style="font-size: 13px;">
                  <i class="ri-error-warning-line me-1"></i>
                  {{ b24Single.error }}
                </div>
              </transition>
            </div>
          </div>

          <!-- ====================== Bitrix24 Range Sync Card ====================== -->
          <div class="card shadow-lg border-0 rounded-4 overflow-hidden p-3 mt-4">
            <div class="card-header bg-gradient-dark text-white py-3 px-4 border-0">
              <div class="d-flex align-items-center gap-2">
                <div class="header-icon">
                  <i class="ri-cloud-line fs-4"></i>
                </div>
                <div>
                  <h5 class="fw-bold mb-0 text-white">Sync from Bitrix24</h5>
                  <small class="mb-0 opacity-75">Pull a range of leads with comments, activities and timeline</small>
                </div>
              </div>
            </div>

            <div class="card-body p-4">
              <div class="alert alert-info border-0 rounded-3 py-2 px-3 mb-3" style="font-size: 12.5px;">
                <i class="ri-information-line me-1"></i>
                Re-running is safe — previously synced leads are matched by Bitrix24 ID and only their timeline/stage are refreshed (no duplicates).
              </div>

              <!-- Progress -->
              <div v-if="b24.running || b24.processed > 0 || b24.total > 0" class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1 flex-wrap gap-2">
                  <small class="fw-semibold" style="color: #1a2a6c;">
                    Processed {{ b24.processed }}<template v-if="b24.total"> / {{ b24.total }}</template>
                  </small>
                  <div class="d-flex gap-2 align-items-center">
                    <span class="badge rounded-pill" style="background:#10b981; font-size: 10px;">{{ b24.newCount }} new</span>
                    <span class="badge rounded-pill" style="background:#64748b; font-size: 10px;">{{ b24.existingCount }} existed</span>
                    <span v-if="b24.errors.length" class="badge rounded-pill" style="background:#ef4444; font-size: 10px;">{{ b24.errors.length }} error<span v-if="b24.errors.length !== 1">s</span></span>
                  </div>
                </div>
                <div class="progress" style="height: 8px;">
                  <div
                    class="progress-bar"
                    :style="{ width: b24ProgressPct + '%', background: 'linear-gradient(135deg, #1a2a6c 0%, #16215c 100%)' }"
                  ></div>
                </div>
              </div>

              <!-- Range Section -->
              <label class="form-label small fw-semibold mb-2" style="color: #1a2a6c;">
                <i class="ri-table-line me-1"></i>Lead range (in Bitrix24 list order, by ID ascending)
              </label>
              <div class="row g-2 mb-3">
                <div class="col-md-6">
                  <label class="form-label small text-muted mb-1" style="font-size: 11px;">From</label>
                  <div class="input-group">
                    <span class="input-group-text bg-light border-end-0" style="padding: 4px 8px;">
                      <i class="ri-arrow-right-up-line" style="font-size: 12px;"></i>
                    </span>
                    <input
                      type="number"
                      class="form-control border-start-0 ps-0"
                      style="font-size: 13px; padding: 6px 8px;"
                      v-model.number="b24.fromRow"
                      min="1"
                      :disabled="b24.running"
                    />
                  </div>
                </div>
                <div class="col-md-6">
                  <label class="form-label small text-muted mb-1" style="font-size: 11px;">To</label>
                  <div class="input-group">
                    <span class="input-group-text bg-light border-end-0" style="padding: 4px 8px;">
                      <i class="ri-arrow-right-down-line" style="font-size: 12px;"></i>
                    </span>
                    <input
                      type="number"
                      class="form-control border-start-0 ps-0"
                      style="font-size: 13px; padding: 6px 8px;"
                      v-model.number="b24.toRow"
                      min="1"
                      :disabled="b24.running"
                    />
                  </div>
                </div>
              </div>
              <div class="form-text mt-1 mb-2" style="font-size: 11px;">
                <i class="ri-lightbulb-line me-1"></i>
                Leave To empty to sync all leads from "From" onwards.
              </div>

              <!-- Skip already-imported toggle -->
              <div class="form-check mb-3">
                <input
                  id="b24-skip-existing"
                  class="form-check-input"
                  type="checkbox"
                  v-model="b24.skipExisting"
                  :disabled="b24.running"
                />
                <label class="form-check-label small" for="b24-skip-existing" style="font-size: 12px;">
                  Skip leads already imported (faster — does not refresh their timeline)
                </label>
              </div>

              <!-- Run sync -->
              <button
                class="btn btn-primary w-100"
                style="padding: 8px 12px; font-size: 13px;"
                :disabled="b24.running"
                @click="startBitrix24Sync"
              >
                <span v-if="b24.running" class="spinner-border spinner-border-sm me-2"></span>
                <i v-else class="ri-refresh-line me-2"></i>
                {{ b24.running ? 'Syncing...' : 'Start Bitrix24 sync' }}
              </button>

              <!-- Result alerts -->
              <transition name="slide-fade">
                <div v-if="b24.done && !b24.error" class="alert alert-success mt-3 border-0 rounded-3 py-2 px-3" style="font-size: 13px;">
                  <i class="ri-checkbox-circle-fill me-1"></i>
                  Sync complete — {{ b24.newCount }} new, {{ b24.existingCount }} already existed (refreshed), {{ b24.errors.length }} error<span v-if="b24.errors.length !== 1">s</span>.
                </div>
              </transition>

              <transition name="slide-fade">
                <div v-if="b24.error" class="alert alert-danger mt-3 border-0 rounded-3 py-2 px-3" style="font-size: 13px;">
                  <i class="ri-error-warning-line me-1"></i>
                  {{ b24.error }}
                </div>
              </transition>

              <!-- Bitrix24 ID chip groups: new / already existed / problems -->
              <div v-if="b24.newLeads.length || b24.existingLeads.length || b24.errors.length" class="mt-3 b24-id-groups">
                <!-- Newly inserted -->
                <div v-if="b24.newLeads.length" class="b24-id-group">
                  <div class="b24-id-group__head" style="color:#10b981;">
                    <i class="ri-add-circle-line"></i>
                    <span>Inserted ({{ b24.newLeads.length }})</span>
                  </div>
                  <div class="b24-id-chips">
                    <a
                      v-for="e in b24.newLeads"
                      :key="'n'+e.lead_id"
                      class="b24-id-chip b24-id-chip--new"
                      :href="'/leads/' + e.lead_id"
                      target="_blank"
                      :title="'Local lead #' + e.lead_id"
                    >
                      #{{ e.bitrix24_id }}
                    </a>
                  </div>
                </div>

                <!-- Already existed -->
                <div v-if="b24.existingLeads.length" class="b24-id-group">
                  <div class="b24-id-group__head" style="color:#64748b;">
                    <i class="ri-history-line"></i>
                    <span>Already inserted ({{ b24.existingLeads.length }})</span>
                  </div>
                  <div class="b24-id-chips">
                    <a
                      v-for="e in b24.existingLeads"
                      :key="'e'+e.lead_id"
                      class="b24-id-chip b24-id-chip--existing"
                      :href="'/leads/' + e.lead_id"
                      target="_blank"
                      :title="'Local lead #' + e.lead_id"
                    >
                      #{{ e.bitrix24_id }}
                    </a>
                  </div>
                </div>

                <!-- Errored -->
                <div v-if="b24.errors.length" class="b24-id-group">
                  <div class="b24-id-group__head" style="color:#ef4444;">
                    <i class="ri-error-warning-line"></i>
                    <span>Has problem ({{ b24.errors.length }})</span>
                  </div>
                  <div class="b24-id-chips">
                    <span
                      v-for="(e, i) in b24.errors"
                      :key="'err'+i"
                      class="b24-id-chip b24-id-chip--error"
                      :title="e.error"
                    >
                      #{{ e.bitrix24_id ?? '?' }}
                    </span>
                  </div>
                  <details class="mt-2">
                    <summary class="small text-muted" style="font-size: 11px; cursor: pointer;">
                      Show error messages
                    </summary>
                    <ul class="small mt-2 mb-0" style="font-size: 11px;">
                      <li v-for="(e, i) in b24.errors" :key="'msg'+i">
                        Bitrix24 #{{ e.bitrix24_id ?? '?' }}: {{ e.error }}
                      </li>
                    </ul>
                  </details>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, reactive } from 'vue'
import axios from 'axios'

const file = ref(null)
const fileInput = ref(null)
const start = ref(1)
const end = ref(100)
const isDragOver = ref(false)

const loading = ref(false)
const success = ref(false)
const error = ref(null)
const queue = reactive({
  active: false,
  jobId: null,
  status: 'queued',
  progress: 0,
  processed: 0,
  total: 0,
  new: 0,
  existing: 0,
  errors: 0,
  message: ''
})
const b24 = reactive({
  running: false,
  done: false,
  processed: 0,
  total: 0,
  next: 0,
  newCount: 0,
  existingCount: 0,
  newLeads: [],
  existingLeads: [],
  errors: [],
  error: null,
  singleId: '',
  fromRow: 1,
  toRow: 1000,
  skipExisting: false,
})

// Dedup helper — push only entries whose bitrix24_id isn't already in the list.
// Defensive against any pagination edge-case that might surface the same
// Bitrix24 lead twice across batches.
const pushUnique = (list, entries) => {
  const seen = new Set(list.map(e => e.bitrix24_id))
  for (const e of entries) {
    if (e && e.bitrix24_id != null && !seen.has(e.bitrix24_id)) {
      list.push(e)
      seen.add(e.bitrix24_id)
    }
  }
}

// Separate state for the single-fetch card so it doesn't share `running` with
// the range sync — both cards should be usable independently.
const b24Single = reactive({
  running: false,
  result: null,   // { lead_id, bitrix24_id, created } | null
  error: null,
})

const b24ProgressPct = computed(() => {
  if (!b24.total) return b24.running ? 5 : 0
  return Math.min(100, Math.round((b24.processed / b24.total) * 100))
})

const resetBitrix24State = () => {
  b24.done = false
  b24.processed = 0
  b24.total = 0
  b24.next = 0
  b24.newCount = 0
  b24.existingCount = 0
  b24.newLeads = []
  b24.existingLeads = []
  b24.errors = []
  b24.error = null
  // newCount / existingCount also accumulate across iterations; reset both.
}

const startBitrix24Sync = async () => {
  if (b24.running) return

  // 1-indexed inputs from the UI -> 0-indexed Bitrix24 offset.
  const fromRow = Math.max(1, Number(b24.fromRow) || 1)
  const toRow = Number(b24.toRow) > 0 ? Number(b24.toRow) : null
  if (toRow !== null && toRow < fromRow) {
    b24.error = 'To must be >= From'
    return
  }

  const rangeLabel = toRow ? `rows ${fromRow} to ${toRow}` : `from row ${fromRow} onwards`
  if (!window.confirm(`Pull Bitrix24 leads (${rangeLabel})? This will create new local leads — duplicates if you have synced before.`)) {
    return
  }

  resetBitrix24State()
  b24.running = true
  try {
    let cursor = fromRow - 1
    const stopAtCursor = toRow !== null ? toRow : null   // exclusive upper bound on the 0-indexed offset
    while (true) {
      const remaining = stopAtCursor !== null ? (stopAtCursor - cursor) : null
      if (remaining !== null && remaining <= 0) {
        b24.done = true
        break
      }
      const batchSize = remaining !== null ? Math.min(25, remaining) : 25
      const { data } = await axios.post('/api/leads/bitrix24/sync', {
        start: cursor,
        batch_size: batchSize,
        skip_existing: b24.skipExisting,
      })
      const payload = data?.data ?? data
      b24.processed += payload.imported_in_batch || 0
      b24.total = payload.total || b24.total
      b24.newCount += payload.new_count || 0
      b24.existingCount += payload.existing_count || 0
      if (Array.isArray(payload.new_leads) && payload.new_leads.length) {
        pushUnique(b24.newLeads, payload.new_leads)
      }
      if (Array.isArray(payload.existing_leads) && payload.existing_leads.length) {
        pushUnique(b24.existingLeads, payload.existing_leads)
      }
      if (Array.isArray(payload.errors) && payload.errors.length) {
        b24.errors.push(...payload.errors)
      }
      if (payload.done || payload.next === null || payload.next === undefined) {
        b24.done = true
        break
      }
      cursor = payload.next
    }
  } catch (err) {
    b24.error = err.response?.data?.message || err.message || 'Bitrix24 sync failed'
  } finally {
    b24.running = false
  }
}

const fetchOneBitrix24 = async () => {
  if (b24Single.running || !b24.singleId) return
  b24Single.result = null
  b24Single.error = null
  b24Single.running = true
  try {
    const { data } = await axios.post(`/api/leads/bitrix24/fetch/${encodeURIComponent(b24.singleId)}`)
    const payload = data?.data ?? data
    b24Single.result = {
      lead_id: payload.lead_id,
      bitrix24_id: payload.bitrix24_id,
      created: !!payload.created,
    }
  } catch (err) {
    b24Single.error = err.response?.data?.message || err.message || 'Bitrix24 fetch failed'
  } finally {
    b24Single.running = false
  }
}

const handleFile = (e) => {
  const selectedFile = e.target.files[0]
  if (selectedFile) {
    validateAndSetFile(selectedFile)
  }
}

const handleDrop = (e) => {
  isDragOver.value = false
  const droppedFile = e.dataTransfer.files[0]
  if (droppedFile) {
    validateAndSetFile(droppedFile)
  }
}

const validateAndSetFile = (selectedFile) => {
  const validExtensions = ['.xlsx', '.xls', '.csv']
  const fileExtension = '.' + selectedFile.name.split('.').pop().toLowerCase()
  
  if (validExtensions.includes(fileExtension)) {
    file.value = selectedFile
    success.value = false
    error.value = null
  } else {
    error.value = 'Invalid file type. Please upload .xlsx, .xls, or .csv file'
    setTimeout(() => {
      error.value = null
    }, 3000)
  }
}

const triggerFileInput = () => {
  if (!loading.value) {
    fileInput.value.click()
  }
}

const removeFile = () => {
  file.value = null
  if (fileInput.value) {
    fileInput.value.value = ''
  }
  success.value = false
  error.value = null
}

const resetForm = () => {
  removeFile()
  start.value = 1
  end.value = 100
  success.value = false
  error.value = null
}

const formatFileSize = (bytes) => {
  if (!bytes) return ''
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(1024))
  return Math.round(bytes / Math.pow(1024, i)) + ' ' + sizes[i]
}

const uploadFile = async () => {
  if (!file.value) return

  try {
    loading.value = true
    error.value = null
    success.value = false

    const formData = new FormData()
    formData.append('file', file.value)
    formData.append('start', start.value || 1)
    formData.append('end', end.value || '')

    await axios.post('/api/leads/import', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    })

    success.value = true
    
    setTimeout(() => {
      success.value = false
    }, 5000)
    
  } catch (err) {
    error.value = err.response?.data?.message || 'Upload failed. Please try again.'
  } finally {
    loading.value = false
  }
}
const startQueueSync = async () => {
  try {
    queue.active = true
    queue.status = 'starting'

    const { data } = await axios.post('/api/leads/bitrix24/sync-queue', {
      from: b24.fromRow,
      to: b24.toRow,
      skip_existing: b24.skipExisting,
    })

    queue.jobId = data.data.job_id
    queue.status = 'running'

    startPollingQueue()

  } catch (err) {
    queue.active = false
    error.value = err.response?.data?.message
  }
}
let queueInterval = null

const startPollingQueue = () => {
  queueInterval = setInterval(async () => {
    if (!queue.jobId) return

    const { data } = await axios.get(`/api/bitrix24/jobs/${queue.jobId}`)
    const job = data.data

    queue.status = job.status
    queue.progress = job.progress
    queue.processed = job.processed
    queue.total = job.total
    queue.new = job.new_count
    queue.existing = job.existing_count
    queue.errors = job.error_count
    queue.message = job.message

    if (job.status === 'done' || job.status === 'failed') {
      clearInterval(queueInterval)
      queue.active = false
    }

  }, 2000)
}
</script>

<style scoped>
.import-leads-container {
  /* background: linear-gradient(135deg, #f0f2f8 0%, #e8ecf5 100%); */
  min-height: 100vh;
}

/* Icon Styles */
.import-icon-wrapper {
  width: 60px;
  height: 60px;
  background: linear-gradient(135deg, #1a2a6c 0%, #16215c 100%);
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 5px 20px rgba(26, 42, 108, 0.25);
}

.import-icon {
  font-size: 28px;
  color: white;
}

/* Card Header Gradient - Dark Blue */
.bg-gradient-dark {
  background: linear-gradient(135deg, #1a2a6c 0%, #0f1a4a 100%);
}

.header-icon {
  width: 40px;
  height: 40px;
  background: rgba(255, 255, 255, 0.15);
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* File Upload Area */
.file-upload-area {
  border: 2px dashed #cbd5e1;
  border-radius: 10px;
  background: #f8fafc;
  cursor: pointer;
  transition: all 0.3s ease;
  min-height: 140px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.file-upload-area:hover {
  border-color: #1a2a6c;
  background: #f0f2ff;
}

.file-upload-area.drag-over {
  border-color: #1a2a6c;
  background: #e8ebff;
  transform: scale(0.98);
}

.file-upload-area.has-file {
  border-color: #10b981;
  background: #f0fdf4;
}

.upload-icon {
  font-size: 36px;
  color: #94a3b8;
  transition: all 0.3s ease;
}

.file-upload-area:hover .upload-icon {
  color: #1a2a6c;
  transform: translateY(-3px);
}

.file-preview {
  width: 100%;
  padding: 12px;
}

.file-icon-wrapper {
  width: 40px;
  height: 40px;
  background: #dbeafe;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.file-icon {
  font-size: 22px;
  color: #1a2a6c;
}

/* Range Inputs */
.range-input-wrapper .input-group-text {
  background: #f8fafc;
  color: #64748b;
  border-right: none;
  padding: 4px 8px;
}

.range-input-wrapper input {
  border-left: none;
  font-size: 13px;
  padding: 6px 8px;
}

.range-input-wrapper input:focus {
  border-color: #cbd5e1;
  box-shadow: none;
}

/* Info Cards */
.info-card {
  background: #f8fafc;
  border-radius: 8px;
  padding: 8px 10px;
  display: flex;
  align-items: center;
  gap: 8px;
  transition: all 0.3s ease;
  border: 1px solid #e2e8f0;
}

.info-card:hover {
  background: #f1f5f9;
  transform: translateY(-1px);
  border-color: #1a2a6c;
}

.info-icon {
  font-size: 18px;
  color: #1a2a6c;
}

.info-label {
  font-size: 9px;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  color: #64748b;
  margin-bottom: 2px;
}

.info-value {
  font-size: 11px;
  font-weight: 600;
  color: #1e293b;
}

/* Alert Animations */
.slide-fade-enter-active {
  transition: all 0.3s ease-out;
}

.slide-fade-leave-active {
  transition: all 0.3s cubic-bezier(1, 0.5, 0.8, 1);
}

.slide-fade-enter-from,
.slide-fade-leave-to {
  transform: translateY(-10px);
  opacity: 0;
}

/* Button Styles - Dark Blue */
.btn-primary {
  background: linear-gradient(135deg, #1a2a6c 0%, #16215c 100%);
  border: none;
  padding: 8px 12px;
  font-weight: 600;
  font-size: 13px;
  transition: all 0.3s ease;
}

.btn-primary:hover:not(:disabled) {
  background: linear-gradient(135deg, #1e3078 0%, #1a2668 100%);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(26, 42, 108, 0.3);
}

.btn-primary:active:not(:disabled) {
  transform: translateY(0);
}

.btn-outline-secondary {
  padding: 8px 12px;
  border-color: #cbd5e1;
  color: #64748b;
  font-size: 13px;
}

.btn-outline-secondary:hover {
  border-color: #1a2a6c;
  color: #1a2a6c;
  background: #f0f2ff;
}

/* Form Controls */
.form-control:focus {
  border-color: #1a2a6c;
  box-shadow: 0 0 0 0.15rem rgba(26, 42, 108, 0.1);
}

.form-label {
  font-size: 12px;
  margin-bottom: 4px;
}

/* Responsive */
@media (max-width: 768px) {
  .import-icon-wrapper {
    width: 50px;
    height: 50px;
  }
  
  .import-icon {
    font-size: 24px;
  }
  
  .file-upload-area {
    min-height: 120px;
  }
  
  .upload-icon {
    font-size: 30px;
  }
  
  .info-card {
    padding: 6px 8px;
  }
  
  .info-icon {
    font-size: 16px;
  }
  
  .info-value {
    font-size: 10px;
  }
  
  .info-label {
    font-size: 8px;
  }
}

/* Loading State */
.btn-primary:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

/* Bitrix24 ID chip groups */
.b24-id-groups {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.b24-id-group {
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 10px 12px;
  background: #f8fafc;
}

.b24-id-group__head {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 11.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  margin-bottom: 6px;
}

.b24-id-group__head i {
  font-size: 14px;
}

.b24-id-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  max-height: 160px;
  overflow-y: auto;
}

.b24-id-chip {
  display: inline-flex;
  align-items: center;
  padding: 2px 8px;
  font-size: 11px;
  font-weight: 600;
  border-radius: 999px;
  border: 1px solid transparent;
  text-decoration: none;
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  white-space: nowrap;
  cursor: default;
  transition: transform 0.1s ease, box-shadow 0.1s ease;
}

a.b24-id-chip {
  cursor: pointer;
}

a.b24-id-chip:hover {
  transform: translateY(-1px);
  box-shadow: 0 2px 6px rgba(15, 23, 42, 0.12);
}

.b24-id-chip--new {
  background: #ecfdf5;
  color: #047857;
  border-color: #6ee7b7;
}

.b24-id-chip--existing {
  background: #f1f5f9;
  color: #475569;
  border-color: #cbd5e1;
}

.b24-id-chip--error {
  background: #fef2f2;
  color: #b91c1c;
  border-color: #fca5a5;
  cursor: help;
}

/* Custom Scrollbar */
::-webkit-scrollbar {
  width: 6px;
}

::-webkit-scrollbar-track {
  background: #f1f1f1;
}

::-webkit-scrollbar-thumb {
  background: #1a2a6c;
  border-radius: 3px;
}

::-webkit-scrollbar-thumb:hover {
  background: #0f1a4a;
}
</style>