<template>
    <div class="lead-info-view">
        <div class="info-section">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div class="info-section-title mb-0">Lead Information</div>
                <!-- emit(edit-request) for all info-->
                <button v-if="showEditIcon && canEdit" class="lead-edit-inline-btn" @click="emit('edit-section', 'leadInfo')">
                    <iconify-icon class="edit-icon-btn" color="#733E87" icon="lucide:pencil"></iconify-icon>
                </button>
            </div>
            <div class="info-group">
                <label class="form-label-custom">Lead Name</label>
                <div class="info-value">{{ lead?.lead_name || '—' }}</div>
            </div>
            <div class="info-group" v-if="lead?.salutation">
                <label class="form-label-custom">Salutation</label>
                <div class="info-value">{{ lead?.salutation || '—' }}</div>
            </div>
            <div class="info-group">
                <label class="form-label-custom">First Name</label>
                <div class="info-value">{{ lead?.first_name || '—' }}</div>
            </div>
            <div class="info-group">
                <label class="form-label-custom">Last Name</label>
                <div class="info-value">{{ lead?.last_name || '—' }}</div>
            </div>
            <div class="info-group" v-if="lead.work_phone">
                <label class="form-label-custom">Primary Phone</label>
                <div class="info-value">
                    <span v-if="canView"><a :href="'tel:' + lead.work_phone">{{ lead?.work_phone || '—' }} </a></span>
                    <span v-else>
                        {{ lead?.work_phone?.slice(0,3) || '' }}
                        <span class="blurred-stars">{{ maskValue(lead?.work_phone?.slice(3)) }}</span>
                    </span>
                </div>
            </div>
            <div class="info-group" v-if="lead?.email">
                <label class="form-label-custom">Primary Email</label>
                <div class="info-value">
                    <span v-if="canView">{{ lead?.email || '—' }}</span>
                    <span v-else>
                        {{ lead?.email?.slice(0,3) || '' }}
                        <span class="blurred-stars">{{ maskValue(lead?.email?.slice(3))}}</span>
                    </span>
                </div>
            </div>
            <div class="info-group" v-if="lead?.work_phone_2">
                <label class="form-label-custom">Secondary Phone</label>
                <div class="info-value">
                    <span v-if="canView"><a :href="'tel:' + lead.work_phone_2">{{ lead?.work_phone_2 || '—' }}</a></span>
                    <span v-else>
                        {{ lead?.work_phone_2?.slice(0,3) || '' }}
                        <span class="blurred-stars">{{ maskValue(lead?.work_phone_2?.slice(3)) }}</span>
                    </span>
                </div>
            </div>
            <div class="info-group" v-if="lead?.secondary_email">
                <label class="form-label-custom">Secondary Email</label>
                <div class="info-value">
                    <span v-if="canView">{{ lead?.secondary_email || '—' }}</span>
                    <span v-else>
                        {{ lead?.secondary_email?.slice(0,3) || '' }}
                        <span class="blurred-stars">{{ maskValue(lead?.secondary_email?.slice(3))}}</span>
                    </span>
                </div>
            </div>
            
        </div>

        <div class="info-section" v-if="hasAdditionalQuestions || lead?.more_information">
            <div class="info-section-title">More Information</div>
            
            <div class="info-group" v-if="lead?.more_information">
                <label class="form-label-custom">Comments</label>
                <div class="info-value">
                    <span >{{ lead?.more_information || '—' }}</span>
                  
                </div>
            </div>
            
            <template v-if="hasAdditionalQuestions">
                <div class="info-group" v-for="(answer, question) in facebookQuestions" :key="question">
                    <label class="form-label-custom">{{ formatQuestion(question) }}</label>
                    <div class="info-value">
                        <a v-if="question === 'link' || question === 'Page_URL' || question ==='inbox_url'" :href="answer" target="_blank" class="facebook-link">
                            {{ answer }}
                        </a>
                        <span v-else>
                            {{ answer }}
                        </span>
                    </div>
                </div>
                <div class="info-group" v-for="(answer, question) in metaQuestions" :key="`meta-${question}`">
                    <label class="form-label-custom">{{ formatQuestion(question) }}</label>
                    <div class="info-value ">
                        <a v-if="question === 'link' || question === 'Page_URL' || question ==='inbox_url'" :href="answer" target="_blank" class="facebook-link">
                            {{ answer }}
                        </a>
                        <span v-else>
                            {{ answer }}
                        </span>
                    </div>
                </div>
            </template>
            <div v-else class="info-empty">
                No additional information
            </div>
        </div>
 <!-- ✅ WhatsApp Qualification Section -->
        <div v-if="lead?.whatsapp_qualification && lead.whatsapp_qualification.length > 0" class="info-section whatsapp-qualification-section">
            <div class="info-section-title">WhatsApp Qualification</div>
            
            <div 
                v-for="(item, index) in lead.whatsapp_qualification" 
                :key="index"
                class="info-group"
            >
                <label class="form-label-custom">{{ item.question }}</label>
                <div class="info-value">{{ item.answer }}</div>
            </div>
        </div>
        <div v-if="showClientRequirementArea" class="client-requirement-wrap">
            <div class="client-requirement-header-row">
                <div class="info-section-title mb-0">Client Requirement</div>
                <div class="client-requirement-header-actions">
                    <button
                        v-if="showEditIcon && canEdit"
                        type="button"
                        class="lead-edit-inline-btn client-req-add-btn"
                        title="Add client requirement"
                        @click="openClientReqModal(null)"
                    >
                        <iconify-icon class="edit-icon-btn" color="#733E87" icon="lucide:plus"></iconify-icon>
                    </button>
                </div>
            </div>

            <div class="client-requirement-panel">
                <template v-if="hasPrimaryClientBlockContent">
                    <div class="client-req-block">
                        <div v-if="showEditIcon && canEdit" class="client-req-block-actions">
                            <div class="client-req-action-main">
                                <button
                                    type="button"
                                    class="client-qualification-btn"
                                    :class="{ 'is-active': qualificationSourceId === 'primary' }"
                                    title="Use this requirement for Lead Qualification"
                                    @click="selectQualificationSource('primary')"
                                >
                                    <iconify-icon icon="lucide:badge-check" />
                                    <span>{{ qualificationSourceId === 'primary' ? 'Selected priority' : 'Set as priority' }}</span>
                                </button>
                            </div>
                            <span class="client-req-created-at">{{ formatUpdatedAtDisplay(lead?.updated_at) }}</span>
                            <button
                                type="button"
                                class="lead-edit-inline-btn"
                                title="Edit primary requirement"
                                @click="emit('edit-section', 'clientRequirement')"
                            >
                                <iconify-icon class="edit-icon-btn" color="#733E87" icon="lucide:pencil"></iconify-icon>
                            </button>
                        </div>
                        <div v-if="hasPrimaryClientCoreContent" class="client-requirement-list">
                            <div class="info-group client-req-location" v-if="lead?.area">
                                <label class="form-label-custom">Location</label>
                                <div class="info-value location-selected-view">
                                    <i class="ri-map-pin-line location-option-icon"></i>
                                    <div class="location-option-text">
                                        <span class="location-option-name">{{ locationFirstLine(lead?.area) }}</span>
                                        <span class="location-option-subtitle">{{ locationSecondLine(lead?.area) }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="info-group client-req-property-type" v-if="lead?.property_type">
                                <label class="form-label-custom">Property Type</label>
                                <div class="info-value">{{ lead?.property_type || '—' }}</div>
                            </div>
                            <div class="info-group client-req-lead-type" v-if="lead?.lead_type">
                                <label class="form-label-custom">Lead Type</label>
                                <div class="info-value">{{ displayLeadType(lead?.lead_type) }}</div>
                            </div>
                            <div class="info-group client-req-property-status" v-if="lead?.property_status && !isRentOnly">
                                <label class="form-label-custom">Property Status</label>
                                <div class="info-value">{{ displayPropertyStatus(lead?.property_status) }}</div>
                            </div>
                            <div class="info-group client-req-bedrooms" v-if="lead?.bedrooms !== null && lead?.bedrooms !== undefined && lead?.bedrooms !== '' && !isPlotsOrLand">
                                <label class="form-label-custom">Bedrooms</label>
                                <div class="info-value">{{ formatBedroomsDisplay(lead?.bedrooms) }}</div>
                            </div>
                            <div
                                class="info-group client-req-budget"
                                v-if="formatLeadBudgetRange(lead)"
                            >
                                <label class="form-label-custom">Budget (AED)</label>
                                <div class="info-value">{{ formatLeadBudgetRange(lead) }}</div>
                            </div>
                            <div class="info-group client-req-purpose" v-if="lead?.purpose_buying && !isRentOnly">
                                <label class="form-label-custom">Purpose Of Purchase</label>
                                <div class="info-value">{{ lead?.purpose_buying || '—' }}</div>
                            </div>
                        </div>

                        <div v-if="hasClientTailContent" class="client-requirement-list client-requirement-list--tail">
                            <div class="info-group" v-if="lead?.branch != null">
                                <label class="form-label-custom">Shared Branch</label>
                                <div class="info-value">{{ lead?.branch }}</div>
                            </div>
                            <div class="info-group" v-if="lead?.available_date != null">
                                <label class="form-label-custom">Available Date</label>
                                <div class="info-value">{{ lead?.available_date != null ? lead.available_date : '—' }}</div>
                            </div>
                            <div class="info-group" v-if="lead?.source_information">
                                <label class="form-label-custom">Source Information</label>
                                <div class="info-value">{{ lead?.source_information || '—' }}</div>
                            </div>
                            <div class="info-group" v-if="lead?.unqualified_status">
                                <label class="form-label-custom">Unqualified Status</label>
                                <div class="info-value">{{ lead?.unqualified_status || '—' }}</div>
                            </div>
                            <div class="info-group" v-if="lead?.why_lost_lead">
                                <label class="form-label-custom">Lost For</label>
                                <div class="info-value">{{ formatLostReason(lead?.why_lost_lead) }}</div>
                            </div>
                            <div class="info-group" v-for="(value, key) in clientRequiredMetaFields" :key="`client-${key}`">
                                <label class="form-label-custom">{{ formatQuestion(key) }}</label>
                                <div class="info-value">{{ value }}</div>
                            </div>
                        </div>
                    </div>
                </template>

                <template
                    v-for="(req, idx) in visibleExtraClientRequirements"
                    :key="req.id || `extra-${idx}`"
                >
                    <div
                        v-if="showClientReqEditor && editingExtraIndex === idx"
                        class="info-section client-req-inline-editor section-highlight"
                    >
                        <div class="info-section-title mb-2">{{ clientReqModalTitle }}</div>
                        <div class="client-req-modal-body">
                            <div class="info-group">
                                <label class="form-label-custom">Location</label>
                                <v-select v-model="clientReqForm.area_id" :options="clientReqAreas" :reduce="(a) => a.id" :disabled="isLoadingClientReqAreas" label="name" placeholder="Select area" class="custom-v-select client-req-vselect location-select" :append-to-body="true">
                                     <template #open-indicator="{ attributes }">
                                          <span v-bind="attributes">
                                              <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                          </span>
                                      </template>
                                    <template #option="option">
                                        <div class="location-option">
                                            <i class="ri-map-pin-line location-option-icon"></i>
                                            <div class="location-option-text">
                                                <span class="location-option-name">{{ locationFirstLineFromOption(option) }}</span>
                                                <span class="location-option-subtitle">{{ locationSecondLineFromOption(option) }}</span>
                                            </div>
                                        </div>
                                    </template>
                                    <template #selected-option="option">
                                        <div v-if="option" class="location-selected">
                                            <span class="location-selected-name">{{ locationFirstLineFromOption(option) }}</span>
                                            <span class="location-selected-subtitle">{{ locationSecondLineFromOption(option) }}</span>
                                        </div>
                                    </template>
                                </v-select>
                            </div>
                            <div class="info-group"  v-if="clientReqForm.lead_type?.toLowerCase() !== 'rent'">
                                <label class="form-label-custom" >Property Status <span class="text-danger">*</span></label>
                                <v-select v-model="clientReqForm.property_status" :options="clientReqPropertyStatusOptions" :reduce="(o) => o.value" label="text" placeholder="Select Property Status" class="custom-v-select client-req-vselect" :append-to-body="true" >
                                     <template #open-indicator="{ attributes }">
                                          <span v-bind="attributes">
                                              <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                          </span>
                                      </template>
                                </v-select>
                            </div>
                            <div class="info-group">
                                <label class="form-label-custom">Property Type</label>
                                <v-select v-model="clientReqForm.property_type_id" :options="clientReqPropertyTypeOptions" :reduce="(o) => o.value" label="text" placeholder="Select Property Type" class="custom-v-select client-req-vselect" :append-to-body="true" >
                                     <template #open-indicator="{ attributes }">
                                          <span v-bind="attributes">
                                              <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                          </span>
                                      </template>
                                </v-select>
                            </div>
                            <div class="info-group">
                                <label class="form-label-custom">Lead Type <span class="text-danger">*</span></label>
                                <v-select v-model="clientReqForm.lead_type" :options="clientReqLeadTypeOptions" :reduce="(o) => o.value" label="text" placeholder="Select Lead Type" class="custom-v-select client-req-vselect" :append-to-body="true" >
                                     <template #open-indicator="{ attributes }">
                                          <span v-bind="attributes">
                                              <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                          </span>
                                      </template>
                                </v-select>
                            </div>
                            <div class="info-group"  v-if="!isPlotsOrLandByTypeId(clientReqForm.property_type_id)">
                                <label class="form-label-custom">Bedrooms</label>
                                <v-select v-model="clientReqForm.bedrooms" :options="clientReqBedroomOptions" :reduce="(o) => o.value" label="text" placeholder="Select Bedrooms" class="custom-v-select client-req-vselect" :append-to-body="true" >
                                     <template #open-indicator="{ attributes }">
                                          <span v-bind="attributes">
                                              <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                          </span>
                                      </template>
                                </v-select>
                            </div>
                            <div class="info-group">
                                <label class="form-label-custom">Quality Status</label>
                                <v-select v-model="clientReqForm.status_lead" :options="clientReqQualityStatusOptions" :reduce="(o) => o.value" label="text" placeholder="Select Quality Status" class="custom-v-select client-req-vselect" :append-to-body="true" >
                                     <template #open-indicator="{ attributes }">
                                          <span v-bind="attributes">
                                              <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                          </span>
                                      </template>
                                </v-select>
                            </div>
                            <div class="info-group client-req-budget-row">
                                <label class="form-label-custom">Budget (AED)</label>
                                <div class="client-req-budget-inputs">
                                    <input v-model="clientReqBudgetFromDisplay" type="text" inputmode="numeric" placeholder="From" class="form-control custom-input flex-grow-1" @input="onClientReqBudgetFrom" />
                                    <input v-model="clientReqBudgetToDisplay" type="text" inputmode="numeric" placeholder="To" class="form-control custom-input flex-grow-1" @input="onClientReqBudgetTo" />
                                </div>
                            </div>
                            <div class="info-group info-group--full mb-0"  v-if="clientReqForm.lead_type?.toLowerCase() !== 'rent'">
                                <label class="form-label-custom">Purpose Of Purchase</label>
                                <v-select v-model="clientReqForm.purpose_buying" :options="clientReqPurposeOptions" :reduce="(o) => o.value" label="text" placeholder="Select Purpose" class="custom-v-select client-req-vselect" :append-to-body="true" >
                                     <template #open-indicator="{ attributes }">
                                          <span v-bind="attributes">
                                              <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                          </span>
                                      </template>
                                </v-select>
                            </div>
                            <div v-if="clientReqSaveError" class="alert alert-danger py-2 small mt-2 mb-0">{{ clientReqSaveError }}</div>
                            <div class="modal-footer-custom mt-3 d-flex justify-content-end gap-2">
                                <b-button variant="light" :disabled="isSavingClientReq" @click="closeClientReqEditor">Cancel</b-button>
                                <b-button variant="warning" :disabled="isSavingClientReq" @click="saveClientRequirement">
                                    <b-spinner v-if="isSavingClientReq" small class="me-1"></b-spinner>
                                    {{ isSavingClientReq ? 'Saving…' : 'Save' }}
                                </b-button>
                            </div>
                        </div>
                    </div>
                    <div
                        v-else
                        class="client-req-block client-req-block--extra"
                    >
                        <div v-if="showEditIcon && canEdit" class="client-req-block-actions">
                            <div class="client-req-action-main">
                                <button
                                    type="button"
                                    class="client-qualification-btn"
                                    :class="{ 'is-active': qualificationSourceId === req.id }"
                                    title="Use this requirement for Lead Qualification"
                                    @click="selectQualificationSource(req.id)"
                                >
                                    <iconify-icon icon="lucide:badge-check" />
                                    <span>{{ qualificationSourceId === req.id ? 'Selected priority' : 'Set as priority'}}</span>
                                </button>
                            </div>
                            <span class="client-req-created-at">{{ formatUpdatedAtDisplay(req.updated_at || req.created_at) }}</span>
                            <button
                                type="button"
                                class="lead-edit-inline-btn"
                                title="Edit this requirement"
                                @click="openClientReqModal(idx)"
                            >
                                <iconify-icon class="edit-icon-btn" color="#733E87" icon="lucide:pencil"></iconify-icon>
                            </button>
                        </div>
                        <div class="client-requirement-list">
                            <div class="info-group client-req-location" v-if="req.area_label || req.area_id">
                                <label class="form-label-custom">Location</label>
                                <div class="info-value location-selected-view">
                                    <i class="ri-map-pin-line location-option-icon"></i>
                                    <div class="location-option-text">
                                        <span class="location-option-name">{{ locationFirstLine(req.area_label || '—') }}</span>
                                        <span class="location-option-subtitle">{{ locationSecondLine(req.area_label || '') }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="info-group" v-if="req.property_type_label" >
                                <label class="form-label-custom">Property Type</label>
                                <div class="info-value">{{ req.property_type_label }}</div>
                            </div>
                            <div class="info-group" v-if="req.lead_type">
                                <label class="form-label-custom">Lead Type</label>
                                <div class="info-value">{{ displayLeadType(req.lead_type) }}</div>
                            </div>
                            <div class="info-group" v-if="req.property_status && !isReqRentOnly(req)">
                                <label class="form-label-custom">Property Status</label>
                                <div class="info-value">{{ displayPropertyStatus(req.property_status) }}</div>
                            </div>
                            <div class="info-group" v-if="req.bedrooms !== null && req.bedrooms !== undefined && req.bedrooms !== '' && !isReqPlotsOrLand(req)">
                                <label class="form-label-custom">Bedrooms</label>
                                <div class="info-value">{{ formatBedroomsDisplay(req.bedrooms) }}</div>
                            </div>
                            <div class="info-group" v-if="formatLeadBudgetRange(req)">
                                <label class="form-label-custom">Budget (AED)</label>
                                <div class="info-value">{{ formatLeadBudgetRange(req) }}</div>
                            </div>
                            <div class="info-group" v-if="req.purpose_buying && !isReqRentOnly(req)">
                                <label class="form-label-custom">Purpose Of Purchase</label>
                                <div class="info-value">{{ req.purpose_buying }}</div>
                            </div>
                        </div>
                    </div>
                </template>

                <div v-if="clientRequirementEmptyHint" class="info-empty client-req-empty-hint">
                    No client requirement yet. Use + to add one, or edit the lead for the primary fields.
                </div>
            </div>
        </div>

        <div
            v-if="showClientReqEditor && editingExtraIndex === null"
            ref="newClientReqEditorRef"
            class="info-section client-req-inline-editor section-highlight"
        >
            <div class="info-section-title mb-2">{{ clientReqModalTitle }}</div>
            <div class="client-req-modal-body">
                <div class="info-group">
                    <label class="form-label-custom">Location</label>
                    <v-select
                        v-model="clientReqForm.area_id"
                        :options="clientReqAreas"
                        :reduce="(a) => a.id"
                        :disabled="isLoadingClientReqAreas"
                        label="name"
                        placeholder="Select area"
                        class="custom-v-select client-req-vselect location-select"
                        :append-to-body="true"
                    >
                         <template #open-indicator="{ attributes }">
                                          <span v-bind="attributes">
                                              <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                          </span>
                                      </template>
                        <template #option="option">
                            <div class="location-option">
                                <i class="ri-map-pin-line location-option-icon"></i>
                                <div class="location-option-text">
                                    <span class="location-option-name">{{ locationFirstLineFromOption(option) }}</span>
                                    <span class="location-option-subtitle">{{ locationSecondLineFromOption(option) }}</span>
                                </div>
                            </div>
                        </template>
                        <template #selected-option="option">
                            <div v-if="option" class="location-selected">
                                <span class="location-selected-name">{{ locationFirstLineFromOption(option) }}</span>
                                <span class="location-selected-subtitle">{{ locationSecondLineFromOption(option) }}</span>
                            </div>
                        </template>
                    </v-select>
                </div>
                <div class="info-group" v-if="!isCurrentReqRentOnly">
                    <label class="form-label-custom">Property Status <span class="text-danger">*</span></label>
                    <v-select
                        v-model="clientReqForm.property_status"
                        :options="clientReqPropertyStatusOptions"
                        :reduce="(o) => o.value"
                        label="text"
                        placeholder="Select Property Status"
                        class="custom-v-select client-req-vselect"
                        :append-to-body="true"
                    >
                         <template #open-indicator="{ attributes }">
                                          <span v-bind="attributes">
                                              <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                          </span>
                                      </template>
                    </v-select>
                </div>
                <div class="info-group">
                    <label class="form-label-custom">Property Type</label>
                    <v-select
                        v-model="clientReqForm.property_type_id"
                        :options="clientReqPropertyTypeOptions"
                        :reduce="(o) => o.value"
                        label="text"
                        placeholder="Select Property Type"
                        class="custom-v-select client-req-vselect"
                        :append-to-body="true"
                    >
                          <template #open-indicator="{ attributes }">
                              <span v-bind="attributes">
                                  <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                              </span>
                          </template>
                    </v-select>
                </div>
                <div class="info-group">
                    <label class="form-label-custom">Lead Type <span class="text-danger">*</span></label>
                    <v-select
                        v-model="clientReqForm.lead_type"
                        :options="clientReqLeadTypeOptions"
                        :reduce="(o) => o.value"
                        label="text"
                        placeholder="Select Lead Type"
                        class="custom-v-select client-req-vselect"
                        :append-to-body="true"
                    >
                         <template #open-indicator="{ attributes }">
                                          <span v-bind="attributes">
                                              <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                          </span>
                                      </template>
                    </v-select>
                </div>
                <div class="info-group" v-if="!isCurrentReqPlotsOrLand">
                    <label class="form-label-custom">Bedrooms</label>
                    <v-select
                        v-model="clientReqForm.bedrooms"
                        :options="clientReqBedroomOptions"
                        :reduce="(o) => o.value"
                        label="text"
                        placeholder="Select Bedrooms"
                        class="custom-v-select client-req-vselect"
                        :append-to-body="true"
                    >
                         <template #open-indicator="{ attributes }">
                                          <span v-bind="attributes">
                                              <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                          </span>
                                      </template>
                    </v-select>
                </div>
                <div class="info-group">
                    <label class="form-label-custom">Quality Status</label>
                    <v-select
                        v-model="clientReqForm.status_lead"
                        :options="clientReqQualityStatusOptions"
                        :reduce="(o) => o.value"
                        label="text"
                        placeholder="Select Quality Status"
                        class="custom-v-select client-req-vselect"
                        :append-to-body="true"
                    >
                         <template #open-indicator="{ attributes }">
                                          <span v-bind="attributes">
                                              <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                          </span>
                                      </template>
                    </v-select>
                </div>
                <div class="info-group client-req-budget-row">
                    <label class="form-label-custom">Budget (AED)</label>
                    <div class="client-req-budget-inputs">
                        <input
                            v-model="clientReqBudgetFromDisplay"
                            type="text"
                            inputmode="numeric"
                            placeholder="From"
                            class="form-control custom-input flex-grow-1"
                            @input="onClientReqBudgetFrom"
                        />
                        <input
                            v-model="clientReqBudgetToDisplay"
                            type="text"
                            inputmode="numeric"
                            placeholder="To"
                            class="form-control custom-input flex-grow-1"
                            @input="onClientReqBudgetTo"
                        />
                    </div>
                </div>
                <div class="info-group info-group--full mb-0" v-if="!isCurrentReqRentOnly">
                    <label class="form-label-custom">Purpose Of Purchase</label>
                    <v-select
                        v-model="clientReqForm.purpose_buying"
                        :options="clientReqPurposeOptions"
                        :reduce="(o) => o.value"
                        label="text"
                        placeholder="Select Purpose"
                        class="custom-v-select client-req-vselect"
                        :append-to-body="true"
                    >
                         <template #open-indicator="{ attributes }">
                                          <span v-bind="attributes">
                                              <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                          </span>
                                      </template>
                    </v-select>
                </div>
                <div v-if="clientReqSaveError" class="alert alert-danger py-2 small mt-2 mb-0">{{ clientReqSaveError }}</div>
                <div class="modal-footer-custom mt-3 d-flex justify-content-end gap-2">
                    <b-button variant="light" :disabled="isSavingClientReq" @click="closeClientReqEditor">Cancel</b-button>
                    <b-button variant="warning" :disabled="isSavingClientReq" @click="saveClientRequirement">
                        <b-spinner v-if="isSavingClientReq" small class="me-1"></b-spinner>
                        {{ isSavingClientReq ? 'Saving…' : 'Save' }}
                    </b-button>
                </div>
            </div>
        </div>

        <MatchingPropertiesSection v-if="lead?.id" :lead="lead" />

        <div v-if="showResponsibleSection" class="info-section">
            <div class="info-section-title">Responsible Person</div>
            <div class="info-group">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <label class="form-label-custom">Responsible Person</label>
                <b-button 
                    variant="link" 
                    class="p-0 edit-person-btn"
                    @click="openPersonModal"
                    :disabled="isUpdatingPerson"
                >
                    <iconify-icon icon="lucide:edit" class="edit-icon"></iconify-icon>
                    <span>Change</span>
                </b-button>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="avatar-wrapper person-hover-anchor" @mouseenter="showPersonCard = true" @mouseleave="showPersonCard = false">
                    <img 
                        v-if="lead?.responsible_person?.avatar" 
                        :src="lead?.responsible_person?.avatar" 
                        class="avatar-md rounded-circle" 
                        
                    />
                    <div v-else class="avatar-placeholder">
                        <iconify-icon icon="lucide:user" class="avatar-icon"></iconify-icon>
                    </div>
                    <transition name="person-card-pop">
                        <div v-if="showPersonCard" class="person-hover-card">
                            <div class="person-hover-head">
                                <img
                                    v-if="lead?.responsible_person?.avatar"
                                    :src="lead?.responsible_person?.avatar"
                                    alt=""
                                    class="person-hover-avatar"
                                />
                                <div v-else class="person-hover-avatar person-hover-avatar-fallback">
                                    <iconify-icon icon="lucide:user" class="avatar-icon"></iconify-icon>
                                </div>
                                <div>
                                    <div class="person-hover-name">{{ lead?.responsible_person?.name || '—' }}</div>
                                    <div class="person-hover-role">{{ lead?.responsible_person?.position || lead?.responsible_person?.role_name || 'Team Member' }}</div>
                                </div>
                            </div>
                            <div class="person-hover-line">
                                <span>Reports To</span>
                                <b>{{ lead?.responsible_person?.manager_name || lead?.responsible_person?.team_lead_name || 'Not specified' }}</b>
                            </div>
                            <div class="person-hover-line">
                                <span>Branch</span>
                                <b>{{ lead?.responsible_person?.branch_name || lead?.lead_branch_source || 'Not specified' }}</b>
                            </div>
                        </div>
                    </transition>
                </div>
                <div class="flex-grow-1">
                    <div class="info-value">{{ lead?.responsible_person?.name || '—' }}</div>
                </div>
            </div>
            
            <!-- Person Update Modal -->
            <b-modal
                v-model="showPersonModal"
                title=" Responsible "
                hide-footer
                size="md"
                class="person-modal"
                @hidden="resetPersonModal"
            >
                <div class="person-modal-content">
                    <!-- Search Input -->
                    <div class="search-input-wrapper mb-3">
                        <b-form-input 
                            v-model="personSearchQuery" 
                            placeholder="Search Person by name or email" 
                            class="person-search-input"
                        />
                        <iconify-icon icon="lucide:search" class="search-icon"></iconify-icon>
                    </div>
                    
                    <!-- Loading State -->
                    <div v-if="isLoadingPersons" class="text-center py-4">
                        <b-spinner small variant="warning" label="Loading..."></b-spinner>
                        <p class="mt-2 text-muted">Loading persons...</p>
                    </div>
                    
                    <!-- Users List -->
                    <div v-else class="person-list-scroll">
                        <div 
                            v-for="user in filteredPersons" 
                            :key="user.id"
                            class="person-item d-flex align-items-center justify-content-between p-2"
                            @click="selectPerson(user)"
                            :class="{ 
                                'selected': selectedPersonId === user.id,
                                'current': lead?.responsible_person?.id === user.id
                            }"
                        >
                            <div class="d-flex align-items-center gap-2">
                                <img 
                                    :src="user.avatar || 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQz_em9Ua12dTx64KMpyFSdH1sbuA2Ud5BKxQ&s'" 
                                    class="person-item-avatar" 
                                />
                                <div class="person-item-info">
                                    <div class="person-item-name">
                                        <span class="user-item-name">{{ user.name }}</span>
                                           <span v-if="user.role_name" class="user-position-badge">
                                                    {{user.role_name }}
                                                </span>
                                    </div>
                                    <div class=" user-item-meta-line"> 
                                                <span class="meta-label">Parent:</span>
                                                <span class="meta-value">{{ user.parent_name }}</span>
                                                <span class="meta-divider" v-if="user.branch_name">|</span>
                                                <span class="meta-label" v-if="user.branch_name">Branch:</span>
                                                <span class="meta-value" v-if="user.branch_name">{{ user.branch_name}}</span></div>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span v-if="lead?.responsible_person?.id === user.id" class="current-badge">
                                    Current
                                </span>
                                <iconify-icon 
                                    v-if="selectedPersonId === user.id" 
                                    icon="lucide:check" 
                                    class="text-warning"
                                ></iconify-icon>
                            </div>
                        </div>
                        
                        <div v-if="filteredPersons?.length === 0" class="text-center p-4 text-muted">
                            <iconify-icon icon="lucide:users" class="mb-2" width="40" height="40"></iconify-icon>
                            <p>No persons found matching "{{ personSearchQuery }}"</p>
                        </div>
                    </div>
                    
                    <!-- Person Update Error -->
                    <div v-if="personUpdateError" class="alert alert-danger mt-3 py-2">
                        <iconify-icon icon="lucide:alert-circle" class="me-1"></iconify-icon>
                        {{ personUpdateError }}
                    </div>
                    
                    <!-- Modal Footer -->
                    <div class="modal-footer-custom mt-3">
                        <b-button 
                            variant="light" 
                            @click="showPersonModal = false"
                            :disabled="isUpdatingPerson"
                        >
                            Cancel
                        </b-button>
                        <b-button 
                            variant="warning" 
                            @click="updateResponsiblePerson"
                            :disabled="!selectedPersonId || isUpdatingPerson || selectedPersonId === lead?.responsible_person?.id"
                        >
                            <b-spinner v-if="isUpdatingPerson" small></b-spinner>
                            <span v-else>Update Person</span>
                        </b-button>
                    </div>
                </div>
            </b-modal>
        </div>
        
        <!--<div class="info-group" v-if="lead?.lead_source">-->
        <!--    <label class="form-label-custom">lead source</label>-->
        <!--    <div class="info-value">{{ lead?.lead_source || '—' }}</div>-->
        <!--</div>-->
        
        <!--<div class="info-group" v-if="lead?.lead_source">-->
        <!--    <label class="form-label-custom">Lead Branch Source</label>-->
        <!--    <div class="info-value">{{ lead?.lead_branch_source || '—' }}</div>-->
        <!--</div>-->
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import { BButton, BModal, BFormInput, BSpinner } from 'bootstrap-vue-3'
import vSelect from 'vue-select'
import 'vue-select/dist/vue-select.css'
import api from '@/plugins/axios'
import { formatLeadBudgetRange, formatBudgetThousands, parseBudgetThousandsInput } from '@/utils/budgetInput'
import MatchingPropertiesSection from './MatchingPropertiesSection.vue'

const props = defineProps({
    lead: Object,
    showResponsibleSection: {
        type: Boolean,
        default: true,
    },
    canEdit: {
        type: Boolean,
        default: false,
    },
    showEditIcon: {
        type: Boolean,
        default: false,
    },
})

const emit = defineEmits(['person-updated', 'edit-request', 'edit-section', 'lead-updated'])

const user = ref(JSON.parse(localStorage.getItem('user') || '{}'))

// Person modal state
const showPersonModal = ref(false)
const isLoadingPersons = ref(false)
const isUpdatingPerson = ref(false)
const personSearchQuery = ref('')
const personsList = ref([])
const selectedPersonId = ref(null)
const personUpdateError = ref('')
const showPersonCard = ref(false)

// Computed property for permission
const canView = computed(() => {
    if (!user.value?.roles) return false
    const isAdmin = user.value.roles.includes('super_admin') || user.value.roles.includes('admin')
    const isResponsible = props.lead?.responsible_person_id === user.value.id
    return isAdmin || isResponsible
})

// Filter persons based on search query
const filteredPersons = computed(() => {
    if (!personSearchQuery.value) return personsList.value
    
    const query = personSearchQuery.value.toLowerCase()
    return personsList.value.filter(person => 
        person.name.toLowerCase().includes(query) ||
        person.email.toLowerCase().includes(query)
    )
})

// Fetch available persons
const fetchAvailablePersons = async () => {
    isLoadingPersons.value = true
    personUpdateError.value = ''
    
    try {
        const response = await api.get('/available-responsible-persons')
        personsList.value = response.data.data || response.data || []
    } catch (error) {
        console.error('Error fetching persons:', error)
        personUpdateError.value = 'Failed to load persons list'
    } finally {
        isLoadingPersons.value = false
    }
}

// Open person modal
const openPersonModal = () => {
    selectedPersonId.value = props.lead?.responsible_person?.id || null
    personSearchQuery.value = ''
    personUpdateError.value = ''
    fetchAvailablePersons()
    showPersonModal.value = true
}

// Select person
const selectPerson = (person) => {
    selectedPersonId.value = person.id
}

// Reset modal
const resetPersonModal = () => {
    selectedPersonId.value = null
    personSearchQuery.value = ''
    personsList.value = []
    personUpdateError.value = ''
}

// Update responsible person
const updateResponsiblePerson = async () => {
    if (!selectedPersonId.value) return
    
    isUpdatingPerson.value = true
    personUpdateError.value = ''
    
    try {
        const response = await api.post(`/leads/${props.lead.id}/assign-responsible-person`, {
            responsible_person_id: selectedPersonId.value
        })
        
        // Find the selected person details
        const selectedPerson = personsList.value.find(p => p.id === selectedPersonId.value)
        
        // Emit the updated person data
        emit('person-updated', {
            id: selectedPersonId.value,
            name: selectedPerson?.name,
            avatar: selectedPerson?.avatar
        })
        
        // Show success notification
        if (window.$showNotification) {
            window.$showNotification('Responsible person updated successfully!', 'success')
        }
        
        // Close modal
        showPersonModal.value = false
        
    } catch (error) {
        console.error('Error updating responsible person:', error)
        
        if (error.response?.status === 422) {
            // Validation errors
            const errors = error.response.data.errors || error.response.data
            personUpdateError.value = Object.values(errors)[0]?.[0] || 'Validation error'
        } else {
            personUpdateError.value = error.response?.data?.message || 'Failed to update responsible person'
        }
        
        if (window.$showNotification) {
            window.$showNotification(personUpdateError.value, 'error')
        }
    } finally {
        isUpdatingPerson.value = false
    }
}

// Mask value function
const maskValue = (value) => {
    if (!value) return ''
    return '★'.repeat(value.length)
}

// Format question function
const formatQuestion = (question) => {
    if (!question) return ''
    return question
        .replace(/_/g, ' ')
        .replace(/\b\w/g, l => l.toUpperCase())
}

const formatText = (raw) => {
    if (raw == null || raw === '') return '—'
    return String(raw)
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (c) => c.toUpperCase())
}

