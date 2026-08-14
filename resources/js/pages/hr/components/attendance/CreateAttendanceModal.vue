<template>
  <div v-if="visible" class="edit-overlay add-employee-overlay" @click.self="handlers.closeCreateAttendanceModal">
    <div class="add-employee-modal leave-apply-modal attendance-create-modal">
      <div class="add-employee-head">
        <h6>Create New Attendance</h6>
        <button type="button" class="add-employee-close" @click="handlers.closeCreateAttendanceModal">
          <iconify-icon icon="lucide:x" />
        </button>
      </div>
      <div class="add-employee-body">
        <section class="add-employee-section">
          <div class="add-grid-two">
            <div class="add-field add-field-full">
              <label>Employee <em>*</em></label>
              <SearchableSelect v-model="state.createAttendanceForm.employee" :options="options.applyLeaveEmployeeOptions" placeholder="Select Employee" />
            </div>
            <div class="add-field add-field-full">
              <label>Department <em>*</em></label>
              <SearchableSelect v-model="state.createAttendanceForm.department" :options="options.attendanceDepartmentNameOptions" placeholder="Select Employee Department" />
            </div>
            <div class="add-field add-field-full">
              <label>Branch <em>*</em></label>
              <SearchableSelect v-model="state.createAttendanceForm.branch" :options="options.attendanceBranchNameOptions" placeholder="Select Branch" />
            </div>
            <div class="add-field">
              <label>Type <em>*</em></label>
              <SearchableSelect v-model="state.createAttendanceForm.type" :options="options.attendanceCreateTypeOptions" placeholder="Select Attendance Type" />
            </div>
            <div class="add-field">
              <label>Date <em>*</em></label>
              <div class="add-field-control">
                <input :value="helpers.formatDateDisplay(state.createAttendanceForm.date)" type="text" placeholder="--/--/--" readonly @click="handlers.openDatePicker('createAttendanceForm.date')" />
                <iconify-icon icon="lucide:calendar" />
              </div>
            </div>
            <div class="add-field">
              <label>Check In <em>*</em></label>
              <div class="add-field-control">
                <input v-model="state.createAttendanceForm.checkIn" type="time" />
                <iconify-icon icon="lucide:clock" />
              </div>
            </div>
            <div class="add-field">
              <label>Check Out <em>*</em></label>
              <div class="add-field-control">
                <input v-model="state.createAttendanceForm.checkOut" type="time" />
                <iconify-icon icon="lucide:clock" />
              </div>
            </div>
            <div class="add-field">
              <label>Break Duration</label>
              <SearchableSelect v-model="state.createAttendanceForm.breakLabel" :options="options.attendanceBreakOptions" placeholder="Select Duration" />
            </div>
            <div class="add-field">
              <label>Total Early Hours</label>
              <SearchableSelect v-model="state.createAttendanceForm.earlyHours" :options="options.attendanceHourOptions" placeholder="Select Hours" />
            </div>
            <div class="add-field">
              <label>Total Missed Hours</label>
              <SearchableSelect v-model="state.createAttendanceForm.missedHours" :options="options.attendanceHourOptions" placeholder="Select Hours" />
            </div>
            <div class="add-field">
              <label>Total OT Hours</label>
              <SearchableSelect v-model="state.createAttendanceForm.otLabel" :options="options.attendanceOtOptions" placeholder="Select Hours" />
            </div>
            <div class="add-field add-field-full">
              <label>Reason</label>
              <textarea v-model="state.createAttendanceForm.description" placeholder="Enter text"></textarea>
            </div>
          </div>
        </section>
        <section class="add-employee-section">
          <label class="attendance-upload-label">Image</label>
          <div class="upload-dropzone leave-upload-dropzone">
            <iconify-icon icon="lucide:file-text" />
            <div>
              <strong>Upload documents</strong>
              <small>JPEG, PNG and PDF formats, up to 50MB</small>
            </div>
            <label class="select-file-btn">Select File<input type="file" class="d-none" accept=".jpg,.jpeg,.png,.pdf" @change="handlers.handleCreateAttendanceFileChange" /></label>
          </div>
          <div v-if="state.createAttendanceAttachment" class="uploaded-doc-card">
            <iconify-icon icon="lucide:file-text" />
            <div>
              <p>{{ state.createAttendanceAttachment.name }}</p>
              <small>{{ `${Math.max(1, Math.round(state.createAttendanceAttachment.size / 1024))}KB` }}</small>
            </div>
            <button type="button" @click="handlers.removeCreateAttendanceFile"><iconify-icon icon="lucide:x-circle" /></button>
          </div>
        </section>
      </div>
      <div class="add-employee-footer">
        <button type="button" class="add-employee-clear-btn" @click="handlers.cancelCreateAttendance">Cancel</button>
        <button type="button" class="add-employee-save-btn" @click="handlers.submitCreateAttendance">Submit</button>
      </div>
    </div>
  </div>
</template>
<script setup>
import SearchableSelect from '@/components/ui/SearchableSelect.vue'
defineProps({ visible: Boolean, state: Object, options: Object, handlers: Object, helpers: Object })
</script>
