/**
 * Property types that need a "Plot Size" on the listing form.
 * Keep in sync with ListingRequest::PLOT_SIZE_TYPES (backend validation).
 */
export const PLOT_SIZE_PROPERTY_TYPES = ['villa', 'townhouse', 'twinhouse', 'twin house', 'duplex'];

/** @param {object|string|null} propertyType — `{ name }` from the select, or a plain name. */
export const requiresPlotSize = (propertyType) => {
  const name = typeof propertyType === 'string' ? propertyType : propertyType?.name;
  if (!name) return false;
  return PLOT_SIZE_PROPERTY_TYPES.includes(String(name).trim().toLowerCase());
};
