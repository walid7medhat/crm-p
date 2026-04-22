<template>
  <div class="report-container">

    <!-- Actions -->
    <div class="actions no-print">
      <div class="date-range">
        <label>From:</label>
        <input type="date" v-model="startDate" />
        <label>To:</label>
        <input type="date" v-model="endDate" />
      </div>
      <button @click="fetchReport">Load</button>
      <button @click="printPage">🖨 Print</button>
    </div>

    <!-- Title -->
    <h2>Attendance Monthly Report</h2>
    <div class="date">Period: {{ formattedDateRange }}</div>

    <!-- Summary Stats - Small boxes -->
    <div class="summary-stats no-print">
      <div class="stat-card">
        <div class="stat-label">Total Employees</div>
        <div class="stat-value">{{ reports.length }}</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Total Present</div>
        <div class="stat-value">{{ totalPresent }}</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Total Late</div>
        <div class="stat-value">{{ totalLate }}</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Total Absent</div>
        <div class="stat-value">{{ totalAbsent }}</div>
      </div>
    </div>

    <!-- Table - Full width -->
    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Employee</th>
            <th>Present</th>
            <th>Late</th>
            <th>Absent</th>
            <th>Deduction %</th>
          </tr>
        </thead>

        <tbody>
          <tr v-for="(emp, index) in reports" :key="emp.user_id">
            <td class="center">{{ index + 1 }}</td>
            <td class="name">{{ emp.name }}</td>
            <td class="present center">{{ emp.present }}</td>
            <td class="late center">{{ emp.late }}</td>
            <td class="absent center">{{ emp.absent }}</td>
            <td class="deduction center">{{ emp.total_deduction_percent }}%</td>
          </tr>
        </tbody>

        <tfoot v-if="reports.length">
          <tr>
            <td colspan="2"><strong>Total</strong></td>
            <td class="center"><strong>{{ totalPresent }}</strong></td>
            <td class="center"><strong>{{ totalLate }}</strong></td>
            <td class="center"><strong>{{ totalAbsent }}</strong></td>
            <td class="center"><strong>{{ avgDeduction }}%</strong></td>
          </tr>
        </tfoot>
      </table>
    </div>

  </div>
</template>

<script>
import axios from "axios";

export default {
  data() {
    return {
      startDate: "",
      endDate: "",
      reports: []
    };
  },

  mounted() {
    // Set default date range to current month
    const now = new Date();
    this.startDate = this.formatDate(new Date(now.getFullYear(), now.getMonth(), 1));
    this.endDate = this.formatDate(new Date(now.getFullYear(), now.getMonth() + 1, 0));
    this.fetchReport();
  },

  computed: {
    formattedDateRange() {
      if (this.startDate && this.endDate) {
        return `${this.formatDisplayDate(this.startDate)} to ${this.formatDisplayDate(this.endDate)}`;
      }
      return this.startDate || this.endDate || 'Current Period';
    },
    totalPresent() {
      return this.reports.reduce((sum, emp) => sum + emp.present, 0);
    },
    totalLate() {
      return this.reports.reduce((sum, emp) => sum + emp.late, 0);
    },
    totalAbsent() {
      return this.reports.reduce((sum, emp) => sum + emp.absent, 0);
    },
    avgDeduction() {
      if (!this.reports.length) return 0;
      const total = this.reports.reduce((sum, emp) => sum + emp.total_deduction_percent, 0);
      return (total / this.reports.length).toFixed(1);
    }
  },

  methods: {
    formatDate(date) {
      return date.toISOString().split('T')[0];
    },
    formatDisplayDate(dateString) {
      const date = new Date(dateString);
      return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    },
    fetchReport() {
      const params = {};
      if (this.startDate) params.start_date = this.startDate;
      if (this.endDate) params.end_date = this.endDate;
      
      axios.get("/api/attendance/period-report", { params })
        .then(res => {
          this.reports = res.data.data;
        })
        .catch(err => {
          console.error("Error fetching report:", err);
          alert("Failed to load report");
        });
    },

    printPage() {
      window.print();
    }
  }
};
</script>

<style scoped>
* {
  box-sizing: border-box;
}

.report-container {
  padding: 15px;
  font-family: Arial, sans-serif;
  font-size: 12px;
  background-color: #fff;
  width: 100%;
  max-width: 100%;
  overflow-x: auto;
}

h2 {
  text-align: center;
  margin-bottom: 5px;
  color: #333;
  font-size: 18px;
}

.date {
  text-align: center;
  margin-bottom: 15px;
  color: #666;
  font-size: 12px;
}

.actions {
  margin-bottom: 15px;
  display: flex;
  gap: 10px;
  align-items: center;
  flex-wrap: wrap;
}

.date-range {
  display: flex;
  gap: 8px;
  align-items: center;
  flex-wrap: wrap;
}

.date-range label {
  font-weight: bold;
  color: #555;
  font-size: 11px;
}

.actions input,
.actions button {
  padding: 5px 10px;
  font-size: 11px;
  border: 1px solid #ddd;
  border-radius: 3px;
}

.actions button {
  background-color: #4CAF50;
  color: white;
  cursor: pointer;
  border: none;
}

.actions button:hover {
  background-color: #45a049;
}

.actions button:last-child {
  background-color: #2196F3;
}

.actions button:last-child:hover {
  background-color: #0b7dda;
}

/* Small Summary Stats */
.summary-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  gap: 8px;
  margin-bottom: 15px;
}

.stat-card {
  background: #f5f5f5;
  border: 1px solid #e0e0e0;
  border-radius: 4px;
  padding: 8px;
  text-align: center;
}

.stat-label {
  font-size: 10px;
  color: #666;
  margin-bottom: 4px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.stat-value {
  font-size: 18px;
  font-weight: bold;
  color: #333;
}

/* Table Full Width */
.table-wrapper {
  width: 100%;
  overflow-x: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
  font-size: 11px;
}

th, td {
  border: 1px solid #ddd;
  padding: 6px 8px;
}

th {
  background: #f0f0f0;
  color: #333;
  font-weight: bold;
  font-size: 11px;
  white-space: nowrap;
}

tr:nth-child(even) {
  background-color: #fafafa;
}

tr:hover {
  background-color: #f5f5f5;
}

.name {
  text-align: left;
  font-weight: 500;
}

.center {
  text-align: center;
}

tfoot {
  background-color: #f0f0f0;
  font-weight: bold;
}

tfoot td {
  padding: 6px 8px;
}

/* Colors */
.present {
  color: #4CAF50;
  font-weight: bold;
}

.late {
  color: #FF9800;
  font-weight: bold;
}

.absent {
  color: #f44336;
  font-weight: bold;
}

.deduction {
  font-weight: bold;
  color: #FF5722;
}

/* Print Styles */
@media print {
  .no-print {
    display: none !important;
  }
  
  body {
    margin: 0;
    padding: 0;
    background: white;
  }
  
  .report-container {
    padding: 0;
    margin: 0;
    width: 100%;
  }
  
  table {
    width: 100%;
    page-break-inside: avoid;
  }
  
  th {
    background: #f0f0f0 !important;
    color: black !important;
  }
  
  .present, .late, .absent, .deduction {
    color: black !important;
  }
}
</style>