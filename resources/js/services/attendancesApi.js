import api from '@/plugins/axios'

const attendancesApi = {
  profileHistory(months = 12) {
    return api.get('/profile/attendance-history', {
      params: { months },
    })
  },
}

export default attendancesApi
