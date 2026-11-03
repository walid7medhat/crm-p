<template>
    <div class="dashboard-main-body">
        <Breadcrumb :title="owner ? owner.name : 'Owner Details'" :breadcrumbs="[
            { name: 'Owners', path: '/owners' },
            { name: owner ? owner.name : 'Loading...' }
        ]" />

        <div class="card" v-if="owner">
            <div class="card-body">
                <!-- Main Info Tabs -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="nav nav-pills owner-tabs mb-3" id="ownerTab" role="tablist">
                            <button class="nav-link active" id="personal-tab" data-bs-toggle="tab"
                                    data-bs-target="#personal" type="button" role="tab">
                                <i class="ri-user-3-line"></i>
                                Personal Info
                            </button>
                            <button class="nav-link" id="documents-tab" data-bs-toggle="tab"
                                    data-bs-target="#documents" type="button" role="tab">
                                <i class="ri-file-text-line"></i>
                                Documents
                                <span v-if="hasDocuments" class="badge">{{ getDocumentCount() }}</span>
                            </button>
                            <!-- إضافة تاب Properties -->
                            <button class="nav-link" id="properties-tab" data-bs-toggle="tab"
                                    data-bs-target="#properties" type="button" role="tab">
                                <i class="ri-home-4-line"></i>
                                Properties
                                <span v-if="totalProperties > 0" class="badge">{{ totalProperties }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="tab-content" id="ownerTabContent">
                    <!-- Personal Info Tab -->
                    <div class="tab-pane fade show active" id="personal" role="tabpanel">
                        <article class="owner-id-card">
                            <header class="owner-id-card__head">
                                <div class="owner-id-card__identity">
                                    <p class="owner-id-card__kicker">Owner</p>
                                    <h2 class="owner-id-card__name">{{ fullName }}</h2>
                                </div>
                                <span v-if="owner.id" class="owner-id-card__id">ID {{ owner.id }}</span>
                            </header>
                            <div class="owner-id-grid">
                                <div class="info-item">
                                    <span class="info-label">Salutation</span>
                                    <span class="info-value">{{ owner.salutation || '—' }}</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">First name</span>
                                    <span class="info-value">{{ owner.first_name || '—' }}</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Last name</span>
                                    <span class="info-value">{{ owner.last_name || '—' }}</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Email</span>
                                    <a :href="`mailto:${owner.email}`" class="info-value">{{ owner.email || '—' }}</a>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Phone</span>
                                    <a :href="`tel:${owner.phone_number}`" class="info-value">{{ owner.phone_number || '—' }}</a>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Nationality</span>
                                    <span class="info-value">{{ owner.nationality || '—' }}</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Residency</span>
                                    <span class="info-value">
                                        <span v-if="owner.residency_status === 'resident'" class="owner-pill owner-pill--ok">Resident</span>
                                        <span v-else-if="owner.residency_status === 'non_resident'" class="owner-pill owner-pill--warn">Non resident</span>
                                        <span v-else>—</span>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">{{ getLocationLabel() }}</span>
                                    <span class="info-value">{{ owner.location?.name || '—' }}</span>
                                </div>
                            </div>
                            <p v-if="owner.notes" class="owner-id-notes">{{ owner.notes }}</p>
                        </article>
                    </div>
                    
                    <!-- Documents Tab -->
                    <div class="tab-pane fade" id="documents" role="tabpanel">
                        <div class="row">
                            <div class="col-12 mb-4">
                                <div class="card">
                                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0">
                                            <iconify-icon icon="lucide:folder" class="me-2 owner-doc-head__icon"></iconify-icon>
                                            Owner Documents
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="owner-doc-list" v-if="hasDocuments">
                                            <div
                                                v-for="(doc, index) in getDocumentList()"
                                                :key="index"
                                                class="owner-doc-row"
                                            >
                                                <iconify-icon :icon="getDocumentIcon(doc.type)" class="owner-doc-row__icon"></iconify-icon>
                                                <span class="owner-doc-row__title">{{ doc.title }}</span>
                                                <button type="button" class="owner-doc-row__btn" @click="viewDocument(doc.url)">View</button>
                                                <button type="button" class="owner-doc-row__btn" @click="downloadDocument(doc.url, doc.title)">Download</button>
                                            </div>
                                        </div>
                                        <div v-else class="owner-empty">No documents uploaded.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Properties Tab -->
                    <div class="tab-pane fade" id="properties" role="tabpanel">
                        <div class="row">
                            <div class="col-12">
                                <!-- Properties Grid -->
                                <div v-if="propertiesLoading" class="text-center py-5">
                                    <div class="spinner-border text-primary" role="status"></div>
                                    <p class="mt-2">Loading properties...</p>
                                </div>

                                <div v-else-if="filteredProperties.length === 0" class="text-center py-5">
                                    <div class="mb-3">
                                        <i class="ri-home-4-line display-1 text-muted"></i>
                                    </div>
                                    <h5 class="text-muted">No Properties Found</h5>
                                    <p class="text-muted">This owner doesn't have any properties yet.</p>
                                </div>

                                <div v-else class="row g-3">
                                    <div
                                        v-for="property in filteredProperties"
                                        :key="property.id"
                                        :class="isMobileViewport ? 'col-12' : 'col-12 col-md-6 col-xl-4 col-xxl-4 custom-1600'"
                                    >
                                        <MobileListingCard
                                            v-if="isMobileViewport"
                                            :property="property"
                                            fallback-image="/assets/images/a.jpeg"
                                        />
                                        <PropertyListingCard
                                            v-else
                                            :property="property"
                                            @open="(event) => openPropertyDetailsFromClick(event, property.id)"
                                        />
                                    </div>
                                </div>

                                <!-- Properties Count -->
                                <div v-if="filteredProperties.length > 0" class="mt-4 text-end">
                                    <small class="text-muted">
                                        Showing {{ filteredProperties.length }} of {{ totalProperties }} properties
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Loading State -->
        <div v-else-if="loading" class="card">
            <div class="card-body text-center py-5">
                <div class="spinner-border text-primary mb-3" role="status"></div>
                <p>Loading owner information...</p>
            </div>
        </div>

        <!-- Error State -->
        <div v-else class="card">
            <div class="card-body text-center py-5">
                <iconify-icon icon="lucide:user-x" class="text-muted mb-3" width="48"></iconify-icon>
                <h5>Owner Not Found</h5>
                <p class="text-muted">The owner you're looking for doesn't exist.</p>
                <button class="btn btn-primary" @click="$router.push('/owners')">
                    Back to Owners List
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import { API_ENDPOINTS } from '@/config/api';
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue';
import userPlaceholder from '@/assets/images/avatar/avatar1.png';
import { openPropertyDetailsFromClick } from '@/composables/usePropertyDetailsModal';
import { useMobileNavigation } from '@/composables/useMobileNavigation.js';
import MobileListingCard from '@/components/listings/MobileListingCard.vue';
import PropertyListingCard from '@/components/listings/PropertyListingCard.vue';

export default {
    name: 'ViewOwner',
    components: {
        Breadcrumb,
        MobileListingCard,
        PropertyListingCard,
    },
    setup() {
        const { isMobileViewport } = useMobileNavigation();
        return { isMobileViewport };
    },
    data() {
        return {
            
            loading: false,
            owner: null,
            error: null,
            userPlaceholder,
            properties: [],
            propertiesLoading: false,
            sortField: 'created_at',
            sortDirection: 'desc',
            propertyStatusFilter: 'all',
            propertyTypeFilter: 'all',
            showSoldProperties: false,
            propertyIcon : '/assets/icons/property-icon.svg',
            bedIcon : '/assets/icons/bedroom-icon.svg',
            bathIcon : '/assets/icons/bathroom-icon.svg',
            sqftIcon : '/assets/icons/area-size.svg',
            
        };
    },
    computed: {
        fullName() {
            if (!this.owner) return '';
            return `${this.owner.first_name || ''} ${this.owner.last_name || ''}`.trim() || 'Unnamed Owner';
        },
        hasDocuments() {
            if (!this.owner) return false;
            const hasStandard = this.owner.id_front_path || this.owner.id_back_path ||
                   this.owner.visa_copy_path || this.owner.passport_copy_path ||
                   this.owner.id_front_url || this.owner.id_back_url ||
                   this.owner.visa_copy_url || this.owner.passport_copy_url;
            const hasAdditional = this.owner.additional_documents && this.owner.additional_documents.length > 0;
            return hasStandard || hasAdditional;
        },
        totalProperties() {
            return this.properties && Array.isArray(this.properties) ? this.properties.length : 0;
        },
        filteredProperties() {
            if (!this.properties || !Array.isArray(this.properties)) return [];
            
            let filtered = this.properties;
            
            // تصفية حسب الحالة
            if (this.propertyStatusFilter !== 'all') {
                filtered = filtered.filter(p => {
                    switch (this.propertyStatusFilter) {
                        case 'active':
                            return p.is_active && !p.is_archived && p.status !== 'converted' && p.status !== 'draft';
                        case 'draft':
                            return p.status === 'draft';
                        case 'sold':
                            return p.status === 'converted';
                        default:
                            return true;
                    }
                });
            }
            
            // تصفية حسب النوع
            if (this.propertyTypeFilter !== 'all') {
                filtered = filtered.filter(p => {
                    if (this.propertyTypeFilter === 'sale') {
                        return p.listing_status === 'Sale' || p.listing_type === 'sale';
                    } else if (this.propertyTypeFilter === 'rent') {
                        return p.listing_status === 'Rent' || p.listing_type === 'rent';
                    }
                    return true;
                });
            }
            
            // إخفاء المباعة حسب التفضيلات
            if (!this.showSoldProperties) {
                filtered = filtered.filter(p => p.status !== 'converted');
            }
            
            // الترتيب
            return [...filtered].sort((a, b) => {
                let aValue = a[this.sortField] || '';
                let bValue = b[this.sortField] || '';
                
                if (this.sortField === 'created_at') {
                    aValue = new Date(aValue).getTime();
                    bValue = new Date(bValue).getTime();
                } else {
                    aValue = String(aValue).toLowerCase();
                    bValue = String(bValue).toLowerCase();
                }
                
                if (this.sortDirection === 'asc') {
                    return aValue > bValue ? 1 : -1;
                } else {
                    return aValue < bValue ? 1 : -1;
                }
            });
        }
    },
    mounted() {
        console.log('🚀 ViewOwner component mounted');
        console.log('🔍 Owner ID from route:', this.$route.params.id);
        
        this.fetchOwner();
    },
    methods: {
        openPropertyDetailsFromClick,
        async fetchOwner() {
            console.log('🔄 [1] Starting fetchOwner...');
            try {
                this.loading = true;
                const token = localStorage.getItem('token');
                const ownerId = this.$route.params.id;
                
                console.log('🔑 [2] Token exists:', !!token);
                console.log('👤 [3] Owner ID:', ownerId);
                
                if (!token) {
                    console.error('❌ No token found');
                    this.showNotification('Please login first', 'error');
                    this.$router.push('/login');
                    return;
                }
                
                console.log('🌐 [4] Calling API:', `/listings/owners/${ownerId}`);
                
                const response = await fetch(API_ENDPOINTS.OWNER_BY_ID(ownerId), {
                    method: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    console.log('✅ [5] API Response:', data);
                    
                    this.owner = data.data || data;
                    console.log('👤 [6] Owner data loaded:', this.owner);
                    
                    // جلب البروبيرتيز الخاصة بهذا المالك فقط
                    console.log('🏠 [7] Fetching properties for this owner...');
                    await this.fetchOwnerProperties();
                    
                } else {
                    console.error('❌ [8] Failed to fetch owner:', response.status);
                    throw new Error(`Failed to fetch owner: ${response.status}`);
                }
                
            } catch (error) {
                console.error('❌ [9] Error in fetchOwner:', error);
                this.error = error.message;
                this.showNotification('Failed to load owner data', 'error');
            } finally {
                this.loading = false;
                console.log('🏁 [10] fetchOwner completed');
            }
        },

        async fetchOwnerProperties() {
            console.log('🔄 [11] Starting fetchOwnerProperties...');
            try {
                this.propertiesLoading = true;
                const token = localStorage.getItem('token');
                const ownerId = this.$route.params.id;
                
                console.log('📝 [12] Parameters:', { 
                    token: !!token, 
                    ownerId 
                });
                
                // ✅ **استخدام الـ endpoint الرئيسي**
                const endpoint = `/api/listings/properties?owner_id=${ownerId}`;
                console.log('🌐 [13] Using endpoint:', endpoint);
                
                const response = await fetch(endpoint, {
                    method: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    }
                });

                console.log('📡 [14] Response status:', response.status);

                if (response.ok) {
                    const data = await response.json();
                    console.log('✅ [15] API Response:', data);
                    
                    // ✅ **معالجة جميع الأشكال المحتملة للرد**
                    if (data.data && Array.isArray(data.data)) {
                        // الحالة 1: {data: [], ...}
                        this.properties = data.data;
                        console.log(`✅ [16] Loaded ${this.properties.length} properties from data.data`);
                    } else if (Array.isArray(data)) {
                        // الحالة 2: []
                        this.properties = data;
                        console.log(`✅ [16] Loaded ${this.properties.length} properties (direct array)`);
                    } else if (data.status && data.data && Array.isArray(data.data)) {
                        // الحالة 3: {status: true, data: [], ...}
                        this.properties = data.data;
                        console.log(`✅ [16] Loaded ${this.properties.length} properties from status.data`);
                    } else if (data.results && Array.isArray(data.results)) {
                        // الحالة 4: {results: [], ...}
                        this.properties = data.results;
                        console.log(`✅ [16] Loaded ${this.properties.length} properties from results`);
                    } else if (data.listings && Array.isArray(data.listings)) {
                        // الحالة 5: {listings: [], ...}
                        this.properties = data.listings;
                        console.log(`✅ [16] Loaded ${this.properties.length} properties from listings`);
                    } else {
                        console.warn('⚠️ [17] No properties found or unexpected structure:', data);
                        this.properties = [];
                    }
                    
                } else if (response.status === 403) {
                    console.error('❌ [18] Access forbidden (403)');
                    await this.fetchAllPropertiesAndFilter(ownerId, token);
                    
                } else {
                    console.error('❌ [19] Failed with status:', response.status);
                    const errorText = await response.text();
                    console.error('📄 [20] Error response:', errorText);
                }
                
            } catch (error) {
                console.error('❌ [21] Error in fetchOwnerProperties:', error);
                this.properties = [];
                this.showNotification('Failed to load owner properties', 'error');
            } finally {
                this.propertiesLoading = false;
                console.log('✅ [22] propertiesLoading set to false');
                console.log('📊 [23] Final properties count:', this.properties.length);
            }
        },

        async fetchAllPropertiesAndFilter(ownerId, token) {
            try {
                console.log('🔄 [24] Fetching all properties to filter...');
                
                const response = await fetch(`/api/listings/properties?per_page=100`, {
                    method: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Accept': 'application/json'
                    }
                });
                
                if (response.ok) {
                    const data = await response.json();
                    
                    let allProperties = [];
                    if (data.data && Array.isArray(data.data)) {
                        allProperties = data.data;
                    } else if (Array.isArray(data)) {
                        allProperties = data;
                    }
                    
                    this.properties = allProperties.filter(property => {
                        return (
                            (property.owner_id && property.owner_id.toString() === ownerId.toString()) ||
                            (property.owner && property.owner.id && property.owner.id.toString() === ownerId.toString()) ||
                            (property.owner_id === parseInt(ownerId))
                        );
                    });
                    
                    console.log(`✅ [25] Filtered ${this.properties.length} properties for owner ${ownerId}`);
                    
                } else {
                    console.error('❌ [26] Failed to fetch all properties:', response.status);
                }
                
            } catch (error) {
                console.error('❌ [27] Error in fetchAllPropertiesAndFilter:', error);
            }
        },

        // وظائف مساعدة للعرض
        getPropertyImage(property) {
            if (!property) return this.getDefaultPropertyImage();
            
            if (property.main_image) return property.main_image;
            if (property.thumbnail) return property.thumbnail;
            if (property.gallery_images && Array.isArray(property.gallery_images) && property.gallery_images.length > 0) {
                const firstImage = property.gallery_images[0];
                return firstImage.image_url || firstImage.url || firstImage;
            }
            if (property.images && Array.isArray(property.images) && property.images.length > 0) {
                const firstImage = property.images[0];
                return firstImage.url || firstImage;
            }
            
            return this.getDefaultPropertyImage();
        },

        getDefaultPropertyImage() {
            return 'https://via.placeholder.com/400x300/e9ecef/6c757d?text=Property';
        },
  getLocation(property)  {
      let loc = property.area || property.location;
      if (!loc) return 'Location not specified';
    
      const parts = loc.split(',').map(p => p.trim());
      parts.shift(); 
      return parts.join(', ');
    },
     getAreaUnit(property)  {
      return property.size_sqft ? 'Sqft' : 'Sqm';
    },
     getAgentName (agent) {
      if (!agent) return 'Unknown Agent';
      if (agent.name) return agent.name;
      if (agent.first_name && agent.last_name) return `${agent.first_name} ${agent.last_name}`;
      if (agent.first_name) return agent.first_name;
      return 'Unknown Agent';
    },
        getPropertyLocation(property) {
            if (!property) return 'Location not specified';
            
            if (property.area) return property.area;
            if (property.area_name) return property.area_name;
            if (property.location) {
                let loc = property.location;
                const parts = loc.split(',').map(p => p.trim());
                parts.shift(); 
                return parts.join(', ');
            }
            
            return 'Location not specified';
        },

        getPropertyType(property) {
            if (!property) return 'Property';
            
            if (property.property_type ) return property.property_type;
            if (property.property_type_name) return property.property_type_name;
            if (property.type) return property.type;
            
            return 'Property';
        },

        formatPrice(price) {
            if (!price || price === 0) return '0';
            return new Intl.NumberFormat().format(price);
        },

        handlePropertyImageLoad(event) {
            // يمكن إضافة تأثيرات تحميل الصور إذا لزم الأمر
        },

        handlePropertyImageError(event) {
            event.target.src = this.getDefaultPropertyImage();
        },

        getLocationLabel() {
            if (!this.owner) return 'Location';
            if (this.owner.nationality === 'UAE') {
                return 'Country';
            } else if (this.owner.residency_status === 'resident') {
                return 'Emirate';
            } else if (this.owner.residency_status === 'non_resident') {
                return 'Country';
            }
            return 'Location';
        },

        getDocumentList() {
            if (!this.owner) return [];
            
            const documents = [];
            
            if (this.owner.id_front_path || this.owner.id_front_url) {
                documents.push({
                    title: 'ID Front',
                    type: 'id_front',
                    url: this.owner.id_front_path || this.owner.id_front_url
                });
            }
            
            if (this.owner.id_back_path || this.owner.id_back_url) {
                documents.push({
                    title: 'ID Back',
                    type: 'id_back',
                    url: this.owner.id_back_path || this.owner.id_back_url
                });
            }
            
            if (this.owner.visa_copy_path || this.owner.visa_copy_url) {
                documents.push({
                    title: 'Visa Copy',
                    type: 'visa',
                    url: this.owner.visa_copy_path || this.owner.visa_copy_url
                });
            }
            
            if (this.owner.passport_copy_path || this.owner.passport_copy_url) {
                documents.push({
                    title: 'Passport Copy',
                    type: 'passport',
                    url: this.owner.passport_copy_path || this.owner.passport_copy_url
                });
            }
            // Additional documents
            if (this.owner.additional_documents && Array.isArray(this.owner.additional_documents)) {
                this.owner.additional_documents.forEach(doc => {
                    documents.push({
                        title: doc.name || 'Document',
                        type: 'additional',
                        url: doc.url
                    });
                });
            }
            return documents;
        },

        getDocumentCount() {
            return this.getDocumentList().length;
        },

        getDocumentIcon(type) {
            const icons = {
                'id_front': 'lucide:id-card',
                'id_back': 'lucide:id-card',
                'visa': 'lucide:file-text',
                'passport': 'mdi:passport',
                'additional': 'lucide:file-plus'
            };
            return icons[type] || 'lucide:file';
        },

        viewDocument(url) {
            if (url) {
                window.open(url, '_blank');
            } else {
                this.showNotification('Document not available', 'warning');
            }
        },

        downloadDocument(url, filename) {
            if (url) {
                const link = document.createElement('a');
                link.href = url;
                link.download = filename || 'document';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            } else {
                this.showNotification('Document not available for download', 'warning');
            }
        },

        async downloadAllDocuments() {
            const documents = this.getDocumentList();
            if (documents.length === 0) {
                this.showNotification('No documents available for download', 'warning');
                return;
            }

            this.showNotification(`Downloading ${documents.length} document(s)...`, 'info');
            
            for (const doc of documents) {
                this.downloadDocument(doc.url, doc.title);
            }
        },

        showNotification(message, type = 'info') {
            console.log(`📢 Notification [${type}]: ${message}`);
            if (window.$showNotification) {
                window.$showNotification(message, type);
            } else {
                alert(`${type.toUpperCase()}: ${message}`);
            }
        }
    }
};
</script>