// دالة لتنسيق lost reason
const formatLostReason = (reason) => {
    if (!reason) return '—'
    const mapping = {
        // 'lost_by_other_company': 'Lost by Other Company',
        // 'lost_by_our_company': 'Lost by Our Company'
        'already_bought': "Already bought" 
    }
    return mapping[reason] || formatText(reason)
}
// ==================== Budget Validation ====================
const budgetRangeError = ref('')

const validateBudgetRange = () => {
    const from = parseFloat(clientReqForm.value.budget_from)
    const to = parseFloat(clientReqForm.value.budget_to)
    
    if (from && to && from > to) {
        budgetRangeError.value = 'Budget From cannot be greater than Budget To'
        return false
    }
    budgetRangeError.value = ''
    return true
}


// دالة لتنسيق lead status حسب المرحلة
const formatLeadStatus = (status, stageOrder = null) => {
    if (!status) return '—'
    
    // إذا تم تمرير رقم المرحلة، نستخدم mapping مناسب
    const order = stageOrder !== null ? stageOrder : currentStageOrder.value
    
    // Stage 4: Qualified - Hot/Warm/Cold فقط
    if (order === 4) {
        const qualifiedMapping = {
            'cold': 'Cold Lead',
            'warm': 'Warm Lead',
            'hot': 'Hot Lead'
        }
        return qualifiedMapping[status] || formatText(status)
    }
    
    // Stage 9: Lead Pool
    if (order === 9) {
        const leadPoolMapping = {
            'no_answer': 'No Answer',
            'contacted': 'Contacted',
            'wrong_person': 'Wrong Person'
        }
        return leadPoolMapping[status] || formatText(status)
    }
    
    // Stage 10: Unqualified
    if (order === 10) {
        const unqualifiedMapping = {
            // 'not_interested': 'Not Interested',
            'wrong_contact_details': 'Wrong Contact Details',
            'job_seeker': 'Job Seeker',
            'broker': 'Broker',
            'registered_by_mistake': 'Registered by Mistake',
            'spam_leads':'Spam Leads',
            'blacklist':'Black Lists',
        }
        return unqualifiedMapping[status] || formatText(status)
    }
    
    // Default mapping لبقية المراحل
    const defaultMapping = {
        'cold': 'Cold Lead',
        'warm': 'Warm Lead',
        'hot': 'Hot Lead',
        'no_answer': 'No Answer',
        'contacted': 'Contacted',
        'wrong_person': 'Wrong Person',
        'canceled': 'Canceled',
        'not_interested': 'Not Interested',
        'wrong_contact_details': 'Wrong Contact Details',
        'no_answer_multiple_calls': 'No Answer — Multiple Calls',
        'job_seeker': 'Job Seeker',
        'broker': 'Broker',
        'registered_by_mistake': 'Registered by Mistake',
        'spam_leads': 'Spam Leads',
        'already_assigned_to_another_agent': 'Already Assigned to Another Agent',
        'client_was_just_searching_online': 'Client Was Just Searching Online',
        'number_does_not_exist': 'Number Does Not Exist'
    }
    
    return defaultMapping[status] || formatText(status)
}

