import Swal from 'sweetalert2'

/**
 * The CRM's rich top-right toast — the same design leads.vue and Deals.vue use for their
 * live pop-ups (gradient icon that pops in, bold headline, name + chip, close ×, progress bar).
 * Styles are injected once under the same id, so whichever screen loads first, they match.
 *
 *   showCrmToast({ kind: 'created', headline: 'Card created', subtitle: 'Ohana Gift', chip: 'Design' })
 */

const ICONS = {
  created: '<svg viewBox="0 0 24 24"><path d="M12 3l1.9 4.6L18.5 9.5 13.9 11.4 12 16l-1.9-4.6L5.5 9.5 10.1 7.6z"/><path d="M19 13.5l.8 2 2 .8-2 .8-.8 2-.8-2-2-.8 2-.8z"/></svg>',
  updated: '<svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>',
  stage_changed: '<svg viewBox="0 0 24 24"><path d="M8 3 4 7l4 4"/><path d="M4 7h16"/><path d="m16 21 4-4-4-4"/><path d="M20 17H4"/></svg>',
  deleted: '<svg viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M10 11v6M14 11v6"/></svg>',
  link: '<svg viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>',
  scheduled: '<svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="m9 16 2 2 4-4"/></svg>',
  approved: '<svg viewBox="0 0 24 24"><path d="M12 2 15 5h4v4l3 3-3 3v4h-4l-3 3-3-3H5v-4l-3-3 3-3V5h4z"/><path d="m9 12 2 2 4-4"/></svg>',
  copied: '<svg viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>',
  default: '<svg viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="m7 15 3-4 3 3 4-6"/></svg>',
}

/** Same palette as the lead / deal pop-ups. */
const THEMES = {
  created: { grad: 'linear-gradient(135deg,#34d399,#059669)', accent: '#10b981', soft: 'rgba(16,185,129,.12)', ring: 'rgba(16,185,129,.45)' },
  approved: { grad: 'linear-gradient(135deg,#34d399,#059669)', accent: '#10b981', soft: 'rgba(16,185,129,.12)', ring: 'rgba(16,185,129,.45)' },
  scheduled: { grad: 'linear-gradient(135deg,#a78bfa,#7c3aed)', accent: '#8b5cf6', soft: 'rgba(139,92,246,.13)', ring: 'rgba(139,92,246,.45)' },
  stage_changed: { grad: 'linear-gradient(135deg,#a78bfa,#7c3aed)', accent: '#8b5cf6', soft: 'rgba(139,92,246,.13)', ring: 'rgba(139,92,246,.45)' },
  updated: { grad: 'linear-gradient(135deg,#60a5fa,#2563eb)', accent: '#3b82f6', soft: 'rgba(59,130,246,.12)', ring: 'rgba(59,130,246,.45)' },
  link: { grad: 'linear-gradient(135deg,#60a5fa,#2563eb)', accent: '#3b82f6', soft: 'rgba(59,130,246,.12)', ring: 'rgba(59,130,246,.45)' },
  copied: { grad: 'linear-gradient(135deg,#94a3b8,#475569)', accent: '#64748b', soft: 'rgba(100,116,139,.13)', ring: 'rgba(100,116,139,.45)' },
  deleted: { grad: 'linear-gradient(135deg,#f87171,#dc2626)', accent: '#ef4444', soft: 'rgba(239,68,68,.12)', ring: 'rgba(239,68,68,.45)' },
  default: { grad: 'linear-gradient(135deg,#94a3b8,#475569)', accent: '#64748b', soft: 'rgba(100,116,139,.13)', ring: 'rgba(100,116,139,.45)' },
}

