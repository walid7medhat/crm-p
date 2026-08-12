<template>
  <div class="user-attendance-carousel">
    <div class="uac-header">
      <div class="uac-title-wrap">
        <h6 class="uac-title">Attendance</h6>
        <p class="uac-subtitle">Daily check-in &amp; check-out by month</p>
      </div>
    </div>

    <div v-if="loading" class="uac-loading">
      <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
      <span>Loading attendance...</span>
    </div>

    <div v-else-if="error" class="uac-empty uac-empty--error">
      <p>{{ error }}</p>
      <button type="button" class="btn btn-sm btn-outline-primary mt-2" @click="fetchHistory">
        Retry
      </button>
    </div>

    <div v-else-if="!monthData.length" class="uac-empty">
      <p>No attendance records found for your account.</p>
      <p v-if="!hasBiometric" class="uac-empty-hint">
        Your profile has no biometric code. Ask HR to link your employee ID (same as HR attendance) to your user account.
      </p>
      <p v-else class="uac-empty-hint">Records sync from the same biometric system used in HR attendance.</p>
    </div>

    <template v-else-if="currentMonth">
      <div class="uac-toolbar">
        <div class="uac-summary">
          <span class="uac-chip present">{{ currentMonth.present }} Present</span>
          <span class="uac-chip late">{{ currentMonth.late }} Late</span>
          <span class="uac-chip absent">{{ currentMonth.absent }} Absent</span>
        </div>
        <div class="uac-month-nav">
          <button
            type="button"
            class="uac-nav-btn"
            :disabled="loading || !canGoNewer"
            @click="goNewer"
            aria-label="Newer month"
          >
            <i class="ri-arrow-left-s-line"></i>
          </button>
          <span class="uac-month-label">{{ currentMonth?.label || '—' }}</span>
          <button
            type="button"
            class="uac-nav-btn"
            :disabled="loading || !canGoOlder"
            @click="goOlder"
            aria-label="Older month"
          >
            <i class="ri-arrow-right-s-line"></i>
          </button>
        </div>
      </div>

      <div class="uac-table-wrap">
        <table class="uac-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Status</th>
              <th>Check in &amp; Check Out</th>
              <th class="text-end">Break</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in sortedDays" :key="row.date">
              <td class="uac-date">{{ formatDate(row.date) }}</td>
              <td>
                <span class="status-badge" :class="statusClass(row.status)">
                  <span class="status-dot" aria-hidden="true" />
                  {{ row.status }}
                </span>
              </td>
              <td>
                <div class="check-flow">
                  <span class="check-time">{{ formatTime(row.date, row.check_in) }}</span>
                  <span class="check-duration-wrap">
                    <span class="dur-line"></span>
                    <span class="dur-text">{{ formatDuration(row.date, row.check_in, row.check_out) }}</span>
                    <span class="dur-line"></span>
                  </span>
                  <span class="check-time">{{ formatTime(row.date, row.check_out) }}</span>
                </div>
              </td>
              <td class="text-muted text-end break-col">--</td>
            </tr>
            <tr v-if="!sortedDays.length">
              <td colspan="4" class="uac-no-rows">No working days this month.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>

<script>
import attendancesApi from '@/services/attendancesApi';

export default {
  name: 'UserAttendanceCarousel',
  props: {
    months: {
      type: Number,
      default: 12,
    },
  },
  data() {
    return {
      loading: false,
      error: null,
      monthData: [],
      monthIndex: 0,
      hasBiometric: true,
    };
  },
  computed: {
    currentMonth() {
      return this.monthData[this.monthIndex] || null;
    },
    sortedDays() {
      if (!this.currentMonth?.daily_breakdown) return [];
      return [...this.currentMonth.daily_breakdown].sort(
        (a, b) => new Date(b.date) - new Date(a.date),
      );
    },
    canGoNewer() {
      return this.monthIndex > 0;
    },
    canGoOlder() {
      return this.monthIndex < this.monthData.length - 1;
    },
  },
  mounted() {
    this.fetchHistory();
  },
  methods: {
    async fetchHistory() {
      this.loading = true;
      this.error = null;

      try {
        const response = await attendancesApi.profileHistory(this.months);
        const payload = response.data || {};
        if (payload.success === false) {
          throw new Error(payload.message || 'Failed to load attendance');
        }
        this.monthData = Array.isArray(payload.data) ? payload.data : [];
        this.hasBiometric = payload.meta?.has_biometric !== false;
        this.monthIndex = 0;
      } catch (e) {
        this.error =
          e?.response?.data?.message
          || e?.message
          || 'Unable to load attendance';
        this.monthData = [];
      } finally {
        this.loading = false;
      }
    },
    goNewer() {
      if (this.canGoNewer) this.monthIndex -= 1;
    },
    goOlder() {
      if (this.canGoOlder) this.monthIndex += 1;
    },
    statusClass(status) {
      return `status-${String(status || '').toLowerCase()}`;
    },
    toDateTime(date, time) {
      if (!time) return null;
      if (String(time).includes('T') || String(time).includes(' ')) {
        return String(time).replace(' ', 'T');
      }
      if (!date) return null;
      return `${date}T${time}`;
    },
    formatDate(value) {
      if (!value) return '—';
      const d = new Date(value);
      if (Number.isNaN(d.getTime())) return String(value);
      return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    },
    formatTime(date, time) {
      const value = this.toDateTime(date, time);
      if (!value) return '--';
      const d = new Date(value);
      if (Number.isNaN(d.getTime())) return '--';
      return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    },
    formatDuration(date, checkIn, checkOut) {
      const inVal = this.toDateTime(date, checkIn);
      const outVal = this.toDateTime(date, checkOut);
      if (!inVal || !outVal) return '--';
      const inDate = new Date(inVal);
      const outDate = new Date(outVal);
      if (Number.isNaN(inDate.getTime()) || Number.isNaN(outDate.getTime())) return '--';
      let diff = Math.round((outDate.getTime() - inDate.getTime()) / 60000);
      if (diff < 0) diff = 0;
      const hours = Math.floor(diff / 60);
      const minutes = diff % 60;
      return `${hours}h ${minutes}m`;
    },
  },
};
</script>