// دالة عامة مع إمكانية إضافة mapping مخصص
const formatWithMapping = (value, mapping = {}) => {
    if (!value) return '—'
    return mapping[value] || formatText(value)
}

const locationFirstLine = (areaValue) => {
    const name = String(areaValue || '').trim()
    if (!name) return '—'
    const idx = name.indexOf(',')
    return idx > 0 ? name.slice(0, idx).trim() : name
}

const locationSecondLine = (areaValue) => {
    const name = String(areaValue || '').trim()
    if (!name) return 'UAE'
    const idx = name.indexOf(',')
    const rest = idx > 0 ? name.slice(idx + 1).trim() : ''
    return rest || 'UAE'
}
// Basic fields for Facebook questions
const basicFields = ['email', 'phone', 'full_name', 'name', 'work_phone','work_phone_number','phone_number','full name', 'first_name', 'last_name','Date','Time','Page_Name','inbox_url','form_name','form_id','No_Label_name','No_Label_email','No_Label_phone']

// Facebook questions computed
const facebookQuestions = computed(() => {
    if (!props.lead?.facebook_questions_answers) {
        return {}
    }
    
    const fields = {}
    Object.keys(props.lead.facebook_questions_answers).forEach(key => {
        if (!basicFields.includes(key) && props.lead.facebook_questions_answers[key]) {
            fields[key] = props.lead.facebook_questions_answers[key]
        }
    })
    
    return fields
})