/** Identical to ensureCrmToastStyles() in leads.vue / Deals.vue (same id → injected once). */
export function ensureCrmToastStyles() {
  if (document.getElementById('crm-toast-styles')) return
  const style = document.createElement('style')
  style.id = 'crm-toast-styles'
  style.textContent = `
        .crm-toast-popup {
            padding: 0 !important;
            border-radius: 16px !important;
            overflow: hidden;
            background: #ffffff !important;
            border: 1px solid rgba(15, 23, 42, 0.06);
            box-shadow: 0 18px 40px -12px rgba(15, 23, 42, 0.28), 0 6px 14px -6px rgba(15, 23, 42, 0.16) !important;
        }
        .crm-toast {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 14px 14px 14px 16px;
            position: relative;
            min-width: 308px;
        }
        .crm-toast__close {
            position: absolute;
            top: 8px; right: 8px;
            width: 22px; height: 22px;
            display: flex; align-items: center; justify-content: center;
            padding: 0; margin: 0;
            border: none; border-radius: 7px;
            background: transparent; color: #94a3b8;
            cursor: pointer;
            transition: background .15s ease, color .15s ease, transform .15s ease;
        }
        .crm-toast__close:hover { background: rgba(15,23,42,.08); color: #334155; transform: rotate(90deg); }
        .crm-toast__close svg { width: 13px; height: 13px; stroke: currentColor; stroke-width: 2.4; fill: none; stroke-linecap: round; }
        .crm-toast::before {
            content: '';
            position: absolute;
            left: 0; top: 0; bottom: 0;
            width: 4px;
            background: var(--crm-accent, #3b82f6);
        }
        .crm-toast__icon {
            flex: 0 0 auto;
            width: 44px; height: 44px;
            border-radius: 13px;
            display: flex; align-items: center; justify-content: center;
            position: relative;
            color: #fff; line-height: 1;
            background: var(--crm-grad, linear-gradient(135deg,#3b82f6,#2563eb));
            animation: crmToastPop .45s cubic-bezier(.18,.89,.32,1.28), crmHalo 2.6s ease-out .45s infinite;
        }
        .crm-toast__icon::after {
            content: '';
            position: absolute; inset: 0;
            border-radius: inherit;
            background: linear-gradient(180deg, rgba(255,255,255,.4), rgba(255,255,255,0) 55%);
            pointer-events: none;
        }
        .crm-toast__icon svg {
            width: 23px; height: 23px;
            stroke: #fff; fill: none;
            stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
            filter: drop-shadow(0 1px 1.5px rgba(0,0,0,.22));
            animation: crmIconPop .6s cubic-bezier(.18,.89,.32,1.4) .08s both;
        }
        .crm-toast__body {
            display: flex; flex-direction: column; gap: 3px;
            text-align: left; min-width: 0;
        }
        .crm-toast__title {
            font-size: 14px; font-weight: 700; color: #0f172a;
            line-height: 1.25; letter-spacing: -0.01em;
        }
        .crm-toast__title b { color: var(--crm-accent, #3b82f6); }
        .crm-toast__sub {
            font-size: 12.5px; color: #64748b; line-height: 1.35;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            max-width: 240px;
        }
        .crm-toast__chip {
            display: inline-block;
            font-size: 11px; font-weight: 600;
            padding: 1px 7px; border-radius: 999px;
            background: var(--crm-soft, rgba(59,130,246,0.12));
            color: var(--crm-accent, #3b82f6);
            margin-left: 4px; vertical-align: middle;
        }
        .crm-toast-progress { background: var(--crm-accent, #3b82f6) !important; height: 3px !important; }
        @keyframes crmToastPop {
            0% { transform: scale(.3) rotate(-8deg); opacity: 0; }
            100% { transform: scale(1) rotate(0); opacity: 1; }
        }
        @keyframes crmIconPop {
            0% { transform: scale(0) rotate(-35deg); opacity: 0; }
            60% { transform: scale(1.18) rotate(9deg); }
            100% { transform: scale(1) rotate(0); opacity: 1; }
        }
        @keyframes crmHalo {
            0% { box-shadow: 0 8px 18px -6px var(--crm-accent,#3b82f6), 0 0 0 0 var(--crm-ring,rgba(59,130,246,.45)); }
            70%, 100% { box-shadow: 0 8px 18px -6px var(--crm-accent,#3b82f6), 0 0 0 12px rgba(0,0,0,0); }
        }
        @media (prefers-color-scheme: dark) {
            .crm-toast-popup { background: #1e293b !important; border-color: rgba(255,255,255,0.06); }
            .crm-toast__title { color: #f1f5f9; }
            .crm-toast__sub { color: #94a3b8; }
            .crm-toast__close:hover { background: rgba(255,255,255,.1); color: #e2e8f0; }
        }
    `
  document.head.appendChild(style)
}

const escapeHtml = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]))

/**
 * @param {object} o
 * @param {'created'|'updated'|'stage_changed'|'deleted'|'link'|'scheduled'|'approved'|'copied'} [o.kind]
 * @param {string} o.headline   bold first line, e.g. "Card created"
 * @param {string} [o.subtitle] second line, e.g. the card title (escaped)
 * @param {string} [o.chip]     small colored pill after the subtitle, e.g. "Design"
 * @param {number} [o.timer]    ms, default 4000 like leads/deals
 */
export function showCrmToast({ kind = 'default', headline, subtitle = '', chip = '', timer = 4000 } = {}) {
  ensureCrmToastStyles()
  const t = THEMES[kind] || THEMES.default
  const icon = ICONS[kind] || ICONS.default
  const sub = `${escapeHtml(subtitle)}${chip ? ` <span class="crm-toast__chip">${escapeHtml(chip)}</span>` : ''}`

  return Swal.fire({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer,
    timerProgressBar: true,
    customClass: {
      popup: 'crm-toast-popup',
      timerProgressBar: 'crm-toast-progress',
    },
    html: `
      <div class="crm-toast">
        <div class="crm-toast__icon">${icon}</div>
        <div class="crm-toast__body">
          <div class="crm-toast__title">${escapeHtml(headline)}</div>
          ${sub.trim() ? `<div class="crm-toast__sub">${sub}</div>` : ''}
        </div>
        <button type="button" class="crm-toast__close" aria-label="Dismiss">
          <svg viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
      </div>
    `,
    didOpen: (toast) => {
      toast.style.setProperty('--crm-accent', t.accent)
      toast.style.setProperty('--crm-grad', t.grad)
      toast.style.setProperty('--crm-soft', t.soft)
      toast.style.setProperty('--crm-ring', t.ring)
      toast.addEventListener('mouseenter', Swal.stopTimer)
      toast.addEventListener('mouseleave', Swal.resumeTimer)
      toast.querySelector('.crm-toast__close')?.addEventListener('click', () => Swal.close())
    },
  })
}
