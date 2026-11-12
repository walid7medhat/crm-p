/**
 * Host-resolved brand config injected by welcome.blade.php as window.__BRAND__.
 * OIA defaults preserve the existing CRM look when brand bootstrap is missing.
 */

const OIA_FALLBACK = {
  key: 'oia',
  company_name: 'OIA Properties',
  company_name_short: 'Oia',
  company_name_display: 'OIA PROPERTIES',
  product_name: 'Alt CRM',
  product_mark: 'A',
  document_title: 'Alt CRM',
  email_domain: 'oiaproperties.com',
  logos: {
    primary: '/assets/images/auth/oia-properties-logo.svg',
    primary_png: '/assets/images/auth/oia-properties-logo.png',
    on_dark: '/assets/images/LogoWhite.png',
    product: '/assets/images/auth/altcrm-logo.png',
    favicon: '/assets/images/Fav icon.svg',
    watermark: '/oia-properties-logo-watermark.svg',
    collapsed: '/assets/images/auth/oia-properties-logo.svg',
  },
  colors: {
    primary: '#733E87',
    primary_rgb: '115, 62, 135',
    secondary: '#0B0736',
    accent: '#d4af37',
  },
  copy: {
    auth_headline_desktop:
      'A Powerful Digital Workspace for Real Estate Professionals and Growing Businesses',
    auth_headline_mobile: "Access OIA'S Exclusive Tools to Boost Your Brokerage",
    loader_label: 'Loading Alt CRM',
    birthday_sub: 'Warm wishes from OIA Properties',
  },
}

function readBrand() {
  if (typeof window !== 'undefined' && window.__BRAND__ && window.__BRAND__.key) {
    return window.__BRAND__
  }
  return OIA_FALLBACK
}

export function getBrand() {
  return readBrand()
}

export function isLuxvantBrand() {
  return readBrand().key === 'luxvant'
}

export function isOiaBrand() {
  return readBrand().key !== 'luxvant'
}

export function useBrand() {
  const brand = readBrand()

  return {
    brand,
    key: brand.key,
    isLuxvant: brand.key === 'luxvant',
    isOia: brand.key !== 'luxvant',
    companyName: brand.company_name,
    companyNameShort: brand.company_name_short,
    companyNameDisplay: brand.company_name_display,
    productName: brand.product_name,
    productMark: brand.product_mark,
    documentTitle: brand.document_title,
    logos: brand.logos || OIA_FALLBACK.logos,
    colors: brand.colors || OIA_FALLBACK.colors,
    copy: brand.copy || OIA_FALLBACK.copy,
  }
}

export default useBrand