const hasAdditionalFacebookQuestions = computed(() => {
    return Object.keys(facebookQuestions.value).length > 0
})

const metaQuestions = computed(() => {
    const meta = props.lead?.meta
    if (!meta || typeof meta !== 'object') return {}
    const result = {}
    Object.keys(meta).forEach((key) => {
        const value = meta[key]
        if (
            value == null ||
            value === '' ||
            typeof value === 'object' ||
            basicFields.includes(key) ||
            ['client_required_info', 'additional_fields', 'questions_answers'].includes(key)
        ) return
        result[key] = value
    })
    return result
})

const clientRequiredMetaFields = computed(() => {
    const meta = props.lead?.meta
    const source =
        props.lead?.client_required_info ||
        props.lead?.additional_fields ||
        meta?.client_required_info ||
        meta?.additional_fields
    if (!source || typeof source !== 'object') return {}
    return Object.fromEntries(
        Object.entries(source).filter(([, value]) => value !== null && value !== '')
    )
})

const hasAdditionalQuestions = computed(() => {
    return hasAdditionalFacebookQuestions.value || Object.keys(metaQuestions.value).length > 0
})
const isPlotsOrLand = computed(() => {
    const propertyType = props.lead?.property_type?.toLowerCase() || ''
    const propertyTypeName = props.lead?.property_type_name?.toLowerCase() || ''
    const propertyTypeLabel = props.lead?.property_type_label?.toLowerCase() || ''
    
    if (propertyType.includes('plot') || propertyType.includes('land') ||
        propertyTypeName.includes('plot') || propertyTypeName.includes('land') ||
        propertyTypeLabel.includes('plot') || propertyTypeLabel.includes('land')) {
        return true
    }
    return false
})

