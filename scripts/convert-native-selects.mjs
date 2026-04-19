/**
 * Replace common native <select> blocks with <SearchableSelect> (globally registered).
 * Run: node scripts/convert-native-selects.mjs
 */
import { readdirSync, readFileSync, statSync, writeFileSync } from 'node:fs'
import { join } from 'node:path'

const ROOT = join(process.cwd(), 'resources', 'js')

function walk(dir, out = []) {
  for (const name of readdirSync(dir)) {
    if (name === 'node_modules' || name === 'dist') continue
    const p = join(dir, name)
    const st = statSync(p)
    if (st.isDirectory()) walk(p, out)
    else if (p.endsWith('.vue')) out.push(p)
  }
  return out
}

const REPLACEMENTS = [
  {
    name: 'areas-per-page-with-all',
    re: /<select[^>]*form-select-lr w-auto rounded-3 me-10[^>]*v-model="selectedShow"[^>]*>[\s\S]*?<option value="10">10<\/option>[\s\S]*?<option value="15">15<\/option>[\s\S]*?<option value="20">20<\/option>[\s\S]*?<option value="all">All<\/option>[\s\S]*?<\/select>/gi,
    to: '<SearchableSelect preset="perPage10_15_20_all" v-model="selectedShow" :clearable="false" inline class="w-auto me-10" :input-style="{ borderRadius: \'10px\', height: \'2.4rem\', minWidth: \'5.5rem\' }" />',
  },
  {
    name: 'datatable-per-page-10-15-20',
    re: /<select[^>]*form-select-lr w-auto rounded-3 me-10[^>]*v-model="selectedShow"[^>]*>[\s\S]*?<option value="10">10<\/option>[\s\S]*?<option value="15">15<\/option>[\s\S]*?<option value="20">20<\/option>[\s\S]*?<\/select>/gi,
    to: '<SearchableSelect preset="perPage10_15_20" v-model="selectedShow" :clearable="false" inline class="w-auto me-10" :input-style="{ borderRadius: \'10px\', height: \'2.4rem\', minWidth: \'5.5rem\' }" />',
  },
  {
    name: 'areas-type-filter',
    re: /<select[^>]*form-select-lr w-auto rounded-3"[^>]*v-model="selectedType"[^>]*>[\s\S]*?<\/select>/gi,
    to: '<SearchableSelect preset="areasType" v-model="selectedType" :clearable="false" inline class="w-auto" :input-style="{ borderRadius: \'10px\', height: \'2.4rem\', minWidth: \'10rem\' }" />',
  },
  {
    name: 'earning-stat-period',
    re: /<select\s+v-model="selectedPeriod"[^>]*class="form-select form-select-sm w-auto bg-base border text-secondary-light radius-8"[^>]*>[\s\S]*?value="yearly"[\s\S]*?<\/select>/gi,
    to: '<SearchableSelect preset="earningPeriodLower" v-model="selectedPeriod" :clearable="false" inline class="form-select form-select-sm w-auto bg-base border text-secondary-light radius-8" />',
  },
  {
    name: 'campaigns-period',
    re: /<select\s+v-model="selectedPeriod"[^>]*class="form-select form-select-sm w-auto bg-base border text-secondary-light radius-8"[^>]*>[\s\S]*?value="Yearly"[\s\S]*?<\/select>/gi,
    to: '<SearchableSelect preset="campaignsPeriodTitle" v-model="selectedPeriod" :clearable="false" inline class="form-select form-select-sm w-auto bg-base border text-secondary-light radius-8" />',
  },
  {
    name: 'sales-listings-period',
    re: /<select\s+v-model="selectedPeriod"\s+@change="fetchData"[^>]*class="form-select[^"]*"[^>]*>[\s\S]*?<\/select>/gi,
    to: '<SearchableSelect preset="salesListingsPeriod3" v-model="selectedPeriod" :clearable="false" inline class="form-select bg-base form-select-sm w-auto radius-8" @update:model-value="fetchData" />',
  },
  {
    name: 'generated-content-period',
    re: /<select\s+v-model="timePeriod"[^>]*class="form-select form-select-sm w-auto bg-base border text-secondary-light radius-8"[^>]*>[\s\S]*?<\/select>/gi,
    to: '<SearchableSelect preset="generatedContentPeriodLabels" v-model="timePeriod" :clearable="false" inline class="form-select form-select-sm w-auto bg-base border text-secondary-light radius-8" />',
  },
  {
    name: 'custom-overview-timeframe',
    re: /<select\s+v-model="selectedTimeframe"[^>]*class="form-select form-select-sm w-auto bg-base border text-secondary-light radius-8"[^>]*>[\s\S]*?<option>Yearly<\/option>[\s\S]*?<\/select>/gi,
    to: '<SearchableSelect preset="overviewTimeframeCapitalized" v-model="selectedTimeframe" :clearable="false" inline class="form-select form-select-sm w-auto bg-base border text-secondary-light radius-8" />',
  },
  {
    name: 'user-overview-timeframe',
    re: /<select\s+[^>]*v-model="selectedTimeframe"[^>]*@change="fetchData"[^>]*>[\s\S]*?value="today"[\s\S]*?<\/select>/gi,
    to: '<SearchableSelect preset="userOverviewTimeframe" v-model="selectedTimeframe" :clearable="false" inline class="form-select form-select-sm w-auto bg-base border text-secondary-light radius-8" @update:model-value="fetchData" />',
  },
  {
    name: 'assign-role-per-page',
    re: /<select\s+v-model="rolesPerPage"\s+@change="changePage\(1\)"[\s\S]*?<\/select>/gi,
    to: '<SearchableSelect preset="assignRolePerPage1_10" v-model="rolesPerPage" :clearable="false" inline class="form-select form-select-sm w-auto ps-12 py-6 radius-12 h-40-px" @update:model-value="changePage(1)" />',
  },
  {
    name: 'role-toolbar-status',
    re: /<select\s+v-model="statusFilter"\s+@change="changePage\(1\)"[\s\S]*?<option>Status<\/option>[\s\S]*?<\/select>/gi,
    to: '<SearchableSelect preset="roleToolbarStatusLabels" v-model="statusFilter" :clearable="false" inline class="form-select form-select-sm w-auto ps-12 py-6 radius-12 h-40-px" @update:model-value="changePage(1)" />',
  },
  {
    name: 'assign-role-status-filter',
    re: /<select\s+v-model="statusFilter"\s+@change="changePage\(1\)"[\s\S]*?<option value="">Status<\/option>[\s\S]*?<\/select>/gi,
    to: '<SearchableSelect preset="assignRoleUserFilterStatus" v-model="statusFilter" :clearable="false" inline class="form-select form-select-sm w-auto ps-12 py-6 radius-12 h-40-px" @update:model-value="changePage(1)" />',
  },
  {
    name: 'user-list-entries',
    re: /<select\s+v-model="entriesPerPage"[^>]*>[\s\S]*?v-for="n in \[5, 10, 15, 20\]"[\s\S]*?<\/select>/gi,
    to: '<SearchableSelect preset="userEntries5101520" v-model="entriesPerPage" :clearable="false" inline class="form-select form-select-sm w-auto ps-12 py-6 radius-12 h-40-px" />',
  },
  {
    name: 'user-list-status',
    re: /<select\s+v-model="selectedStatus"[^>]*>[\s\S]*?<option>Status<\/option>[\s\S]*?<\/select>/gi,
    to: '<SearchableSelect preset="userStatusFilter" v-model="selectedStatus" :clearable="false" inline class="form-select form-select-sm w-auto ps-12 py-6 radius-12 h-40-px" />',
  },
  {
    name: 'user-grid-per-page',
    re: /<select[^>]*v-model="itemsPerPage"[^>]*>[\s\S]*?v-for="n in 12"[\s\S]*?<\/select>/gi,
    to: '<SearchableSelect preset="userGridPerPage1_12" v-model="itemsPerPage" :clearable="false" inline class="form-select form-select-sm w-auto ps-12 py-6 radius-12 h-40-px" />',
  },
  {
    name: 'settings-items-to-show',
    re: /<select class="form-select form-select-sm w-auto ps-12 py-6 radius-12 h-40-px" v-model="itemsToShow">[\s\S]*?<\/select>/gi,
    to: '<SearchableSelect preset="languagesPageSize" v-model="itemsToShow" :clearable="false" inline class="form-select form-select-sm w-auto ps-12 py-6 radius-12 h-40-px" />',
  },
]

let filesTouched = 0
let replCount = 0

for (const file of walk(ROOT)) {
  let content = readFileSync(file, 'utf8')
  let changed = false
  for (const { re, to } of REPLACEMENTS) {
    const next = content.replace(re, () => {
      replCount++
      return to
    })
    if (next !== content) {
      content = next
      changed = true
    }
  }
  if (changed) {
    writeFileSync(file, content, 'utf8')
    filesTouched++
  }
}

console.log(`Select conversion: ${replCount} replacements across ${filesTouched} files.`)
