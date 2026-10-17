/**
 * Marketing workspace — single place for categories, content types, deliverables,
 * channels, platforms and link kinds (labels, colors, icons). Keys match the backend.
 */

export const MARKETING_CATEGORIES = {
  production: { label: 'Production', color: '#e11d48', icon: 'lucide:clapperboard', hint: 'Videos & photo shoots' },
  design: { label: 'Design', color: '#7c3aed', icon: 'lucide:palette', hint: 'Stories, posts, covers & campaigns' },
  content: { label: 'Content', color: '#0d9488', icon: 'lucide:file-text', hint: 'Copy & content pieces' },
};

/*
 * Colors: every item has a solid `color` (dots, icons, tints, calendar headers) and a
 * `grad` used when a chip is selected. Content types & deliverables are a jewel-tone set
 * tuned to sit next to the CRM's plum (#733E87) and gold; social channels keep a refined
 * version of their own brand gradient so they stay recognisable. `ink` = text on the fill
 * when white wouldn't read (Snapchat yellow).
 */
export const CONTENT_TYPES = {
  video: { label: 'Video', color: '#C2416B', grad: 'linear-gradient(135deg, #E0607E 0%, #B0305D 100%)', icon: 'lucide:video' },
  photo: { label: 'Photo', color: '#4A63D8', grad: 'linear-gradient(135deg, #6E86F0 0%, #3F51C4 100%)', icon: 'lucide:camera' },
  reel: { label: 'Reel', color: '#9B4DCA', grad: 'linear-gradient(135deg, #B970E0 0%, #7E3BAF 100%)', icon: 'lucide:film' },
  event: { label: 'Event', color: '#C2902B', grad: 'linear-gradient(135deg, #E2B755 0%, #B0801F 100%)', icon: 'lucide:party-popper' },
  unit_shoot: { label: 'Unit Photo Shoot', color: '#0F8B8D', grad: 'linear-gradient(135deg, #2FB5B7 0%, #0B6E70 100%)', icon: 'lucide:building-2' },

};

export const DELIVERABLES = {
  campaign: { label: 'Campaign', color: '#B8892B', grad: 'linear-gradient(135deg, #DDB052 0%, #A97B1F 100%)' },
  post: { label: 'Post', color: '#7A4A95', grad: 'linear-gradient(135deg, #9A6AB4 0%, #6A3D84 100%)' },
  story: { label: 'Story', color: '#D9675A', grad: 'linear-gradient(135deg, #F08A6E 0%, #C9524A 100%)' },
  linkedin: { label: 'LinkedIn', color: '#2C6FB7', grad: 'linear-gradient(135deg, #4A90D9 0%, #1F5EA3 100%)' },
  cover: { label: 'Cover', color: '#3F8C6B', grad: 'linear-gradient(135deg, #5CAE89 0%, #33785B 100%)' },
  sales_story: { label: 'Sales Story', color: '#2A8C99', grad: 'linear-gradient(135deg, #47AEBB 0%, #237683 100%)' },
};

/** Production distribution channels (where the finished video/photo goes). */
export const CHANNELS = {
  instagram: { label: 'Instagram', color: '#D6336C', grad: 'linear-gradient(45deg, #F58529 0%, #DD2A7B 50%, #8134AF 100%)', icon: 'mdi:instagram' },
  story: { label: 'Story', color: '#E8664A', grad: 'linear-gradient(135deg, #FCAF45 0%, #E1306C 100%)', icon: 'mdi:circle-slice-8' },
  youtube: { label: 'YouTube', color: '#E5322D', grad: 'linear-gradient(135deg, #FF5A4E 0%, #C4221B 100%)', icon: 'mdi:youtube' },
  linkedin: { label: 'LinkedIn', color: '#1F6FC2', grad: 'linear-gradient(135deg, #3A8FE0 0%, #0A5BAA 100%)', icon: 'mdi:linkedin' },
  unit_video: { label: 'Unit Video', color: '#733E87', grad: 'linear-gradient(135deg, #9562AA 0%, #5C2F70 100%)', icon: 'lucide:building-2' },
  campaigns: { label: 'Campaigns', color: '#B8892B', grad: 'linear-gradient(135deg, #DDB052 0%, #A97B1F 100%)', icon: 'lucide:megaphone' },
};