const isRentOnly = computed(() => {
    return props.lead?.lead_type?.toLowerCase() === 'rent'
})

const isReqPlotsOrLand = (req) => {
    const propertyType = req?.property_type_label?.toLowerCase() || ''
    return propertyType.includes('plot') || propertyType.includes('land')
}

const isReqRentOnly = (req) => {
    return req?.lead_type?.toLowerCase() === 'rent'
}
const isCurrentReqRentOnly = computed(() => {
    if (editingExtraIndex.value !== null && extraClientRequirementsList.value[editingExtraIndex.value]) {
        const req = extraClientRequirementsList.value[editingExtraIndex.value]
        return req?.lead_type?.toLowerCase() === 'rent'
    }
    return clientReqForm.value.lead_type?.toLowerCase() === 'rent'
})

const isCurrentReqPlotsOrLand = computed(() => {
    if (editingExtraIndex.value !== null && extraClientRequirementsList.value[editingExtraIndex.value]) {
        const req = extraClientRequirementsList.value[editingExtraIndex.value]
        const propertyType = req?.property_type_label?.toLowerCase() || ''
        return propertyType.includes('plot') || propertyType.includes('land')
    }
    const propertyTypeId = clientReqForm.value.property_type_id
    if (!propertyTypeId) return false
    
    const selectedType = clientReqPropertyTypeOptions.value.find(opt => opt.value === propertyTypeId)
    if (!selectedType) return false
    
    const typeName = selectedType.text.toLowerCase()
    return typeName.includes('plot') || typeName.includes('land')
})
const isPlotsOrLandByTypeId = (propertyTypeId) => {
    if (!propertyTypeId) return false
    const selectedType = clientReqPropertyTypeOptions.value.find(opt => opt.value === propertyTypeId)
    if (!selectedType) return false
    const typeName = selectedType.text.toLowerCase()
    return typeName.includes('plot') || typeName.includes('land')
}
const handleLeadTypeChange = () => {
    if (clientReqForm.value.lead_type?.toLowerCase() === 'rent') {
        clientReqForm.value.property_status = null
        clientReqForm.value.purpose_buying = null
    }
}

const handlePropertyTypeChange = () => {
    const propertyTypeId = clientReqForm.value.property_type_id
    if (!propertyTypeId) return
    
    const selectedType = clientReqPropertyTypeOptions.value.find(opt => opt.value === propertyTypeId)
    if (!selectedType) return
    
    const typeName = selectedType.text.toLowerCase()
    if (typeName.includes('plot') || typeName.includes('land')) {
        clientReqForm.value.bedrooms = null
    }
}
const applyConditionsToCurrentReq = () => {
    if (clientReqForm.value.lead_type?.toLowerCase() === 'rent') {
        clientReqForm.value.property_status = null
        clientReqForm.value.purpose_buying = null
    }
    
    const propertyTypeId = clientReqForm.value.property_type_id
    if (propertyTypeId) {
        const selectedType = clientReqPropertyTypeOptions.value.find(opt => opt.value === propertyTypeId)
        if (selectedType) {
            const typeName = selectedType.text.toLowerCase()
            if (typeName.includes('plot') || typeName.includes('land')) {
                clientReqForm.value.bedrooms = null
            }
        }
    }
}
const QUAL_META_ID = '__qualification_meta__'
const QUAL_META_KIND = 'qualification_meta'

const localExtraClientRequirements = ref([])

watch(
    () => props.lead?.extra_client_requirements,
    (raw) => {
        localExtraClientRequirements.value = Array.isArray(raw) ? [...raw] : []
    },
    { immediate: true }
)

const extraClientRequirementsList = computed(() =>
    localExtraClientRequirements.value.filter((item) => item?._kind !== QUAL_META_KIND)
)

const qualificationSourceId = computed(() => {
    const meta = localExtraClientRequirements.value.find((item) => item?._kind === QUAL_META_KIND)
    const source = meta?.source || 'primary'
    if (source === 'primary') return 'primary'
    const exists = extraClientRequirementsList.value.some((req) => req.id === source)
    return exists ? source : 'primary'
})

const hasPrimaryClientCoreContent = computed(() => {
    const l = props.lead
    if (!l) return false
    const hasBudget =
        formatLeadBudgetRange(l) && Number(l.budget_from) > 0 && Number(l.budget_to) > 0
    return Boolean(
        l.area ||
            l.property_type ||
            l.lead_type ||
            l.property_status ||
            (l.bedrooms !== null && l.bedrooms !== undefined && l.bedrooms !== '') ||
            hasBudget ||
            l.purpose_buying
    )
})

const hasClientTailContent = computed(() => {
    return Boolean(
        props.lead?.branch != null ||
            props.lead?.available_date != null ||
            props.lead?.source_information ||
            props.lead?.unqualified_status ||
            props.lead?.why_lost_lead ||
            Object.keys(clientRequiredMetaFields.value).length > 0
    )
})

const hasPrimaryClientBlockContent = computed(() => {
    return hasPrimaryClientCoreContent.value || hasClientTailContent.value
})

const hasClientRequiredInfo = computed(() => {
    return Boolean(
        extraClientRequirementsList.value.length > 0 ||
            props.lead?.bedrooms ||
            props.lead?.area ||
            props.lead?.property_type ||
            props.lead?.lead_type ||
            props.lead?.property_status ||
            props.lead?.purpose_buying ||
            props.lead?.source_information ||
            props.lead?.budget > 0 ||
            props.lead?.budget_from > 0 ||
            props.lead?.budget_to > 0 ||
            props.lead?.branch != null ||
            props.lead?.available_date != null ||
            props.lead?.status_lead != null ||
            props.lead?.unqualified_status != null ||
            props.lead?.why_lost_lead != null ||
            Object.keys(clientRequiredMetaFields.value).length > 0
    )
})

const showClientRequirementArea = computed(() => {
    if (props.canEdit && props.showEditIcon) return true
    return hasClientRequiredInfo.value
})

const clientRequirementEmptyHint = computed(() => {
    return (
        props.canEdit &&
        props.showEditIcon &&
        !hasPrimaryClientBlockContent.value &&
        extraClientRequirementsList.value.length === 0
    )
})

const displayLeadType = (v) => {
    if (!v) return '—'
    const m = { sale: 'Sale', rent: 'Rent' ,both:'Both'}
    return m[String(v).toLowerCase()] || formatText(v)
}

const displayPropertyStatus = (v) => {
    if (!v) return '—'
    const m = { ready: 'Ready', off_plan: 'Off Plan', both: 'Both' }
    return m[String(v).toLowerCase()] || formatText(v)
}

const formatBedroomsDisplay = (b) => {
    if (b === null || b === undefined || b === '') return '—'
    if (b === 0 || b === '0' || String(b).toLowerCase() === 'studio') return 'Studio'
    return String(b)
}

