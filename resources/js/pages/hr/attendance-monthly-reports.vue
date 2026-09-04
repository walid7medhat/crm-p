<template>
  <div class="ar-page">
    <div class="ar-shell">
      <div class="ar-top no-print">
        <div class="ar-top__left">
          <h6 class="ar-title">Monthly deductions</h6>
          <div class="ar-policy">
            <span><b>10%</b> 09:16–10:00</span>
            <span><b>25%</b> 10:01–12:00</span>
            <span><b>100%</b> after 12:01</span>
          </div>
        </div>
        <label class="ar-people-search">
          <iconify-icon icon="lucide:search" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search employee by name…"
          />
          <button v-if="searchQuery" type="button" class="ar-people-search__clear" @click="searchQuery = ''">Clear</button>
        </label>
      </div>

      <div class="ar-toolbar no-print">
        <div class="ar-toolbar__dates">
          <label class="ar-field">
            <span>Month</span>
            <input v-model="selectedMonth" type="month" @change="applyMonthSelection" />
          </label>
          <label class="ar-field">
            <span>From</span>
            <input v-model="startDate" type="date" :max="maxDate" @change="validateDateRange" />
          </label>
          <label class="ar-field">
            <span>To</span>
            <input v-model="endDate" type="date" :max="maxDate" @change="validateDateRange" />
          </label>
          <select v-model="employeeFilter" class="ar-select">
            <option value="all">All employees</option>
            <option v-for="emp in employeeOptions" :key="emp" :value="emp">{{ emp }}</option>
          </select>
          <select v-model="riskFilter" class="ar-select">
            <option value="all">All flags</option>
            <option value="high-absence">High absence</option>
            <option value="high-100">High 100%</option>
          </select>
        </div>
        <div class="ar-toolbar__actions">
          <label class="ar-btn ar-upload">
            <iconify-icon icon="lucide:upload" />
            Upload
            <input type="file" accept=".csv,.json" @change="handleFileUpload" />
          </label>
          <button type="button" class="ar-btn ar-btn--primary" @click="fetchReport">
            <iconify-icon icon="lucide:refresh-cw" />
            Run
          </button>
          <button type="button" class="ar-btn" @click="exportReport">
            <iconify-icon icon="lucide:download" />
            Export
          </button>
          <button type="button" class="ar-btn" @click="printPage">
            <iconify-icon icon="lucide:printer" />
            Print
          </button>
        </div>
      </div>

      <div class="ar-kpi">
        <article v-for="kpi in kpiCards" :key="kpi.label" class="ar-kpi__card" :class="kpi.tone">
          <span>{{ kpi.label }}</span>
          <strong>{{ kpi.value }}</strong>
        </article>
      </div>

      <div class="ar-charts no-print">
        <div class="ar-card">
          <p class="ar-card__title">Deduction distribution</p>
          <apexchart type="bar" height="200" :options="barChartOptions" :series="barChartSeries" />
        </div>
        <div class="ar-card">
          <p class="ar-card__title">Attendance vs absence</p>
          <apexchart type="donut" height="200" :options="donutChartOptions" :series="donutChartSeries" />
        </div>
      </div>

      <div class="ar-card ar-table-card">
        <div class="ar-table-head no-print">
          <p class="ar-card__title">Employees <span>{{ processedRows.length }}</span></p>
          <div class="ar-table-head__right">
            <select v-model="sortBy" class="ar-select ar-select--sm">
              <option value="name">Name</option>
              <option value="totalDays">Total days</option>
              <option value="noDeductionDays">Present</option>
              <option value="absentDays">Absent</option>
              <option value="d10">10%</option>
              <option value="d25">25%</option>
              <option value="d100">100%</option>
              <option value="totalDeductionPercent">Deduction %</option>
            </select>
            <button type="button" class="ar-btn ar-btn--sm" @click="sortOrder = sortOrder === 'asc' ? 'desc' : 'asc'">
              {{ sortOrder === 'asc' ? 'Asc' : 'Desc' }}
            </button>
          </div>
        </div>

        <div class="ar-table-shell">
          <table>
            <thead>
              <tr>
                <th class="center">#</th>
                <th>Employee</th>
                <th class="center">Total</th>
                <th class="center">Present</th>
                <th class="center">Absent</th>
                <th class="center">Weekend</th>
                <th class="center">10%</th>
                <th class="center">25%</th>
                <th class="center">100%</th>
                <th class="center no-print"></th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(row, index) in processedRows"
                :key="row.employeeName"
                :class="riskClass(row)"
              >
                <td class="center ar-muted">{{ index + 1 }}</td>
                <td>
                  <button type="button" class="ar-employee" @click="openEmployeeProfile(row)">
                    <span class="ar-employee__avatar">{{ initials(row.employeeName) }}</span>
                    <span class="ar-employee__name">{{ row.employeeName }}</span>
                  </button>
                </td>
                <td class="center">{{ row.totalDays }}</td>
                <td class="center">{{ row.presentDays }}</td>
                <td class="center">{{ row.absentDays }}</td>
                <td class="center">{{ row.weekendDays }}</td>
                <td class="center"><span class="ar-pill ar-pill--warn">{{ row.d10 }}</span></td>
                <td class="center"><span class="ar-pill ar-pill--orange">{{ row.d25 }}</span></td>
                <td class="center"><span class="ar-pill ar-pill--danger">{{ row.d100 }}</span></td>
                <td class="center no-print">
                  <button type="button" class="ar-link" @click="openEmployeeProfile(row)">View</button>
                </td>
              </tr>
              <tr v-if="!processedRows.length">
                <td colspan="10" class="ar-empty">No employees found for this range.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div v-if="selectedEmployee" class="ar-modal-overlay no-print" @click.self="closeEmployeeProfile">
      <div class="ar-modal" role="dialog" aria-modal="true">
        <header class="ar-modal__head">
          <div class="ar-modal__identity">
            <div class="ar-modal__avatar">{{ initials(selectedEmployee.employeeName) }}</div>
            <div>
              <p class="ar-modal__name">{{ selectedEmployee.employeeName }}</p>
              <p class="ar-modal__meta">{{ selectedEmployee.totalDays }} working days · {{ selectedEmployee.totalDeductionPercent.toFixed(1) }}% avg deduction</p>
            </div>
          </div>
          <button type="button" class="ar-modal__close" aria-label="Close" @click="closeEmployeeProfile">
            <iconify-icon icon="lucide:x" />
          </button>
        </header>

        <div class="ar-modal__stats">
          <div class="ar-stat"><span>On time</span><strong>{{ selectedEmployee.noDeductionDays }}</strong></div>
          <div class="ar-stat"><span>Present</span><strong>{{ selectedEmployee.presentDays }}</strong></div>
          <div class="ar-stat"><span>Absent</span><strong>{{ selectedEmployee.absentDays }}</strong></div>
          <div class="ar-stat"><span>Weekend</span><strong>{{ selectedEmployee.weekendDays }}</strong></div>
          <div class="ar-stat tone-warn"><span>10%</span><strong>{{ selectedEmployee.d10 }}</strong></div>
          <div class="ar-stat tone-orange"><span>25%</span><strong>{{ selectedEmployee.d25 }}</strong></div>
          <div class="ar-stat tone-danger"><span>100%</span><strong>{{ selectedEmployee.d100 }}</strong></div>
        </div>

        <p class="ar-modal__note">
          Validation: {{ selectedEmployee.presentDays }} present + {{ selectedEmployee.absentDays }} absent = {{ selectedEmployee.totalDays }} working days
        </p>

        <div class="ar-breakdown">
          <table>
            <thead>
              <tr>
                <th>Date</th>
                <th>Punch in</th>
                <th>Punch out</th>
                <th>Status</th>
                <th>Deduction</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in selectedEmployee.dailyBreakdown" :key="`${item.date}-${item.checkInMinutes}-${item.checkOutMinutes}`">
                <td>{{ item.date }}</td>
                <td>{{ formatMinutes(item.checkInMinutes) }}</td>
                <td>{{ formatMinutes(item.checkOutMinutes) }}</td>
                <td>{{ item.status }}</td>
                <td>
                  <span class="ar-pill" :class="deductionPillClass(item.deduction, item.status)">
                    {{ item.deduction === null ? '—' : `${item.deduction}%` }}
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

