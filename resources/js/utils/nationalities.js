import countries from 'i18n-iso-countries'
import en from 'i18n-iso-countries/langs/en.json'

countries.registerLocale(en)

// Package "official" names are formal ("Türkiye", "Russian Federation"); use the names people search for.
const COMMON_NAMES = {
  BN: 'Brunei',
  CD: 'DR Congo',
  CG: 'Congo',
  CN: 'China',
  CV: 'Cabo Verde',
  CZ: 'Czechia',
  FM: 'Micronesia',
  GM: 'Gambia',
  IR: 'Iran',
  LA: 'Laos',
  MD: 'Moldova',
  MK: 'North Macedonia',
  PS: 'Palestine',
  RU: 'Russia',
  SY: 'Syria',
  TR: 'Turkey',
  TW: 'Taiwan',
  TZ: 'Tanzania',
  US: 'United States',
  VA: 'Vatican City',
}

export const nationalityOptions = Object.entries(countries.getNames('en', { select: 'official' }))
  .map(([code, name]) => ({
    value: code.toLowerCase(),
    text: COMMON_NAMES[code] || name,
    code: code.toLowerCase(),
  }))
  .sort((a, b) => a.text.localeCompare(b.text))

export const nationalityNames = nationalityOptions.map((o) => o.text)
