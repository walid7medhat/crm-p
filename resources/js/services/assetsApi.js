// services/assetsApi.js
import api from '@/plugins/axios'

const STATUS_LABELS = {
  available: 'Available',
  assigned: 'Assigned',
  maintenance: 'Under Maintenance',
  disposed: 'Lost / Disposed',
}

const CONDITION_LABELS = {
  new: 'New',
  used: 'Used',
  working: 'Working',
  damaged: 'Damaged',
  maintenance: 'Maintenance',
}

function unwrapPaginated(payload) {
  const root = payload?.data
  if (root?.data && Array.isArray(root.data)) {
    return {
      items: root.data,
      currentPage: root.current_page ?? 1,
      lastPage: root.last_page ?? 1,
      total: root.total ?? root.data.length,
      perPage: root.per_page ?? root.data.length,
    }
  }
  if (Array.isArray(root)) {
    return {
      items: root,
      currentPage: 1,
      lastPage: 1,
      total: root.length,
      perPage: root.length,
    }
  }
  return {
    items: [],
    currentPage: 1,
    lastPage: 1,
    total: 0,
    perPage: 0,
  }
}

export function resolveWarrantyStatus(warrantyDate) {
  if (!warrantyDate) {
    return { key: 'none', label: 'No warranty' }
  }

  const end = new Date(warrantyDate)
  if (Number.isNaN(end.getTime())) {
    return { key: 'none', label: 'No warranty' }
  }

  const daysLeft = Math.ceil((end.getTime() - Date.now()) / (1000 * 60 * 60 * 24))
  if (daysLeft < 0) {
    return { key: 'expired', label: 'Expired' }
  }
  if (daysLeft <= 30) {
    return { key: 'expiring_soon', label: 'Expiring soon' }
  }
  return { key: 'active', label: 'Active' }
}

export function normalizeAsset(row) {
  if (!row) return null

  const status = row.status || 'available'
  const condition = row.condition || 'new'

  return {
  id: row.id,
  name: row.name || '—',
  assetId: row.asset_code || `AST-${String(row.id).padStart(3, '0')}`,
  serialNumber: row.serial_number || '—',
  modelNumber: row.model_number || '—',
  rdpNumber: row.rdp_number || '—',

  category: row.asset_type?.name || '—',
  assetTypeId: row.asset_type_id || row.asset_type?.id || null,

  assignedEmployee: row.current_user?.name || '—',
  assignedAvatar: row.current_user?.avatar || null,
  assignedUserId:
  row.current_user?.id ||
  row.currentAssignment?.user_id ||
  row.current_assignment?.user_id ||
  row.user_id ||
  null,

  currentAssignment: row.currentAssignment || row.current_assignment,
  department: row.department?.name || '—',
  departmentId: row.department_id || row.department?.id || null,

  branch: row.branch?.name || '—',
  branchId: row.branch_id || row.branch?.id || null,

  status,
  statusLabel: row.status_label || STATUS_LABELS[status] || status,
  condition,
  conditionLabel: CONDITION_LABELS[condition] || condition,

  purchaseDate: row.purchase_date || null,
  warrantyDate: row.warranty_date || null,
  warrantyStatus: resolveWarrantyStatus(row.warranty_date),

  handoverDate: row.current_assignment?.handover_date || null,
  supplierName: row.supplier_name || '—',
  unitPrice: row.unit_price ?? null,
  quantity: row.quantity ?? 1,
  description: row.description || '',
  remarks: row.remarks || '',
  imageIcon: 'lucide:package',
  raw: row,
}
}
export async function updateAssetAssignment(assetId, payload) {
    const response = await api.put(
        `/assets/${assetId}/assignment`,
        payload
    )

    return response.data?.data ?? response.data
}
// ==================== Asset Types ====================

/**
 * Fetch all asset types
 * @param {string} search - Search keyword
 * @returns {Promise<Array>} List of asset types
 */
export const fetchAssetTypes = async (search = '') => {
  try {
    const response = await api.get('/asset-types', { params: { search } })
    const data = response?.data?.data || response?.data || []
    return Array.isArray(data) ? data : []
  } catch (error) {
    console.error('❌ Failed to fetch asset types:', error)
    return []
  }
}

/**
 * Create a new asset type
 * @param {Object} data
 * @param {string} data.name - Asset type name
 * @returns {Promise<Object>} Created asset type
 */