/** Posting calendar platforms. */
export const PLATFORMS = {
  instagram_story: { label: 'Instagram Story', short: 'IG Story', color: '#E8664A', grad: 'linear-gradient(135deg, #FCAF45 0%, #E1306C 100%)', icon: 'mdi:circle-slice-8' },
  instagram: { label: 'Instagram', short: 'Instagram', color: '#D6336C', grad: 'linear-gradient(45deg, #F58529 0%, #DD2A7B 50%, #8134AF 100%)', icon: 'mdi:instagram' },
  tiktok: { label: 'TikTok', short: 'TikTok', color: '#1E1B2E', grad: 'linear-gradient(135deg, #1E1B2E 0%, #2B2140 60%, #FE2C55 140%)', icon: 'ic:baseline-tiktok' },
  linkedin: { label: 'LinkedIn', short: 'LinkedIn', color: '#1F6FC2', grad: 'linear-gradient(135deg, #3A8FE0 0%, #0A5BAA 100%)', icon: 'mdi:linkedin' },
  snapchat: { label: 'Snapchat', short: 'Snapchat', color: '#D4A900', grad: 'linear-gradient(135deg, #FFF26B 0%, #F7CF1D 100%)', ink: '#2A2310', icon: 'mdi:snapchat' },
  youtube: { label: 'YouTube', short: 'YouTube', color: '#E5322D', grad: 'linear-gradient(135deg, #FF5A4E 0%, #C4221B 100%)', icon: 'mdi:youtube' },
  sales: { label: 'Sales', short: 'Sales', color: '#0F766E', grad: 'linear-gradient(135deg, #2AA89A 0%, #0B6B62 100%)', icon: 'lucide:badge-dollar-sign' },
};

/** Inline style for a toggle chip: solid color, selected gradient, and text color on the fill. */
export function chipStyle(item) {
  return {
    '--chip': item?.color,
    '--chip-grad': item?.grad || item?.color,
    '--chip-ink': item?.ink || '#fff',
  };
}

export const LINK_KINDS = {
  drive_folder: { label: 'Drive folder', icon: 'mdi:folder-google-drive', color: '#1a73e8' },
  drive_file: { label: 'Drive file', icon: 'mdi:google-drive', color: '#1a73e8' },
  sheet: { label: 'Google Sheet', icon: 'mdi:google-spreadsheet', color: '#0f9d58' },
  doc: { label: 'Google Doc', icon: 'mdi:file-document', color: '#4285f4' },
  image: { label: 'Image', icon: 'lucide:image', color: '#db2777' },
  video: { label: 'Video', icon: 'lucide:play-circle', color: '#dc2626' },
  other: { label: 'Link', icon: 'lucide:link', color: '#64748b' },
};

export const STAGE_COLOR_SWATCHES = ['#f5b300', '#2563eb', '#9b87f5', '#475569', '#dc2626', '#16a34a', '#0d9488', '#db2777', '#ea580c', '#7c3aed'];

export const HISTORY_ACTIONS = {
  created: { label: 'created the card', icon: 'lucide:sparkles', color: '#7c3aed' },
  updated: { label: 'edited', icon: 'lucide:pencil', color: '#2563eb' },
  moved: { label: 'moved the card', icon: 'lucide:move-right', color: '#0d9488' },
  link_added: { label: 'added a link', icon: 'lucide:link', color: '#1a73e8' },
  link_removed: { label: 'removed a link', icon: 'lucide:unlink', color: '#64748b' },
  approved: { label: 'approved the design', icon: 'lucide:badge-check', color: '#16a34a' },
  unapproved: { label: 'removed approval', icon: 'lucide:badge-x', color: '#dc2626' },
  commented: { label: 'commented', icon: 'lucide:message-circle', color: '#d97706' },
  scheduled: { label: 'scheduled a post', icon: 'lucide:calendar-plus', color: '#9333ea' },
  posted: { label: 'marked as posted', icon: 'lucide:send', color: '#16a34a' },
  unposted: { label: 'unmarked posted', icon: 'lucide:undo-2', color: '#64748b' },
  deleted: { label: 'deleted the card', icon: 'lucide:trash-2', color: '#dc2626' },
};

export function initials(name) {
  const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
  if (!parts.length) return '?';
  return parts.slice(0, 2).map((p) => p[0]).join('').toUpperCase();
}

export function timeAgo(iso) {
  if (!iso) return '';
  const diff = (Date.now() - new Date(iso).getTime()) / 1000;
  if (diff < 60) return 'just now';
  if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
  if (diff < 86400 * 7) return `${Math.floor(diff / 86400)}d ago`;
  return new Date(iso).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

export function formatDate(value, opts = { day: '2-digit', month: 'short' }) {
  if (!value) return '';
  const d = new Date(value.length === 10 ? `${value}T00:00:00` : value);
  if (Number.isNaN(d.getTime())) return value;
  return d.toLocaleDateString('en-GB', opts);
}

/** YYYY-MM-DD in local time. */
export function isoDate(d) {
  const pad = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

export function apiError(err, fallback = 'Something went wrong. Please try again.') {
  const data = err?.response?.data;
  const firstFieldError = data?.errors && Object.values(data.errors)[0];
  return (Array.isArray(firstFieldError) ? firstFieldError[0] : null) || data?.message || fallback;
}
export const DEPARTMENTS = {
  listing:   { label: 'Listing',   icon: 'lucide:list',       color: '#2563eb' },
  leasing:   { label: 'Leasing',   icon: 'lucide:key-round',  color: '#16a34a' },
  homya:     { label: 'Homya',     icon: 'lucide:home',       color: '#db2777' },
  marketing: { label: 'Marketing', icon: 'lucide:megaphone',  color: '#9333ea' },
}