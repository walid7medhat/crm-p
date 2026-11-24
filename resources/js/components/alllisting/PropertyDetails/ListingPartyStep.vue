<template>
  <!-- Seller (Sold Out) / Landlord (Rented) details + documents — last step when an OIA
       agent closes the listing. Saved on the listing and given to the deal on this unit. -->
  <div class="sold-out-add-owner-step sold-seller-step">
    <p class="sold-seller-intro">
      <i class="ri-information-line"></i>
      <span>
        Saved with the {{ actionWord }} and added automatically to the deal for this unit.
        Only the listing side and admins can see them.
      </span>
    </p>

    <div class="row g-2">
      <div class="col-md-6">
        <label class="form-label">First Name <span class="text-danger">*</span></label>
        <input :value="details.first_name" type="text" class="form-control" maxlength="255"
               :class="{ 'is-invalid': err('first_name') }" @input="set('first_name', $event.target.value)" />
        <small v-if="err('first_name')" class="sold-seller-error">{{ err('first_name') }}</small>
      </div>
      <div class="col-md-6">
        <label class="form-label">Last Name <span class="text-danger">*</span></label>
        <input :value="details.last_name" type="text" class="form-control" maxlength="255"
               :class="{ 'is-invalid': err('last_name') }" @input="set('last_name', $event.target.value)" />
        <small v-if="err('last_name')" class="sold-seller-error">{{ err('last_name') }}</small>
      </div>
      <div class="col-md-6">
        <label class="form-label">Phone <span class="text-danger">*</span></label>
        <input :value="details.phone" type="text" class="form-control" maxlength="50"
               :class="{ 'is-invalid': err('phone') }" @input="set('phone', $event.target.value)" />
        <small v-if="err('phone')" class="sold-seller-error">{{ err('phone') }}</small>
      </div>
      <div class="col-md-6">
        <label class="form-label">Email</label>
        <input :value="details.email" type="email" class="form-control" maxlength="255"
               :class="{ 'is-invalid': err('email') }" @input="set('email', $event.target.value)" />
        <small v-if="err('email')" class="sold-seller-error">{{ err('email') }}</small>
      </div>
      <div class="col-md-6">
        <label class="form-label">Nationality <span class="text-danger">*</span></label>
        <v-select
          :model-value="details.nationality"
          :options="nationalities"
          placeholder="Select nationality"
          :class="{ 'sold-seller-select-invalid': err('nationality') }"
          @update:model-value="(v) => set('nationality', v)"
        />
        <small v-if="err('nationality')" class="sold-seller-error">{{ err('nationality') }}</small>
      </div>
      <div class="col-md-6">
        <label class="form-label">Residency Status <span class="text-danger">*</span></label>
        <select :value="details.residency_status" class="form-control"
                :class="{ 'is-invalid': err('residency_status') }"
                @change="set('residency_status', $event.target.value)">
          <option value="">Select…</option>
          <option value="resident">Resident</option>
          <option value="non_resident">Non Resident</option>
        </select>
        <small v-if="err('residency_status')" class="sold-seller-error">{{ err('residency_status') }}</small>
      </div>
      <div class="col-md-6">
        <label class="form-label">Date of Birth</label>
        <input :value="details.date_of_birth" type="date" class="form-control"
               @input="set('date_of_birth', $event.target.value)" />
      </div>
    </div>

    <div class="sold-seller-section-title">{{ label }} Documents</div>
    <div class="row g-2">
      <div v-for="docType in docTypes" :key="docType.value" class="col-md-6">
        <label class="form-label">
          {{ docType.label }}
          <span v-if="requiredDocTypes.includes(docType.value)" class="text-danger">*</span>
        </label>
        <input
          type="file"
          class="form-control"
          :class="{ 'is-invalid': err('doc_' + docType.value) }"
          multiple
          accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx"
          @change="$emit('files', docType.value, Array.from($event.target.files || []))"
        />
        <small v-if="err('doc_' + docType.value)" class="sold-seller-error">{{ err('doc_' + docType.value) }}</small>
        <small v-else-if="files[docType.value]?.length" class="text-muted">
          {{ files[docType.value].length }} file(s) selected
        </small>
      </div>
    </div>

    <div class="sold-seller-actions">
      <button type="button" class="btn-modal btn-modal-secondary" @click="$emit('back')">
        Back
      </button>
      <button type="button" class="btn-modal btn-modal-primary" :disabled="submitting" @click="$emit('confirm')">
        {{ submitting ? 'Saving…' : confirmLabel }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import vSelect from 'vue-select';

const props = defineProps({
  label: { type: String, default: 'Seller' }, // Seller | Landlord
  details: { type: Object, required: true },
  files: { type: Object, required: true },
  docTypes: { type: Array, required: true },
  requiredDocTypes: { type: Array, default: () => [] },
  nationalities: { type: Array, default: () => [] },
  errors: { type: Object, default: () => ({}) },
  showErrors: { type: Boolean, default: false },
  submitting: { type: Boolean, default: false },
  confirmLabel: { type: String, default: 'Confirm' },
});

const emit = defineEmits(['update:details', 'files', 'back', 'confirm']);

const actionWord = computed(() => (props.label === 'Landlord' ? 'rental' : 'sale'));
const err = (key) => (props.showErrors ? props.errors?.[key] : null);
const set = (key, value) => emit('update:details', { ...props.details, [key]: value });
</script>

<style scoped>
.sold-seller-step .sold-seller-intro {
  display: flex;
  gap: 8px;
  align-items: flex-start;
  margin: 0 0 16px;
  padding: 10px 12px;
  border-radius: 10px;
  background: #f6f3fa;
  color: #5b5470;
  font-size: 12.5px;
  line-height: 1.5;
}
.sold-seller-step .sold-seller-intro i {
  font-size: 16px;
  line-height: 1.2;
  color: #7b4fa3;
}
.sold-seller-step .form-label {
  display: block;
  margin-bottom: 4px;
  font-weight: 600;
  color: #333;
  font-size: 12.5px;
}
.sold-seller-step .form-control {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #e9ecef;
  border-radius: 6px;
  font-weight: 600;
  font-size: 13px;
}
.sold-seller-step .form-control.is-invalid {
  border-color: #dc3545;
}
.sold-seller-step :deep(.sold-seller-select-invalid .vs__dropdown-toggle) {
  border-color: #dc3545;
}
.sold-seller-step .sold-seller-section-title {
  margin: 18px 0 8px;
  padding-top: 14px;
  border-top: 1px solid #eef0f4;
  font-size: 14px;
  font-weight: 700;
  color: #333;
}
.sold-seller-step input[type="file"].form-control {
  padding: 6px 8px;
  font-size: 12px;
}
.sold-seller-step .sold-seller-error {
  display: block;
  margin-top: 3px;
  color: #dc3545;
  font-size: 11.5px;
}
.sold-seller-step .sold-seller-actions {
  position: sticky;
  bottom: -1px;
  display: flex;
  gap: 8px;
  margin-top: 16px;
  padding: 12px 0 4px;
  background: #fff;
  border-top: 1px solid #eef0f4;
  z-index: 2;
}
/* Same buttons as the property modals (BlogOne's are scoped, so they don't reach here). */
.sold-seller-step .btn-modal {
  flex: 1;
  padding: 10px 20px;
  border: none;
  border-radius: 6px;
  font-weight: 600;
  font-size: 13px;
  cursor: pointer;
  transition: all 0.3s ease;
}
.sold-seller-step .btn-modal:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}
.sold-seller-step .btn-modal-secondary {
  background: #6c757d;
  color: #fff;
}
.sold-seller-step .btn-modal-secondary:hover {
  background: #5a6268;
}
.sold-seller-step .btn-modal-primary {
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--gradient-primary) !important;
  color: #fff !important;
}
</style>
