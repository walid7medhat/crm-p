<template>
  <div class="attendance-report-page">
    <div class="toolbar no-print">
      <div class="toolbar-title">
        <h6>Attendance Monthly Deductions Dashboard</h6>
        <p>Policy: 09:16-10:00 = 10% | 10:01-12:00 = 25% | after 12:01 = 100%</p>
      </div>
      <div class="toolbar-actions">
        <div class="date-fields">
          <label>Month</label>
          <input v-model="selectedMonth" type="month" @change="applyMonthSelection" />
          <label>From</label>
          <input v-model="startDate" type="date" />
          <label>To</label>
          <input v-model="endDate" type="date" />
        </div>
        <label class="btn upload-btn">
          Upload CSV/JSON
          <input type="file" accept=".csv,.json" @change="handleFileUpload" />
        </label>
        <button class="btn primary" @click="fetchReport">
          Search
        </button>
        <button class="btn success" @click="exportReport">Export Report</button>
        <button class="btn print" @click="printPage">Print</button>
      </div>
    </div>

    <div class="kpi-grid">
      <div class="kpi-card">
        <span>Total Employees</span>
        <strong>{{ summary.totalEmployees }}</strong>
      </div>
      <div class="kpi-card">
        <span>Total Working Days</span>
        <strong>{{ summary.totalWorkingDays }}</strong>
      </div>
      <div class="kpi-card danger">
        <span>Absent Days</span>
        <strong>{{ summary.totalAbsentDays }}</strong>
      </div>
      <div class="kpi-card warning">
        <span>10% Deduction Days</span>
        <strong>{{ summary.totalTenPercentDays }}</strong>
      </div>
      <div class="kpi-card warning">
        <span>25% Deduction Days</span>
        <strong>{{ summary.totalTwentyFivePercentDays }}</strong>
      </div>
      <div class="kpi-card danger">
        <span>100% Deduction Days</span>
        <strong>{{ summary.totalHundredPercentDays }}</strong>
      </div>
      <div class="kpi-card present-kpi">
        <span>Present Days</span>
        <strong>{{ summary.totalPresentDays }}</strong>
      </div>
    </div>

    <div class="charts-grid no-print">
      <div class="chart-card">
        <h6>Deduction Distribution</h6>
        <apexchart type="bar" height="280" :options="barChartOptions" :series="barChartSeries" />
      </div>
      <div class="chart-card">
        <h6>Attendance vs Absence</h6>
        <apexchart type="donut" height="280" :options="donutChartOptions" :series="donutChartSeries" />
      </div>
    </div>

    <div class="table-controls no-print">
      <div class="search-wrap">
        <i class="ri-search-line"></i>
        <input
          v-model="searchQuery"
          type="text"
          class="search-input"
          placeholder="Search by name, risk (high/medium/low), 10%/25%/100%, total days, deduction %, or date..."
        />
        <button v-if="searchQuery" class="search-clear" @click="searchQuery = ''">Clear</button>
      </div>
      <select v-model="riskFilter">
        <option value="all">All Flags</option>
        <option value="high-absence">High Absence</option>
        <option value="high-100">High 100% Deduction</option>
      </select>
      <select v-model="employeeFilter">
        <option value="all">All Employees</option>
        <option v-for="emp in employeeOptions" :key="emp" :value="emp">{{ emp }}</option>
      </select>
      <select v-model="sortBy">
        <option value="name">Sort: Name</option>
        <option value="totalDays">Sort: Total Days</option>
        <option value="noDeductionDays">Sort: Present Days</option>
        <option value="absentDays">Sort: Absent Days</option>
        <option value="d10">Sort: 10% Days</option>
        <option value="d25">Sort: 25% Days</option>
        <option value="d100">Sort: 100% Days</option>
        <option value="totalDeductionPercent">Sort: Total Deduction %</option>
      </select>
      <button class="btn" @click="sortOrder = sortOrder === 'asc' ? 'desc' : 'asc'">
        {{ sortOrder === 'asc' ? 'Asc' : 'Desc' }}
      </button>
    </div>

    <div class="table-shell">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Employee Name</th>
            <th>Total Days</th>
            <th>Present Days</th>
            <th>Absent Days</th>
            <th>10% Days Count</th>
            <th>25% Days Count</th>
            <th>100% Days Count</th>
            <th>Total Deduction %</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(row, index) in processedRows"
            :key="row.employeeName"
            :class="riskClass(row)"
          >
            <td class="center">{{ index + 1 }}</td>
            <td>
              <button class="employee-link" @click="openEmployeeProfile(row)">
                {{ row.employeeName }}
              </button>
            </td>
            <td class="center">{{ row.totalDays }}</td>
            <td class="center">{{ row.noDeductionDays }}</td>
            <td class="center">{{ row.absentDays }}</td>
            <td class="center deduction-10">{{ row.d10 }}</td>
            <td class="center deduction-25">{{ row.d25 }}</td>
            <td class="center deduction-100">{{ row.d100 }}</td>
            <td class="center">
              <span class="total-badge" :class="riskBadgeClass(row)">
                {{ row.totalDeductionPercent.toFixed(1) }}%
              </span>
            </td>
          </tr>
          <tr v-if="!processedRows.length">
            <td colspan="9" class="empty-cell">No data found for selected date range.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="selectedEmployee" class="modal-overlay no-print" @click.self="closeEmployeeProfile">
      <div class="profile-modal">
        <button class="modal-close" @click="closeEmployeeProfile">×</button>
        <div class="profile-head">
          <div class="profile-avatar">{{ initials(selectedEmployee.employeeName) }}</div>
          <div>
            <h4>{{ selectedEmployee.employeeName }}</h4>
            <p>{{ selectedEmployee.totalDays }} working days in selected range</p>
          </div>
        </div>
        <div class="profile-stats">
          <div class="chip green">0% days: {{ selectedEmployee.noDeductionDays }}</div>
          <div class="chip slate">Absent days: {{ selectedEmployee.absentDays }}</div>
          <div class="chip yellow">10% days: {{ selectedEmployee.d10 }}</div>
          <div class="chip orange">25% days: {{ selectedEmployee.d25 }}</div>
          <div class="chip red">100% days: {{ selectedEmployee.d100 }}</div>
        </div>
        <div class="profile-meta">
          <p><strong>Present Days:</strong> {{ selectedEmployee.noDeductionDays }}</p>
          <p><strong>Absent Days:</strong> {{ selectedEmployee.absentDays }}</p>
          <p><strong>Validation:</strong> {{ selectedEmployee.presentDays }} + {{ selectedEmployee.absentDays }} = {{ selectedEmployee.totalDays }}</p>
          <p><strong>Total Deduction %:</strong> {{ selectedEmployee.totalDeductionPercent.toFixed(1) }}%</p>
        </div>
        <div class="profile-actions">
          <button class="btn primary" @click="openDailyBreakdown">Daily Breakdown</button>
        </div>
      </div>
    </div>

    <div v-if="showDailyBreakdownModal && selectedEmployee" class="modal-overlay no-print" @click.self="showDailyBreakdownModal = false">
      <div class="breakdown-modal">
        <button class="modal-close" @click="showDailyBreakdownModal = false">×</button>
        <h4>Daily Breakdown - {{ selectedEmployee.employeeName }}</h4>
        <div class="breakdown-table-shell">
          <table class="breakdown-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Check-in</th>
                <th>Status</th>
                <th>Deduction</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in selectedEmployee.dailyBreakdown" :key="`${item.date}-${item.checkInMinutes}`">
                <td>{{ item.date }}</td>
                <td>{{ formatMinutes(item.checkInMinutes) }}</td>
                <td>{{ item.status }}</td>
                <td>
                  <span class="chip" :class="deductionClass(item.deduction, item.status)">
                    {{ item.deduction === null ? '-' : `${item.deduction}%` }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

const START_10 = 9 * 60 + 16
const END_10 = 10 * 60
const START_25 = 10 * 60 + 1
const END_25 = 12 * 60
const START_100 = 12 * 60 + 1

export default {
  name: 'AttendanceMonthlyReports',
  data() {
    return {
      startDate: '',
      endDate: '',
      selectedMonth: '',
      rawRows: [],
      selectedEmployee: null,
      showDailyBreakdownModal: false,
      searchQuery: '',
      riskFilter: 'all',
      employeeFilter: 'all',
      sortBy: 'noDeductionDays',
      sortOrder: 'desc',
    }
  },
  mounted() {
    const now = new Date()
    this.startDate = this.formatDate(new Date(now.getFullYear(), now.getMonth(), 1))
    this.endDate = this.formatDate(new Date(now.getFullYear(), now.getMonth() + 1, 0))
    this.selectedMonth = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`
    this.fetchReport()
  },
  computed: {
    workingDatesInRange() {
      return this
        .getDateRange(this.startDate, this.endDate)
        .filter((date) => {
          const day = new Date(`${date}T00:00:00`).getDay()
          // Sunday is OFF and excluded completely.
          // Monday-Saturday are working days.
          return day !== 0
        })
    },
    workingDaysCountInRange() {
      return this.workingDatesInRange.length
    },
    employeeOptions() {
      return [...new Set(this.processedRows.map((row) => row.employeeName))].sort((a, b) => a.localeCompare(b))
    },
    processedRows() {
      const grouped = new Map()
      for (const row of this.rawRows) {
        const employeeName = this.pickField(row, [
          'Employee Name',
          'employee_name',
          'name',
          'Employee',
          'employee',
        ])
        const dateRaw = this.pickField(row, ['Date', 'date', 'attendance_date', 'day'])
        const checkInRaw = this.pickField(row, [
          'Check-in Time',
          'check_in_time',
          'check_in',
          'checkin',
          'time_in',
        ])

        const date = this.normalizeDate(dateRaw)
        const checkInMinutes = this.toMinutes(checkInRaw)
        if (!employeeName || !date || checkInMinutes === null) continue
        if (!this.isInDateRange(date)) continue

        if (!grouped.has(employeeName)) grouped.set(employeeName, new Map())
        const byDate = grouped.get(employeeName)
        const current = byDate.get(date)
        if (current === undefined || checkInMinutes < current) byDate.set(date, checkInMinutes)
      }

      const rangeDates = this.workingDatesInRange
      const rows = []
      for (const [employeeName, byDate] of grouped.entries()) {
        let d10 = 0
        let d25 = 0
        let d100 = 0
        let noDeductionDays = 0
        let absentDays = 0
        let weightedDeduction = 0
        const dailyBreakdown = []
        const totalDays = this.workingDaysCountInRange

        for (const date of rangeDates) {
          // 1) Day type first
          const dayIndex = new Date(`${date}T00:00:00`).getDay()
          const minutes = byDate.get(date)

          // Saturday is ALWAYS present with no deduction (WFH),
          // even if there is no check-in record.
          if (dayIndex === 6) {
            noDeductionDays += 1
            dailyBreakdown.push({
              date,
              checkInMinutes: minutes ?? null,
              deduction: 0,
              status: 'Present',
            })
            continue
          }

          // 2) Attendance check for weekdays (Mon-Fri)
          if (minutes === undefined) {
            absentDays += 1
            dailyBreakdown.push({
              date,
              checkInMinutes: null,
              deduction: null,
              status: 'Absent',
            })
            continue
          }

          // 3) Deduction only for actual attendance days
          const deduction = this.getDeduction(minutes)
          weightedDeduction += deduction
          if (deduction === 10) d10 += 1
          else if (deduction === 25) d25 += 1
          else if (deduction === 100) d100 += 1
          else noDeductionDays += 1

          dailyBreakdown.push({
            date,
            checkInMinutes: minutes,
            deduction,
            status: deduction === 0 ? 'Present' : 'Late',
          })
        }
        dailyBreakdown.sort((a, b) => a.date.localeCompare(b.date))
        const presentDays = noDeductionDays + d10 + d25 + d100
        const totalDeductionPercent = presentDays ? Math.min(100, weightedDeduction / presentDays) : 0
        const avgDeductionPerDay = totalDeductionPercent
        rows.push({
          employeeName,
          totalDays,
          noDeductionDays,
          absentDays,
          presentDays,
          d10,
          d25,
          d100,
          totalDeductionPercent,
          avgDeductionPerDay,
          dailyBreakdown,
        })
      }

      return rows
        .filter((row) => {
          const q = this.searchQuery.trim().toLowerCase()
          if (q && !this.matchesSmartSearch(row, q)) return false
          if (this.employeeFilter !== 'all' && row.employeeName !== this.employeeFilter) return false
          if (this.riskFilter === 'high-absence') return row.absentDays >= 4
          if (this.riskFilter === 'high-100') return row.d100 >= 4
          return true
        })
        .sort((a, b) => {
          const direction = this.sortOrder === 'asc' ? 1 : -1
          const field = this.sortBy === 'name' ? 'employeeName' : this.sortBy
          const av = a[field]
          const bv = b[field]
          if (typeof av === 'string') return av.localeCompare(bv) * direction
          return (av - bv) * direction
        })
    },
    summary() {
      return this.processedRows.reduce(
        (acc, row) => {
          acc.totalEmployees += 1
          acc.totalWorkingDays += row.totalDays
          acc.totalAbsentDays += row.absentDays
          acc.totalTenPercentDays += row.d10
          acc.totalTwentyFivePercentDays += row.d25
          acc.totalHundredPercentDays += row.d100
          acc.totalPresentDays += row.noDeductionDays
          acc.totalDeductionPercent += row.totalDeductionPercent
          return acc
        },
        {
          totalEmployees: 0,
          totalWorkingDays: 0,
          totalAbsentDays: 0,
          totalTenPercentDays: 0,
          totalTwentyFivePercentDays: 0,
          totalHundredPercentDays: 0,
          totalPresentDays: 0,
          totalDeductionPercent: 0,
        },
      )
    },
    barChartSeries() {
      return [
        {
          name: 'Days',
          data: [
            this.summary.totalTenPercentDays,
            this.summary.totalTwentyFivePercentDays,
            this.summary.totalHundredPercentDays,
          ],
        },
      ]
    },
    barChartOptions() {
      return {
        chart: { toolbar: { show: false } },
        xaxis: { categories: ['10% Days', '25% Days', '100% Days'] },
        colors: ['#ff9f1a'],
        dataLabels: { enabled: true },
        plotOptions: { bar: { borderRadius: 6, columnWidth: '50%' } },
      }
    },
    donutChartSeries() {
      return [this.summary.totalPresentDays, this.summary.totalAbsentDays]
    },
    donutChartOptions() {
      return {
        labels: ['Present Days', 'Absent Days'],
        colors: ['#10b981', '#ef4444'],
        legend: { position: 'bottom' },
        dataLabels: { enabled: true },
      }
    },
  },
  methods: {
    applyMonthSelection() {
      if (!this.selectedMonth) return
      const [year, month] = this.selectedMonth.split('-').map(Number)
      if (!year || !month) return
      const from = new Date(year, month - 1, 1)
      const to = new Date(year, month, 0)
      this.startDate = this.formatDate(from)
      this.endDate = this.formatDate(to)
    },
    async handleFileUpload(event) {
      const file = event?.target?.files?.[0]
      if (!file) return
      const lowerName = String(file.name || '').toLowerCase()
      try {
        if (lowerName.endsWith('.csv')) {
          const text = await file.text()
          this.rawRows = this.parseCsv(text)
        } else if (lowerName.endsWith('.json')) {
          const text = await file.text()
          const parsed = JSON.parse(text)
          this.rawRows = Array.isArray(parsed) ? parsed : (parsed?.data || [])
        } else {
          alert('Unsupported file type. Please upload CSV or JSON.')
        }
      } catch (error) {
        console.error('Failed to parse uploaded file:', error)
        alert('Failed to parse uploaded file.')
      } finally {
        event.target.value = ''
      }
    },
    parseCsv(text) {
      const lines = String(text || '').split(/\r?\n/).filter((line) => line.trim())
      if (!lines.length) return []
      const headers = lines[0].split(',').map((h) => h.trim().replace(/^"|"$/g, ''))
      return lines.slice(1).map((line) => {
        const cols = line.split(',').map((c) => c.trim().replace(/^"|"$/g, ''))
        const row = {}
        headers.forEach((header, idx) => {
          row[header] = cols[idx] ?? ''
        })
        return row
      })
    },
    async fetchReport() {
      try {
        const params = {}
        if (this.startDate) params.start_date = this.startDate
        if (this.endDate) params.end_date = this.endDate

        const res = await axios.get('/api/attendance/period-report', { params })
        const payload = Array.isArray(res?.data?.data) ? res.data.data : []

        // Accept both raw attendance rows and already-aggregated API rows.
        if (payload.length && payload[0]?.present !== undefined && payload[0]?.late !== undefined) {
          const generated = []
          const rangeDates = this.workingDatesInRange

          for (const item of payload) {
            const name = item.name || item.employee_name || item.employee || 'Unknown'
            const presentCount = Number(item.present || 0)
            const lateCount = Number(item.late || 0)
            const absentCount = Number(item.absent || 0)
            const totalCount = presentCount + lateCount
            const fallbackDate = this.startDate || this.formatDate(new Date())
            const attendanceDates = rangeDates.length
              ? Array.from({ length: totalCount }, (_, index) => rangeDates[index % rangeDates.length])
              : Array.from({ length: totalCount }, () => fallbackDate)
            const absentDates = rangeDates.length
              ? Array.from({ length: absentCount }, (_, index) => rangeDates[(totalCount + index) % rangeDates.length])
              : Array.from({ length: absentCount }, () => fallbackDate)
            let dateIndex = 0

            for (let i = 0; i < presentCount; i += 1) {
              generated.push({ 'Employee Name': name, Date: attendanceDates[dateIndex], 'Check-in Time': '09:10 AM' })
              dateIndex += 1
            }
            for (let i = 0; i < lateCount; i += 1) {
              generated.push({ 'Employee Name': name, Date: attendanceDates[dateIndex], 'Check-in Time': '09:35 AM' })
              dateIndex += 1
            }
            // Keep absent weekday dates as explicit no-punch records so they are counted correctly.
            for (const absentDate of absentDates) {
              generated.push({ 'Employee Name': name, Date: absentDate, 'Check-in Time': '' })
            }
          }
          this.rawRows = generated
        } else {
          this.rawRows = payload
        }
      } catch (error) {
        console.error('Error fetching attendance data:', error)
        this.rawRows = []
      }
    },
    normalizeText(value) {
      return String(value || '')
        .toLowerCase()
        .replace(/\s+/g, ' ')
        .trim()
    },
    matchesSmartSearch(row, query) {
      const tokens = this.normalizeText(query).split(' ').filter(Boolean)
      if (!tokens.length) return true

      const riskLabel =
        row.avgDeductionPerDay >= 25 ? 'high' : row.avgDeductionPerDay >= 10 ? 'medium' : 'low'

      const searchable = this.normalizeText(
        [
          row.employeeName,
          riskLabel,
          `${row.totalDays}`,
          `${row.presentDays}`,
          `${row.absentDays}`,
          `${row.d10}`,
          `${row.d25}`,
          `${row.d100}`,
          `${row.totalDeductionPercent.toFixed(1)}%`,
          ...row.dailyBreakdown.map((item) => item.date),
        ].join(' '),
      )

      return tokens.every((token) => {
        if (token === '10%' || token === '10') return row.d10 > 0 || searchable.includes('10')
        if (token === '25%' || token === '25') return row.d25 > 0 || searchable.includes('25')
        if (token === '100%' || token === '100') return row.d100 > 0 || searchable.includes('100')
        if (token === '0%' || token === '0') return row.noDeductionDays > 0
        if (token === 'high' || token === 'medium' || token === 'low') return riskLabel === token
        return searchable.includes(token)
      })
    },
    formatDate(date) {
      return date.toISOString().split('T')[0]
    },
    pickField(row, keys) {
      for (const key of keys) {
        if (row[key] !== undefined && row[key] !== null && String(row[key]).trim() !== '') {
          return String(row[key]).trim()
        }
      }
      return ''
    },
    normalizeDate(value) {
      if (!value) return ''
      if (value instanceof Date && !Number.isNaN(value.getTime())) return this.formatDate(value)
      const str = String(value).trim()
      const date = new Date(str)
      if (!Number.isNaN(date.getTime())) return this.formatDate(date)
      return ''
    },
    toMinutes(value) {
      if (!value) return null
      if (value instanceof Date && !Number.isNaN(value.getTime())) {
        return value.getHours() * 60 + value.getMinutes()
      }
      const str = String(value).trim()
      const dateParsed = new Date(str)
      if (!Number.isNaN(dateParsed.getTime()) && (str.includes('T') || str.includes('-'))) {
        return dateParsed.getHours() * 60 + dateParsed.getMinutes()
      }

      const match = str.match(/(\d{1,2}):(\d{2})(?:\s*(AM|PM))?/i)
      if (!match) return null
      let hour = Number(match[1])
      const minute = Number(match[2])
      const ampm = match[3] ? match[3].toUpperCase() : null
      if (ampm === 'PM' && hour < 12) hour += 12
      if (ampm === 'AM' && hour === 12) hour = 0
      if (hour < 0 || hour > 23 || minute < 0 || minute > 59) return null
      return hour * 60 + minute
    },
    getDeduction(minutes) {
      if (minutes <= 9 * 60 + 15) return 0
      if (minutes >= START_10 && minutes <= END_10) return 10
      if (minutes >= START_25 && minutes <= END_25) return 25
      if (minutes >= START_100) return 100
      return 0
    },
    isInDateRange(dateString) {
      if (!this.startDate && !this.endDate) return true
      if (this.startDate && dateString < this.startDate) return false
      if (this.endDate && dateString > this.endDate) return false
      return true
    },
    riskClass(row) {
      if (row.avgDeductionPerDay >= 25) return 'risk-high'
      if (row.avgDeductionPerDay >= 10) return 'risk-medium'
      return 'risk-low'
    },
    riskBadgeClass(row) {
      if (row.avgDeductionPerDay >= 25) return 'danger'
      if (row.avgDeductionPerDay >= 10) return 'warning'
      return 'safe'
    },
    deductionClass(value, status = '') {
      if (status === 'Absent') return 'slate'
      if (value === 0) return 'green'
      if (value === 10) return 'yellow'
      if (value === 25) return 'orange'
      return 'red'
    },
    getDateRange(start, end) {
      if (!start || !end) return []
      const dates = []
      const cursor = new Date(`${start}T00:00:00`)
      const to = new Date(`${end}T00:00:00`)
      while (cursor <= to) {
        dates.push(this.formatDate(new Date(cursor)))
        cursor.setDate(cursor.getDate() + 1)
      }
      return dates
    },
    initials(name) {
      const parts = String(name || '').trim().split(/\s+/).slice(0, 2)
      return parts.map((part) => part[0]?.toUpperCase() || '').join('') || 'U'
    },
    formatMinutes(minutes) {
      if (minutes === null || minutes === undefined) return '-'
      const hour = Math.floor(minutes / 60)
      const minute = minutes % 60
      const meridiem = hour >= 12 ? 'PM' : 'AM'
      const hour12 = hour % 12 || 12
      return `${String(hour12).padStart(2, '0')}:${String(minute).padStart(2, '0')} ${meridiem}`
    },
    openEmployeeProfile(row) {
      this.selectedEmployee = row
      this.showDailyBreakdownModal = false
    },
    closeEmployeeProfile() {
      this.selectedEmployee = null
      this.showDailyBreakdownModal = false
    },
    openDailyBreakdown() {
      this.showDailyBreakdownModal = true
    },
    exportReport() {
      const exportRows = this.processedRows.map((row) => ({
        'Employee Name': row.employeeName,
        'Total Working Days': row.totalDays,
        'Present Days': row.noDeductionDays,
        'Absent Days': row.absentDays,
        '10% Days Count': row.d10,
        '25% Days Count': row.d25,
        '100% Days Count': row.d100,
        'Total Deduction %': row.totalDeductionPercent.toFixed(1),
      }))
      const headers = Object.keys(exportRows[0] || {})
      const csvLines = [headers.join(',')]
      for (const row of exportRows) {
        csvLines.push(
          headers
            .map((key) => `"${String(row[key] ?? '').replace(/"/g, '""')}"`)
            .join(','),
        )
      }
      const csv = `\uFEFF${csvLines.join('\n')}`
      const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
      const link = document.createElement('a')
      link.href = URL.createObjectURL(blob)
      link.download = `attendance-monthly-report-${this.startDate}-to-${this.endDate}.csv`
      link.click()
      URL.revokeObjectURL(link.href)
    },
    printPage() {
      window.print()
    },
  },
}
</script>