<style scoped>
/* ============ TABS ============ */
/* Segmented control: soft brand track, active tab lifts as a white pill */
.owner-tabs {
    display: inline-flex;
    flex-wrap: nowrap;
    gap: 2px;
    max-width: 100%;
    padding: 3px;
    border-radius: 11px;
    background: var(--color-primary-soft);
    border: 1px solid var(--color-primary-border);
    overflow-x: auto;
    scrollbar-width: none;
}

.owner-tabs::-webkit-scrollbar {
    display: none;
}

/* Colors are !important: global theme pill/button rules otherwise paint these grey/navy */
.owner-tabs.nav-pills .nav-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    flex: 0 0 auto;
    margin: 0;
    padding: 6px 14px;
    border: none !important;
    border-radius: 8px !important;
    background: transparent !important;
    box-shadow: none !important;
    color: var(--color-text-nav) !important;
    -webkit-text-fill-color: currentColor !important;
    font-size: 0.8125rem;
    font-weight: 600;
    line-height: 1.4;
    white-space: nowrap;
    transition: background 0.2s, color 0.2s, box-shadow 0.2s;
}

.owner-tabs.nav-pills .nav-link i {
    font-size: 1rem;
    color: var(--color-text-muted) !important;
    transition: color 0.2s;
}

.owner-tabs.nav-pills .nav-link:hover,
.owner-tabs.nav-pills .nav-link:hover i {
    color: var(--color-primary) !important;
}

