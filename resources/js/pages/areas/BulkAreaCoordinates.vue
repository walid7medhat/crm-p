<template>
    <div class="bulk-coordinates-page">
        <div class="page-card">
            <div class="card-header">
                <div>
                    <h3>
                        <iconify-icon icon="lucide:map-pin" />
                        Bulk Area Coordinates Manager
                    </h3>
                    <p>Add or edit coordinates for multiple areas</p>
                </div>
                <button class="btn-add-row" @click="addNewRow">
                    <iconify-icon icon="lucide:plus" />
                    Add Area
                </button>
            </div>

            <div class="card-body">
                <!-- Table Header -->
                <div class="table-header">
                    <div class="col-area">Area Name</div>
                    <div class="col-lat">Latitude</div>
                    <div class="col-lng">Longitude</div>
                    <div class="col-actions">Actions</div>
                </div>

                <!-- Table Rows -->
                <div class="table-body">
                    <div 
                        v-for="(row, index) in rows" 
                        :key="row.id"
                        class="table-row"
                        :class="{ 'new-row': row.isNew, 'has-changes': hasChanges(row) }"
                    >
                        <!-- Area Select -->
                        <div class="col-area">
                            <v-select
                                v-model="row.area"
                                :options="getAvailableAreas(row)"
                                :reduce="area => area"
                                label="name"
                                placeholder="Select area..."
                                class="area-select"
                                append-to-body
                                @update:model-value="onAreaSelected(row, $event)"
                            >
                                <template #option="{ name, city }">
                                    <div class="area-option">
                                        <span class="area-name">{{ name }}</span>
                                        <span class="area-city">{{ city || '—' }}</span>
                                    </div>
                                </template>
                                <template #selected-option="{ name, city }">
                                    <div class="area-selected">
                                        <iconify-icon icon="lucide:map-pin" />
                                        <span>{{ name }}</span>
                                    </div>
                                </template>
                            </v-select>
                        </div>

                        <!-- Latitude -->
                        <div class="col-lat">
                            <div class="coord-input-wrapper">
                                <iconify-icon icon="lucide:arrow-up" />
                                <input 
                                    type="number" 
                                    step="any"
                                    v-model="row.latitude" 
                                    placeholder="e.g., 25.2048"
                                    class="coord-input"
                                    :class="{ 'has-value': row.latitude, 'changed': isLatChanged(row) }"
                                >
                            </div>
                        </div>

                        <!-- Longitude -->
                        <div class="col-lng">
                            <div class="coord-input-wrapper">
                                <iconify-icon icon="lucide:arrow-right" />
                                <input 
                                    type="number" 
                                    step="any"
                                    v-model="row.longitude" 
                                    placeholder="e.g., 55.2708"
                                    class="coord-input"
                                    :class="{ 'has-value': row.longitude, 'changed': isLngChanged(row) }"
                                >
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="col-actions">
                            <!--<button -->
                            <!--    class="icon-btn save" -->
                            <!--    :disabled="!hasChanges(row) || saving[row.id]"-->
                            <!--    @click="saveSingleRow(row)"-->
                            <!--    :title="'Save ' + row.area?.name">-->
                         
                            <!--    <iconify-icon v-if="saving[row.id]" icon="lucide:loader-2" class="spin" />-->
                            <!--    <iconify-icon v-else icon="lucide:save" />-->
                            <!--</button>-->
                            <button 
                                class="icon-btn delete" 
                                @click="removeRow(index)"
                                :title="'Remove row'"
                            >
                                <iconify-icon icon="lucide:trash-2" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="rows.length === 0" class="empty-state">
                    <iconify-icon icon="lucide:map-off" />
                    <p>No areas added yet</p>
                    <button class="btn-add-first" @click="addNewRow">
                        <iconify-icon icon="lucide:plus" />
                        Add your first area
                    </button>
                </div>

                <!-- Bulk Actions -->
                <div v-if="rows.length > 0" class="bulk-actions">
                    <div class="changes-count" v-if="changedRowsCount > 0">
                        <iconify-icon icon="lucide:git-pull-request" />
                        {{ changedRowsCount }} pending change{{ changedRowsCount !== 1 ? 's' : '' }}
                    </div>
                    <button class="btn-save-all" @click="saveAllRows" :disabled="savingAll || changedRowsCount === 0">
                        <iconify-icon v-if="savingAll" icon="lucide:loader-2" class="spin" />
                        <iconify-icon v-else icon="lucide:save-all" />
                        Save All Changes
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import vSelect from 'vue-select'
import 'vue-select/dist/vue-select.css'
import api from '@/plugins/axios'