export const createAssetType = async (data) => {
  try {
    const response = await api.post('/asset-types', data)
    return response.data.data
  } catch (error) {
    console.error('Error creating asset type:', error)
    throw error
  }
}

/**
 * Update an asset type
 * @param {number} id - Asset type ID
 * @param {Object} data - Update data
 * @returns {Promise<Object>} Updated asset type
 */
export const updateAssetType = async (id, data) => {
  try {
    const response = await api.put(`/asset-types/${id}`, data)
    return response.data.data
  } catch (error) {
    console.error('Error updating asset type:', error)
    throw error
  }
}

/**
 * Delete an asset type
 * @param {number} id - Asset type ID
 * @returns {Promise<Object>} Deletion response
 */
export const deleteAssetType = async (id) => {
  try {
    const response = await api.delete(`/asset-types/${id}`)
    return response.data
  } catch (error) {
    console.error('Error deleting asset type:', error)
    throw error
  }
}

// ==================== Assets CRUD ====================

/**
 * Fetch assets with filters
 * @param {Object} params - Filter parameters
 * @param {number} params.asset_type_id - Asset type ID
 * @param {string} params.status - Asset status
 * @param {number} params.branch_id - Branch ID
 * @param {number} params.department_id - Department ID
 * @param {number} params.user_id - User ID
 * @param {string} params.search - Search keyword
 * @param {string} params.purchase_date_from - Purchase date from
 * @param {string} params.purchase_date_to - Purchase date to
 * @param {string} params.warranty_status - Warranty status (expired/active/expiring_soon)
 * @param {number} params.per_page - Items per page
 * @param {number} params.page - Page number
 * @returns {Promise<Object>} Paginated assets
 */
export const fetchAssets = async (params = {}) => {
  try {
    const response = await api.get('/assets', { params })
    const page = unwrapPaginated(response.data)
    return {
      ...page,
      items: page.items.map(normalizeAsset).filter(Boolean),
    }
  } catch (error) {
    console.error('Error fetching assets:', error)
    throw error
  }
}

/**
 * Create a new asset
 * @param {Object} data
 * @param {string} data.name - Asset name
 * @param {number} data.asset_type_id - Asset type ID
 * @param {string} data.serial_number - Serial number
 * @param {string} data.model_number - Model number
 * @param {string} data.rdp_number - RDP number
 * @param {string} data.description - Description
 * @param {string} data.remarks - Remarks
 * @param {string} data.purchase_date - Purchase date
 * @param {string} data.warranty_date - Warranty date
 * @param {number} data.unit_price - Unit price
 * @param {string} data.supplier_name - Supplier name
 * @param {number} data.quantity - Quantity
 * @param {string} data.condition - Condition (new/used/working/damaged/maintenance)
 * @param {number} data.branch_id - Branch ID
 * @param {number} data.department_id - Department ID
 * @returns {Promise<Object>} Created asset
 */
export const createAsset = async (data) => {
  try {
    const response = await api.post('/assets/store/new', data)
    return response.data.data
  } catch (error) {
    console.error('Error creating asset:', error)
    throw error
  }
}

/**
 * Get asset details
 * @param {number} id - Asset ID
 * @returns {Promise<Object>} Asset details
 */
export const getAsset = async (id) => {
  try {
    const response = await api.get(`/assets/${id}`)
    return response.data.data
  } catch (error) {
    console.error('Error fetching asset:', error)
    throw error
  }
}

/**
 * Update an asset
 * @param {number} id - Asset ID
 * @param {Object} data - Update data
 * @returns {Promise<Object>} Updated asset
 */
export const updateAsset = async (id, data) => {
  try {
    const response = await api.put(`/assets/${id}`, data)
    return response.data.data
  } catch (error) {
    console.error('Error updating asset:', error)
    throw error
  }
}

/**
 * Delete an asset
 * @param {number} id - Asset ID
 * @returns {Promise<Object>} Deletion response
 */
export const deleteAsset = async (id) => {
  try {
    const response = await api.delete(`/assets/${id}`)
    return response.data
  } catch (error) {
    console.error('Error deleting asset:', error)
    throw error
  }
}

// ==================== Asset Assignment ====================

