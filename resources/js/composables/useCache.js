import { ref } from 'vue'
import api from '@/plugins/axios'

const cache = {
  offices: null,
  areas: null,
  propertyTypes: null,
  stages: null,
  branchSources: null,
}

export function useCache() {
  const fetchOffices = async () => {
    if (cache.offices) return cache.offices
    try {
      const res = await api.get('/get-offices')
      let data = res.data?.data
      if (!Array.isArray(data)) {
        data = Array.isArray(res.data) ? res.data : []
      }
      
      // تحويل المكاتب إلى التنسيق المطلوب مع إضافة cityKey
      if (Array.isArray(data) && data.length) {
        cache.offices = data.map(office => ({
          value: office.id,
          text: office.name || `Office ${office.id}`,
          cityKey: detectCityKeyFromOffice(office),
          raw: office,
        }))
      } else {
        cache.offices = []
      }
    } catch (error) {
      console.error('Error fetching offices:', error)
      cache.offices = []
    }
    return cache.offices
  }

  const fetchAreas = async () => {
    if (cache.areas) return cache.areas
    try {
      const res = await api.get('/listings/areas/?has_listings=true')
      let data = res.data?.data
      if (!Array.isArray(data)) {
        data = Array.isArray(res.data) ? res.data : []
      }
      
      // تحويل المناطق إلى التنسيق المطلوب
      if (Array.isArray(data) && data.length) {
        cache.areas = data.map(area => ({
          id: area.id,
          name: area.name || area.title || `Area ${area.id}`,
          parent: area.area_parents_title || area.parent || area.parent_name || '',
          community_name: area.community_name || area.communityName || '',
          city_name: area.city_name || area.cityName || '',
        }))
      } else {
        cache.areas = []
      }
    } catch (error) {
      console.error('Error fetching areas:', error)
      cache.areas = []
    }
    return cache.areas
  }

  const fetchPropertyTypes = async () => {
    if (cache.propertyTypes) return cache.propertyTypes
    try {
      const res = await api.get('/listings/property-types')
      let data = res.data?.data
      if (!Array.isArray(data)) {
        data = Array.isArray(res.data) ? res.data : []
      }
      
      // تحويل أنواع العقارات إلى التنسيق المطلوب { value, text }
      if (Array.isArray(data) && data.length) {
        cache.propertyTypes = data.map(type => ({
          value: type.id,
          text: type.name || `Type ${type.id}`
        }))
      } else {
        cache.propertyTypes = []
      }
    } catch (error) {
      console.error('Error fetching property types:', error)
      cache.propertyTypes = []
    }
    return cache.propertyTypes
  }
const fetchStages = async () => {
  if (cache.stages) return cache.stages
  try {
    const res = await api.get('/stages')
    console.log('Stages API Response:', res.data) // للتشخيص
    
    // نفس المنطق من الفانكشن القديمة
    const raw = res.data?.data
    const data = Array.isArray(raw?.data) ? raw.data : (Array.isArray(raw) ? raw : [])
    
    console.log('Extracted stages data:', data) // للتشخيص
    
    if (data.length) {
      // ✅ تصفية أول اتنين Stage (بناءً على order)
      const filteredStages = data.filter(s => {
        const order = Number(s.order || 0)
        return order !== 1 && order !== 2
      })
      
      cache.stages = filteredStages.map(s => ({ 
        value: s.id, 
        text: s.name, 
        order: Number(s.order || 0) 
      }))
      
      console.log('Final stages options:', cache.stages) // للتشخيص
    } else {
      cache.stages = []
    }
  } catch (error) {
    console.error('Error fetching stages:', error)
    cache.stages = []
  }
  return cache.stages
}
  const fetchBranchSources = async () => {
    if (cache.branchSources) return cache.branchSources
    try {
      const res = await api.get('/get/lead/branch_source')
      let data = res.data?.data
      if (!Array.isArray(data)) {
        data = Array.isArray(res.data) ? res.data : []
      }
      
      if (Array.isArray(data) && data.length) {
        cache.branchSources = data.map(b => ({ 
          value: b.name, 
          text: b.name 
        }))
      } else {
        cache.branchSources = []
      }
    } catch (error) {
      console.error('Error fetching branch sources:', error)
      cache.branchSources = []
    }
    return cache.branchSources
  }

  return {
    fetchOffices,
    fetchAreas,
    fetchPropertyTypes,
    fetchStages,
    fetchBranchSources,
  }
}

// دالة مساعدة للكشف عن المدينة (يجب أن تكون معرفة هنا أو مستوردة)
function detectCityKeyFromOffice(office) {
  const normalizeCityText = (value) => String(value || '').toLowerCase().replace(/\s+/g, ' ').trim()
  
  const probes = [
    office?.city,
    office?.city_name,
    office?.branch_source,
    office?.branchSource,
    office?.parent_name,
    office?.parent?.name,
    office?.region,
    office?.office_city,
    office?.text,
    office?.name,
  ]
    .map(normalizeCityText)
    .filter(Boolean)

  const all = probes.join(' | ')
  if (all.includes('dubai')) return 'dubai'
  if (all.includes('abu dhabi') || all.includes('abudhabi') || all.includes('abu-dhabi')) return 'abu-dhabi'
  return ''
}