.owner-tabs.nav-pills .nav-link.active,
.owner-tabs.nav-pills .nav-link.active:hover,
.owner-tabs.nav-pills .nav-link.active:focus {
    background: var(--color-surface) !important;
    color: var(--color-primary) !important;
    box-shadow: 0 2px 8px rgba(var(--color-primary-rgb), 0.15) !important;
}

.owner-tabs.nav-pills .nav-link.active i {
    color: var(--color-primary) !important;
}

.owner-tabs.nav-pills .badge {
    min-width: 20px;
    padding: 1px 7px;
    border-radius: 999px;
    font-size: 0.6875rem;
    font-weight: 700;
    line-height: 1.5;
    color: var(--color-primary) !important;
    -webkit-text-fill-color: currentColor !important;
    background: var(--color-surface) !important;
    border: 1px solid var(--color-primary-border);
}

.owner-tabs.nav-pills .nav-link.active .badge {
    color: var(--color-on-primary) !important;
    background: var(--gradient-primary) !important;
    border-color: transparent;
}

.owner-id-card {
    border: 1px solid var(--color-primary-border);
    border-radius: 14px;
    background: #ffffff;
    overflow: hidden;
}

.owner-id-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 14px;
    background: linear-gradient(90deg, var(--color-primary-soft) 0%, #ffffff 72%);
    border-bottom: 1px solid var(--color-primary-border);
}

