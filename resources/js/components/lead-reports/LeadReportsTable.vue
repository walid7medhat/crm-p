<template>
  <section class="lr-table-wrap">
    <div class="lr-table-head">
      <h3>{{ title }}</h3>
      <div class="lr-table-tools">
        <div class="lr-search-box">
          <iconify-icon icon="lucide:search" />
          <input :value="search" type="text" placeholder="Search Leads" @input="$emit('update:search', $event.target.value)" />
        </div>
        <button class="lr-refresh" @click="$emit('refresh')">Refresh</button>
      </div>
    </div>
    <div class="lr-table-overflow">
      <table>
        <thead>
          <tr>
            <th>Created On</th>
            <th>Lead Name</th>
            <th>Responsible Person</th>
            <th>Closing Date</th>
            <th>Source</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.id">
            <td>{{ row.createdOn }}</td>
            <td>{{ row.leadName }}</td>
            <td>
              <div class="person">
                <div class="avatar">{{ row.responsibleName.charAt(0) }}</div>
                <div>
                  <div class="name">{{ row.responsibleName }}</div>
                  <div class="mail">{{ row.responsibleEmail }}</div>
                </div>
              </div>
            </td>
            <td>{{ row.closingDate }}</td>
            <td>{{ row.source }}</td>
            <td><button class="dot-btn"><iconify-icon icon="lucide:more-vertical" /></button></td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="lr-pagination">
      <button :disabled="currentPage <= 1" @click="$emit('update:page', currentPage - 1)">Previous</button>
      <span>{{ currentPage }}</span>
      <button :disabled="currentPage >= totalPages" @click="$emit('update:page', currentPage + 1)">Next</button>
    </div>
  </section>
</template>

<script setup>
defineProps({
  title: { type: String, default: 'Qualified Leads Reports' },
  rows: { type: Array, default: () => [] },
  search: { type: String, default: '' },
  currentPage: { type: Number, default: 1 },
  totalPages: { type: Number, default: 1 }
})
defineEmits(['update:search', 'update:page', 'refresh'])
</script>

<style scoped>
.lr-table-wrap { background: #fff; border: 1px solid #ebeef3; border-radius: 14px; margin: 0 16px; padding: 12px; }
.lr-table-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
.lr-table-head h3 { font-size: 30px; color: #10152f; margin: 0; }
.lr-table-tools { display: flex; gap: 8px; align-items: center; }
.lr-search-box { width: 220px; height: 36px; border: 1px solid #ebeef3; border-radius: 20px; display: flex; align-items: center; padding: 0 10px; color: #9ca2ae; }
.lr-search-box input { border: none; outline: none; width: 100%; font-size: 12px; }
.lr-refresh { border: none; background: transparent; font-size: 13px; color: #10152f; }
.lr-table-overflow { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; min-width: 920px; }
th { font-size: 12px; color: #6f7282; font-weight: 600; padding: 10px 12px; border-bottom: 1px solid #ebeef3; text-align: left; }
td { font-size: 13px; color: #10152f; padding: 10px 12px; border-bottom: 1px solid #f2f4f8; }
.person { display: flex; align-items: center; gap: 8px; }
.avatar { width: 28px; height: 28px; border-radius: 50%; background: #dfe9ff; color: #2442a8; display: grid; place-items: center; font-size: 12px; font-weight: 600; }
.name { font-size: 13px; }
.mail { font-size: 11px; color: #9ca2ae; }
.dot-btn { border: none; background: transparent; color: #6f7282; }
.lr-pagination { display: flex; justify-content: flex-end; gap: 10px; margin-top: 12px; }
.lr-pagination button, .lr-pagination span { height: 32px; min-width: 32px; border: 1px solid #ebeef3; border-radius: 16px; background: #fff; padding: 0 10px; font-size: 12px; color: #10152f; display: grid; place-items: center; }
</style>
