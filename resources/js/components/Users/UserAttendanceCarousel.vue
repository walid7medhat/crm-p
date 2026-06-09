<template>
  <div class="user-attendance-carousel">
    <div class="uac-header">
      <div class="uac-title-wrap">
        <h6 class="uac-title">Attendance</h6>
        <p class="uac-subtitle">Daily check-in &amp; check-out by month</p>
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

    <div v-if="loading" class="uac-loading">
      <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
      <span>Loading attendance...</span>
    </div>

    <div v-else-if="error" class="uac-empty">
      <p>{{ error }}</p>
    </div>

    <template v-else-if="currentMonth">
      <div class="uac-summary">
        <span class="uac-chip present">{{ currentMonth.present }} Present</span>
        <span class="uac-chip late">{{ currentMonth.late }} Late</span>
        <span class="uac-chip absent">{{ currentMonth.absent }} Absent</span>
      </div>

      <div class="uac-table-wrap">
        <table class="uac-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Status</th>
              <th>Check In &amp; Check Out</th>
              <th class="text-end">Break</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in sortedDays" :key="row.date">
              <td class="uac-date">{{ formatDate(row.date) }}</td>
              <td>
                <span class="status-badge" :class="statusClass(row.status)">{{ row.status }}</span>
              </td>
              <td>
                <div class="check-flow">
                  <span class="check-time">{{ formatTime(row.date, row.check_in) }}</span>
                  <span class="check-duration-wrap">
                    <span class="dur-dot"></span>
                    <span class="dur-line"></span>
                    <span class="dur-text">{{ formatDuration(row.date, row.check_in, row.check_out) }}</span>
                    <span class="dur-line"></span>
                    <span class="dur-dot"></span>
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
import { API_ENDPOINTS } from '@/config/api';

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
        const token = localStorage.getItem('token') || sessionStorage.getItem('token');
        const url = `${API_ENDPOINTS.PROFILE_ATTENDANCE_HISTORY}?months=${this.months}`;
        const response = await fetch(url, {
          headers: {
            Authorization: `Bearer ${token}`,
            Accept: 'application/json',
          },
        });

        if (!response.ok) {
          throw new Error('Failed to load attendance');
        }

        const payload = await response.json();
        this.monthData = payload.data || [];
        this.monthIndex = 0;
      } catch (e) {
        this.error = e.message || 'Unable to load attendance';
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
      if (!date || !time) return null;
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
  background: #fff;
  border: 1px solid #eceff5;
  border-radius: 12px;
  padding: 16px 18px;
}

.uac-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 14px;
  flex-wrap: wrap;
}

.uac-title {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 700;
  color: #0b0736;
}

.uac-subtitle {
  margin: 2px 0 0;
  font-size: 0.75rem;
  color: #9ca3af;
}

.uac-month-nav {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.uac-nav-btn {
  width: 30px;
  height: 30px;
  border: 1px solid #eceff5;
  border-radius: 50%;
  background: #fff;
  color: #4b5563;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background 0.15s ease;
}

.uac-nav-btn:hover:not(:disabled) {
  background: #f4f0f8;
  color: #733e87;
}

.uac-nav-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.uac-month-label {
  min-width: 110px;
  text-align: center;
  font-size: 0.82rem;
  font-weight: 600;
  color: #374151;
}

.uac-summary {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 12px;
}

.uac-chip {
  font-size: 0.7rem;
  font-weight: 600;
  padding: 4px 10px;
  border-radius: 999px;
}

.uac-chip.present {
  background: #dcfce7;
  color: #166534;
}

.uac-chip.late {
  background: #ffedd5;
  color: #9a3412;
}

.uac-chip.absent {
  background: #fee2e2;
  color: #991b1b;
}

.uac-loading,
.uac-empty {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 120px;
  color: #6b7280;
  font-size: 0.85rem;
}

.uac-table-wrap {
  overflow-x: auto;
}

.uac-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.8rem;
}

.uac-table thead th {
  text-align: left;
  font-size: 0.72rem;
  font-weight: 600;
  color: #9ca3af;
  padding: 8px 10px;
  border-bottom: 1px solid #eceff5;
  white-space: nowrap;
}

.uac-table tbody td {
  padding: 10px;
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
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 0.68rem;
  font-weight: 600;
  display: inline-block;
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
  background: #ffedd5;
  color: #9a3412;
}

.check-flow {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  white-space: nowrap;
}

.check-time {
  color: #111827;
  font-weight: 500;
  font-size: 0.78rem;
}

.check-duration-wrap {
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

.dur-dot {
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: #9ca3af;
}

.dur-line {
  width: 16px;
  height: 1px;
  background: #cfd4dc;
}

.dur-text {
  color: #d69a22;
  font-size: 0.75rem;
  font-weight: 500;
}

.break-col {
  font-size: 0.78rem;
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
