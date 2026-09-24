/**
 * Small source mark for lead cards. Brand logos where they exist;
 * a short colored badge for portals and websites so each source is distinct.
 */
const EXACT = {
    'social media - linkedin': { icon: 'logos:linkedin-icon' },
    'social media-facebook': { icon: 'logos:facebook' },
    'meta - comments and direct messages': { icon: 'logos:messenger' },
    'lead form': { icon: 'logos:meta-icon' },
    snapchat: { icon: 'logos:snapchat' },
    'whatsapp campaign': { icon: 'logos:whatsapp-icon' },
    wati: { icon: 'logos:whatsapp-icon' },
    whatsapp: { icon: 'logos:whatsapp-icon' },
    'whatsapp from bayut': { badge: 'WB', bg: '#128C7E' },
    'whatsapp from property finder': { badge: 'WP', bg: '#25D366' },
    'call from bayut': { badge: 'B', bg: '#00A651' },
    'email from bayut': { icon: 'lucide:mail', bg: '#00A651' },
    'email from property finder': { icon: 'lucide:mail', bg: '#E4002B' },
    'call from dubizzle': { badge: 'D', bg: '#E00000' },
    'allproperties.ae': { badge: 'AP', bg: '#1d4ed8' },
    'oiaproperties.com': { badge: 'OIA', bg: '#0f766e' },
    'bayn-by-ora.com': { badge: 'BY', bg: '#7c3aed' },
    'company website': { icon: 'lucide:globe', bg: '#2563eb' },
    website: { icon: 'lucide:globe', bg: '#2563eb' },
    booking: { icon: 'lucide:calendar-check', bg: '#003580' },
    'land line': { icon: 'lucide:phone', bg: '#334155' },
    'mobile marketing': { icon: 'lucide:smartphone', bg: '#ea580c' },
    'self leads': { icon: 'lucide:user', bg: '#7c3aed' },
    'my self network': { icon: 'lucide:user', bg: '#6d28d9' },
    'via references': { icon: 'lucide:users', bg: '#0f766e' },
    referral: { icon: 'lucide:users', bg: '#0f766e' },
    'my social media app': { icon: 'lucide:share-2', bg: '#db2777' },
    'others (comment below)': { icon: 'lucide:more-horizontal', bg: '#64748b' },
    other: { icon: 'lucide:more-horizontal', bg: '#64748b' },
    portal: { icon: 'lucide:building-2', bg: '#b45309' },
    web: { icon: 'lucide:globe', bg: '#2563eb' },
    webform: { icon: 'logos:meta-icon' },
    propertyfinder: { badge: 'PF', bg: '#E4002B' },
    bayut: { badge: 'B', bg: '#00A651' },
}

function fuzzyMark(key) {
    if (key.includes('linkedin')) return { icon: 'logos:linkedin-icon' }
    if (key.includes('snapchat')) return { icon: 'logos:snapchat' }
    if (key.includes('facebook')) return { icon: 'logos:facebook' }
    if (key.includes('whatsapp') || key.includes('wati')) return { icon: 'logos:whatsapp-icon' }
    if (key.includes('lead form') || key.includes('meta')) return { icon: 'logos:meta-icon' }
    if (key.includes('property finder') || key.includes('propertyfinder')) return { badge: 'PF', bg: '#E4002B' }
    if (key.includes('bayut')) return { badge: 'B', bg: '#00A651' }
    if (key.includes('dubizzle')) return { badge: 'D', bg: '#E00000' }
    if (key.includes('email')) return { icon: 'lucide:mail', bg: '#0284c7' }
    if (key.includes('call') || key.includes('land line') || key.includes('phone')) return { icon: 'lucide:phone', bg: '#334155' }
    if (key.includes('refer') || key.includes('self')) return { icon: 'lucide:users', bg: '#0f766e' }
    if (/\.ae|\.com|website|webform|^web$/.test(key)) return { icon: 'lucide:globe', bg: '#2563eb' }
    return { icon: 'lucide:megaphone', bg: '#64748b' }
}

export function leadSourceMark(source) {
    const title = String(source || '').trim()
    if (!title) return null
    const key = title.toLowerCase()
    const mark = EXACT[key] || fuzzyMark(key)
    return { title, ...mark }
}