const formatUpdatedAtDisplay = (input) => {
    if (!input) return 'Updated: —'
    const d = new Date(input)
    if (Number.isNaN(d.getTime())) return 'Updated: —'
    return `Updated: ${d.toLocaleDateString('en-GB')}`
}

const hasExtraBlockDisplay = (req) => {
    if (!req) return false
    const hasBudget = Number(req.budget_from) > 0 || Number(req.budget_to) > 0
    return Boolean(
        req.area_label ||
            req.area_id ||
            req.property_type_label ||
            req.property_type_id ||
            req.lead_type ||
            req.property_status ||
            req.status_lead ||
            (req.bedrooms !== null && req.bedrooms !== undefined && req.bedrooms !== '') ||
            hasBudget ||
            req.purpose_buying
    )
}

const visibleExtraClientRequirements = computed(() =>
    extraClientRequirementsList.value.filter((req) => hasExtraBlockDisplay(req))
)

const locationFirstLineFromOption = (option) => {
    const name = String(option?.name || '').trim()
    if (!name) return '—'
    const idx = name.indexOf(',')
    return idx > 0 ? name.slice(0, idx).trim() : name
}

const locationSecondLineFromOption = (option) => {
    const name = String(option?.name || '').trim()
    const idx = name.indexOf(',')
    const rest = idx > 0 ? name.slice(idx + 1).trim() : ''
    if (rest) return rest
    return option?.parent || 'UAE'
}

// ——— Extra client requirement modal ———
const showClientReqEditor = ref(false)
const isSavingClientReq = ref(false)
const clientReqSaveError = ref('')
const editingExtraIndex = ref(null)
const newClientReqEditorRef = ref(null)
const isLoadingClientReqAreas = ref(false)
const isLoadingClientReqPropertyTypes = ref(false)
const clientReqAreas = ref([])
const clientReqPropertyTypeOptions = ref([])
const clientReqBudgetFromDisplay = ref('')
const clientReqBudgetToDisplay = ref('')

const clientReqLeadTypeOptions = [
    { value: 'sale', text: 'Sale' },
    { value: 'rent', text: 'Rent' },
    // { value: 'both', text: 'Both' },
]

const clientReqPropertyStatusOptions = [
    { value: 'ready', text: 'Ready' },
    { value: 'off_plan', text: 'Off Plan' },
    { value: 'both', text: 'Both' },
]

const clientReqBedroomOptions = [
    { value: '0', text: 'Studio' },
    { value: 1, text: '1' },
    { value: 2, text: '2' },
    { value: 3, text: '3' },
    { value: 4, text: '4' },
    { value: 5, text: '5' },
    { value: 6, text: '6' },
    { value: 7, text: '7' },
    { value: 8, text: '8' },
    { value: 9, text: '9' },
]

// ==================== Quality Status Options based on Stage ====================
const currentStageOrder = computed(() => {
    return props.lead?.stage?.order || 0
})

const clientReqQualityStatusOptions = computed(() => {
    const stageOrder = currentStageOrder.value
    
    // Stage 4: Qualified - Hot/Warm/Cold فقط
    if (stageOrder === 4) {
        return [
            { value: 'cold', text: 'Cold Lead' },
            { value: 'warm', text: 'Warm Lead' },
            { value: 'hot', text: 'Hot Lead' }
        ]
    }
    
    // Stage 9: Lead Pool
    if (stageOrder === 9) {
        return [
            { value: 'no_answer', text: 'No Answer' },
            { value: 'contacted', text: 'Contacted' },
            { value: 'wrong_person', text: 'Wrong Person' }
        ]
    }
    
    // Stage 10: Unqualified
    if (stageOrder === 10) {
        return [
            { value: 'not_interested', text: 'Not Interested' },
            { value: 'wrong_contact_details', text: 'Wrong Contact Details' },
            { value: 'no_answer_multiple_calls', text: 'No Answer — Multiple Calls' },
            { value: 'job_seeker', text: 'Job Seeker' },
            { value: 'broker', text: 'Broker' },
            { value: 'registered_by_mistake', text: 'Registered by Mistake' },
            { value: 'spam_leads', text: 'Spam Leads' },
            { value: 'already_assigned_to_another_agent', text: 'Already Assigned to Another Agent' },
            { value: 'client_was_just_searching_online', text: 'Client Was Just Searching Online' },
            { value: 'number_does_not_exist', text: 'Number Does Not Exist' }
        ]
    }
    
    // Default لبقية المراحل (3,5,6,7,8)
    return [
        { value: 'cold', text: 'Cold' },
        { value: 'warm', text: 'Warm' },
        { value: 'hot', text: 'Hot' }
    ]
})
const clientReqPurposeOptions = [
    { value: 'Live in', text: 'Live in' },
    { value: 'Short-term investment', text: 'Short-term investment' },
    { value: 'Long-term investment', text: 'Long-term investment' },
    // { value: 'Holiday home', text: 'Holiday home' },
    // { value: 'Rental', text: 'Rental' },
]

const emptyClientReqForm = () => ({
    id: null,
    area_id: null,
    property_type_id: null,
    lead_type: null,
    property_status: null,
    bedrooms: null,
    status_lead: null,
    budget_from: null,
    budget_to: null,
    purpose_buying: null,
})

const clientReqForm = ref(emptyClientReqForm())

const clientReqModalTitle = computed(() =>
    editingExtraIndex.value === null ? 'Add client requirement' : 'Edit client requirement'
)

const makeClientReqId = () =>
    `ecr-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`

const normalizeRequirementRows = (rows) =>
    rows.map((row) => ({
        ...row,
        id: row?.id || makeClientReqId(),
    }))

const buildPersistedRequirementList = (rows, sourceId = 'primary') => {
    const normalized = normalizeRequirementRows(rows)
    const withFlags = normalized.map((row) => ({
        ...row,
        selected_for_qualification: sourceId !== 'primary' && row.id === sourceId,
    }))

    return [
        ...withFlags,
        { id: QUAL_META_ID, _kind: QUAL_META_KIND, source: sourceId },
    ]
}

const fetchClientReqLookups = async () => {
    if (clientReqAreas.value.length === 0) {
        try {
            isLoadingClientReqAreas.value = true
            const response = await api.get('/listings/areas/?has_listings=true')
            const data = response.data.data || response.data
            clientReqAreas.value = data.map((area) => ({
                id: area.id,
                name: area.name || area.title,
                parent: area.area_parents_title || null,
            }))
        } catch (e) {
            console.error(e)
        } finally {
            isLoadingClientReqAreas.value = false
        }
    }
    if (clientReqPropertyTypeOptions.value.length === 0) {
        try {
            isLoadingClientReqPropertyTypes.value = true
            const res = await api.get('/listings/property-types')
            const data = res.data.data || res.data
            clientReqPropertyTypeOptions.value = data.map((item) => ({
                value: item.id,
                text: item.name,
            }))
        } catch (e) {
            console.error(e)
        } finally {
            isLoadingClientReqPropertyTypes.value = false
        }
    }
}

const syncClientReqBudgetDisplays = () => {
    clientReqBudgetFromDisplay.value = formatBudgetThousands(clientReqForm.value.budget_from)
    clientReqBudgetToDisplay.value = formatBudgetThousands(clientReqForm.value.budget_to)
}

const extractInputValue = (raw) => (raw?.target?.value ?? raw ?? '').toString()

const onClientReqBudgetFrom = (raw) => {
    const { numeric, display } = parseBudgetThousandsInput(extractInputValue(raw))
    clientReqForm.value.budget_from = numeric
    clientReqBudgetFromDisplay.value = display
    validateBudgetRange() // إضافة التحقق
}

const onClientReqBudgetTo = (raw) => {
    const { numeric, display } = parseBudgetThousandsInput(extractInputValue(raw))
    clientReqForm.value.budget_to = numeric
    clientReqBudgetToDisplay.value = display
    validateBudgetRange() // إضافة التحقق
}

const resetClientReqForm = () => {
    clientReqForm.value = emptyClientReqForm()
    clientReqBudgetFromDisplay.value = ''
    clientReqBudgetToDisplay.value = ''
    clientReqSaveError.value = ''
}
watch(() => clientReqForm.value.lead_type, (newVal) => {
    if (newVal?.toLowerCase() === 'rent') {
        clientReqForm.value.property_status = null
        clientReqForm.value.purpose_buying = null
    }
})

watch(() => clientReqForm.value.property_type_id, (newVal) => {
    if (!newVal) return
    const selectedType = clientReqPropertyTypeOptions.value.find(opt => opt.value === newVal)
    if (selectedType) {
        const typeName = selectedType.text.toLowerCase()
        if (typeName.includes('plot') || typeName.includes('land')) {
            clientReqForm.value.bedrooms = null
        }
    }
})
const fillClientReqFormFromExtra = (req) => {
    if (!req) return
    let beds = req.bedrooms
    if (beds === 'studio' || beds === 'Studio') beds = '0'
    clientReqForm.value = {
        id: req.id || null,
        area_id: req.area_id ?? null,
        property_type_id: req.property_type_id ?? null,
        lead_type: req.lead_type ?? null,
        property_status: req.property_status ?? null,
        bedrooms: beds === 0 || beds === '0' ? '0' : beds,
        status_lead: req.status_lead ?? null,
        budget_from: req.budget_from ?? null,
        budget_to: req.budget_to ?? null,
        purpose_buying: req.purpose_buying ?? null,
    }
        applyConditionsToCurrentReq()

    syncClientReqBudgetDisplays()
}

const openClientReqModal = async (idx) => {
    editingExtraIndex.value = idx
    resetClientReqForm()
    await fetchClientReqLookups()
    if (idx !== null && extraClientRequirementsList.value[idx]) {
        fillClientReqFormFromExtra(extraClientRequirementsList.value[idx])
    }
    showClientReqEditor.value = true
    if (idx === null) {
        await nextTick()
        newClientReqEditorRef.value?.scrollIntoView({ behavior: 'smooth', block: 'nearest' })
    }
}