/**
 * Assign asset to an employee
 * @param {number} id - Asset ID
 * @param {Object} data
 * @param {number} data.user_id - Employee ID
 * @param {string} data.handover_date - Handover date
 * @param {string} data.notes - Notes
 * @returns {Promise<Object>} Assignment details
 */
export const assignAsset = async (id, data) => {
  try {
    const response = await api.post(`/assets/${id}/assign`, data)
    return response.data.data
  } catch (error) {
    console.error('Error assigning asset:', error)
    throw error
  }
}

/**
 * Return an asset
 * @param {number} id - Asset ID
 * @param {Object} data
 * @param {string} data.return_date - Return date
 * @param {string} data.notes - Notes
 * @returns {Promise<Object>} Return details
 */
export const returnAsset = async (id, data) => {
  try {
    const response = await api.post(`/assets/${id}/return`, data)
    return response.data.data
  } catch (error) {
    console.error('Error returning asset:', error)
    throw error
  }
}

/**
 * Transfer asset to another employee
 * @param {number} id - Asset ID
 * @param {Object} data
 * @param {number} data.user_id - New employee ID
 * @param {string} data.handover_date - Handover date
 * @param {string} data.notes - Notes
 * @returns {Promise<Object>} Transfer details
 */
export const transferAsset = async (id, data) => {
  try {
    const response = await api.post(`/assets/${id}/transfer`, data)
    return response.data.data
  } catch (error) {
    console.error('Error transferring asset:', error)
    throw error
  }
}

/**
 * Mark asset for maintenance
 * @param {number} id - Asset ID
 * @param {Object} data
 * @param {string} data.notes - Notes
 * @returns {Promise<Object>} Updated asset
 */
export const markAssetMaintenance = async (id, data = {}) => {
  try {
    const response = await api.post(`/assets/${id}/maintenance`, data)
    return response.data.data
  } catch (error) {
    console.error('Error marking asset for maintenance:', error)
    throw error
  }
}

/**
 * Get asset history logs
 * @param {number} id - Asset ID
 * @returns {Promise<Array>} History logs
 */
export const getAssetHistory = async (id) => {
  try {
    const response = await api.get(`/assets/${id}/history`)
    return response.data.data
  } catch (error) {
    console.error('Error fetching asset history:', error)
    throw error
  }
}

/**
 * Get employee's assets
 * @param {number} userId - Employee ID
 * @returns {Promise<Array>} List of assets
 */
export const getEmployeeAssets = async (userId) => {
  try {
    const response = await api.get(`/assets/employee/${userId}/assets`)
    return response.data.data
  } catch (error) {
    console.error('Error fetching employee assets:', error)
    throw error
  }
}

// ==================== Asset Statistics ====================

/**
 * Get asset statistics
 * @returns {Promise<Object>} Asset statistics data
 */
export const fetchAssetStatistics = async () => {
  try {
    const response = await api.get('/assets/get/statistics')
    return response.data.data
  } catch (error) {
    console.error('Error fetching asset statistics:', error)
      return {
      total_assets: 0,
      available: 0,
      assigned: 0,
      maintenance: 0,
      disposed: 0,
      by_type: [],
      by_condition: [],
      recent_assignments: [],
    }
    throw error
  }
}

// ==================== Responsible Persons ====================

/**
 * Fetch list of responsible persons for asset assignment
 * @returns {Promise<Array>} List of responsible persons
 */
export const fetchResponsiblePersons = async () => {
  try {
    const response = await api.get('/available-responsible-persons')
    return response.data.data || response.data || []
  } catch (error) {
    console.error('Error fetching responsible persons:', error)
    throw error
  }
}



// ==================== Asset Activity Timeline ====================

/**
 * Build activity timeline for an asset
 * @param {Array} history - Asset history records
 * @returns {Array} Formatted timeline events
 */
