import http from 'k6/http';
import { sleep } from 'k6';
import { Counter, Rate, Trend } from 'k6/metrics';
import { textSummary } from 'https://jslib.k6.io/k6-summary/0.1.0/index.js';

/**
 * Local GET-only read test.
 * 429 responses are the shared per-IP limiter (throttle:300,1). They are counted
 * separately and are not application errors. 403 is authorization, not capacity.
 * This script does not bypass that limiter.
 */

const vus = Number(__ENV.VUS || 5);
const duration = __ENV.DURATION || '1m';
const baseUrl = (__ENV.BASE_URL || 'http://127.0.0.1:8000').replace(/\/$/, '');

if (!/^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?$/i.test(baseUrl)) {
  throw new Error(`Refusing non-local BASE_URL: ${baseUrl}`);
}

const ENDPOINTS = [
  ['sidebar_counts', '/sidebar/counts', 'shell'],
  ['announcements_unread', '/announcements/unread', 'shell'],
  ['auth_notifications', '/auth/notifications', 'shell'],
  ['birthdays_today', '/auth/birthdays/today', 'shell'],
  ['chat_unread', '/chat/unread-count', 'shell'],
  ['analytics_crm', '/dashboard/analytics-overview/crm', 'dashboard'],
  ['analytics_deals', '/dashboard/analytics-overview/deals', 'dashboard'],
  ['analytics_listing', '/dashboard/analytics-overview/listing', 'dashboard'],
  ['analytics_hr', '/dashboard/analytics-overview/hr', 'dashboard'],
  ['kanban_board', '/stages/kanban/stages-with-leads', 'kanban'],
  ['kanban_settings', '/settings/kanban', 'kanban'],
  ['lead_show', '/leads/{id}', 'view_lead'],
  ['stages', '/stages', 'view_lead'],
  ['listings', '/listings/properties', 'listings'],
  ['property_show', '/listings/properties/{id}', 'property'],
  ['property_comments', '/listings/{id}/comments', 'property'],
  ['property_comment_stats', '/listings/{id}/comments/stats', 'property'],
  ['deals_board', '/deals/grouped-by-stage', 'deals'],
  ['lead_search', '/stages/kanban/stages-with-leads?search', 'search'],
  ['kanban_more', '/stages/kanban/stage/{id}/more-leads', 'kanban_more'],
];

const meta = {};
const durationByEndpoint = {};
const countByEndpoint = {};
const c429 = {};
const c403 = {};
const c5xx = {};
const cConn = {};

for (const [name, path, flow] of ENDPOINTS) {
  meta[name] = { path, flow };
  durationByEndpoint[name] = new Trend(`dur_${name}`, true);
  countByEndpoint[name] = new Counter(`n_${name}`);
  c429[name] = new Counter(`rl_${name}`);
  c403[name] = new Counter(`fb_${name}`);
  c5xx[name] = new Counter(`sx_${name}`);
  cConn[name] = new Counter(`cf_${name}`);
}

const applicationError = new Rate('application_error');
const rateLimited = new Rate('rate_limited');
const forbidden = new Rate('forbidden');

const headers = {
  Accept: 'application/json',
  'Content-Type': 'application/json',
  'X-Requested-With': 'XMLHttpRequest',
};

http.setResponseCallback(http.expectedStatuses(200, 403, 429));

let token = '';
let leadIds = [];
let listingIds = [];
let leadStageId = 0;
let dealType = 'primary';
let searchTerm = 'al';
let lastShellAt = 0;

export const options = {
  scenarios: {
    local_read_only: {
      executor: 'constant-vus',
      vus,
      duration,
      gracefulStop: '30s',
    },
  },
  summaryTrendStats: ['avg', 'min', 'med', 'max', 'p(50)', 'p(90)', 'p(95)', 'p(99)'],
  thresholds: {
    application_error: [{ threshold: 'rate<0.01', abortOnFail: false }],
  },
};

