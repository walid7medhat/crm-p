<template>
    <div class="dashboard-main-body">
        <Breadcrumb 
            title="Deal Costs Settings" 
            :breadcrumbs="[
                { name: 'Settings', path: '/settings' },
                { name: 'Deal Costs' }
            ]" 
        />

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <iconify-icon icon="lucide:settings-2" class="me-2"></iconify-icon>
                    Deal Costs Admin Fees
                </h5>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-secondary" @click="resetForm">
                        <iconify-icon icon="lucide:rotate-ccw" class="me-2"></iconify-icon>
                        Reset
                    </button>
                    <button class="btn btn-primary" @click="saveSettings" :disabled="loading">
                        <iconify-icon icon="lucide:save" class="me-2"></iconify-icon>
                        <span v-if="loading">Saving...</span>
                        <span v-else>Save Settings</span>
                    </button>
                </div>
            </div>
            
            <div class="card-body">
                <!-- Loading State -->
                <div v-if="loading" class="text-center py-5">
                    <div class="spinner-border text-primary mb-3" role="status"></div>
                    <p class="text-muted">Loading settings...</p>
                </div>

                <!-- Settings Form -->
                <form v-else @submit.prevent="saveSettings">
                    <div class="row">
                      

                        <!-- Dari Admin Fee -->
                        <div class="col-md-6">
                            <div class="mb-4">
                                <label class="form-label fw-semibold">
                                    Dari Admin Fee
                                    <span class="text-muted small">(Dari)</span>
                                </label>
                                <div class="input-group">
                                    
                                    <input type="number" 
                                           class="form-control form-control-lg" 
                                           v-model="form.dari_admin_fee"
                                           :class="{'is-invalid': errors.dari_admin_fee}"
                                           placeholder="Enter Dari admin fee"
                                           step="0.01"
                                           min="0"
                                           @input="validateInput('dari_admin_fee')">
                                    <span class="input-group-text">AED</span>
                                </div>
                                <div class="invalid-feedback" v-if="errors.dari_admin_fee">
                                    {{ errors.dari_admin_fee[0] }}
                                </div>
                                <div class="form-text">
                                    <iconify-icon icon="lucide:info" class="me-1"></iconify-icon>
                                    Admin fee applied to Dari type deals
                                </div>
                            </div>
                        </div>

                        <!-- ADGM Admin Fee -->
                        <div class="col-md-6">
                            <div class="mb-4">
                                <label class="form-label fw-semibold">
                                    ADGM Admin Fee
                                    <span class="text-muted small">(ADGM)</span>
                                </label>
                                <div class="input-group">
                                   
                                    <input type="number" 
                                           class="form-control form-control-lg" 
                                           v-model="form.adgm_admin_fee"
                                           :class="{'is-invalid': errors.adgm_admin_fee}"
                                           placeholder="Enter ADGM admin fee"
                                           step="0.01"
                                           min="0"
                                           @input="validateInput('adgm_admin_fee')">
                                    <span class="input-group-text">AED</span>
                                </div>
                                <div class="invalid-feedback" v-if="errors.adgm_admin_fee">
                                    {{ errors.adgm_admin_fee[0] }}
                                </div>
                                <div class="form-text">
                                    <iconify-icon icon="lucide:info" class="me-1"></iconify-icon>
                                    Admin fee applied to ADGM type deals
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-4">
                                <label class="form-label fw-semibold">
                                    Agency Fee
                                </label>
                                <div class="input-group">
                                   
                                    <input type="number" 
                                           class="form-control form-control-lg" 
                                           v-model="form.agency_fee"
                                           :class="{'is-invalid': errors.agency_fee}"
                                           placeholder="Enter ADGM admin fee"
                                           step="0.01"
                                           min="0"
                                           @input="validateInput('agency_fee')">
                                    <span class="input-group-text">%</span>
                                </div>
                                <div class="invalid-feedback" v-if="errors.agency_fee">
                                    {{ errors.agency_fee[0] }}
                                </div>
                                <div class="form-text">
                                    <iconify-icon icon="lucide:info" class="me-1"></iconify-icon>
                                    Agency fee applied 
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-4">
                                <label class="form-label fw-semibold">
                                    Transfer Fees
                                </label>
                                <div class="input-group">
                                   
                                    <input type="number" 
                                           class="form-control form-control-lg" 
                                           v-model="form.transfer_fee"
                                           :class="{'is-invalid': errors.transfer_fee}"
                                           placeholder="Enter ADGM admin fee"
                                           step="0.01"
                                           min="0"
                                           @input="validateInput('transfer_fee')">
                                    <span class="input-group-text">%</span>
                                </div>
                                <div class="invalid-feedback" v-if="errors.transfer_fee">
                                    {{ errors.transfer_fee[0] }}
                                </div>
                                <div class="form-text">
                                    <iconify-icon icon="lucide:info" class="me-1"></iconify-icon>
                                    Transfer fee applied 
                                </div>
                            </div>
                        </div>
                      
                    </div>
                </form>
            </div>
        </div>

        <!-- Audit Log / History -->
        <div class="card mt-4" v-if="settingsDetails.length > 0">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <iconify-icon icon="lucide:clock" class="me-2"></iconify-icon>
                    Update History
                </h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Setting</th>
                                <th>Value</th>
                                <th>Updated By</th>
                                <th>Updated At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="setting in settingsDetails" :key="setting.id">
                                <td>
                                    <span class="badge bg-info">
                                        {{ formatKey(setting.key) }}
                                    </span>
                                </td>
                                <td>{{ formatCurrency(setting.value) }}</td>
                                <td>{{ setting.updated_by || 'System' }}</td>
                                <td>{{ formatDate(setting.updated_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { API_ENDPOINTS } from '@/config/api';
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue';

export default {
    name: 'DealCostSettings',
    components: {
        Breadcrumb
    },
    data() {
        return {
            loading: false,
            saving: false,
            settingsDetails: [],
            form: {
                dari_admin_fee: 0,
                adgm_admin_fee: 0,
                agency_fee:0,
                transfer_fee:0,
            },
            errors: {},
            originalForm: {}
        };
    },
    computed: {
        totalFees() {
            return (parseFloat(this.form.dari_admin_fee) || 0) + 
                   (parseFloat(this.form.adgm_admin_fee) || 0);
        },
        lastUpdated() {
            if (this.settingsDetails.length > 0) {
                const dates = this.settingsDetails.map(s => new Date(s.updated_at));
                return new Date(Math.max(...dates));
            }
            return null;
        }
    },
    mounted() {
        this.fetchSettings();
    },
    methods: {
        async fetchSettings() {
            try {
                this.loading = true;
                const token = localStorage.getItem('token');
                
                const response = await fetch(API_ENDPOINTS.DEAL_COST_SETTINGS, {
                    method: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Content-Type': 'application/json'
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    console.log(data);
                    if (data.status && data.data) {
                        const settings = data.data.settings || {};
                        this.settingsDetails = data.data.details || [];
                        
                        // تحديث الفورم بالقيم الموجودة
                        this.form.dari_admin_fee = parseFloat(settings.dari_admin_fee) || 0;
                        this.form.adgm_admin_fee = parseFloat(settings.adgm_admin_fee) || 0;
                        this.form.agency_fee = parseFloat(settings.agency_fee) || 0;
                        this.form.transfer_fee = parseFloat(settings.transfer_fee) || 0;
                        
                        // حفظ نسخة أصلية للمقارنة
                        this.originalForm = { ...this.form };
                        
                        this.errors = {};
                    }
                } else {
                    const errorData = await response.json();
                    throw new Error(errorData.message || 'Failed to fetch settings');
                }
            } catch (error) {
                console.error('Error fetching settings:', error);
                this.$showNotification('Error loading settings: ' + error.message, 'error');
            } finally {
                this.loading = false;
            }
        },

        async saveSettings() {
            if (!this.$hasPermission('settings-update')) {
                this.$showNotification('You do not have permission to update settings', 'error');
                return;
            }

            if (JSON.stringify(this.form) === JSON.stringify(this.originalForm)) {
                this.$showNotification('No changes to save', 'info');
                return;
            }

            try {
                this.saving = true;
                this.errors = {};
                
                const token = localStorage.getItem('token');
                
                const payload = {
                    dari_admin_fee: parseFloat(this.form.dari_admin_fee) || 0,
                    adgm_admin_fee: parseFloat(this.form.adgm_admin_fee) || 0,
                    agency_fee: parseFloat(this.form.agency_fee) || 0,
                    transfer_fee: parseFloat(this.form.transfer_fee) || 0,
                };
                
                const response = await fetch(API_ENDPOINTS.DEAL_COST_SETTINGS_UPDATE, {
                    method: 'PUT',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                const data = await response.json();

                if (response.ok) {
                    this.$showNotification('Settings updated successfully!', 'success');
                    
                    // تحديث البيانات
                    if (data.data) {
                        this.settingsDetails = data.data;
                    }
                    
                    // تحديث النسخة الأصلية
                    this.originalForm = { ...this.form };
                    
                    // إعادة تحميل البيانات للتأكد
                    await this.fetchSettings();
                } else {
                    if (response.status === 422 && data.errors) {
                        this.errors = data.errors;
                        const errorMessage = this.formatValidationErrors(data.errors);
                        throw new Error(`Validation failed: ${errorMessage}`);
                    }
                    throw new Error(data.message || 'Failed to update settings');
                }
            } catch (error) {
                console.error('Error saving settings:', error);
                this.$showNotification('Error saving settings: ' + error.message, 'error');
            } finally {
                this.saving = false;
            }
        },

        resetForm() {
            if (this.originalForm) {
                this.form = { ...this.originalForm };
                this.errors = {};
                this.$showNotification('Form reset to original values', 'info');
            }
        },

        validateInput(field) {
            // تنظيف الأخطاء عند تعديل الحقل
            if (this.errors[field]) {
                this.errors[field] = null;
            }
            
            // التحقق من القيمة
            const value = parseFloat(this.form[field]);
            if (!isNaN(value) && value < 0) {
                this.form[field] = 0;
            }
        },

        formatValidationErrors(errors) {
            if (typeof errors === 'object') {
                return Object.values(errors).flat().join(', ');
            }
            return errors || 'Please check the form for errors';
        },

        formatCurrency(value) {
            if (value === null || value === undefined) return 'AED 0.00';
            return new Intl.NumberFormat('en-AE', {
                style: 'currency',
                currency: 'AED',
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(value);
        },

        formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        },

        formatKey(key) {
            if (!key) return '';
            return key
                .replace(/_/g, ' ')
                .replace(/\b\w/g, l => l.toUpperCase());
        },

        $hasPermission(permission) {
            try {
                const userData = localStorage.getItem('user');
                if (!userData) return false;
                
                const user = JSON.parse(userData);
                const permissions = user.permissions || [];
                const roles = user.roles || [];
                
                if (roles.includes('admin')) return true;
                return permissions.includes(permission);
            } catch (error) {
                return false;
            }
        }
    }
};
</script>

<style scoped>
.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border: 1px solid rgba(0, 0, 0, 0.125);
}

.form-control-lg {
    font-size: 1.1rem;
}

.bg-light {
    background-color: #f8f9fa !important;
}

.table-sm td {
    vertical-align: middle;
}

.invalid-feedback {
    display: block;
}

.input-group-text {
    background-color: #f8f9fa;
}

.alert-info {
    background-color: #e7f3ff;
    border-color: #b8d4f0;
}

.badge {
    font-size: 0.85rem;
}

/* تحسين المظهر للـ mobile */
@media (max-width: 768px) {
    .card-header {
        flex-direction: column;
        gap: 10px;
        align-items: stretch !important;
    }
    
    .card-header .d-flex {
        flex-wrap: wrap;
        justify-content: center;
    }
    
    .col-md-4 .bg-light {
        margin-bottom: 10px;
    }
}
</style>