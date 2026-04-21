<template>
  <div class="page">

    <!-- Filters -->
    <div class="filters">

      <select v-model="filters.model" @change="fetchLogs">
        <option value="">All Models</option>
        <option value="listing">Listing</option>
        <option value="area">Area</option>
        <option value="developer">Developer</option>
        <option value="stage">Stage</option>
        <option value="owner">Owner</option>
        <option value="project">Project</option>
        <option value="user">User</option>
      </select>

      <input type="date" v-model="filters.date_from" @change="fetchLogs" />
      <input type="date" v-model="filters.date_to" @change="fetchLogs" />

      <select v-model="filters.user_id" @change="fetchLogs">
        <option value="">All Users</option>
        <option v-for="u in users" :key="u.id" :value="u.id">
          {{ u.name }}
        </option>
      </select>

    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading">
      Loading...
    </div>

    <!-- Timeline -->
    <div v-else class="timeline">

      <div v-for="log in logs" :key="log.id" class="log">

        <div class="dot"></div>

        <div class="card">

          <!-- Header -->
          <div class="header">
            <div class="user">
              👤 {{ log.causer?.name || 'System' }}
            </div>

            <div class="date">
              {{ formatDate(log.created_at) }}
            </div>
          </div>

          <!-- Model -->
          <div class="meta">
            📦 {{ getModelName(log.subject_type) }}
          </div>

          <!-- Changes -->
          <div class="changes">

            <div
              v-for="field in getChangedFields(log)"
              :key="field"
              class="change"
            >

              <span class="field">
                {{ field }}
              </span>

              <span class="old">
                {{ formatValue(getOldValue(log, field)) }}
              </span>

              <span class="arrow">→</span>

              <span class="new">
                {{ formatValue(getNewValue(log, field)) }}
              </span>

            </div>

          </div>

        </div>

      </div>

    </div>

  </div>
</template>

<script>
export default {
  data() {
    return {
      logs: [],
      users: [],
      loading: false,

      filters: {
        model: '',
        date_from: '',
        date_to: '',
        user_id: ''
      }
    }
  },

  mounted() {
    this.fetchUsers()
    this.fetchLogs()
  },

  methods: {

    async fetchLogs() {
      this.loading = true

      try {
        const res = await axios.get('/api/logs', {
          params: this.filters,
          headers: {
            Authorization: `Bearer ${localStorage.getItem('token')}`
          }
        })

        this.logs = Array.isArray(res.data)
          ? res.data
          : (res.data.data || [])

      } catch (e) {
        console.log(e)
        this.logs = []
      }

      this.loading = false
    },

    async fetchUsers() {
      try {
        const res = await axios.get('/api/users', {
          headers: {
            Authorization: `Bearer ${localStorage.getItem('token')}`
          }
        })

        this.users = res.data.data || res.data

      } catch (e) {
        this.users = []
      }
    },

    // 🔥 أهم جزء: استخراج attributes
    getAttributes(log) {
      return log?.properties?.attributes || {}
    },

    getOld(log) {
      return log?.properties?.old || {}
    },

    getNewValue(log, field) {
      return this.getAttributes(log)?.[field]
    },

    getOldValue(log, field) {
      return this.getOld(log)?.[field]
    },

    // 🔥 نعرض فقط اللي اتغير
    getChangedFields(log) {
      const attrs = this.getAttributes(log)

      return Object.keys(attrs).filter(field => {
        const oldVal = this.getOldValue(log, field)
        const newVal = attrs[field]

        return JSON.stringify(oldVal) !== JSON.stringify(newVal)
      })
    },

    formatValue(val) {
      if (val === null || val === undefined) return '-'
      if (typeof val === 'object') return JSON.stringify(val)
      return val
    },

    getModelName(type) {
      if (!type) return '-'
      return type.split('\\').pop()
    },

    formatDate(date) {
      return new Date(date).toLocaleString('ar-EG')
    }

  }
}
</script>

<style scoped>
.page {
  padding: 20px;
  background: #f4f6f9;
  font-size: 13px;
}

/* Filters */
.filters {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  background: white;
  padding: 12px;
  border-radius: 10px;
  margin-bottom: 20px;
}

.filters select,
.filters input {
  padding: 6px 10px;
  font-size: 12px;
  border: 1px solid #ddd;
  border-radius: 6px;
}

/* Timeline */
.timeline {
  display: flex;
  flex-direction: column;
  gap: 15px;
}

.log {
  display: flex;
  gap: 10px;
}

.dot {
  width: 10px;
  height: 10px;
  background: #4f46e5;
  border-radius: 50%;
  margin-top: 10px;
}

/* Card */
.card {
  background: white;
  padding: 12px;
  border-radius: 10px;
  width: 100%;
  box-shadow: 0 2px 6px rgba(0,0,0,0.05);
}

/* Header */
.header {
  display: flex;
  justify-content: space-between;
}

.user {
  font-weight: 600;
}

.date {
  font-size: 11px;
  color: #888;
}

.meta {
  font-size: 12px;
  color: #555;
  margin: 5px 0 10px;
}

/* Changes */
.changes {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.change {
  display: flex;
  gap: 8px;
  font-size: 12px;
  align-items: center;
}

.field {
  min-width: 140px;
  font-weight: 600;
}

.old {
  color: #ef4444;
  text-decoration: line-through;
}

.new {
  color: #22c55e;
  font-weight: 600;
}

.arrow {
  color: #999;
}

/* Loading */
.loading {
  text-align: center;
  padding: 30px;
}
</style>