.owner-id-card__kicker {
    margin: 0;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--color-primary);
    line-height: 1.2;
}

.owner-id-card__name {
    margin: 1px 0 0;
    font-size: 16px !important;
    font-weight: 700;
    letter-spacing: -0.02em;
    color: var(--color-primary-ink);
    line-height: 1.2;
}

.owner-id-card__id {
    flex: 0 0 auto;
    padding: 3px 8px;
    border-radius: 999px;
    background: var(--gradient-primary);
    color: #ffffff;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.02em;
}

.owner-id-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0;
}

.info-item {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 1px;
    min-width: 0;
    padding: 8px 14px;
    border-bottom: 1px solid var(--color-border);
}

.info-label {
    font-weight: 600;
    color: var(--color-text-muted);
    font-size: 10px;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.info-value {
    font-size: 13px;
    font-weight: 600;
    color: var(--color-primary-ink);
    text-align: left;
    line-height: 1.3;
    word-break: break-word;
}

.info-value:is(a) {
    color: var(--color-primary-ink);
    text-decoration: none;
}

.owner-pill {
    display: inline-flex;
    align-items: center;
    min-height: 18px;
    padding: 0 7px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
}

.owner-pill--ok {
    background: var(--color-primary-soft);
    color: var(--color-primary-ink);
}

.owner-pill--warn {
    background: #fff7ed;
    color: #c2410c;
}

.owner-id-notes {
    margin: 0;
    padding: 8px 14px 10px;
    font-size: 12px;
    line-height: 1.45;
    color: var(--color-text-nav);
}

.owner-doc-list {
    display: flex;
    flex-direction: column;
}

.owner-doc-row {
    display: flex;
    align-items: center;
    gap: 8px;
    min-height: 36px;
    padding: 4px 0;
    border-bottom: 1px solid var(--color-border);
}

.owner-doc-row__icon {
    font-size: 16px;
    color: var(--color-primary);
    flex-shrink: 0;
}

.owner-doc-row__title {
    min-width: 0;
    flex: 1;
    font-size: 13px;
    font-weight: 600;
    color: var(--color-primary-ink);
}

.owner-doc-row__btn {
    border: 1px solid var(--color-border);
    background: #ffffff;
    color: var(--color-primary-ink);
    border-radius: 999px;
    height: 26px;
    padding: 0 10px;
    font-size: 11px;
    font-weight: 700;
}

.owner-doc-row__btn:hover {
    background: var(--gradient-primary);
    border-color: var(--color-primary);
    color: #ffffff;
}

.owner-empty {
    padding: 16px 0;
    font-size: 13px;
    color: var(--color-text-muted);
}

@media (max-width: 640px) {
    .owner-id-grid {
        grid-template-columns: 1fr;
    }
}

/* Bootstrap "primary" bits on this page follow the brand, not the template blue */
.owner-doc-head__icon {
    color: var(--color-primary);
}

.spinner-border.text-primary {
    color: var(--color-primary) !important;
}

.btn-primary {
    background: var(--gradient-primary);
    border-color: transparent;
    color: var(--color-on-primary);
}

.btn-primary:hover {
    background: var(--gradient-primary-hover);
    color: var(--color-on-primary);
}

/* ============ DOCUMENT ITEMS ============ */
.document-item {
    transition: all 0.3s ease;
    border: 1px solid #dee2e6;
}

.document-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    border-color: var(--color-primary);
}