const closeClientReqEditor = () => {
    showClientReqEditor.value = false
    editingExtraIndex.value = null
    resetClientReqForm()
}

const buildClientReqPayload = () => {
    const areaOpt = clientReqAreas.value.find((a) => a.id === clientReqForm.value.area_id)
    const ptOpt = clientReqPropertyTypeOptions.value.find(
        (p) => p.value === clientReqForm.value.property_type_id
    )
    let area_label = ''
    if (areaOpt) {
        const sub = areaOpt.parent || 'UAE'
        area_label = `${areaOpt.name}, ${sub}`
    }
    const id = clientReqForm.value.id || makeClientReqId()
    return {
        id,
        created_at: clientReqForm.value.created_at || new Date().toISOString(),
        updated_at: new Date().toISOString(),
        area_id: clientReqForm.value.area_id,
        area_label,
        property_type_id: clientReqForm.value.property_type_id,
        property_type_label: ptOpt?.text || '',
        lead_type: clientReqForm.value.lead_type,
        property_status: clientReqForm.value.property_status,
        bedrooms: clientReqForm.value.bedrooms,
        status_lead: clientReqForm.value.status_lead,
        budget_from: clientReqForm.value.budget_from,
        budget_to: clientReqForm.value.budget_to,
        purpose_buying: clientReqForm.value.purpose_buying,
    }
}

const selectQualificationSource = async (sourceId) => {
    if (!props.lead?.id) return
    if (!sourceId) return
    if (qualificationSourceId.value === sourceId) return

    try {
        isSavingClientReq.value = true
        clientReqSaveError.value = ''
        const persisted = buildPersistedRequirementList(extraClientRequirementsList.value, sourceId)

        const response = await api.put(`/leads/${props.lead.id}/extra-client-requirements`, {
            extra_client_requirements: persisted,
        })

        localExtraClientRequirements.value = persisted
        emit('lead-updated', response.data)
        if (window.$showNotification) {
            window.$showNotification('Lead Qualification source updated', 'success')
        }
    } catch (err) {
        console.error(err)
        clientReqSaveError.value = err.response?.data?.message || err.message || 'Failed to update source'
        if (window.$showNotification) {
            window.$showNotification(clientReqSaveError.value, 'error')
        }
    } finally {
        isSavingClientReq.value = false
    }
}

const saveClientRequirement = async () => {
    if (!props.lead?.id) return
    clientReqSaveError.value = ''
     if (!validateBudgetRange()) {
        clientReqSaveError.value = budgetRangeError.value
        if (window.$showNotification) {
            window.$showNotification(budgetRangeError.value, 'warning')
        }
        return
    }
    
    isSavingClientReq.value = true
    try {
        const next = [...extraClientRequirementsList.value]
        const payload = buildClientReqPayload()
        if (!hasExtraBlockDisplay(payload)) {
            clientReqSaveError.value = 'Please fill at least one field.'
            isSavingClientReq.value = false
            return
        }
        if (editingExtraIndex.value === null) {
            next.push(payload)
        } else {
            next[editingExtraIndex.value] = payload
        }
        const source = qualificationSourceId.value === 'primary' ? 'primary' : qualificationSourceId.value
        const persisted = buildPersistedRequirementList(next, source)
        const response = await api.put(`/leads/${props.lead.id}/extra-client-requirements`, {
            extra_client_requirements: persisted,
        })
        localExtraClientRequirements.value = persisted
        emit('lead-updated', response.data)
        showClientReqEditor.value = false
        editingExtraIndex.value = null
        resetClientReqForm()
        if (window.$showNotification) {
            window.$showNotification('Client requirement saved', 'success')
        }
    } catch (err) {
        console.error(err)
        const apiErrors = err.response?.data?.errors
        if (apiErrors && typeof apiErrors === 'object') {
            const first = Object.values(apiErrors)[0]
            clientReqSaveError.value = Array.isArray(first) ? first[0] : String(first)
        } else {
            clientReqSaveError.value =
                err.response?.data?.message || err.message || 'Failed to save'
        }
        if (window.$showNotification) {
            window.$showNotification(clientReqSaveError.value, 'error')
        }
    } finally {
        isSavingClientReq.value = false
    }
}

</script>

<style scoped>
.client-requirement-wrap {
    margin-bottom: 18px;
}

.client-requirement-header-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
    padding: 0 2px;
}