<style scoped>
* { box-sizing: border-box; }

.attendance-report-page {
  padding: 16px;
  background: #f7f8fc;
  min-height: 100vh;
}

.toolbar {
  background: #fff;
  border: 1px solid #e8ebf4;
  border-radius: 14px;
  padding: 14px;
  display: grid;
  gap: 12px;
  box-shadow: 0 12px 28px rgba(17, 24, 39, 0.06);
}

.toolbar-title h2 {
  margin: 0;
  font-size: 20px;
  color: #111827;
}

.toolbar-title p {
  margin: 6px 0 0;
  font-size: 12px;
  color: #6b7280;
}

.toolbar-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}

.date-fields {
  display: flex;
  align-items: center;
  gap: 8px;
}

.date-fields label {
  font-size: 12px;
  color: #4b5563;
}

input, select {
  border: 1px solid #d9deea;
  border-radius: 8px;
  min-height: 36px;
  padding: 0 10px;
  font-size: 13px;
}

.btn {
  border: 1px solid #d9deea;
  background: #fff;
  color: #111827;
  border-radius: 8px;
  min-height: 36px;
  padding: 0 12px;
  font-size: 13px;
}
.upload-btn {
  position: relative;
  overflow: hidden;
}
.upload-btn input[type='file'] {
  position: absolute;
  inset: 0;
  opacity: 0;
  cursor: pointer;
}