.document-icon {
    color: var(--color-primary);
}

.document-title {
    font-size: 14px;
    font-weight: 600;
    color: #343a40;
}

/* ============ PROPERTY CARDS ============ */
/* تحسينات الـ CSS للصور */
/*.property-image img {*/
/*  height: 230px;*/
/*  transition: transform 0.4s ease;*/
/*  opacity: 0;*/
/*  transition: opacity 0.3s ease;*/
/*}*/

/*.property-image img.loaded {*/
/*  opacity: 1;*/
/*}*/

/* تحسين الـ performance للـ animations */








/* Converted Badge */









/* Converted Card Styles */





/* Pagination Styles */
.pagination {
  margin-bottom: 0;
}

.page-item.active .page-link {
  background-color: #1e1e1e;
  border-color: #1e1e1e;
  color: white;
}

.page-link {
  color: #333;
  border: 1px solid #dee2e6;
  padding: 8px 16px;
  margin: 0 4px;
  border-radius: 8px;
  transition: all 0.3s ease;
}

.page-link:hover {
  background-color: #f8f9fa;
  border-color: #dee2e6;
  color: #333;
}

.page-item.disabled .page-link {
  color: #6c757d;
  background-color: #fff;
  border-color: #dee2e6;
}
/* Status Toggle Buttons */
.status-toggle-buttons {
  display: flex;
  gap: 12px;
  padding: 0 16px;
  flex-wrap: wrap;
  margin-bottom: 20px;
}

