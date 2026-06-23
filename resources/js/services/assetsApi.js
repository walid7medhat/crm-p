import api from '@/plugins/axios'

const TYPE_ICONS = {
  laptop: 'lucide:laptop',
  phone: 'lucide:smartphone',
  monitor: 'lucide:monitor',
  vehicle: 'lucide:car',
  furniture: 'lucide:armchair',
  default: 'lucide:package',
}

function unwrapPaginated(payload) {
  const root = payload?.data
  if (root?.data && Array.isArray(root.data)) {
    return {
      items: root.data,
      currentPage: root.current_page ?? 1,
      lastPage: root.last_page ?? 1,
      total: root.total ?? root.data.length,
    }
  }
  if (Array.isArray(root)) {
    return { items: root, currentPage: 1, lastPage: 1, total: root.length }
  }
  return { items: [], currentPage: 1, lastPage: 1, total: 0 }
}

export function formatAssetStatus(status) {
  const map = {
    available: 'Available',
    assigned: 'Assigned',
    maintenance: 'Under Maintenance',
    disposed: 'Lost / Disposed',
  }
  return map[status] || status || '—'
}

export function formatCondition(condition) {
  const map = {
    new: 'New',
    used: 'Used',
    working: 'Working',
    damaged: 'Damaged',
    maintenance: 'Maintenance',
  }
  return map[condition] || condition || '—'
}

export function resolveWarrantyStatus(warrantyDate) {
  if (!warrantyDate) return { key: 'none', label: 'No warranty' }
  const end = new Date(warrantyDate)
  if (Number.isNaN(end.getTime())) return { key: 'none', label: 'No warranty' }
  const now = new Date()
  const days = Math.ceil((end - now) / (1000 * 60 * 60 * 24))
  if (days < 0) return { key: 'expired', label: 'Expired' }
  if (days <= 30) return { key: 'expiring_soon', label: `Expires in ${days}d` }
  return { key: 'active', label: 'Active' }
}

export function resolveAssetImage(asset) {
  const typeName = (asset?.asset_type?.name || asset?.assetType?.name || '').toLowerCase()
  let icon = TYPE_ICONS.default
  if (typeName.includes('laptop') || typeName.includes('computer')) icon = TYPE_ICONS.laptop
  else if (typeName.includes('phone') || typeName.includes('mobile')) icon = TYPE_ICONS.phone
  else if (typeName.includes('monitor') || typeName.includes('screen')) icon = TYPE_ICONS.monitor
  else if (typeName.includes('car') || typeName.includes('vehicle')) icon = TYPE_ICONS.vehicle
  else if (typeName.includes('chair') || typeName.includes('desk')) icon = TYPE_ICONS.furniture
  return { icon, isPlaceholder: true }
}

export function normalizeAsset(asset) {
  if (!asset) return null
  const type = asset.asset_type || asset.assetType || {}
  const user = asset.current_user || asset.currentUser || asset.current_assignment?.user || asset.currentAssignment?.user
  const assignment = asset.current_assignment || asset.currentAssignment
  const warranty = resolveWarrantyStatus(asset.warranty_date)
  const image = resolveAssetImage(asset)

  return {
    id: asset.id,
    assetId: asset.asset_code,
    name: asset.name,
    category: type.name || '—',
    assetTypeId: asset.asset_type_id,
    serialNumber: asset.serial_number || '—',
    modelNumber: asset.model_number || '—',
    assignedEmployee: user?.name || 'Unassigned',
    assignedEmployeeId: user?.id || assignment?.user_id || null,
    assignedAvatar: user?.name
      ? `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&background=733e87&color=fff`
      : null,
    status: asset.status,
    statusLabel: formatAssetStatus(asset.status),
    condition: asset.condition,
    conditionLabel: formatCondition(asset.condition),
    purchaseDate: asset.purchase_date,
    warrantyDate: asset.warranty_date,
    warrantyStatus: warranty,
    department: asset.department?.name || '—',
    departmentId: asset.department_id,
    branch: asset.branch?.name || '—',
    branchId: asset.branch_id,
    unitPrice: asset.unit_price,
    supplierName: asset.supplier_name || '—',
    quantity: asset.quantity ?? 1,
    handoverDate: assignment?.handover_date || null,
    description: asset.description || '',
    remarks: asset.remarks || '',
    imageIcon: image.icon,
    assignments: asset.assignments || [],
    histories: asset.histories || [],
    raw: asset,
  }
}