// State
const areas = ref([])
const rows = ref([])
const saving = ref({})
const savingAll = ref(false)
let nextId = 1

// Computed
const changedRowsCount = computed(() => {
    return rows.value.filter(row => row.area && hasChanges(row)).length
})

// Load all areas
const loadAreas = async () => {
    try {
        const response = await api.get('/listings/areas')
        areas.value = response.data.data || []
    } catch (error) {
        console.error('Error loading areas:', error)
        if (window.$showNotification) {
            window.$showNotification('Failed to load areas', 'error')
        }
    }
}

// Get available areas (not already selected in other rows)
const getAvailableAreas = (currentRow) => {
    const selectedAreaIds = rows.value
        .filter(row => row !== currentRow && row.area)
        .map(row => row.area.id)
    
    return areas.value.filter(area => !selectedAreaIds.includes(area.id))
}

// Check if latitude changed
const isLatChanged = (row) => {
    return row.latitude !== row.originalLatitude
}

// Check if longitude changed
const isLngChanged = (row) => {
    return row.longitude !== row.originalLongitude
}

// Check if row has changes
const hasChanges = (row) => {
    if (!row.area) return false
    return row.latitude !== row.originalLatitude ||
           row.longitude !== row.originalLongitude
}

// Add new row
const addNewRow = () => {
    rows.value.push({
        id: nextId++,
        area: null,
        latitude: null,
        longitude: null,
        originalLatitude: null,
        originalLongitude: null,
        isNew: true
    })
}

// On area selected
const onAreaSelected = (row, selectedArea) => {
    if (selectedArea) {
        row.area = selectedArea
        row.latitude = selectedArea.latitude || null
        row.longitude = selectedArea.longitude || null
        row.originalLatitude = selectedArea.latitude || null
        row.originalLongitude = selectedArea.longitude || null
        row.isNew = false
    }
}

// Save single row
const saveSingleRow = async (row) => {
    if (!row.area) {
        if (window.$showNotification) window.$showNotification('Please select an area first', 'warning')
        return
    }
    
    saving.value[row.id] = true
    try {
        const payload = {
            id: row.area.id,
            latitude: row.latitude || null,
            longitude: row.longitude || null
        }
        
        await api.put(`/listings/areas/${row.area.id}`, payload)
        
        // Update original values
        row.originalLatitude = row.latitude
        row.originalLongitude = row.longitude
        
        // Update the area in the main list
        const areaInList = areas.value.find(a => a.id === row.area.id)
        if (areaInList) {
            areaInList.latitude = row.latitude
            areaInList.longitude = row.longitude
        }
        
        if (window.$showNotification) {
            window.$showNotification(`Saved coordinates for ${row.area.name}`, 'success')
        }
    } catch (error) {
        console.error('Error saving:', error)
        if (window.$showNotification) {
            window.$showNotification(error.response?.data?.message || 'Failed to save coordinates', 'error')
        }
    } finally {
        saving.value[row.id] = false
    }
}

const showNotification = (message, type = 'success') => {
    if (window.$swal) {
        window.$swal({
            title: type === 'success' ? 'Success!' : type === 'error' ? 'Error!' : 'Info',
            text: message,
            icon: type,
            timer: 3000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        })
    } else if (window.$showNotification) {
        window.$showNotification(message, type)
    } else {
        // Fallback alert
        alert(message)
    }
}