.btn.primary { background: #0f4ae8; border-color: #0f4ae8; color: #fff; }
.btn.success { background: #16a34a; border-color: #16a34a; color: #fff; }
.btn.print { background: #0ea5e9; border-color: #0ea5e9; color: #fff; }

.kpi-grid {
  margin-top: 12px;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 10px;
}

.kpi-card {
  background: #fff;
  border: 1px solid #e8ebf4;
  border-radius: 12px;
  padding: 10px 12px;
  box-shadow: 0 8px 20px rgba(17, 24, 39, 0.05);
}

.kpi-card span {
  display: block;
  color: #6b7280;
  font-size: 12px;
}

.kpi-card strong {
  font-size: 22px;
  color: #111827;
  line-height: 1.2;
}

.kpi-card.warning strong { color: #f59e0b; }
.kpi-card.danger strong { color: #ef4444; }
.kpi-card.present-kpi strong { color: #0ea5e9; }

.charts-grid {
  margin-top: 12px;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: 10px;
}

.chart-card {
  background: #fff;
  border: 1px solid #e8ebf4;
  border-radius: 12px;
  padding: 10px;
  box-shadow: 0 8px 20px rgba(17, 24, 39, 0.05);
}

.chart-card h6 {
  margin: 0 0 8px;
  font-size: 14px;
  color: #111827;
}

.table-controls {
  margin-top: 12px;
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.search-input {
  min-width: 440px;
  border: none;
  outline: none;
  background: transparent;
  flex: 1;
}

.search-wrap {
  min-width: min(560px, 100%);
  background: #fff;
  border: 1px solid #d9deea;
  border-radius: 10px;
  min-height: 38px;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 0 10px;
  box-shadow: 0 6px 16px rgba(15, 23, 42, 0.04);
}

.search-wrap > i {
  color: #9ca3af;
  font-size: 16px;
}

.search-clear {
  border: none;
  background: #f3f4f6;
  color: #374151;
  border-radius: 999px;
  font-size: 11px;
  padding: 3px 8px;
  line-height: 1.2;
}

.table-shell {
  margin-top: 10px;
  background: #fff;
  border: 1px solid #e8ebf4;
  border-radius: 12px;
  overflow: auto;
  box-shadow: 0 10px 24px rgba(17, 24, 39, 0.05);
}

table {
  width: 100%;
  border-collapse: collapse;
  min-width: 900px;
}

th, td {
  padding: 10px;
  border-bottom: 1px solid #eef1f6;
  font-size: 13px;
}

th {
  background: #f8f9fd;
  text-align: left;
  color: #4b5563;
  font-weight: 600;
}

.center { text-align: center; }

.employee-link {
  border: none;
  background: transparent;
  color: #0f4ae8;
  font-weight: 600;
  padding: 0;
}

.deduction-10 { color: #d97706; font-weight: 700; }
.deduction-25 { color: #ea580c; font-weight: 700; }
.deduction-100 { color: #dc2626; font-weight: 700; }

.total-badge {
  display: inline-flex;
  min-width: 74px;
  justify-content: center;
  padding: 4px 10px;
  border-radius: 999px;
  font-weight: 700;
  font-size: 12px;
}
.total-badge.safe { background: #dcfce7; color: #166534; }
.total-badge.warning { background: #fef3c7; color: #b45309; }
.total-badge.danger { background: #fee2e2; color: #b91c1c; }

tr.risk-high { background: #fff7f7; }
tr.risk-medium { background: #fffdf4; }
tr.risk-low { background: #f6fff8; }

.empty-cell {
  text-align: center;
  color: #9ca3af;
  padding: 26px;
}

.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
  display: grid;
  place-items: center;
  z-index: 1200;
}

.profile-modal,
.breakdown-modal {
  width: min(760px, 94vw);
  max-height: 88vh;
  overflow: auto;
  background: #fff;
  border-radius: 16px;
  border: 1px solid #e8ebf4;
  box-shadow: 0 20px 44px rgba(15, 23, 42, 0.18);
  padding: 16px;
  position: relative;
}

.modal-close {
  position: absolute;
  top: 10px;
  right: 12px;
  border: none;
  background: transparent;
  font-size: 24px;
  line-height: 1;
  color: #6b7280;
}

.profile-head {
  display: flex;
  gap: 12px;
  align-items: center;
}

.profile-avatar {
  width: 46px;
  height: 46px;
  border-radius: 50%;
  display: grid;
  place-items: center;
  background: #0f4ae8;
  color: #fff;
  font-weight: 700;
}

.profile-head h4 {
  margin: 0;
  font-size: 18px;
  color: #111827;
}

.profile-head p {
  margin: 4px 0 0;
  color: #6b7280;
  font-size: 12px;
}

.profile-stats {
  margin-top: 14px;
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.chip {
  border-radius: 999px;
  padding: 5px 10px;
  font-size: 12px;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
}
.chip.green { background: #dcfce7; color: #166534; }
.chip.yellow { background: #fef9c3; color: #a16207; }
.chip.orange { background: #ffedd5; color: #c2410c; }
.chip.red { background: #fee2e2; color: #b91c1c; }
.chip.slate { background: #e5e7eb; color: #374151; }

.profile-meta {
  margin-top: 12px;
  color: #374151;
  font-size: 13px;
}

.profile-actions {
  margin-top: 14px;
  display: flex;
  justify-content: flex-end;
}

.breakdown-modal h4 {
  margin: 0 0 12px;
  color: #111827;
}

.breakdown-table-shell {
  border: 1px solid #e8ebf4;
  border-radius: 10px;
  overflow: auto;
}

.breakdown-table {
  min-width: 520px;
}

@media (max-width: 768px) {
  .attendance-report-page {
    padding: 10px;
  }
  .date-fields {
    width: 100%;
    flex-wrap: wrap;
  }
  .date-fields input {
    flex: 1;
    min-width: 140px;
  }
  .search-wrap {
    min-width: 100%;
  }
  .search-input {
    min-width: 0;
  }
}

@media print {
  @page {
    size: A4 landscape;
    margin: 8mm;
  }

  .no-print {
    display: none !important;
  }

  .modal-overlay {
    display: none !important;
  }

  .attendance-report-page {
    background: #fff;
    padding: 0;
  }

  .table-shell {
    border: none;
    overflow: visible !important;
    box-shadow: none !important;
  }

  table {
    min-width: 100% !important;
    width: 100% !important;
    table-layout: fixed;
    page-break-inside: auto;
  }

  tr {
    page-break-inside: avoid;
    page-break-after: auto;
  }

  th, td {
    font-size: 11px;
    padding: 6px 4px;
    white-space: normal;
    word-break: break-word;
  }

  .employee-link {
    color: #111827 !important;
    text-decoration: none !important;
    pointer-events: none;
  }
}
</style>