export const buildActivityTimeline = (history = []) => {
  if (!Array.isArray(history) || history.length === 0) {
    return []
  }

  return history.map((record, index) => {
    const date = record.created_at || record.date || new Date().toISOString()
    const action = record.action || 'updated'
    const user = record.user?.name || record.performed_by?.name || 'System'
    const details = record.details || ''

    // تحديد لون ونوع النشاط
    let type = 'info'
    let icon = 'lucide:activity'
    let color = '#6b7280'

    switch (action.toLowerCase()) {
      case 'assigned':
        type = 'assignment'
        icon = 'lucide:user-check'
        color = '#16a34a'
        break
      case 'returned':
        type = 'return'
        icon = 'lucide:undo'
        color = '#f59e0b'
        break
      case 'transferred':
        type = 'transfer'
        icon = 'lucide:arrow-right-left'
        color = '#3b82f6'
        break
      case 'maintenance':
        type = 'maintenance'
        icon = 'lucide:tool'
        color = '#ef4444'
        break
      case 'created':
        type = 'creation'
        icon = 'lucide:plus-circle'
        color = '#8b5cf6'
        break
      case 'updated':
        type = 'update'
        icon = 'lucide:edit'
        color = '#f59e0b'
        break
      case 'deleted':
        type = 'deletion'
        icon = 'lucide:trash-2'
        color = '#ef4444'
        break
      case 'disposed':
        type = 'disposal'
        icon = 'lucide:trash'
        color = '#dc2626'
        break
      default:
        type = 'info'
        icon = 'lucide:activity'
        color = '#6b7280'
    }

    return {
      id: record.id || index,
      date,
      action: action.charAt(0).toUpperCase() + action.slice(1),
      user,
      details,
      type,
      icon,
      color,
      raw: record
    }
  }).sort((a, b) => new Date(b.date) - new Date(a.date))
}

// ==================== Asset Maintenance Records ====================

/**
 * Get maintenance records for an asset
 * @param {number} assetId - Asset ID
 * @param {Object} params - Filter parameters
 * @returns {Promise<Array>} Maintenance records
 */
export const getMaintenanceRecords = async (assetId, params = {}) => {
  try {
    const response = await api.get(`/assets/${assetId}/maintenance`, { params })
    return response.data.data || response.data || []
  } catch (error) {
    console.error('Error fetching maintenance records:', error)
    throw error
  }
}

/**
 * Create a maintenance record for an asset
 * @param {number} assetId - Asset ID
 * @param {Object} data - Maintenance data
 * @param {string} data.type - Maintenance type (preventive/corrective/emergency)
 * @param {string} data.description - Description
 * @param {string} data.date - Maintenance date
 * @param {number} data.cost - Cost (optional)
 * @param {string} data.status - Status (scheduled/in-progress/completed)
 * @param {File} data.attachment - Attachment (optional)
 * @returns {Promise<Object>} Created maintenance record
 */
export const createMaintenanceRecord = async (assetId, data) => {
  try {
    const formData = new FormData()
    Object.keys(data).forEach(key => {
      if (key === 'attachment' && data[key] instanceof File) {
        formData.append(key, data[key])
      } else if (data[key] !== null && data[key] !== undefined) {
        formData.append(key, data[key])
      }
    })
    
    const response = await api.post(`/assets/${assetId}/maintenance`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    return response.data.data || response.data
  } catch (error) {
    console.error('Error creating maintenance record:', error)
    throw error
  }
}

/**
 * Update a maintenance record
 * @param {number} assetId - Asset ID
 * @param {number} recordId - Record ID
 * @param {Object} data - Update data
 * @returns {Promise<Object>} Updated maintenance record
 */
export const updateMaintenanceRecord = async (assetId, recordId, data) => {
  try {
    const response = await api.put(`/assets/${assetId}/maintenance/${recordId}`, data)
    return response.data.data || response.data
  } catch (error) {
    console.error('Error updating maintenance record:', error)
    throw error
  }
}

/**
 * Delete a maintenance record
 * @param {number} assetId - Asset ID
 * @param {number} recordId - Record ID
 * @returns {Promise<Object>} Deletion response
 */
export const deleteMaintenanceRecord = async (assetId, recordId) => {
  try {
    const response = await api.delete(`/assets/${assetId}/maintenance/${recordId}`)
    return response.data
  } catch (error) {
    console.error('Error deleting maintenance record:', error)
    throw error
  }
}


export const exportAssetsCsv = async (params = {}) => {
  try {
    const response = await api.get('/assets/export', {
      params,
      responseType: 'blob'
    })
    return response.data
  } catch (error) {
    console.error('Error exporting assets CSV:', error)
    throw error
  }
}

export const fetchAsset = async (id) => {
  try {
    const response = await api.get(`/assets/${id}`)
    return response.data.data || response.data
  } catch (error) {
    console.error('Error fetching asset:', error)
    throw error
  }
}