.client-requirement-header-actions {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.client-requirement-panel {
    display: grid;
    gap: 12px;
    overflow: visible;
}

.client-req-block {
    position: relative;
    padding: 14px;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    background: #ffffff;
}

.client-req-block + .client-req-block {
    margin-top: 0;
    padding-top: 14px;
    border-top: 1px solid #e2e8f0;
}

.client-req-block-actions {
    position: static;
    display: flex;
    justify-content: flex-start;
    flex-direction: row;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 2px;
    width: 100%;
}

.client-req-action-main {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    justify-content: flex-start;
    width: auto;
    order: 1;
    margin-left: 0;
}

.client-req-created-at {
    font-size: 10px;
    color: #64748b;
    white-space: nowrap;
    padding-inline: 2px;
    order: 2;
    text-align: left;
    width: auto;
    margin-left: 2px;
}

.lead-edit-inline-btn {
    order: 3;
}


.client-req-block-actions .lead-edit-inline-btn {
    margin-left: auto;
}

.client-qualification-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: 1px solid #cbd5e1;
    background: #f8fafc;
    color: #334155;
    border-radius: 999px;
    padding: 2px 8px;
    font-size: 10px;
    font-weight: 600;
    transition: all 0.2s ease;
    min-height: 24px;
    max-width: none;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.client-qualification-btn.is-active {
    border-color: #16a34a;
    background: #dcfce7;
    color: #166534;
    box-shadow: 0 2px 8px rgba(22, 163, 74, 0.18);
}

.client-requirement-list--tail {
    margin-top: 8px;
    padding-top: 10px;
    border-top: 1px solid #f1f5f9;
}

.client-req-empty-hint {
    text-align: center;
    padding: 12px 8px;
}

.client-req-modal-body .info-group {
    margin-bottom: 15px;
}

.client-req-modal-body .form-label-custom {
    display: block;
    font-size: 12px !important;
    font-weight: 300 !important;
    color: #666666 !important;
    margin-top: 5px !important;
    margin-bottom: 5px !important;
    line-height: 10px !important;
}

.client-req-modal-body .custom-input {
    height: 42px !important;
    font-size: 13px !important;
    color: #000000 !important;
    border-radius: 10px !important;
    border: 1px solid #E2E8F0 !important;
    font-family: 'Montserrat';
}

.client-req-modal-body .custom-input::placeholder {
    color: #64748B !important;
    opacity: 1;
    font-size: 13px !important;
    font-family: 'Montserrat';
}

:deep(.client-req-vselect .vs__dropdown-toggle) {
    height: 42px;
    border-radius: 10px;
    border: 1px solid #E2E8F0;
    background: #fff;
    padding: 0 8px;
}

:deep(.client-req-vselect .vs__selected),
:deep(.client-req-vselect .vs__search),
:deep(.client-req-vselect .vs__placeholder) {
    font-size: 13px;
    color: #64748B;
}

:deep(.client-req-vselect .vs__selected) {
    margin: 0;
    padding: 0;
}

:deep(.client-req-vselect .vs__actions) {
    padding: 0 8px;
}

:deep(.client-req-vselect .vs__open-indicator-icon) {
    font-size: 15px;
    color: #cfdbec;
}

:deep(.client-req-vselect .vs__dropdown-menu) {
    border: 1px solid #E2E8F0;
    box-shadow: 0px 10px 30px rgba(0, 0, 0, 0.1);
    margin-top: 5px;
    z-index: 1100;
}

:deep(.client-req-vselect .vs__dropdown-option) {
    padding: 5px 10px;
    font-size: 14px;
    color: #475569;
}

:deep(.client-req-vselect .vs__dropdown-option--highlight) {
    background: #733E87 !important;
    color: #fff !important;
}

:deep(.client-req-vselect .vs__dropdown-option--selected) {
    background: #733E87;
    color: #fff;
}

:deep(.client-req-modal .modal-content) {
    border-radius: 12px;
    border: none;
    box-shadow: none;
    background: #ffffff;
}

:deep(.client-req-modal .modal-header) {
    padding: 12px 16px;
    border-bottom: 1px solid #E2E8F0;
    background: #fff;
}

:deep(.client-req-modal .modal-title) {
    font-size: 14px;
    font-weight: 600;
    color: #0f172a;
}

:deep(.client-req-modal .modal-body) {
    padding: 14px 16px 16px;
}

.client-req-modal .modal-footer-custom .btn {
    padding: 6px 14px;
    font-size: 12px;
    border-radius: 999px;
}

.client-req-modal-body {
    padding: 0;
}

.client-req-budget-inputs {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
}

.client-req-modal .modal-footer-custom {
    padding-top: 15px;
    border-top: 1px solid #F1F5F9;
}

.client-requirement-list {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 6px 14px;
}

.client-req-location {
    grid-column: 1 / -1;
}

.client-requirement-list .info-group {
    margin-bottom: 0 !important;
}
.lead-info-view .info-group {
    margin-bottom: 1rem;
}

.lead-info-view {
    overflow: visible;
}

.info-section {
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 14px;
    margin-bottom: 10px;
    background: #ffffff;
    overflow: visible;
}

.info-section-title {
    font-size: 12px;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 12px;
    padding-bottom: 0;
    border-bottom: none;
}


.lead-edit-inline-btn {
    border: none;
    background: transparent;
    padding: 2px 4px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
}

.lead-edit-inline-btn .edit-icon-btn {
    padding: 1px;
    font-size: 15px;
}

@media (max-width: 768px) {
    .client-req-block-actions {
        justify-content: flex-start;
        align-items: center;
    }

    .client-req-created-at {
        width: auto;
        margin-top: 0;
        align-self: auto;
    }

    .client-req-action-main {
        justify-content: flex-start;
        flex-wrap: wrap;
    }

    .client-req-budget-inputs {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

.lead-edit-inline-btn:hover {
    opacity: 0.85;
}

.edit-icon-btn {
    font-size: 18px;
}
.info-empty {
    font-size: 12px;
    color: #94a3b8;
}

.lead-info-view .form-label-custom {
    display: block;
    font-size: 13px;
    font-weight: 500;
    color: #64748B;
    margin-bottom: 6px;
}

.lead-info-view .info-value,
.lead-info-view .info-value a {
    word-break: break-word;
    overflow-wrap: anywhere;
}
.lead-info-view .info-value {
    font-size: 14px;
    color: #1E293B;

    word-break: break-word;
    overflow-wrap: anywhere;
}

.location-selected-view {
    display: flex;
    align-items: flex-start;
    gap: 10px;
}

.location-option-icon {
    font-size: 16px;
    color: #64748b;
    margin-top: 2px;
    flex-shrink: 0;
}

.location-option-text {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.location-option-name {
    font-size: 13px;
    font-weight: 600;
    color: #0f172a;
    line-height: 1.25;
}

.location-option-subtitle {
    font-size: 12px;
    color: #64748b;
    line-height: 1.2;
}

.lead-info-view .info-value-block {
    white-space: pre-wrap;
}

.blurred-stars {
    filter: blur(3px);
    user-select: none;
}

.facebook-link {
    color: #2563eb;
    text-decoration: underline;
    text-decoration-color: #2563eb;
}

.facebook-link:hover {
    color: #1d4ed8;
    text-decoration: none;
}

/* Avatar Styles */
.avatar-wrapper {
    width: 48px;
    height: 48px;
    flex-shrink: 0;
}

.person-hover-anchor {
    position: relative;
    overflow: visible;
}

.person-hover-card {
    position: absolute;
    top: 50%;
    left: calc(100% + 10px);
    transform: translateY(-50%);
    width: 200px;
    z-index: 1200;
    border-radius: 12px;
    border: 1px solid #dbe3ef;
    background: rgba(255, 255, 255, 0.97);
    box-shadow: 0 14px 30px rgba(15, 23, 42, 0.2);
    backdrop-filter: blur(8px);
    padding: 10px;
}

.person-hover-head {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}

.person-hover-avatar {
    width: 34px;
    height: 34px;
    border-radius: 999px;
    object-fit: cover;
    border: 1px solid #e2e8f0;
}

.person-hover-avatar-fallback {
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f1f5f9;
}

.person-hover-name {
    font-size: 12px;
    font-weight: 700;
    color: #0f172a;
}

.person-hover-role {
    margin-top: 1px;
    font-size: 11px;
    color: #64748b;
}

.person-hover-line {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    font-size: 11px;
    padding: 4px 0;
    border-top: 1px dashed #e2e8f0;
}

.person-hover-line span {
    color: #64748b;
}

.person-hover-line b {
    color: #0f172a;
    font-weight: 700;
    text-align: right;
    max-width: 120px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.person-card-pop-enter-active,
.person-card-pop-leave-active {
    transition: opacity 0.14s ease, transform 0.14s ease;
}

.person-card-pop-enter-from,
.person-card-pop-leave-to {
    opacity: 0;
    transform: translateY(-50%) translateX(4px) scale(0.98);
}

.avatar-md {
    width: 48px;
    height: 48px;
    object-fit: cover;
}

.avatar-placeholder {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: #F3F4F6;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #E5E7EB;
}

.avatar-icon {
    font-size: 24px;
    color: #9CA3AF;
}

/* Edit Person Button */
.edit-person-btn {
    text-decoration: none;
    color: #733E87;
    font-size: 12px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 4px;
}

.edit-person-btn:hover {
    color: #E89200;
}

.edit-person-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.edit-icon {
    font-size: 14px;
}

/* Modal Styles */
:deep(.person-modal .modal-content) {
    border-radius: 12px !important;
    border: none !important;
}

:deep(.person-modal .modal-header) {
    border-bottom: 1px solid #E2E8F0 !important;
    padding: 1rem 1.5rem 1important;
}

:deep(.person-modal .modal-title) {
    font-size: 24px !important;
    font-weight: 600 !important;
    color: #1E293B;
}
.person-modal .modal-title {
    font-size: 24px !important;
    font-weight: 600 !important;
    color: #1E293B;
}
:deep(.person-modal .modal-body) {
    padding: 1.5rem;
}

.person-modal-content {
    padding: 0.5rem 0 !important;
}

/* Search Input */
.search-input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.person-search-input {
    height: 45px !important;
    border-radius: 25px !important;
    padding-left: 20px !important;
    padding-right: 45px !important;
    border: 1px solid #E2E8F0 !important;
    font-size: 14px !important;
}

.person-search-input::placeholder {
    font-size: 15px !important;
}

.search-icon {
    position: absolute;
    right: 15px;
    color: #733E87;
    font-size: 20px;
}

/* Person List */
.person-list-scroll {
    max-height: 350px;
    overflow-y: auto;
    padding-right: 5px;
}

/* Custom Scrollbar */
.person-list-scroll::-webkit-scrollbar {
    width: 4px;
}

.person-list-scroll::-webkit-scrollbar-track {
    background: #F1F5F9;
    border-radius: 10px;
}

.person-list-scroll::-webkit-scrollbar-thumb {
    background: #CBD5E1;
    border-radius: 10px;
}

.person-item {
    cursor: pointer;
    border-radius: 8px;
    transition: all 0.2s;
    margin-bottom: 4px;
    border: 1px solid transparent;
}

.person-item:hover {
    background: #F8FAFC;
    border-color: #733E87;
}

.person-item.selected {
    background: #FFFBEB;
    border-color: #733E87;
}

.person-item.current {
    background: #F0F9FF;
}

.person-item-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
}

.person-item-name {
    font-weight: 600;
    font-size: 14px;
    color: #1E293B;
    font-family: 'Montserrat';
}

.person-item-email {
    font-size: 12px;
    color: #64748B;
    font-family: 'Montserrat';
}

.user-position-badge {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    color: #475569;
    font-size: 11px;
    font-weight: 600;
    line-height: 1;
    padding: 4px 8px;
    border-radius: 999px;
        margin-left: 10px;

}
.user-item-name{
    text-transform: capitalize;
}
.user-item-meta-line {
    margin-top: 2px;
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    line-height: 1.3;
    color: #64748b;
    font-family: 'Montserrat';
}

.meta-label {
    font-weight: 600;
    color: #64748b;
}

.meta-value {
    font-weight: 500;
    color: #334155;
}

.meta-divider {
    color: #cbd5e1;
}
.current-badge {
    background: #E2E8F0;
    color: #475569;
    font-size: 11px;
    font-weight: 500;
    padding: 2px 8px;
    border-radius: 12px;
}

.text-warning {
    color: #733E87 !important;
}

/* Modal Footer */
.modal-footer-custom {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 12px;
    border-top: 1px solid #E2E8F0;
    padding-top: 1.5rem;
}

.modal-footer-custom .btn {
    padding: 0.5rem 1.5rem;
    border-radius: 100px;
    font-size: 14px;
    font-weight: 500;
}

.modal-footer-custom .btn-light {
    background: #F4F4F4;
    border: none;
    color: #0B0736;
}

.modal-footer-custom .btn-light:hover {
    background: #E2E8F0;
}

.modal-footer-custom .btn-warning {
    background: #733E87;
    border: none;
    color: #fff;
}

.modal-footer-custom .btn-warning:hover:not(:disabled) {
    background: #E89200;
}

.modal-footer-custom .btn-warning:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Alert Styles */
.alert-danger {
    background-color: #FEF2F2;
    border: 1px solid #FEE2E2;
    color: #DC2626;
    border-radius: 8px;
    font-size: 13px;
}
.lead-edit-inline-btn.client-req-add-btn {
        padding: 0px;
    font-size: 17px !important;
    border: 1px solid rgb(250, 163, 0);
    border-radius: 20px;
}
:deep(.custom-v-select .vs__open-indicator-icon) {
    font-size: 12px;
    color: #64748b52;
}
.custom-date-trigger svg,.custom-date-trigger i ,.form-label-custom svg ,.form-label-custom i{
    color: #64748b52 !important;
}
/* تصغير علامة الإغلاق (X) في v-select */
:deep(.custom-v-select .vs__clear) {
    width: 16px !important;
    height: 16px !important;
    font-size: 12px !important;
    line-height: 1 !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
}

:deep(.custom-v-select .vs__clear svg) {
    width: 12px !important;
    height: 12px !important;
       fill: #64748b52;
}

/* للـ client-req-vselect نفس الشيء */
:deep(.client-req-vselect .vs__clear) {
    width: 16px !important;
    height: 16px !important;
    font-size: 12px !important;
    line-height: 1 !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
}

:deep(.client-req-vselect .vs__clear svg) {
    width: 12px !important;
    height: 12px !important;
}

.vs__clear {
    width: 16px !important;
    height: 16px !important;
    font-size: 12px !important;
    line-height: 1 !important;
}

.vs__clear svg {
    width: 12px !important;
    height: 12px !important;
}


/* تأثير الـ highlight */
.section-highlight {
    animation: highlight-pulse 0.5s ease-in-out 3;
    border: 2px solid #733E87 !important;
    background: linear-gradient(90deg, #FFF8E7, #FFFFFF) !important;
    box-shadow: 0 0 0 2px rgba(250, 163, 0, 0.2) !important;
    transition: all 0.3s ease;
}

@keyframes highlight-pulse {
    0% {
        border-color: #733E87;
        box-shadow: 0 0 0 0 rgba(250, 163, 0, 0.4);
    }
    50% {
        border-color: #FFD700;
        box-shadow: 0 0 0 4px rgba(250, 163, 0, 0.2);
    }
    100% {
        border-color: #733E87;
        box-shadow: 0 0 0 0 rgba(250, 163, 0, 0);
    }
}


</style>