export async function fetchAssetStatistics() {
  const response = await api.get('/assets/get/statistics')
  return response.data?.data ?? {}
}

export async function fetchAssetTypes() {
  const response = await api.get('/asset-types')
  return response.data?.data ?? []
}

export async function fetchAssets(params = {}) {
  const response = await api.get('/assets', { params })
  const page = unwrapPaginated(response.data)
  return { ...page, items: page.items.map(normalizeAsset) }
}

export async function fetchAsset(id) {
  const response = await api.get(`/assets/${id}`)
  return normalizeAsset(response.data?.data)
}

export async function fetchAssetHistory(id) {
  const response = await api.get(`/assets/${id}/history`)
  return response.data?.data ?? []
}

export async function createAsset(payload) {
  const response = await api.post('/assets/store/new', payload)
  return normalizeAsset(response.data?.data)
}

export async function updateAsset(id, payload) {
  const response = await api.put(`/assets/${id}`, payload)
  return normalizeAsset(response.data?.data)
}

export async function deleteAsset(id) {
  await api.delete(`/assets/${id}`)
}

export async function assignAsset(id, payload) {
  const response = await api.post(`/assets/${id}/assign`, payload)
  return response.data?.data
}

export async function returnAsset(id, payload) {
  const response = await api.post(`/assets/${id}/return`, payload)
  return response.data?.data
}

export async function transferAsset(id, payload) {
  const response = await api.post(`/assets/${id}/transfer`, payload)
  return response.data?.data
}

export async function markAssetMaintenance(id, notes = '') {
  const response = await api.post(`/assets/${id}/maintenance`, { notes })
  return normalizeAsset(response.data?.data)
}

export function exportAssetsCsv(filename, rows, columns) {
  const header = columns.map((c) => c.label).join(',')
  const body = rows.map((row) =>
    columns.map((c) => `"${String(c.value(row) ?? '').replace(/"/g, '""')}"`).join(',')
  ).join('\n')
  const blob = new Blob([`${header}\n${body}`], { type: 'text/csv;charset=utf-8;' })
  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = filename
  link.click()
  URL.revokeObjectURL(link.href)
}

export function buildActivityTimeline(asset) {
  const items = []
  ;(asset?.histories || []).forEach((h) => {
    items.push({
      id: `h-${h.id}`,
      type: h.action,
      title: formatHistoryAction(h.action),
      detail: h.details,
      user: h.user?.name,
      performedBy: h.performed_by_user?.name || h.performedBy?.name,
      date: h.created_at,
    })
  })
  ;(asset?.assignments || []).forEach((a) => {
    items.push({
      id: `a-${a.id}`,
      type: 'assignment',
      title: a.status === 'active' ? 'Currently assigned' : 'Assignment ended',
      detail: a.user?.name || `User #${a.user_id}`,
      user: a.user?.name,
      performedBy: a.assigned_by_user?.name || a.assignedBy?.name,
      date: a.handover_date || a.created_at,
    })
  })
  return items.sort((a, b) => new Date(b.date) - new Date(a.date))
}

function formatHistoryAction(action) {
  const map = {
    assigned: 'Asset assigned',
    returned: 'Asset returned',
    transferred: 'Asset transferred',
    maintenance: 'Under maintenance',
    updated: 'Asset updated',
  }
  return map[action] || action || 'Activity'
}

export function getMaintenanceRecords(asset) {
  return (asset?.histories || []).filter((h) => h.action === 'maintenance' || String(h.details || '').toLowerCase().includes('maintenance'))
}

export function getWarrantyAlerts(assets) {
  return assets
    .filter((a) => a.warrantyStatus?.key === 'expiring_soon' || a.warrantyStatus?.key === 'expired')
    .map((a) => ({
      id: a.id,
      name: a.name,
      assetId: a.assetId,
      warrantyDate: a.warrantyDate,
      status: a.warrantyStatus,
    }))
}
