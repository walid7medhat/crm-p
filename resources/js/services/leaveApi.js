// services/leaveApi.js
import api from '@/plugins/axios'

// ==================== Leave Types ====================

/**
 * Fetch all leave types
 * @param {string} search - Search keyword
 * @returns {Promise<Array>} List of leave types
 */
export const fetchLeaveTypes = async (search = '', { all = false } = {}) => {
  try {
    const response = await api.get('/leaves/types', { params: { search, all: all ? 1 : undefined } })
    const data = response?.data?.data || response?.data || []
    return Array.isArray(data) ? data : []
  } catch (error) {
    console.error('❌ Failed to fetch leave types:', error)
    return []
  }
}

/**
 * Create a new leave type
 * @param {Object} data
 * @param {string} data.name - Leave type name
 * @param {string} data.payment_type - paid/half_paid/unpaid
 * @param {number} data.default_days - Default days
 * @param {boolean} data.requires_attachment - Requires attachment
 * @returns {Promise<Object>} Created leave type
 */
export const createLeaveType = async (data) => {
  try {
    const response = await api.post('/leaves/types', data)
    return response.data.data
  } catch (error) {
    console.error('Error creating leave type:', error)
    throw error
  }
}

/**
 * Update an existing leave type
 * @param {number} id - Leave type ID
 * @param {Object} data - Update data
 * @returns {Promise<Object>} Updated leave type
 */
export const updateLeaveType = async (id, data) => {
  try {
    const response = await api.put(`/leaves/types/${id}`, data)
    return response.data.data
  } catch (error) {
    console.error('Error updating leave type:', error)
    throw error
  }
}

/**
 * Delete a leave type
 * @param {number} id - Leave type ID
 * @returns {Promise<Object>} Deletion response
 */
export const deleteLeaveType = async (id) => {
  try {
    const response = await api.delete(`/leaves/types/${id}`)
    return response.data
  } catch (error) {
    console.error('Error deleting leave type:', error)
    throw error
  }
}

// ==================== Leave Balance ====================

/**
 * Get current user's leave balance
 * @returns {Promise<Array>} List of leave balances
 */
export const fetchMyLeaveBalance = async () => {
  try {
    const response = await api.get('/leaves/my-balance')
    return response.data.data || []
  } catch (error) {
    console.error('Error fetching leave balance:', error)
    throw error
  }
}

/**
 * Get employee leave balance
 * @param {number} userId - Employee ID
 * @returns {Promise<Array>} List of leave balances
 */
export const fetchEmployeeLeaveBalance = async (userId) => {
  try {
    const response = await api.get(`/leaves/balance/${userId}`)
    return response.data.data || []
  } catch (error) {
    console.error('Error fetching employee leave balance:', error)
    throw error
  }
}

// ==================== Leave Requests ====================

/**
 * Fetch leave requests with filters
 * @param {Object} params - Filter parameters
 * @param {string} params.status - Request status
 * @param {number} params.leave_type_id - Leave type ID
 * @param {number} params.user_id - Employee ID
 * @param {string} params.start_date - Start date (Y-m-d)
 * @param {string} params.end_date - End date (Y-m-d)
 * @param {string} params.search - Search keyword
 * @param {number} params.per_page - Items per page
 * @param {number} params.page - Page number
 * @returns {Promise<Object>} Paginated leave requests
 */
export const fetchLeaveRequests = async (params = {}) => {
  try {
    const response = await api.get('/leaves', { params })
    return response.data.data
  } catch (error) {
    console.error('Error fetching leave requests:', error)
    throw error
  }
}

/**
 * Create a new leave request
 * @param {Object} data
 * @param {number} data.leave_type_id - Leave type ID
 * @param {string} data.start_date - Start date (Y-m-d)
 * @param {string} data.end_date - End date (Y-m-d)
 * @param {string} data.reason - Leave reason
 * @param {boolean} data.is_half_day - Is half day
 * @param {string} data.half_day_type - morning/afternoon
 * @param {File} data.attachment - Attachment file (optional)
 * @returns {Promise<Object>} Created leave request
 */
