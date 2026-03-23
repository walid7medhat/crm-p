export const branches = ['All Team', 'Abu Dhabi Team', 'Dubai Team']

export const stages = [
  'New Leads',
  'Assigned',
  'Follow Up / Contacted',
  'Qualified',
  'Future Prospected',
  'Converted',
  'Shared Leads',
  'Lead Pool',
  'Lost Lead',
  'Unqualified'
]

export const kpiCards = [
  { key: 'total', title: 'Total leads', value: 155, trend: '-5% Last Week', delta: '7.2%', positive: false, icon: 'lucide:users' },
  { key: 'follow', title: 'Follow Ups', value: 120, trend: '+2% Last Week', delta: '18.2%', positive: true, icon: 'lucide:message-circle' },
  { key: 'qualified', title: 'Qualified Leads', value: 80, trend: '+4% Last Week', delta: '10.2%', positive: true, icon: 'lucide:user-check' },
  { key: 'unqualified', title: 'Unqualified Leads', value: 25, trend: '-5% Last Week', delta: '9.2%', positive: false, icon: 'lucide:user-x' },
  { key: 'converted', title: 'Converted Leads', value: 15, trend: '+5% Last Week', delta: '15.2%', positive: true, icon: 'lucide:refresh-cw' }
]

const baseRows = [
  ['01 Feb 2025', 'Leads For Sadiyath Island', 'Maria Guan', '10 Feb 2025', 'Meta Ads - Leads Form', 'Qualified'],
  ['03 Feb 2025', 'Gulf Star Investments - Office Tower Deal', 'Ahmad Al Daghash', '12 Feb 2025', 'Self Leads', 'Qualified'],
  ['04 Feb 2025', 'Horizon Capital Group - Property Portfolio', 'Omar Moraden', '15 Feb 2025', 'Call From Bayut', 'Qualified'],
  ['06 Feb 2025', 'Prime Assets Group - Retail Units', 'Ahmad Al Adaway', '18 Feb 2025', 'Meta Ads - Leads Form', 'Qualified'],
  ['10 Feb 2025', 'Urban Edge Holdings - Office Lease', 'Tarek Mahmoud', '20 Feb 2025', 'Meta - Comments and Direct Messages', 'Qualified'],
  ['13 Feb 2025', 'Apex Property Group - Investment', 'Hadi Zain', '19 Feb 2025', 'Booking', 'Qualified'],
  ['15 Mar 2025', 'Urban Edge Holdings - Office Sale', 'Karim Haddad', '22 Mar 2025', 'Oiaproperties.com', 'Qualified'],
  ['16 Mar 2025', 'Bayn-by-ora Property Sale', 'Omar Al Kaabi', '25 Mar 2025', 'Social Media - LinkedIn', 'Qualified'],
  ['18 Mar 2025', 'Manchester City - Yas Residence Villas', 'Khalid Al Mazrouei', '27 Mar 2025', 'Social Media - Facebook', 'Qualified'],
  ['22 Feb 2025', 'Bayn-by-ora Property Sale', 'MarAbdullah Al Falasi', '28 Feb 2025', 'Meta Ads - Leads Form', 'Qualified']
]

export const reportRows = baseRows.map((row, index) => ({
  id: index + 1,
  createdOn: row[0],
  leadName: row[1],
  responsibleName: row[2],
  responsibleEmail: `${row[2].toLowerCase().replace(/\s+/g, '.')}@gmail.com`,
  closingDate: row[3],
  source: row[4],
  stage: row[5],
  branch: index % 2 ? 'Abu Dhabi Team' : 'Dubai Team'
}))
