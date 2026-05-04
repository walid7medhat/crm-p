<template>
    <div class="dashboard-main-body">
        <!-- Breadcrumb -->
        <Breadcrumb 
            :title="user ? user.name : 'User Details'" 
            :breadcrumbs="[
                { name: 'Dashboard', path: '/' },
                { name: 'Users', path: '/users' },
                { name: user ? user.name : 'Loading...' }
            ]" 
        />
        
        <!-- Main Content Container -->
        <div class="user-details-container">
            <!-- Loading State -->
            <div v-if="loading" class="card mb-4">
                <div class="card-body text-center py-5">
                    <div class="spinner-border text-primary mb-3" role="status"></div>
                    <p class="mb-0">Loading user information...</p>
                </div>
            </div>

            <!-- User Data -->
            <div v-else-if="user">
                <!-- User Header Section -->
                <div class="user-header-section mb-5">
                    <div class="row align-items-center">
                        <div class="col-md-3">
                            <div class="profile-avatar-wrapper position-relative">
                                <img :src="user.avatar || userPlaceholder" 
                                     alt="User Avatar" 
                                     class="profile-avatar  "
                                     @error="handleImageError">
                                <!-- <div class="online-status" :class="user.status"></div> -->
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="">
                                <div class="user-info mb-3 mb-md-0">
                                    <div class="user-head">
                                    <h5 class="user-name mb-2">{{ user.name }}</h5>
                                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3 buttons-status">
                                        <span class="user-role">{{ user.role_name }}</span>
                                        <span class="user-status" :class="user.status">
                                            {{ user.status === 'active' ? 'Active' : 'Inactive' }}
                                        </span>
                                    </div>
                                    </div>
                                    <div class="user-contact">
                                         <div class="col-12" v-if="user.email" >
                                                <label class="col-3">Email:</label>
                                                <span v-if="user.email" class=" text-muted col-6 mb-0">
                                                    {{ user.email }}
                                                </span>
                                        </div>
                                        <div class="col-12" v-if="user.phone" >
                                                <label class="col-3">Phone:</label>
                                                <span v-if="user.phone" class=" text-muted col-6 mb-0">
                                                    {{ user.phone }}
                                                </span>
                                        </div>
                                        <div class="col-12" v-if="user.role_name" >
                                                <label class="col-3">Role:</label>
                                                <span v-if="user.role_name" class=" text-muted col-6 mb-0">
                                                    {{ user.role_name }}
                                                </span>
                                        </div>
                                         <div class="col-12" v-if="user.admin_parent_name" >
                                                <label class="col-3 capital"> {{ user.parent_role ? user.parent_role.replace(/_/g, ' ') : 'SYSTEM ADMIN' }}:</label>
                                                <span v-if="user.admin_parent_name" class=" text-muted col-6 mb-0">
                                                    {{ user.parent_name }}
                                                </span>
                                        </div>
                                        <div class="col-12" v-if="user.admin_parent_name" >
                                                <label class="col-3">Branch:</label>
                                                <span v-if="user.admin_parent_name" class=" text-muted col-6 mb-0">
                                                    {{ user.admin_parent_name }}
                                                </span>
                                        </div>
                                         
                                    </div>
                                </div>
                              
                            </div>
                            
                             <div class="user-actions">
                                <div class="d-flex flex-sm-row gap-2" style="max-width: 200px;">
                                    <button class="btn btn-outline-secondary btn-sm" @click="$router.back()">
                                        <i class="ri-arrow-left-line me-1"></i>
                                        <span class="d-sm-inline">Back</span>
                                    </button>
                                    <button v-if="$hasPermission('users-edit')" 
                                            class="btn btn-success btn-sm"
                                            @click="$router.push(`/users/${user.id}/edit`)">
                                        <i class="ri-edit-line me-1"></i>
                                        <span class="d-sm-inline">Edit User</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <!--<div class="col-md-1 d-none d-sm-block"></div>-->
                        <!-- <div class="col-md-1 d-none d-sm-block">-->
                        <!--    <div class="line-before"></div>-->
                        <!--</div>-->

                        <!--<div class="col-md-2 ">-->
                        <!--     <div class="profile-avatar-wrapper mb-3">-->
                        <!--        <img :src="user.parent_avatar || userPlaceholder" -->
                        <!--             alt="User Avatar" -->
                        <!--             class="profile-avatar2  rounded-circle  "-->
                        <!--             @error="handleImageError">-->
                                  
                        <!--    </div>-->
                        <!--       <label class="col-12">supervisor:</label>-->
                        <!--       <span class="col-12">{{user.parent_name}}</span>-->
                        <!--</div>-->
                    </div>
                </div>

          

                <!-- Tab Content -->
                <div class="tab-content" id="userTabContent">
                          <!-- Main Content Tabs -->
                <div class="user-content-tabs mb-4">
                    <nav>
                        <div class="nav nav-tabs" id="userTab" role="tablist">
                            <button v-if="isAgent" class="nav-link active" id="properties-tab" data-bs-toggle="tab" 
                                    data-bs-target="#properties" type="button" role="tab">
                                <i class="ri-home-4-line me-2"></i>
                                <span class="d-none d-md-inline">Properties</span>
                                <span class="d-inline d-md-none">Properties</span>
                                <span v-if="agentProperties.length > 0" class="badge bg-primary ms-2">{{ agentProperties.length }}</span>
                            </button>
                        </div>
                    </nav>
                </div>

                    <!-- Properties Tab -->
                    <div class="tab-pane fade show active" id="properties" role="tabpanel" v-if="isAgent">
                        <div class="properties-section">
                            <div class="card border-0 shadow-sm">
                              
                                <div class="card-body">
                                    <!-- Loading State -->
                                    <div v-if="agentPropertiesLoading" class="text-center py-5">
                                        <div class="spinner-border text-primary mb-3" role="status"></div>
                                        <p class="text-muted">Loading properties...</p>
                                    </div>
                                    
                                    <!-- Properties Grid -->
                                    <div v-else-if="agentProperties.length > 0">
                                        

                                        <div class="row g-3">
                                            <div 
                                                v-for="property in filteredProperties" 
                                                :key="property.id" 
                                                class="col-12 col-md-6 col-xl-4 col-xxl-4 custom-1600"
                                            >
                                                <router-link
                                                    :to="`/property-details/${property.id}`"
                                                    class="property-card-link"
                                                >
                                                    <div class="property-card h-100">
                                                        <!-- Image -->
                                                        <div class="property-image position-relative">
                                                            <img 
                                                                :src="getPropertyImage(property)" 
                                                                :alt="property.title"
                                                                class="w-100 h-100 object-fit-cover property-img" 
                                                                loading="lazy" 
                                                                @load="handlePropertyImageLoad" 
                                                                @error="handlePropertyImageError" 
                                                            />
                                                            
                                                            <div class="status-badges">
                                                                <span v-if="property.status === 'converted'" class="badge bg-danger">
                                                                    <i class="ri-checkbox-circle-fill me-1"></i>Sold Out
                                                                </span>
                                                                <span v-else-if="property.status === 'draft'" class="badge bg-secondary">
                                                                    <i class="ri-draft-line me-1"></i>Draft
                                                                </span>
                                                                <span v-else-if="property.is_archived" class="badge bg-warning">
                                                                    <i class="ri-archive-fill me-1"></i>Archived
                                                                </span>
                                                                <span v-else-if="!property.is_active" class="badge bg-secondary">
                                                                    <i class="ri-eye-off-fill me-1"></i>Inactive
                                                                </span>
                                                                <span v-else class="badge bg-success">
                                                                    <i class="ri-checkbox-circle-line me-1"></i>Active
                                                                </span>
                                                                <span v-if="property.listing_status" class="status-badge">
                                                                         {{ property.listing_status === 'sale' ? 'For Sale' : 'For Rent' }}
                                                                </span>
                                                                 <span v-if="property.is_hot_deal== 'Yes'" class="badge-off_plan">
                                                                    Hot Deal
                                                                  </span>
                                                                 <span v-if="property.occupancy_status && property.completion_status != 'Under Construction'" class="badge-occupancy_status">
                                                                    {{property.occupancy_status}}
                                                                  </span>
                                                                  <span v-if="!property.approved && property.rejection_reason" class="badge-sold bg-danger">
                                                                    <i class="ri-close-circle-fill me-1"></i>Rejected
                                                                  </span>
                                                                  <span v-else-if="!property.approved && !property.rejection_reason && property.status==draft" class="badge-sold bg-danger">
                                                                    <i class="ri-time-line me-1"></i>Need Approve
                                                                  </span>
                                                            </div>
                                                            
                                                            <span class="image-count" v-if="property.gallery_images && property.gallery_images.length">
                                                                <i class="ri-image-line me-1"></i>{{ property.gallery_images.length }}
                                                            </span>
                                                        </div>

                                                        <!-- Content -->
                                                        <div class="property-content p-3">
                                                            <div class="property-price mb-2">
                                                                <span class="price">{{ formatPrice(property.price) }}</span>
                                                                <span class="currency">AED</span>
                                                            </div>

                                                            <h6 class="property-title mb-2">{{ property.title || 'No Title' }}</h6>
                                                            <p class="property-location mb-3 text-muted" :title="property.area">
                                                                <i class="ri-map-pin-line me-1"></i>{{ getPropertyLocation(property) }}
                                                            </p>

                                                                <div class="property-details d-flex justify-content-between text-muted small mb-3">
                                                                      <span class="d-flex justify-content-between icons">
                                                                          <img :src="propertyIcon" class="imgicon"/>
                                                                          <!--<i class="ri-building-4-line me-1"></i>-->
                                                                          <span>
                                                                          {{ getPropertyType(property) }}
                                                                          </span>
                                                                          
                                                                    </span>
                                                                      <span class="d-flex justify-content-between icons" v-if="!property.property_type.toLowerCase().includes('plot') && !property.property_type.toLowerCase().includes('land') && property.number_of_bedrooms !== null && property.number_of_bedrooms !== undefined">
                                                                          <!--<i class="ri-hotel-bed-line me-1"></i>-->
                                                                          <img :src="bedIcon" class="imgicon"/>
                                                                           <span>
                                                                          {{ property.number_of_bedrooms || 0 }}
                                                                          </span>
                                                                     </span>
                                                                      <span class="d-flex justify-content-between icons" v-if="!property.property_type.toLowerCase().includes('plot') && !property.property_type.toLowerCase().includes('land') && property.number_of_bathrooms !== null && property.number_of_bathrooms !== undefined && property.number_of_bathrooms!=0">
                                                                          <!--<i class="ri-water-flash-line me-1"></i>-->
                                                                          <img :src="bathIcon" class="imgicon"/>
                                                                           <span>
                                                                          {{ property.number_of_bathrooms || 0 }}
                                                                          </span>
                                                                    </span>
                                                                      <span class="d-flex justify-content-between icons">
                                                                          <!--<i class="ri-ruler-line me-1"></i>-->
                                                                          <img :src="sqftIcon" class="imgicon"/>
                                                                           <span>
                                                                          {{ property.size_sqft || property.size_sqmt || 0 }} {{ getAreaUnit(property) }}
                                                                          </span>
                                                                    </span>
                                                                    </div>

                                                                    <div class="property-listed-date mb-2">
                                                                        <i class="ri-calendar-line me-1"></i>
                                                                        <small class="text-muted">Listed at: {{ formatDate(property.created_at) }}</small>
                                                                      </div>
                                                                 <span class="view-more-btn mt-2">
                                                                      View Details
                                                                    </span>
                                                        </div>
                                                    </div>
                                                </router-link>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- No Properties -->
                                    <div v-else class="text-center py-5">
                                        <div class="mb-3">
                                            <i class="ri-home-4-line display-1 text-muted"></i>
                                        </div>
                                        <h5 class="text-muted">No Properties Found</h5>
                                        <p class="text-muted">This agent doesn't have any properties assigned yet.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Error State -->
            <div v-else class="card">
                <div class="card-body text-center py-5">
                    <i class="ri-user-unfollow-line display-1 text-danger mb-3"></i>
                    <h4>User Not Found</h4>
                    <p class="text-muted mb-4">The user you're looking for doesn't exist or has been removed.</p>
                    <button class="btn btn-primary" @click="$router.push('/users')">
                        <i class="ri-arrow-left-line me-2"></i>
                        Back to Users List
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { API_ENDPOINTS } from '@/config/api';
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue';