// services/leaveApi.js
// services/leaveApi.js
export const createLeaveRequest = async (data) => {
  try {
    const isHalfDay = data.is_half_day === true || data.is_half_day === 'true'
    
    const formData = new FormData()
    
    // أضف الحقول الأساسية
    formData.append('leave_type_id', Number(data.leave_type_id))
     formData.append('user_id', Number(data.user_id))
    formData.append('start_date', data.start_date)
    formData.append('end_date', data.end_date)
    if (data.reason) {
      formData.append('reason', data.reason)
    }
    formData.append('is_half_day', isHalfDay ? '1' : '0')
    
    // أضف half_day_type فقط إذا كان نصف يوم
    if (isHalfDay) {
      formData.append('half_day_type', data.half_day_type || 'morning')
    }
    
    // أضف المرفق إن وجد
    if (data.attachment instanceof File) {
      formData.append('attachment', data.attachment)
    }
    
    console.log('📦 API FormData keys:', [...formData.keys()])
    
    const response = await api.post('/leaves', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    return response.data.data || response.data
  } catch (error) {
    console.error('Error creating leave request:', error)
    console.error('Response:', error.response?.data)
    throw error
  }
}
/**
 * Update a leave request
 * @param {number} id - Leave request ID
 * @param {Object} data - Update data
 * @returns {Promise<Object>} Updated leave request
 */
export const updateLeaveRequest = async (id, data) => {
  try {
    const response = await api.put(`/leaves/${id}`, data)
    return response.data.data
  } catch (error) {
    console.error('Error updating leave request:', error)
    throw error
  }
}

/**
 * Get leave request details
 * @param {number} id - Leave request ID
 * @returns {Promise<Object>} Leave request details
 */
export const getLeaveRequest = async (id) => {
  try {
    const response = await api.get(`/leaves/${id}`)
    return response.data.data
  } catch (error) {
    console.error('Error fetching leave request:', error)
    throw error
  }
}

/**
 * Cancel a leave request
 * @param {number} id - Leave request ID
 * @returns {Promise<Object>} Cancellation response
 */
export const cancelLeaveRequest = async (id) => {
  try {
    const response = await api.delete(`/leaves/${id}`)
    return response.data
  } catch (error) {
    console.error('Error canceling leave request:', error)
    throw error
  }
}

// ==================== Leave Approvals ====================

/**
 * Approve leave by parent/manager
 * @param {number} id - Leave request ID
 * @returns {Promise<Object>} Approved leave request
 */
export const approveByParent = async (id) => {
  try {
    const response = await api.post(`/leaves/${id}/approve-parent`)
    return response.data.data
  } catch (error) {
    console.error('Error approving leave by parent:', error)
    throw error
  }
}

/**
 * Reject leave by parent/manager
 * @param {number} id - Leave request ID
 * @param {Object} data
 * @param {string} data.rejection_reason - Rejection reason
 * @returns {Promise<Object>} Rejected leave request
 */
export const rejectByParent = async (id, data) => {
  try {
    const response = await api.post(`/leaves/${id}/reject-parent`, data)
    return response.data.data
  } catch (error) {
    console.error('Error rejecting leave by parent:', error)
    throw error
  }
}

/**
 * Approve leave by HR
 * @param {number} id - Leave request ID
 * @returns {Promise<Object>} Approved leave request
 */
export const approveByHr = async (id) => {
  try {
    const response = await api.post(`/leaves/${id}/approve-hr`)
    return response.data.data
  } catch (error) {
    console.error('Error approving leave by HR:', error)
    throw error
  }
}

/**
 * Reject leave by HR
 * @param {number} id - Leave request ID
 * @param {Object} data
 * @param {string} data.rejection_reason - Rejection reason
 * @returns {Promise<Object>} Rejected leave request
 */
export const rejectByHr = async (id, data) => {
  try {
    const response = await api.post(`/leaves/${id}/reject-hr`, data)
    return response.data.data
  } catch (error) {
    console.error('Error rejecting leave by HR:', error)
    throw error
  }
}

// ==================== Leave Statistics ====================

/**
 * Get leave statistics
 * @returns {Promise<Object>} Leave statistics data
 */
export const fetchLeaveStatistics = async () => {
  try {
    const response = await api.get('/leaves/statistics')
    return response.data.data
  } catch (error) {
    console.error('Error fetching leave statistics:', error)
    throw error
  }
}

// ==================== Leave Exports ====================

/**
 * Export leave requests
 * @param {Object} params - Export parameters
 * @returns {Promise<Blob>} CSV/Excel file blob
 */
export const exportLeaveRequests = async (params = {}) => {
  try {
    const response = await api.get('/leaves/export', {
      params,
      responseType: 'blob'
    })
    return response.data
  } catch (error) {
    console.error('Error exporting leave requests:', error)
    throw error
  }
}