<style scoped>
.user-attendance-carousel {
  background: transparent;
  border: none;
  border-radius: 0;
  padding: 0;
}

.uac-header {
  margin-bottom: 16px;
}

.uac-title {
  margin: 0;
  font-size: 1.0625rem;
  font-weight: 700;
  color: #0b0736;
}

.uac-subtitle {
  margin: 4px 0 0;
  font-size: 0.8125rem;
  color: #9ca3af;
}

.uac-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 16px;
}

.uac-month-nav {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 6px;
  border: 1px solid #eceff5;
  border-radius: 999px;
  background: #fff;
}

.uac-nav-btn {
  width: 28px;
  height: 28px;
  border: none;
  border-radius: 50%;
  background: transparent;
  color: #4b5563;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background 0.15s ease;
}

.uac-nav-btn:hover:not(:disabled) {
  background: #f3f4f6;
  color: #0b0736;
}

.uac-nav-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.uac-month-label {
  min-width: 100px;
  text-align: center;
  font-size: 0.8125rem;
  font-weight: 600;
  color: #0b0736;
  padding: 0 4px;
}

.uac-summary {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.uac-chip {
  font-size: 0.75rem;
  font-weight: 600;
  padding: 6px 12px;
  border-radius: 999px;
}

.uac-chip.present {
  background: #dcfce7;
  color: #166534;
}

.uac-chip.late {
  background: #fef3c7;
  color: #b45309;
}

.uac-chip.absent {
  background: #fee2e2;
  color: #991b1b;
}

.uac-loading,
.uac-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 120px;
  color: #6b7280;
  font-size: 0.85rem;
  text-align: center;
  padding: 12px;
}

.uac-empty-hint {
  margin: 0;
  font-size: 0.75rem;
  color: #9ca3af;
  max-width: 320px;
}

.uac-empty--error p {
  margin: 0;
  color: #b91c1c;
}

.uac-table-wrap {
  overflow-x: auto;
}

.uac-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
}

.uac-table thead th {
  text-align: left;
  font-size: 0.75rem;
  font-weight: 600;
  color: #9ca3af;
  padding: 10px 12px;
  border-bottom: 1px solid #eceff5;
  white-space: nowrap;
}

.uac-table tbody td {
  padding: 14px 12px;
  border-bottom: 1px solid #f3f4f6;
  vertical-align: middle;
}

.uac-table tbody tr:last-child td {
  border-bottom: none;
}

.uac-date {
  color: #374151;
  font-weight: 500;
  white-space: nowrap;
}

.uac-no-rows {
  text-align: center;
  color: #9ca3af;
  padding: 24px 10px !important;
}

.status-badge {
  text-transform: capitalize;
  padding: 5px 12px 5px 10px;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.status-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: currentColor;
  flex-shrink: 0;
}

.status-present {
  background: #dcfce7;
  color: #166534;
}

.status-absent {
  background: #fee2e2;
  color: #991b1b;
}

.status-late {
  background: #fef3c7;
  color: #b45309;
}

.check-flow {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  white-space: nowrap;
}

.check-time {
  color: #111827;
  font-weight: 500;
  font-size: 0.8125rem;
}

.check-duration-wrap {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-width: 90px;
}

.dur-line {
  flex: 1;
  min-width: 18px;
  height: 1px;
  background: #d1d5db;
}

.dur-text {
  color: #d69a22;
  font-size: 0.75rem;
  font-weight: 600;
  white-space: nowrap;
}

.break-col {
  font-size: 0.8125rem;
}

@media (max-width: 768px) {
  .check-flow {
    flex-wrap: wrap;
    gap: 4px;
  }

  .uac-table thead th:nth-child(4),
  .uac-table tbody td:nth-child(4) {
    display: none;
  }
}
</style>