// Save all rows using Bulk Update API
// Save all rows using Bulk Update API
const saveAllRows = async () => {
    const rowsToSave = rows.value.filter(row => row.area && hasChanges(row))
    
    if (rowsToSave.length === 0) {
        showNotification('No changes to save', 'info')
        return
    }
    
    savingAll.value = true
    
    try {
        // Prepare bulk data
        const bulkData = {
            areas: rowsToSave.map(row => ({
                id: row.area.id,
                latitude: row.latitude === '' ? null : row.latitude,
                longitude: row.longitude === '' ? null : row.longitude
            }))
        }
        
        // Single API call for all updates
        const response = await api.post('/listings/areas/coordinates/bulk', bulkData)
        
        if (response.data.success) {
            const { updated, failed, total_updated, total_failed } = response.data.data
            
            // Update successful rows
            rowsToSave.forEach(row => {
                const wasUpdated = updated.some(u => u.id === row.area.id)
                if (wasUpdated) {
                    row.originalLatitude = row.latitude
                    row.originalLongitude = row.longitude
                    
                    // Update areas list
                    const areaInList = areas.value.find(a => a.id === row.area.id)
                    if (areaInList) {
                        areaInList.latitude = row.latitude
                        areaInList.longitude = row.longitude
                    }
                }
            })
            
            // Show notification based on result
            if (total_failed === 0) {
                const message = total_updated === 1 
                    ? `✅ "${rowsToSave[0].area.name}" coordinates saved successfully!`
                    : `✅ Successfully saved coordinates for ${total_updated} areas`
                showNotification(message, 'success')
            } else {
                showNotification(`⚠️ Saved ${total_updated} area(s), ${total_failed} failed`, 'warning')
                console.error('Failed updates:', failed)
            }
        }
    } catch (error) {
        console.error('Bulk update error:', error)
        const errorMessage = error.response?.data?.message || 'Failed to save coordinates'
        showNotification(errorMessage, 'error')
    } finally {
        savingAll.value = false
    }
}
// Remove row
const removeRow = (index) => {
    rows.value.splice(index, 1)
}

// Load data on mount
onMounted(() => {
    loadAreas()
})
</script>

<style scoped>
.bulk-coordinates-page {
    padding: 24px;
    background: #f1f5f9;
    min-height: 100vh;
}

.page-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    overflow: hidden;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid #e2e8f0;
    background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
}

.card-header h3 {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0 0 4px 0;
    font-size: 20px !important;
    font-weight: 600;
    color: #1e293b;
}

.card-header p {
    margin: 0;
    color: #64748b;
    font-size: 13px;
}