.status-btn {
  padding: 10px 20px;
  border: 2px solid #e9ecef;
  background: white;
  border-radius: 12px;
  color: #6c757d;
  font-weight: 500;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}

.status-btn:hover {
  border-color: var(--color-primary);
  color: var(--color-primary);
}

.status-btn.active {
  background: var(--gradient-primary) !important;
  border-color: var(--color-primary);
  color: #ffffff !important;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
}

/* تحسين التنسيق للشاشات الصغيرة */
@media (max-width: 768px) {
  .status-toggle-buttons {
    flex-direction: column;
  }
  
  .status-btn {
    justify-content: center;
  }
}

@media (max-width: 1200px) {
  .status-toggle-buttons {
    gap: 8px;
  }
  
  .status-btn {
    padding: 8px 16px;
    font-size: 0.9rem;
  }
}




/* Card States */







/* ============ CARDS ============ */
.card {
    border-radius: 14px;
    border: 1px solid #efe4f3;
    box-shadow: none;
}

.card:hover {
    transform: none;
    box-shadow: none !important;
}

.card-header {
    border-radius: 0.75rem 0.75rem 0 0 !important;
    background-color: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
    padding: 1rem;
}

.card-body {
    padding: 1rem;
}

/* ============ RESPONSIVE ============ */
@media (max-width: 375px) {
    .property-image {
        height: 8rem;
    }
    
    .property-content {
        padding: 0.75rem;
    }
    
    .property-price .price {
        font-size: 1rem;
    }
    
    .property-title {
        font-size: 0.8125rem;
        height: 2.25rem;
    }
    
    .detail-item i {
        font-size: 0.875rem;
    }
    
    .detail-item small {
        font-size: 0.5625rem;
    }
    
    .owner-tabs .nav-link {
        padding: 5px 10px;
        font-size: 0.75rem;
    }
}

@media (min-width: 376px) and (max-width: 575px) {
    .property-image {
        height: 9rem;
    }
    
    .detail-item small {
        font-size: 0.6875rem;
    }
}

@media (min-width: 576px) and (max-width: 767px) {
    .property-image {
        height: 10rem;
    }
    
    .property-title {
        font-size: 0.875rem;
    }
}

@media (min-width: 768px) and (max-width: 991px) {
    .property-image {
        height: 11rem;
    }
    
    .property-title {
        font-size: 0.9375rem;
    }
}

@media (min-width: 992px) {
    .property-image {
        height: 12rem;
    }
}

@media (min-width: 1200px) {
    .property-image {
        height: 13rem;
    }
}







.card-body {
    padding: 12px;
}

.card-header {
    padding: 8px 12px;
}
</style>