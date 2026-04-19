<template>
  <div class="import-leads-container mt-3">
    <div class="container py-4 ">
      <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10">
          
         

          <!-- Main Card -->
          <div class="card shadow-lg border-0 rounded-4 overflow-hidden p-3">
            <!-- Card Header -->
            <div class="card-header bg-gradient-dark text-white py-3 px-4 border-0">
              <div class="d-flex align-items-center gap-2">
                <div class="header-icon">
                  <i class="ri-file-excel-2-line fs-4"></i>
                </div>
                <div>
                  <h6 class="ui-h-mini fw-bold mb-0 text-white">Import Leads</h6>
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
          
          <!-- Help Section -->
          <div class="text-center mt-3">
            <small class="text-muted" style="font-size: 11px;">
              <i class="ri-question-line me-1"></i>
              Need help? Check our 
              <a href="#" class="text-decoration-none" style="color: #1a2a6c;">documentation</a>
            </small>
          </div>
          
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import axios from 'axios'

const file = ref(null)
const fileInput = ref(null)
const start = ref(1)
const end = ref(100)
const isDragOver = ref(false)

const loading = ref(false)
const success = ref(false)
const error = ref(null)

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