const START_10 = 9 * 60 + 16      // 9:16 AM
const END_10 = 10 * 60            // 10:00 AM (inclusive)
const START_25 = 10 * 60 + 1      // 10:01 AM
const END_25 = 12 * 60            // 12:00 PM
const START_100 = 12 * 60 + 1     // 12:01 PM

export default {
  name: 'AttendanceMonthlyReports',
  data() {
    return {
      startDate: '',
      endDate: '',
      selectedMonth: '',
      rawRows: [],
      selectedEmployee: null,
      searchQuery: '',
      riskFilter: 'all',
      employeeFilter: 'all',
      sortBy: 'noDeductionDays',
      sortOrder: 'desc',
    }
  },
  async mounted() {
const today = new Date()
  today.setHours(0,0,0,0)

    const now = new Date()
    this.startDate = this.formatDate(new Date(now.getFullYear(), now.getMonth(), 1))
    // this.endDate = this.formatDate(new Date(now.getFullYear(), now.getMonth() + 1, 0))
      this.endDate =this.formatDate(today)
    this.selectedMonth = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`
    this.fetchReport()
  },
  computed: {
    maxDate() {
      const today = new Date()
      return this.formatDate(today)
    },
    calendarDatesInRange() {
      if (!this.startDate || !this.endDate) return []

      const normalizeDate = (d) => new Date(d.getFullYear(), d.getMonth(), d.getDate())
      const startDate = normalizeDate(new Date(`${this.startDate}T00:00:00`))
      const endDate = normalizeDate(new Date(`${this.endDate}T00:00:00`))

      if (
        Number.isNaN(startDate.getTime())
        || Number.isNaN(endDate.getTime())
        || startDate > endDate
      ) {
        return []
      }

      const dates = []
      let current = new Date(startDate)
      while (current <= endDate) {
        dates.push(normalizeDate(new Date(current)))
        current.setDate(current.getDate() + 1)
      }
      return dates
    },
    workingDatesInRange() {
      return this.calendarDatesInRange.filter((date) => date.getDay() >= 1 && date.getDay() <= 6)
    },
    workingDaysCountInRange() {
      return this.workingDatesInRange.length
    },
    employeeOptions() {
      return [...new Set(this.groupedRows.map((row) => row.employeeName))].sort((a, b) => a.localeCompare(b))
    },
    groupedRows() {
      const start = this.normalizeDateObject(this.startDate)
      const end = this.normalizeDateObject(this.endDate)

      const filteredRecords = this.rawRows.filter((record) => {
        const recordDateRaw = this.pickField(record, ['Date', 'date', 'attendance_date', 'day'])
        const recordDate = this.normalizeDateObject(recordDateRaw)
        return !!recordDate && (!start || recordDate >= start) && (!end || recordDate <= end)
      })

      const grouped = new Map()
      for (const row of filteredRecords) {
        const employeeName = this.pickField(row, [
          'Employee Name',
          'employee_name',
          'name',
          'Employee',
          'employee',
        ])
        const employeeId = this.pickField(row, [
          'Employee ID',
          'employee_id',
          'emp_id',
          'id',
          'user_id',
        ])
        const department = this.pickField(row, [
          'Department',
          'department',
          'department_name',
          'dept',
        ])
        const dateRaw = this.pickField(row, ['Date', 'date', 'attendance_date', 'day'])
        const checkInRaw = this.pickField(row, [
          'Check-in Time',
          'check_in_time',
          'check_in',
          'checkin',
          'time_in',
        ])
        const checkOutRaw = this.pickField(row, [
          'Check-out Time',
          'check_out_time',
          'check_out',
          'checkout',
          'time_out',
        ])
  const biometricCode = this.pickField(row, [
          'Biometric Code',
          'biometric_code',
        ])
        const date = this.normalizeDate(dateRaw)
        const checkInMinutes = this.toMinutes(checkInRaw)
        const checkOutMinutes = this.toMinutes(checkOutRaw)
        if (!employeeName || !date) continue

        const groupKey = employeeId ? `${employeeName}__${employeeId}` : employeeName
        if (!grouped.has(groupKey)) {
          grouped.set(groupKey, {
            employeeName,
             biometricCode: biometricCode || '',
            employeeId: employeeId || '',
            department: department || '',
            byDate: new Map(),
          })
        }
        const group = grouped.get(groupKey)
        if (!group.employeeId && employeeId) group.employeeId = employeeId
        if (!group.department && department) group.department = department
        const byDate = group.byDate
        const current = byDate.get(date)

        if (current === undefined) {
          byDate.set(date, { checkInMinutes, checkOutMinutes })
        } else {
          const next = { ...current }
          if (next.checkInMinutes === null || next.checkInMinutes === undefined) {
            next.checkInMinutes = checkInMinutes
          } else if (checkInMinutes !== null && checkInMinutes !== undefined && checkInMinutes < next.checkInMinutes) {
            next.checkInMinutes = checkInMinutes
          }

          if (next.checkOutMinutes === null || next.checkOutMinutes === undefined) {
            next.checkOutMinutes = checkOutMinutes
          } else if (checkOutMinutes !== null && checkOutMinutes !== undefined && checkOutMinutes > next.checkOutMinutes) {
            next.checkOutMinutes = checkOutMinutes
          }

          byDate.set(date, next)
        }
      }

      const rangeDates = this.calendarDatesInRange
      const totalWorkingDays = this.workingDaysCountInRange
      const rows = []
      for (const [, employeeData] of grouped.entries()) {
        const employeeName = employeeData.employeeName
        const employeeId = employeeData.employeeId || ''
        const department = employeeData.department || ''
        const biometricCode = employeeData.biometricCode || ''
        const byDate = employeeData.byDate
        let d10 = 0
        let d25 = 0
        let d100 = 0
        let noDeductionDays = 0
        let absentDays = 0
        let weekendDays = 0
        let weightedDeduction = 0
        const dailyBreakdown = []
        const totalDays = totalWorkingDays

        for (const date of rangeDates) {
          const dateKey = this.formatDate(date)
          // 1) Day type first
          const dayIndex = date.getDay()
          const record = byDate.get(dateKey)
          const minutes = record?.checkInMinutes
          const checkOutMinutes = record?.checkOutMinutes ?? null

          // Weekend is handled separately and never counted as absent.|| dayIndex === 6
          if (dayIndex === 0 ) {
            weekendDays += 1
            dailyBreakdown.push({
              date: dateKey,
              checkInMinutes: minutes ?? null,
              checkOutMinutes,
              deduction: null,
              status: 'Weekend',
            })
            continue
          }

          // 2) Attendance check for weekdays (Mon-Fri)
          if (minutes === undefined || minutes === null) {
              absentDays += 1
            
              weightedDeduction += 100 // ✅ مهم جدا
            
              d100 += 1 // ✅ لو عايز تحسبه ضمن 100%
            
              dailyBreakdown.push({
                date: dateKey,
                checkInMinutes: null,
                checkOutMinutes: null,
                deduction: 100, // بدل null
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
            date: dateKey,
            checkInMinutes: minutes,
            checkOutMinutes,
            deduction,
            status: deduction === 0 ? 'Present' : 'Late',
          })
        }
        dailyBreakdown.sort((a, b) => a.date.localeCompare(b.date))
        const presentDays = noDeductionDays + d10 + d25 + d100
        const totalDeductionPercent = totalDays
          ? Math.min(100, weightedDeduction / totalDays)
          : 0
        const avgDeductionPerDay = totalDeductionPercent
        rows.push({
          employeeName,
          employeeId,
          biometricCode,
          department,
          totalDays,
          noDeductionDays,
          absentDays,
          presentDays,
          weekendDays,
          d10,
          d25,
          d100,
          totalDeductionPercent,
          avgDeductionPerDay,
          dailyBreakdown,
        })
      }

      return rows
    },
    processedRows() {
      return this.groupedRows
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
          acc.totalPresentDays += row.presentDays
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
        chart: { toolbar: { show: false }, fontFamily: 'inherit', parentHeightOffset: 0 },
        xaxis: {
          categories: ['10%', '25%', '100%'],
          labels: { style: { colors: '#6b7280', fontSize: '11px' } },
          axisBorder: { show: false },
          axisTicks: { show: false },
        },
        yaxis: {
          labels: { style: { colors: '#9ca3af', fontSize: '10px' } },
        },
        colors: ['#0b0736'],
        dataLabels: {
          enabled: true,
          style: { fontSize: '11px', fontWeight: 600, colors: ['#fff'] },
        },
        plotOptions: {
          bar: { borderRadius: 6, columnWidth: '45%' },
        },
        grid: { borderColor: '#f0ecf5', strokeDashArray: 3 },
        legend: { show: false },
        tooltip: { theme: 'light' },
      }
    },
    donutChartSeries() {
      return [this.summary.totalPresentDays, this.summary.totalAbsentDays]
    },
    donutChartOptions() {
      return {
        labels: ['Present', 'Absent'],
        colors: ['#0b0736', '#dc2626'],
        legend: {
          position: 'bottom',
          fontSize: '11px',
          labels: { colors: '#6b7280' },
          markers: { width: 7, height: 7, radius: 7 },
        },
        dataLabels: { enabled: false },
        stroke: { width: 0 },
        plotOptions: {
          pie: {
            donut: {
              size: '72%',
              labels: {
                show: true,
                name: { show: true, fontSize: '11px', color: '#6b7280', offsetY: 12 },
                value: { show: true, fontSize: '18px', fontWeight: 700, color: '#0b0736', offsetY: -8 },
                total: {
                  show: true,
                  label: 'Days',
                  fontSize: '11px',
                  color: '#9ca3af',
                  formatter: () => String(this.summary.totalPresentDays + this.summary.totalAbsentDays),
                },
              },
            },
          },
        },
      }
    },
    kpiCards() {
      const s = this.summary
      return [
        { label: 'Employees', value: s.totalEmployees, tone: '' },
        { label: 'Working days', value: s.totalWorkingDays, tone: '' },
        { label: 'Present', value: s.totalPresentDays, tone: 'is-present' },
        { label: 'Absent', value: s.totalAbsentDays, tone: 'is-danger' },
        { label: '10% days', value: s.totalTenPercentDays, tone: 'is-warn' },
        { label: '25% days', value: s.totalTwentyFivePercentDays, tone: 'is-orange' },
        { label: '100% days', value: s.totalHundredPercentDays, tone: 'is-danger' },
      ]
    },
  },
  methods: {
     validateDateRange() {
      const today = this.formatDate(new Date())
    
      let start = this.startDate
      let end = this.endDate
    
      if (start > today) {
        this.startDate = today
        alert('Start date cannot be in the future')
      }
    
      if (end > today) {
        this.endDate = today
        alert('End date cannot be in the future'+today+end)
      }
    
      if (start && end && start > end) {
        this.endDate = this.startDate
        alert('Start date cannot be after end date')
      }
    },
    applyMonthSelection() {
      if (!this.selectedMonth) return
      const [year, month] = this.selectedMonth.split('-').map(Number)
      if (!year || !month) return
      
      const from = new Date(year, month - 1, 1)
      
      const to = new Date(year, month, 0)
      
      this.startDate = this.formatDate(from)
      this.endDate = this.formatDate(to)
      
      this.fetchReport()
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
        this.validateDateRange()
        const params = {}
        if (this.startDate) params.start_date = this.startDate
        if (this.endDate) params.end_date = this.endDate

        const res = await axios.get('/attendance/period-report', { params })
        const payload = Array.isArray(res?.data?.data) ? res.data.data : []

        const hasDailyBreakdown = payload.length && payload.some((u) => Array.isArray(u?.daily_breakdown))

        if (hasDailyBreakdown) {
          this.rawRows = payload.flatMap((user) =>
            (user.daily_breakdown || []).map((day) => ({
              'Employee Name': user.name,
              'Employee ID': user.employee_id || user.id || '',
                  'Biometric Code': user.biometric_code || '', 

              'Department':  user.department || '',
              Date: day.date,
              'Check-in Time': day.check_in,
              'Check-out Time': day.check_out,
            })),
          )
          
        } else if (payload.length && payload[0]?.present !== undefined && payload[0]?.late !== undefined) {
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
              generated.push({
                'Employee Name': name,
                'Employee ID': item.employee_id || item.id || '',
                'Department':  item.department || '',
                Date: attendanceDates[dateIndex],
                'Check-in Time': '09:10 AM',
                'Check-out Time': '06:00 PM',
              })
              dateIndex += 1
            }
            for (let i = 0; i < lateCount; i += 1) {
              generated.push({
                'Employee Name': name,
                'Employee ID': item.employee_id || item.id || '',
                'Department': item.department || '',
                Date: attendanceDates[dateIndex],
                'Check-in Time': '09:35 AM',
                'Check-out Time': '06:00 PM',
              })
              dateIndex += 1
            }
            for (const absentDate of absentDates) {
              generated.push({
                'Employee Name': name,
                'Employee ID': item.employee_id || item.id || '',
                'Department': item.department || '',
                Date: absentDate,
                'Check-in Time': '',
                'Check-out Time': '',
              })
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
      const y = date.getFullYear()
      const m = String(date.getMonth() + 1).padStart(2, '0')
      const d = String(date.getDate()).padStart(2, '0')
      return `${y}-${m}-${d}`
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
      const normalized = this.normalizeDateObject(value)
      if (normalized) return this.formatDate(normalized)
      return ''
    },
    normalizeDateObject(value) {
      if (!value) return null

      if (value instanceof Date && !Number.isNaN(value.getTime())) {
        return new Date(value.getFullYear(), value.getMonth(), value.getDate())
      }

      const str = String(value).trim()
      if (!str) return null

      // ISO date without time
      if (/^\d{4}-\d{2}-\d{2}$/.test(str)) {
        const [year, month, day] = str.split('-').map(Number)
        return new Date(year, month - 1, day)
      }

      // ISO datetime
      if (/^\d{4}-\d{2}-\d{2}T/.test(str)) {
        const [year, month, day] = str.slice(0, 10).split('-').map(Number)
        return new Date(year, month - 1, day)
      }

      const parsed = new Date(str)
      if (Number.isNaN(parsed.getTime())) return null
      return new Date(parsed.getFullYear(), parsed.getMonth(), parsed.getDate())
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
      if (minutes <= 9 * 60 + 16) return 0
      if (minutes >= START_10 && minutes <= END_10) return 10
      if (minutes >= START_25 && minutes <= END_25) return 25
      if (minutes >= START_100) return 100
      return 0
    },
    isInDateRange(dateValue) {
      const recordDate = this.normalizeDateObject(dateValue)
      if (!recordDate) return false

      const start = this.normalizeDateObject(this.startDate)
      const end = this.normalizeDateObject(this.endDate)
      if (!start && !end) return true
      if (start && recordDate < start) return false
      if (end && recordDate > end) return false
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
      if (status === 'Weekend') return 'sky'
      if (value === 0) return 'green'
      if (value === 10) return 'yellow'
      if (value === 25) return 'orange'
      return 'red'
    },
    deductionPillClass(value, status = '') {
      if (status === 'Absent') return 'ar-pill--muted'
      if (status === 'Weekend') return 'ar-pill--sky'
      if (value === 0) return 'ar-pill--ok'
      if (value === 10) return 'ar-pill--warn'
      if (value === 25) return 'ar-pill--orange'
      if (value === 100) return 'ar-pill--danger'
      return 'ar-pill--muted'
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
    },
    closeEmployeeProfile() {
      this.selectedEmployee = null
    },
    exportReport() {
      const dates = this.calendarDatesInRange.map((date) => this.formatDate(date))
      const baseHeaders = [
        'Employee Name',
        'Employee Code',
        'Department',
        'Total Working Days',
        'Present Days',
        'Absent Days',
        'Weekend Days',
        '10% Days Count',
        '25% Days Count',
        '100% Days Count',
      ]
      const perDayHeaders = dates.flatMap((date) => ([
        `${date} In`,
        `${date} Out`,
        `${date} Status`,
      ]))
      const headers = [...baseHeaders, ...perDayHeaders]

      const exportRows = this.processedRows.map((row) => {
        const dailyMap = new Map(row.dailyBreakdown.map((item) => [item.date, item]))
        const exportRow = {
          'Employee Name': row.employeeName,
          'Employee Code': row.biometricCode || '',
          'Department': row.department || '',
          'Total Working Days': row.totalDays,
          'Present Days': row.presentDays,
          'Absent Days': row.absentDays,
          'Weekend Days': row.weekendDays,
          '10% Days Count': row.d10,
          '25% Days Count': row.d25,
          '100% Days Count': row.d100,
        }

        for (const date of dates) {
          const day = dailyMap.get(date)
          exportRow[`${date} In`] = day ? this.formatMinutes(day.checkInMinutes) : '-'
          exportRow[`${date} Out`] = day ? this.formatMinutes(day.checkOutMinutes) : '-'
          exportRow[`${date} Status`] = day?.status || '-'
          
        }

        return exportRow
      })

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
.ar-page {
  --navy: #0b0736;
  --purple: #733e87;
  --border: #ece8f3;
  --muted: #6b7280;
  padding: 0;
  background: transparent;
  min-height: 0;
  font-size: 13px;
  color: #111827;
}

.ar-shell {
  background: #fff;
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 14px;
  box-shadow: 0 8px 24px rgba(11, 7, 54, 0.06);
}

.ar-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 12px;
  padding-bottom: 12px;
  border-bottom: 1px solid #f3f0f7;
}
.ar-top__left {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 0;
}
.ar-title {
  margin: 0 !important;
  padding: 0 !important;
  font-size: 15px !important;
  font-weight: 700 !important;
  line-height: 1.3 !important;
  color: var(--navy) !important;
}
.ar-policy {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.ar-policy span {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  height: 24px;
  padding: 0 8px;
  border-radius: 999px;
  background: #f7f5fa;
  border: 1px solid var(--border);
  font-size: 11px !important;
  color: #4b5563;
  font-weight: 500;
}
.ar-policy b {
  color: var(--navy);
  font-weight: 700;
}

.ar-people-search {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  width: min(340px, 100%);
  height: 36px;
  padding: 0 12px;
  border: 1px solid #e5e7eb;
  border-radius: 999px;
  background: #faf8fc;
  color: #9ca3af;
  flex-shrink: 0;
}
.ar-people-search input {
  border: none !important;
  outline: none !important;
  box-shadow: none !important;
  background: transparent !important;
  width: 100%;
  height: 100%;
  padding: 0 !important;
  font-size: 13px !important;
  color: #111827 !important;
}
.ar-people-search__clear {
  border: none;
  background: #eef2f7;
  color: #4b5563;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 600;
  padding: 2px 8px;
  cursor: pointer;
}

.ar-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 12px;
}
.ar-toolbar__dates,
.ar-toolbar__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: flex-end;
}
.ar-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.ar-field span {
  font-size: 10px !important;
  font-weight: 700;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: 0.03em;
}
.ar-field input,
.ar-select {
  height: 34px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 0 10px;
  font-size: 12px !important;
  color: #111827;
  background: #fff;
  min-width: 120px;
}
.ar-select--sm {
  min-width: 110px;
  height: 30px;
}
.ar-field input:focus,
.ar-select:focus {
  outline: none;
  border-color: var(--navy);
}

.ar-btn {
  height: 34px;
  padding: 0 12px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  background: #fff;
  color: #111827;
  font-size: 12px !important;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  cursor: pointer;
  white-space: nowrap;
}
.ar-btn--primary {
  background: var(--navy);
  border-color: var(--navy);
  color: #fff;
}
.ar-btn--sm {
  height: 30px;
  padding: 0 10px;
}
.ar-upload {
  position: relative;
  overflow: hidden;
}
.ar-upload input[type='file'] {
  position: absolute;
  inset: 0;
  opacity: 0;
  cursor: pointer;
}

.ar-kpi {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
  gap: 8px;
  margin-bottom: 12px;
}
.ar-kpi__card {
  background: #faf8fc;
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 10px 12px;
  min-height: 58px;
}
.ar-kpi__card span {
  display: block;
  margin: 0 0 2px;
  font-size: 11px !important;
  font-weight: 600;
  color: var(--muted);
  line-height: 1.2;
}
.ar-kpi__card strong {
  display: block;
  margin: 0;
  font-size: 18px !important;
  font-weight: 700;
  color: var(--navy);
  line-height: 1.15;
}
.ar-kpi__card.is-present strong { color: var(--purple); }
.ar-kpi__card.is-warn strong { color: #c2410c; }
.ar-kpi__card.is-orange strong { color: #ea580c; }
.ar-kpi__card.is-danger strong { color: #dc2626; }

.ar-charts {
  display: grid;
  grid-template-columns: 1.15fr 1fr;
  gap: 10px;
  margin-bottom: 12px;
}
.ar-card {
  background: #fff;
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 12px;
}
.ar-card__title {
  margin: 0 0 8px !important;
  padding: 0 !important;
  font-size: 13px !important;
  font-weight: 700 !important;
  color: var(--navy) !important;
  line-height: 1.3 !important;
}
.ar-card__title span {
  display: inline-block;
  margin-left: 6px;
  padding: 1px 7px;
  border-radius: 999px;
  background: #f3f0f7;
  color: var(--muted);
  font-size: 11px !important;
  font-weight: 700;
}

.ar-table-card {
  padding: 12px;
}
.ar-table-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 8px;
  flex-wrap: wrap;
}
.ar-table-head__right {
  display: flex;
  gap: 6px;
  align-items: center;
}
.ar-table-shell {
  overflow: auto;
  border: 1px solid var(--border);
  border-radius: 10px;
}
.ar-table-shell table,
.ar-breakdown table {
  width: 100%;
  border-collapse: collapse;
  min-width: 860px;
}
.ar-table-shell th,
.ar-table-shell td,
.ar-breakdown th,
.ar-breakdown td {
  padding: 9px 10px;
  border-bottom: 1px solid #f3f0f7;
  font-size: 12px !important;
  vertical-align: middle;
}
.ar-table-shell th,
.ar-breakdown th {
  background: #faf8fc;
  color: var(--muted);
  font-size: 11px !important;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  white-space: nowrap;
}
.ar-table-shell tbody tr:last-child td,
.ar-breakdown tbody tr:last-child td { border-bottom: none; }
.ar-table-shell tbody tr:hover { background: #faf8fc; }
.center { text-align: center; }
.ar-muted { color: #9ca3af; }
.ar-empty {
  text-align: center;
  color: #9ca3af;
  padding: 28px 12px !important;
}

.ar-employee {
  border: none;
  background: transparent;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 0;
  cursor: pointer;
  text-align: left;
  max-width: 280px;
}
.ar-employee__avatar,
.ar-modal__avatar {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  background: var(--navy);
  color: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 10px;
  font-weight: 700;
  flex-shrink: 0;
}
.ar-employee__name {
  font-size: 12px !important;
  font-weight: 600;
  color: var(--navy);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.ar-link {
  border: none;
  background: transparent;
  color: var(--purple);
  font-size: 12px !important;
  font-weight: 700;
  cursor: pointer;
  padding: 0;
}

.ar-pill {
  display: inline-flex;
  min-width: 24px;
  justify-content: center;
  padding: 2px 7px;
  border-radius: 999px;
  font-size: 11px !important;
  font-weight: 700;
  background: #f3f4f6;
  color: #4b5563;
}
.ar-pill--ok { background: #ecfdf5; color: #047857; }
.ar-pill--warn { background: #fff7ed; color: #c2410c; }
.ar-pill--orange { background: #ffedd5; color: #ea580c; }
.ar-pill--danger { background: #fef2f2; color: #dc2626; }
.ar-pill--muted { background: #f3f4f6; color: #6b7280; }
.ar-pill--sky { background: #e0f2fe; color: #0369a1; }

tr.risk-high { background: #fff8f8; }
tr.risk-medium { background: #fffdf7; }

.ar-modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 13000;
  background: rgba(11, 7, 54, 0.45);
  backdrop-filter: blur(5px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}
.ar-modal {
  width: min(760px, 96vw);
  max-height: min(86vh, 860px);
  overflow: auto;
  background: #fff;
  border-radius: 14px;
  border: 1px solid var(--border);
  box-shadow: 0 24px 60px rgba(11, 7, 54, 0.25);
}
.ar-modal__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 10px;
  padding: 14px 16px;
  border-bottom: 1px solid #f3f0f7;
  position: sticky;
  top: 0;
  background: #fff;
  z-index: 1;
}
.ar-modal__identity {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}
.ar-modal__avatar {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  font-size: 12px;
}
.ar-modal__name {
  margin: 0 !important;
  font-size: 14px !important;
  font-weight: 700 !important;
  color: var(--navy) !important;
}
.ar-modal__meta {
  margin: 2px 0 0 !important;
  font-size: 12px !important;
  color: var(--muted) !important;
}
.ar-modal__close {
  width: 30px;
  height: 30px;
  border: none;
  border-radius: 999px;
  background: #f3f4f6;
  color: #4b5563;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}
.ar-modal__stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(78px, 1fr));
  gap: 8px;
  padding: 12px 16px;
}
.ar-stat {
  padding: 8px 10px;
  border-radius: 10px;
  background: #faf8fc;
  border: 1px solid var(--border);
}
.ar-stat span {
  display: block;
  font-size: 10px !important;
  color: var(--muted);
  font-weight: 600;
}
.ar-stat strong {
  display: block;
  margin-top: 2px;
  font-size: 16px !important;
  color: var(--navy);
}
.ar-stat.tone-warn strong { color: #c2410c; }
.ar-stat.tone-orange strong { color: #ea580c; }
.ar-stat.tone-danger strong { color: #dc2626; }
.ar-modal__note {
  margin: 0 16px 12px !important;
  padding: 8px 10px;
  border-radius: 8px;
  background: #faf8fc;
  border: 1px dashed #ddd3e8;
  font-size: 12px !important;
  color: #4b5563;
}
.ar-breakdown {
  margin: 0 16px 16px;
  border: 1px solid var(--border);
  border-radius: 10px;
  overflow: auto;
}
.ar-breakdown table { min-width: 520px; }

@media (max-width: 1100px) {
  .ar-kpi { grid-template-columns: repeat(4, minmax(0, 1fr)); }
}
@media (max-width: 860px) {
  .ar-charts { grid-template-columns: 1fr; }
  .ar-kpi { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .ar-people-search { width: 100%; }
  .ar-toolbar__dates,
  .ar-toolbar__actions { width: 100%; }
}
@media (max-width: 520px) {
  .ar-kpi { grid-template-columns: 1fr 1fr; }
}

@media print {
  @page { size: A4 landscape; margin: 8mm; }
  .no-print { display: none !important; }
  .ar-modal-overlay { display: none !important; }
  .ar-shell { box-shadow: none; border: none; padding: 0; }
  .ar-table-shell { overflow: visible !important; }
  .ar-table-shell table {
    min-width: 100% !important;
    table-layout: fixed;
  }
  th, td {
    font-size: 10px !important;
    padding: 5px 3px;
    white-space: normal;
    word-break: break-word;
  }
  .ar-employee { pointer-events: none; }
  .ar-employee__avatar { display: none; }
}
</style>