const fixture = JSON.parse(open('./data/tokens.json'));
const fixtureUsers = Array.isArray(fixture.users) ? fixture.users : [];
if (fixtureUsers.length === 0) {
  throw new Error('data/tokens.json must contain users[].lead_ids. Run: php loadtests/k6/scripts/build-local-fixture.php');
}
for (const user of fixtureUsers) {
  if (!user.token || String(user.token).includes('PASTE_') || !Array.isArray(user.lead_ids) || user.lead_ids.length === 0) {
    throw new Error(`User ${user.user_id || '?'} needs a JWT and at least one authorized lead_id`);
  }
}

export function setup() {
  return fixture;
}

function pick(list, fallback) {
  if (!list || !list.length) return fallback;
  return list[(__VU + __ITER) % list.length];
}

function get(path, params, endpoint) {
  const info = meta[endpoint];
  const qs = params
    ? Object.entries(params)
        .filter(([, v]) => v !== undefined && v !== null && v !== '')
        .map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`)
        .join('&')
    : '';
  const url = `${baseUrl}/api${path}${qs ? `?${qs}` : ''}`;
  const res = http.get(url, {
    headers: Object.assign({ Authorization: `Bearer ${token}` }, headers),
    tags: {
      flow: info.flow,
      endpoint,
      path: info.path,
      method: 'GET',
    },
  });
  const method = res.request && res.request.method ? String(res.request.method) : 'GET';
  if (method.toUpperCase() !== 'GET') {
    throw new Error(`Blocked non-GET ${method} ${path}`);
  }

  const status = res.status || 0;
  countByEndpoint[endpoint].add(1);
  durationByEndpoint[endpoint].add(res.timings.duration);
  const is429 = status === 429;
  const is403 = status === 403;
  const is5xx = status >= 500;
  const isConn = status === 0;
  if (is429) c429[endpoint].add(1);
  if (is403) c403[endpoint].add(1);
  if (is5xx) c5xx[endpoint].add(1);
  if (isConn) cConn[endpoint].add(1);
  applicationError.add(is5xx || isConn);
  rateLimited.add(is429);
  forbidden.add(is403);
  if (status !== 200) {
    console.warn(`non-200 ${status} flow=${info.flow} endpoint=${endpoint} path=${info.path}`);
  }
  return res;
}

function shell() {
  get('/sidebar/counts', null, 'sidebar_counts');
  get('/announcements/unread', null, 'announcements_unread');
  get('/auth/notifications', null, 'auth_notifications');
  get('/auth/birthdays/today', null, 'birthdays_today');
  get('/chat/unread-count', null, 'chat_unread');
}

function dashboard() {
  const params = { period: 'monthly' };
  get('/dashboard/analytics-overview/crm', params, 'analytics_crm');
  get('/dashboard/analytics-overview/deals', params, 'analytics_deals');
  get('/dashboard/analytics-overview/listing', params, 'analytics_listing');
  get('/dashboard/analytics-overview/hr', params, 'analytics_hr');
}

function kanban() {
  get('/stages/kanban/stages-with-leads', { per_page: 15 }, 'kanban_board');
  get('/settings/kanban', null, 'kanban_settings');
}

function viewLead() {
  get(`/leads/${pick(leadIds, 0)}`, null, 'lead_show');
  get('/stages', null, 'stages');
}

function listings(page) {
  get('/listings/properties', { page, per_page: 12, is_active: 1, is_archived: 0 }, 'listings');
}

function property() {
  const id = pick(listingIds, 0);
  get(`/listings/properties/${id}`, null, 'property_show');
  get(`/listings/${id}/comments`, null, 'property_comments');
  get(`/listings/${id}/comments/stats`, null, 'property_comment_stats');
}

function deals() {
  get('/deals/grouped-by-stage', { deal_type: dealType, per_page: 10 }, 'deals_board');
}

function search() {
  get('/stages/kanban/stages-with-leads', { per_page: 15, search: searchTerm }, 'lead_search');
}

function moreLeads() {
  get(`/stages/kanban/stage/${leadStageId}/more-leads`, { page: 2, per_page: 15 }, 'kanban_more');
}

const flows = [
  [15, dashboard],
  [30, kanban],
  [12, viewLead],
  [18, () => listings(1)],
  [8, () => listings(2)],
  [7, property],
  [7, deals],
  [2, search],
  [1, moreLeads],
];

function pickFlow() {
  const roll = Math.random() * 100;
  let cursor = 0;
  for (const [weight, fn] of flows) {
    cursor += weight;
    if (roll < cursor) return fn;
  }
  return kanban;
}

export default function (data) {
  const user = data.users[(__VU - 1) % data.users.length];
  token = user.token;
  leadIds = user.lead_ids;
  listingIds = data.listing_ids || [];
  leadStageId = data.lead_stage_id;
  dealType = data.deal_type || 'primary';
  searchTerm = data.search_term || 'al';

  const now = Date.now();
  if (now - lastShellAt >= 60000) {
    shell();
    lastShellAt = now;
  }
  pickFlow()();
  sleep(2 + Math.random() * 3);
}

function values(metric) {
  return (metric && metric.values) || {};
}

function countOf(data, key) {
  return values(data.metrics[key]).count || 0;
}

export function handleSummary(data) {
  const durationMetric = values(data.metrics.http_req_duration);
  const reqs = values(data.metrics.http_reqs);
  let total429 = 0;
  let total403 = 0;
  let total5xx = 0;
  let totalConn = 0;
  let totalN = 0;

  const endpoints = ENDPOINTS.map(([name, path, flow]) => {
    const n = countOf(data, `n_${name}`);
    const n429 = countOf(data, `rl_${name}`);
    const n403 = countOf(data, `fb_${name}`);
    const n5xx = countOf(data, `sx_${name}`);
    const nConn = countOf(data, `cf_${name}`);
    const dur = values(data.metrics[`dur_${name}`]);
    totalN += n;
    total429 += n429;
    total403 += n403;
    total5xx += n5xx;
    totalConn += nConn;
    return {
      flow,
      endpoint: name,
      path,
      count: n,
      p95_ms: dur['p(95)'] ?? null,
      p99_ms: dur['p(99)'] ?? null,
      application_error_rate: n ? (n5xx + nConn) / n : 0,
      count_429: n429,
      count_403: n403,
      count_5xx: n5xx,
      connection_failures: nConn,
    };
  }).sort((a, b) => (b.p95_ms || 0) - (a.p95_ms || 0));

  const report = {
    vus,
    duration,
    base_url: baseUrl,
    limiter_note: 'throttle:300,1 is keyed before JWT auth, so one generator IP shares 300 requests/minute. 429 is that limiter, not an application failure. Do not bypass it.',
    p50_ms: durationMetric['p(50)'],
    p95_ms: durationMetric['p(95)'],
    p99_ms: durationMetric['p(99)'],
    rps: reqs.rate,
    requests: reqs.count,
    application_error_rate: values(data.metrics.application_error).rate || 0,
    rate_limited_rate: values(data.metrics.rate_limited).rate || 0,
    forbidden_rate: values(data.metrics.forbidden).rate || 0,
    count_429: total429,
    count_403: total403,
    count_5xx: total5xx,
    connection_failures: totalConn,
    endpoints,
  };

  const lines = [
    `requests ${totalN}`,
    `p50_ms ${report.p50_ms}`,
    `p95_ms ${report.p95_ms}`,
    `p99_ms ${report.p99_ms}`,
    `rps ${report.rps}`,
    `application_error_rate ${report.application_error_rate}`,
    `count_429 ${total429}`,
    `count_403 ${total403}`,
    `count_5xx ${total5xx}`,
    `connection_failures ${totalConn}`,
    'endpoint flow path p95_ms p99_ms app_error_rate n429 n403 n5xx conn',
  ];
  for (const row of endpoints) {
    if (!row.count) continue;
    lines.push(
      `${row.endpoint} ${row.flow} ${row.path} ${row.p95_ms} ${row.p99_ms} ${row.application_error_rate} ${row.count_429} ${row.count_403} ${row.count_5xx} ${row.connection_failures}`
    );
  }

  return {
    stdout: `${textSummary(data, { indent: ' ', enableColors: false })}\n${lines.join('\n')}\n`,
    [`results/summary-${vus}vu.json`]: JSON.stringify(report, null, 2),
  };
}