export default {
    name: 'ViewUser',
    components: {
        Breadcrumb
    },
    data() {
        return {
            loading: false,
            user: null,
            teamMembers: [],
            userPermissions: [],
            error: null,
    
            showAllTeamMembers: false,
            
            // Agent Properties
            agentProperties: [],
            agentPropertiesLoading: false,
            propertyFilter: 'all',
            sortBy: 'newest',
             propertyIcon : '/assets/icons/property-icon.svg',
            bedIcon : '/assets/icons/bedroom-icon.svg',
            bathIcon : '/assets/icons/bathroom-icon.svg',
            sqftIcon : '/assets/icons/area-size.svg',
            userPlaceholder:'/assets/images/user.png'
        };
    },
    computed: {
        isAgent() {
            if (!this.user) return false;
            
            const role = (this.user.role_name || '').toLowerCase();
            
            const agentRoles = [
                'agent', 
                'sales',
                'sales agent', 
                'property agent', 
                'real estate agent', 
                'listing agent',
                'broker',
                'salesperson'
            ];
            
            return agentRoles.some(agentRole => role.includes(agentRole));
        },
        
        filteredProperties() {
            let properties = [...this.agentProperties];
            
            switch (this.propertyFilter) {
                case 'active':
                    properties = properties.filter(p => p.is_active && !p.is_archived && p.status !== 'converted' && p.status !== 'draft');
                    break;
                case 'sold':
                    properties = properties.filter(p => p.status === 'converted');
                    break;
                case 'draft':
                    properties = properties.filter(p => p.status === 'draft');
                    break;
            }
            
            switch (this.sortBy) {
                case 'newest':
                    properties.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
                    break;
                case 'oldest':
                    properties.sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
                    break;
                case 'price_high':
                    properties.sort((a, b) => (b.price || 0) - (a.price || 0));
                    break;
                case 'price_low':
                    properties.sort((a, b) => (a.price || 0) - (b.price || 0));
                    break;
            }
            
            return properties;
        },
        
        totalAgentProperties() {
            return this.agentProperties.length;
        },
        
        activeAgentProperties() {
            return this.agentProperties.filter(p => p.is_active && !p.is_archived && p.status !== 'converted' && p.status !== 'draft').length;
        },
        
        forSaleAgentProperties() {
            return this.agentProperties.filter(p => p.listing_status === 'Sale').length;
        },
        
        soldAgentProperties() {
            return this.agentProperties.filter(p => p.status === 'converted').length;
        }
    },
    mounted() {
        this.fetchUser();
    },
    methods: {
        async fetchUser() {
            try {
                this.loading = true;
                this.error = null;
                
                const token = localStorage.getItem('token');
                const userId = this.$route.params.id;
                
                const response = await fetch(API_ENDPOINTS.USER_BY_ID(userId), {
                    method: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    this.user = data.data || data;
                    
                    const promises = [];
                    
                    if (this.isManager(this.user) || this.isTeamLead(this.user)) {
                        promises.push(this.fetchTeamMembers(userId).catch(e => {
                            console.warn('⚠️ Team members fetch failed:', e);
                        }));
                    }
                    
                    promises.push(this.fetchUserPermissions(userId));
                    
                    if (this.isAgent) {
                        promises.push(this.fetchAgentProperties());
                    }
                    
                    await Promise.allSettled(promises);
                    
                } else if (response.status === 404) {
                    this.error = 'User not found';
                } else {
                    throw new Error(`Failed to fetch user: ${response.status} ${response.statusText}`);
                }
            } catch (error) {
                console.error('❌ Error fetching user:', error);
                this.error = error.message;
                this.showNotification('❌ Failed to load user data', 'error');
            } finally {
                this.loading = false;
            }
        },

        async fetchTeamMembers(managerId) {
            try {
                const token = localStorage.getItem('token');
                const response = await fetch(`${API_ENDPOINTS.USERS}?parent_id=${managerId}`, {
                    method: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Content-Type': 'application/json'
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    this.teamMembers = data.data || data;
                }
            } catch (error) {
                console.error('Error fetching team members:', error);
            }
        },

        async fetchUserPermissions(userId) {
            try {
                const token = localStorage.getItem('token');
                const response = await fetch(`${API_ENDPOINTS.USER_PERMISSIONS(userId)}`, {
                    method: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Content-Type': 'application/json'
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    this.userPermissions = data.data || data.permissions || [];
                } else {
                    this.userPermissions = [];
                }
            } catch (error) {
                this.userPermissions = [];
            }
        },

        async fetchAgentProperties() {
            if (!this.isAgent) return;
            
            try {
                this.agentPropertiesLoading = true;
                const token = localStorage.getItem('token');
                const agentId = this.user.id;

                const endpoint = `/api/listings/properties?agent_id=${agentId}`;
                const response = await fetch(endpoint, {
                    method: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) {
                    this.agentProperties = [];
                    return;
                }

                const data = await response.json();

                if (data.status && Array.isArray(data.data)) {
                    this.agentProperties = data.data;
                } else if (Array.isArray(data)) {
                    this.agentProperties = data;
                } else {
                    this.agentProperties = [];
                }

            } catch (error) {
                console.error('❌ Error in fetchAgentProperties:', error);
                this.agentProperties = [];
            } finally {
                this.agentPropertiesLoading = false;
            }
        },

        getPropertyImage(property) {
            if (!property) return this.getDefaultPropertyImage();
            
            if (property.main_image) {
                return property.main_image;
            }
            
            if (property.hero_image_path) {
                return property.hero_image_path.includes('http') 
                    ? property.hero_image_path 
                    : `/storage/${property.hero_image_path}`;
            }
            
            if (property.hero_image_url) {
                return property.hero_image_url;
            }
            
            if (property.gallery_images && Array.isArray(property.gallery_images) && property.gallery_images.length > 0) {
                const firstImage = property.gallery_images[0];
                return firstImage.image_url || firstImage.url || firstImage;
            }
            
            return this.getDefaultPropertyImage();
        },
        
        getDefaultPropertyImage() {
            return 'https://via.placeholder.com/400x300/e9ecef/6c757d?text=Property';
        },
        
        handlePropertyImageError(event) {
            event.target.src = this.getDefaultPropertyImage();
        },
        
        handlePropertyImageLoad(event) {
            event.target.classList.add('loaded');
        },
        
        getPropertyLocation(property) {
            if (!property) return 'Location not specified';
            
            if (property.area && property.area.name) {
                return property.area.name;
            }
            
            if (property.area_name) {
                return property.area_name;
            }
            
            if (property.area) {
                return property.area;
            }
            
            if (property.location) {
                return property.location;
            }
            
            return 'Location not specified';
        },
        
        getPropertyType(property) {
            if (property.property_type ) {
                const type = property.property_type;
                return type.length > 8 ? type.substring(0, 8) + '...' : type;
            }
            if (property.type) {
                const type = property.type;
                return type.length > 8 ? type.substring(0, 8) + '...' : type;
            }
            return 'Property';
        },
        
        getAreaUnit(property) {
            return property.size_sqft ? 'Sqft' : 'Sqm';
        },
        
        formatPrice(price) {
            if (!price || price === 0) return '0';
            return new Intl.NumberFormat().format(price);
        },

        formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        },

        isTeamLead(user) {
            return user.role_name?.toLowerCase().includes('team') || 
                   user.roles?.some(role => role.toLowerCase().includes('team'));
        },

        isManager(user) {
            return user.role_name?.toLowerCase().includes('manager') || 
                   user.roles?.some(role => role.toLowerCase().includes('manager'));
        },

        getSupervisorType(user) {
            if (this.isManager(user)) return 'Manager';
            if (this.isTeamLead(user)) return 'Team Lead';
            return 'Supervisor';
        },

        handleImageError(event) {
            event.target.src = this.userPlaceholder;
        },

        showNotification(message, type = 'info') {
            if (window.$showNotification) {
                window.$showNotification(message, type);
            }
        }
    }
};
</script>

<style scoped>
/* ============ BASE STYLES ============ */
.dashboard-main-body {
    padding: 1rem;
    min-height: calc(100vh - 60px);
    background: #f8f9fa;
}

.user-details-container {
    max-width: 1400px;
    margin: 0 auto;
}

/* ============ USER HEADER SECTION ============ */
.user-header-section {
    background: white;
    border-radius: 1rem;
    padding: 1.5rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.profile-avatar-wrapper {
    position: relative;
}

.profile-avatar {
    width: 10rem;
    height: 10rem;
    object-fit: cover;
    /*box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);*/
    border-radius: 10px;
}

.online-status {
    position: absolute;
    bottom: 0.5rem;
    right: 0.5rem;
    width: 1.25rem;
    height: 1.25rem;
    border-radius: 50%;
    border: 0.1875rem solid white;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
}

.online-status.active {
    background-color: #28a745;
}

.online-status.inactive {
    background-color: #dc3545;
}

.user-name {
    color: #2c3e50;
    font-weight: 700;
    font-size: 18px !important;
    line-height: 1.2;
    margin-bottom: 0.5rem;
    display: inline;
}

.user-role {
    /* background: linear-gradient(

        90deg,                       

        rgba(255, 255, 255, 0.25) 0%, 

        rgba(20, 30, 80, 0.79) 0%,  

        rgba(5, 10, 40, 0.95) 100%    

    ); */
    background-color: #FAA300;
    color: white;
    padding: 5px 10px;
    border-radius: 5px;
    font-size: 0.75rem;
    font-weight: 500;
    display: inline-block;
}

.user-status {
    /* background-color: #B8B8B8; */
    padding: 5px 10px;
    border-radius: 5px;
    font-size: 0.75rem;
    font-weight: 500;
    display: inline-block;
}

.user-status.active {
    background-color: #B8B8B8;
    color: white;
}

.user-status.inactive {
    background: linear-gradient(135deg, #d63031, #fab1a0);
    color: white;
}

.user-email, .user-phone {
    font-size: 0.875rem;
    margin-bottom: 0.25rem;
}

.user-actions {
    margin-top: 0.5rem;
}

.user-action-buttons {
    display: flex;
    gap: 0.5rem;
    max-width: 180px;
    margin-left: auto;
}

.user-action-buttons .btn {
    padding: 0.25rem 0.75rem;
    font-size: 0.75rem;
    border-radius: 0.375rem;
    white-space: nowrap;
    flex: 1;
    min-width: 70px;
}

/* للشاشات الصغيرة */
@media (max-width: 576px) {
    .user-action-buttons {
        max-width: 100%;
        justify-content: flex-start;
    }
    
    .user-action-buttons .btn {
        flex: none;
        padding: 0.25rem 0.5rem;
    }
}

/* للشاشات المتوسطة والكبيرة */
@media (min-width: 577px) {
    .user-action-buttons {
        justify-content: flex-end;
    }
}

/* ============ TABS SECTION ============ */
.user-content-tabs {
    background: white;
    border-radius: 0.75rem;
    padding: 0.5rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    overflow-x: auto;
}

.nav-tabs {
    border-bottom: 1px solid rgba(0, 0, 0, 0.08);
    flex-wrap: nowrap;
    min-width: max-content;
}

.nav-tabs .nav-link {
    border: none;
    background: none;
    color: #6c757d;
    font-weight: 500;
    padding: 0.5rem 0.75rem;
    border-radius: 0.5rem;
    margin-right: 0.25rem;
    font-size: 0.875rem;
    white-space: nowrap;
    transition: all 0.3s;
}

.nav-tabs .nav-link:hover {
    background: rgba(0, 0, 0, 0.03);
    color: #495057;
}

.nav-tabs .nav-link.active {
    background: linear-gradient(

        90deg,                       

        rgba(255, 255, 255, 0.25) 0%, 

        rgba(20, 30, 80, 0.79) 0%,  

        rgba(5, 10, 40, 0.95) 100%    

    );
    color: white;
    box-shadow: 0 4px 12px rgba(108, 92, 231, 0.25);
}

.nav-tabs .nav-link i {
    font-size: 1rem;
}

.nav-tabs .nav-link .badge {
    font-size: 0.625rem;
    padding: 0.125rem 0.375rem;
}

/* ============ OVERVIEW TAB ============ */
.tab-content {
    padding-top: 1rem;
}

.card {
    border-radius: 0.75rem;
    transition: transform 0.3s, box-shadow 0.3s;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1) !important;
}

.card-header {
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    padding: 1rem;
}

.card-body {
    padding: 1rem;
}

.info-item {
    margin-bottom: 0.5rem;
}

.info-label {
    color: #6c757d;
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.125rem;
}

.info-value {
    color: #2c3e50;
    font-size: 0.875rem;
    font-weight: 600;
}

.supervisor-avatar {
    width: 3rem;
    height: 3rem;
    object-fit: cover;
}

/* ============ STAT CARDS ============ */
.stat-card {
    background: white;
    border: 1px solid rgba(0, 0, 0, 0.08);
    transition: all 0.3s;
    height: 100%;
}

.stat-card:hover {
    border-color: #FAA300;
    box-shadow: 0 6px 20px rgba(108, 92, 231, 0.15);
}

.stat-icon {
    opacity: 0.9;
}

.stat-icon i {
    font-size: 2rem;
}

.stat-value {
    color: #2c3e50;
    font-weight: 700;
    font-size: 1.5rem;
}

.stat-label {
    font-size: 0.75rem;
}

/* ============ PROPERTIES SECTION ============ */
.properties-section .card-header {
    padding: 1rem;
}

.stat-badge {
    display: flex;
    flex-direction: column;
    align-items: center;
    min-width: 3rem;
}

.stat-badge .badge {
    width: 2.25rem;
    height: 2.25rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    font-size: 0.75rem;
    font-weight: 600;
}

.stat-badge small {
    font-size: 0.625rem;
    margin-top: 0.25rem;
}

.properties-filter .form-select {
    border-radius: 0.5rem;
    border: 1px solid rgba(0, 0, 0, 0.1);
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
}

/* ============ PROPERTY CARDS ============ */
.property-card {
    background: white;
    border-radius: 0.75rem;
    overflow: hidden;
    border: 1px solid rgba(0, 0, 0, 0.08);
    transition: all 0.3s;
    height: 100%;
}

.property-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
}

.property-card-link {
    display: block;
    text-decoration: none;
    color: inherit;
    height: 100%;
}

.property-image {
    height: 10rem;
    overflow: hidden;
    position: relative;
}

.property-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s;
}

.property-card:hover .property-img {
    transform: scale(1.05);
}

.status-badges {
    position: absolute;
    top: 0.5rem;
    left: 0.5rem;
    display: flex;
    flex-direction: row;
    gap: 0.25rem;
      flex-wrap: wrap;

}

.status-badges .badge {
       padding: 4px 8px;
    border-radius: 6px;
    font-size: .7rem;
    font-weight: 500;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
}

.image-count {
    position: absolute;
    bottom: 0.5rem;
    right: 0.5rem;
    background: rgba(255, 255, 255, 0.95);
    padding: 0.25rem 0.5rem;
    border-radius: 1rem;
    font-size: 0.75rem;
    font-weight: 500;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.property-content {
    padding: 1rem;
}

.property-price {
    display: flex;
    align-items: baseline;
    gap: 0.25rem;
    margin-bottom: 0.5rem;
}

.property-price .price {
    font-size: 1.125rem;
    font-weight: 700;
    color: #2c3e50;
}

.property-price .currency {
    font-size: 0.75rem;
    color: #6c757d;
}

.property-title {
    font-size: 0.875rem;
    font-weight: 600;
    color: #2c3e50;
    line-height: 1.3;
    margin-bottom: 0.5rem;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    height: 2.5rem;
}

.property-location {
    font-size: 0.75rem;
    margin-bottom: 0.75rem;
    color: #6c757d;
     overflow: hidden;
  text-overflow: ellipsis;
    white-space: nowrap;
}

.property-details {
    margin-top: 0.75rem;
    padding-top: 0.75rem;
    border-top: 1px solid rgba(0, 0, 0, 0.08);
}

.detail-item {
    padding: 0.25rem;
}

.detail-item i {
    font-size: 1rem;
    color: #FAA300;
    margin-bottom: 0.25rem;
}

.detail-item small {
    font-size: 0.625rem;
    color: #6c757d;
    line-height: 1.2;
    display: block;
}

/* ============ TEAM SECTION ============ */
.team-member-card {
    border-radius: 0.75rem;
    transition: all 0.3s;
    height: 100%;
}

.team-member-card:hover {
    border-color: #FAA300;
    box-shadow: 0 6px 20px rgba(108, 92, 231, 0.1);
}

.team-avatar {
    width: 3rem;
    height: 3rem;
    object-fit: cover;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
    flex-shrink: 0;
}

.role-badge {
    background: #f1f3f5;
    color: #495057;
    padding: 0.25rem 0.5rem;
    border-radius: 0.75rem;
    font-size: 0.75rem;
    font-weight: 500;
}

.team-member-card .btn {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    white-space: nowrap;
}

/* ============ ERROR STATE ============ */
.error-state .card {
    border-radius: 1rem;
    border: none;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
}

/* ============ RESPONSIVE DESIGN ============ */

/* Extra Small Mobile (≤375px) */
@media (max-width: 375px) {
    .dashboard-main-body {
        padding: 0.75rem;
    }
    
    .user-header-section {
        padding: 1rem;
        border-radius: 0.75rem;
    }
    
    .profile-avatar {
        width: 4rem;
        height: 4rem;
    }
    
    .user-name {
        font-size: 1.25rem;
    }
    
    .user-role, .user-status {
        font-size: 0.625rem;
        padding: 0.1875rem 0.5rem;
    }
    
    .user-email, .user-phone {
        font-size: 0.75rem;
    }
    
    .user-actions .btn {
        padding: 0.375rem 0.75rem;
        font-size: 0.75rem;
    }
    
    .user-actions .btn i {
        margin-right: 0;
    }
    
    .nav-tabs .nav-link {
        padding: 0.375rem 0.5rem;
        font-size: 0.75rem;
    }
    
    .nav-tabs .nav-link i {
        font-size: 0.875rem;
        margin-right: 0.25rem;
    }
    
    .card {
        margin-bottom: 0.75rem;
    }
    
    .card-header, .card-body {
        padding: 0.75rem;
    }
    
    .info-value {
        font-size: 0.8125rem;
    }
    
    .stat-card {
        padding: 0.75rem;
    }
    
    .stat-icon i {
        font-size: 1.5rem;
    }
    
    .stat-value {
        font-size: 1.25rem;
    }
    
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
    
    .team-avatar {
        width: 2.5rem;
        height: 2.5rem;
    }
    
    .team-member-card .btn {
        font-size: 0.6875rem;
        padding: 0.1875rem 0.375rem;
    }
}

/* Small Mobile (376px - 575px) */
@media (min-width: 376px) and (max-width: 575px) {
    .user-header-section {
        padding: 1.25rem;
    }
    
    .profile-avatar {
        width: 4.5rem;
        height: 4.5rem;
    }
    
    .user-name {
              font-size: 16px !important;
    }
    
    .user-actions .btn {
        font-size: 0.8125rem;
    }
    
    .property-image {
        height: 9rem;
    }
    
    .detail-item small {
        font-size: 0.6875rem;
    }
}

/* Medium Mobile (576px - 767px) */
@media (min-width: 576px) and (max-width: 767px) {
    .dashboard-main-body {
        padding: 1.25rem;
    }
    
    .profile-avatar {
        width: 5rem;
        height: 5rem;
    }
    
    .user-name {
        font-size: 1.5rem;
    }
    
    .user-role, .user-status {
        font-size: 0.75rem;
    }
    
    .nav-tabs .nav-link {
        font-size: 0.875rem;
        padding: 0.5rem 1rem;
    }
    
    .property-image {
        height: 10rem;
    }
    
    .property-title {
        font-size: 0.875rem;
    }
}

/* Tablet (768px - 991px) */
@media (min-width: 768px) and (max-width: 991px) {
    .user-header-section {
        padding: 1.5rem;
    }
    
    .profile-avatar {
        width: 5.5rem;
        height: 5.5rem;
    }
    
    .user-name {
        font-size: 1.75rem;
    }
    
    .property-image {
        height: 11rem;
    }
    
    .property-title {
        font-size: 0.9375rem;
    }
}

/* Desktop (992px - 1199px) */
@media (min-width: 992px) {
    .dashboard-main-body {
        padding: 1.5rem;
    }
    
    .user-header-section {
        padding: 2rem;
    }
    
    .profile-avatar {
        width: 6rem;
        height: 6rem;
    }
    
    .user-name {
        font-size: 2rem;
    }
    
    .property-image {
        height: 12rem;
    }
}

/* Large Desktop (≥1200px) */
@media (min-width: 1200px) {
    .profile-avatar {
        width: 14rem;
        height: 14rem;
    }
    
    .user-name {
        font-size: 2.25rem;
    }
    
    .property-image {
        height: 13rem;
    }
}

/* Fix for very small screens */
@media (max-width: 320px) {
    .user-actions .btn span {
        display: none;
    }
    
    .user-actions .btn i {
        margin-right: 0;
    }
    
    .nav-tabs .nav-link span {
        display: none;
    }
    
    .nav-tabs .nav-link i {
        margin-right: 0;
    }
}
.view-more-btn {
  display: inline-block;
  text-align: center;
  width: 100%;
  background: #FAA300;
  color: #ffffff;
  font-weight: 500;
  border-radius: 10px;
  padding: 8px 0;
  font-size: 0.85rem;
  text-decoration: none;
  transition: all 0.3s;
}

.view-more-btn:hover {
  background: #111;
  color: #fff;
}
.property-card-link .view-more-btn {
  display: inline-block;
  pointer-events: none; /* لمنع النقر على الزر نفسه بشكل منفصل */
}
.btn-success{
    background-color: #0D1237 !important;
    border: 1px solid #0D1237 !important ;
    border-radius: 5px !important;
}
.btn-outline-secondary{
    background-color: #B8B8B8 !important;
    color: #fff !important;
    border: none !important;
    border-radius: 5px !important;
}
.buttons-status{
    float: right;
}

.user-contact label {
    font-weight: 700;
    color: #000;
    text-align: start;
}
 .user-contact span {
   
    text-align: start;
}



.user-head {
    margin-bottom: 20px;
    position: relative; /* لو حابة تتحكم في العنصر المطلق */
}

.user-head:after {
    content: "";
    display: block;        /* مهم عشان يظهر كعنصر */
    width: 100%;           /* عرض الخط بالكامل */
    height: 2px;           /* سمك الخط */
    background-color: #ddd8d8b1; /* لون الخط */
    margin-top: 10px;      /* مسافة بين العنصر والخط */
}

.profile-avatar2 {
    margin-top: 2rem;
    width: 10rem;
    height: 10rem;
}
.line-before {
    width: 2px;                  
    height: 10rem;                 
    background-color: #ddd8d8b1;  
        margin-top: 2rem;

}
.imgicon{
    max-width:15px;
    max-height:15px;
}
.capital{
    text-transform: capitalize;
}
/*============*/


.status-badge {
   background: #01062d ;
  color: white;
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 0.7rem;
  font-weight: 500;
}



.property-listed-date {
  font-size: 0.75rem;
  /*color: #666;*/
  margin-bottom:0 !important;
}

.property-listed-date i {
  font-size: 0.8rem;
  color: #FAA300;
}
.justify-between{
    justify-content:space-between;
}
.badge-off_plan {
  background: #B60F1C;
  color: white;
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 0.7rem;
  font-weight: 500;
}
.badge-occupancy_status{
    background: #EDEBEB !important;
    color: #01062C !important;
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 0.7rem;
  font-weight: 500;  
}
.icons img{
    width: 17px;
    height: 17px;
}
</style>

