/**
 * Listing property types that have no bedrooms/bathrooms: plots, land and offices.
 * Used by the listing create/edit forms, listing cards, property details and the
 * sales offer PDF so they all hide Bedrooms / Bathrooms the same way.
 */
const NO_BEDS_BATHS_KEYWORDS = ['plot', 'land', 'office'];

/** @param {string|{name?: string}|null|undefined} propertyType */
export const propertyTypeName = (propertyType) =>
  String((typeof propertyType === 'string' ? propertyType : propertyType?.name) || '').toLowerCase();

/** True when Bedrooms / Bathrooms don't apply to this property type. */
export const hidesBedsBaths = (propertyType) => {
  const name = propertyTypeName(propertyType);
  return !!name && NO_BEDS_BATHS_KEYWORDS.some((kw) => name.includes(kw));
};