.btn-add-row {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: #f59e0b;
    border: none;
    border-radius: 12px;
    color: white;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-add-row:hover {
    background: #d97706;
    transform: translateY(-1px);
}

.card-body {
    padding: 24px;
}

/* Table Styles */
.table-header {
    display: grid;
    grid-template-columns: 1fr 0.8fr 0.8fr 80px;
    gap: 16px;
    padding: 12px 16px;
    background: #f8fafc;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 12px;
}

.table-body {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.table-row {
    display: grid;
    grid-template-columns: 1fr 0.8fr 0.8fr 80px;
    gap: 16px;
    align-items: center;
    padding: 12px 16px;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    transition: all 0.2s;
}

.table-row:hover {
    border-color: #cbd5e1;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.table-row.new-row {
    background: #fffbeb;
    border-color: #fde68a;
}

.table-row.has-changes {
    border-left: 3px solid #f59e0b;
}

/* Column Styles */
.col-area {
    min-width: 0;
}

.col-lat,
.col-lng {
    min-width: 0;
}

.col-actions {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
}

/* V-Select Styles */
:deep(.area-select .vs__dropdown-toggle) {
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 6px 10px;
    background: #fafbfc;
}

:deep(.area-select .vs__selected) {
    margin: 0;
    padding: 0;
}

.area-selected {
    display: flex;
    align-items: center;
    gap: 8px;
}

.area-selected iconify-icon {
    color: #f59e0b;
    font-size: 14px;
}

.area-option {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 4px 0;
}

.area-name {
    font-weight: 500;
    color: #1e293b;
    font-size: 13px;
}

.area-city {
    font-size: 11px;
    color: #94a3b8;
}

/* Coordinates Input */
.coord-input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.coord-input-wrapper iconify-icon {
    position: absolute;
    left: 10px;
    color: #94a3b8;
    font-size: 14px;
}

.coord-input {
    width: 100%;
    padding: 10px 10px 10px 32px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    font-size: 13px;
    transition: all 0.2s;
    background: #fafbfc;
}

.coord-input:focus {
    outline: none;
    border-color: #f59e0b;
    box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1);
    background: white;
}

.coord-input.has-value {
    border-color: #22c55e;
    background: #f0fdf4;
}

.coord-input.changed {
    border-color: #f59e0b;
    background: #fffbeb;
}

/* Action Buttons */
.icon-btn {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #e2e8f0;
    background: white;
    cursor: pointer;
    transition: all 0.2s;
}

.icon-btn.save {
    color: #f59e0b;
}

.icon-btn.save:hover:not(:disabled) {
    background: #fef3c7;
    border-color: #fde68a;
    transform: scale(1.02);
}

.icon-btn.delete {
    color: #ef4444;
}

.icon-btn.delete:hover {
    background: #fee2e2;
    border-color: #fecaca;
}

.icon-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Bulk Actions */
.bulk-actions {
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.changes-count {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: #f59e0b;
    background: #fffbeb;
    padding: 6px 12px;
    border-radius: 20px;
}

.btn-save-all {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 24px;
    background: #1e293b;
    border: none;
    border-radius: 12px;
    color: white;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-save-all:hover:not(:disabled) {
    background: #0f172a;
    transform: translateY(-1px);
}

.btn-save-all:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #94a3b8;
}

.empty-state iconify-icon {
    font-size: 48px;
    margin-bottom: 12px;
    opacity: 0.5;
}

.empty-state p {
    margin-bottom: 16px;
}

.btn-add-first {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: #f59e0b;
    border: none;
    border-radius: 12px;
    color: white;
    font-weight: 500;
    cursor: pointer;
}

/* Animations */
.spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Custom Toast Notifications */
.custom-toast {
    position: fixed;
    bottom: 30px;
    right: 30px;
    min-width: 320px;
    max-width: 450px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
    z-index: 10000;
    transform: translateX(450px);
    transition: transform 0.3s ease;
    overflow: hidden;
}

.custom-toast.show {
    transform: translateX(0);
}

.toast-content {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
}

.toast-icon {
    font-size: 20px;
    flex-shrink: 0;
}

.toast-success .toast-icon {
    color: #22c55e;
}

.toast-error .toast-icon {
    color: #ef4444;
}

.toast-warning .toast-icon {
    color: #f59e0b;
}

.toast-info .toast-icon {
    color: #3b82f6;
}

.toast-content span {
    font-size: 14px;
    color: #1e293b;
    line-height: 1.4;
    flex: 1;
}

.toast-progress {
    height: 3px;
    background: #e2e8f0;
    width: 100%;
    position: relative;
}

.toast-progress::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    height: 100%;
    width: 100%;
    background: #22c55e;
    animation: progress 3s linear forwards;
}

.toast-error .toast-progress::after {
    background: #ef4444;
}

.toast-warning .toast-progress::after {
    background: #f59e0b;
}

.toast-info .toast-progress::after {
    background: #3b82f6;
}

@keyframes progress {
    from {
        width: 100%;
    }
    to {
        width: 0%;
    }
}

/* Responsive */
@media (max-width: 640px) {
    .custom-toast {
        left: 20px;
        right: 20px;
        min-width: auto;
        max-width: none;
        bottom: 20px;
        transform: translateY(100px);
    }
    
    .custom-toast.show {
        transform: translateY(0);
    }
}
</style>