/**
 * Central list of listing amenities / features.
 *
 * - Frontend forms (create / edit), SearchBar filters and PropertyDetails all
 *   read from this single source of truth.
 * - Backend keeps a matching FEATURE_LABELS constant in
 *   App\Http\Resources\Listing\ListingResource so the resource maps the same keys.
 *
 * `key`      → column / JSON key used in `listings.additional_features`
 * `label`    → human-readable text shown in UI
 * `category` → used by forms to render grouped sections (optional)
 *
 * Order matters — it's the order rows render in the property detail page.
 */
export const LISTING_FEATURE_OPTIONS = [
  // Rooms
  { key: 'maid', label: "Maid's Room", category: 'rooms' },
  { key: 'storage', label: 'Storage Areas', category: 'rooms' },
  { key: 'study', label: 'Study Room', category: 'rooms' },
  { key: 'laundry', label: 'Laundry Room', category: 'rooms' },
  { key: 'driver', label: 'Driver Room', category: 'rooms' },

  // Amenities
  { key: 'swimming_pool', label: 'Swimming Pool', category: 'amenities' },
  { key: 'gym', label: 'Fully Equipped Gymnasium', category: 'amenities' },
  { key: 'kids_play_area', label: 'Kids Play Area', category: 'amenities' },
  { key: 'garden', label: 'Landscaped Gardens', category: 'amenities' },
  { key: 'bbq', label: 'BBQ Area', category: 'amenities' },
  { key: 'jogging', label: 'Jogging & Cycling Tracks', category: 'amenities' },
  { key: 'sauna', label: 'Sauna & Steam Room', category: 'amenities' },
  { key: 'jacuzzi', label: 'Jacuzzi', category: 'amenities' },

  // Community
  { key: 'community_parks', label: 'Community Parks', category: 'community' },
  { key: 'multi_purpose_courts', label: 'Multi-Purpose Courts', category: 'community' },
  { key: 'community_center', label: 'Community Center', category: 'community' },
  { key: 'pet_friendly', label: 'Pet-Friendly Community', category: 'community' },
  { key: 'family_oriented', label: 'Family-Oriented Environment', category: 'community' },

  // Convenience / nearby
  { key: 'cafes_restaurants', label: 'Cafés & Restaurants', category: 'convenience' },
  { key: 'retail_shops', label: 'Retail Shops & Supermarkets', category: 'convenience' },
  { key: 'mosque', label: 'Mosque', category: 'convenience' },
  { key: 'day_care', label: 'Day Care Center', category: 'convenience' },
  { key: 'easy_access_roads', label: 'Easy Access to Major Roads', category: 'convenience' },
  { key: 'close_to_essentials', label: 'Close to Schools, Hospitals & Shopping Malls', category: 'convenience' },

  // Interior
  { key: 'balcony', label: 'Balcony / Terrace', category: 'interior' },
  { key: 'spacious_living', label: 'Spacious Living Areas', category: 'interior' },
  { key: 'wardrobes', label: 'Built-in Wardrobes', category: 'interior' },
  { key: 'high_quality_finishes', label: 'High-Quality Finishes', category: 'interior' },
  { key: 'central_ac', label: 'Central Air Conditioning', category: 'interior' },
  { key: 'double_glazed_windows', label: 'Double-Glazed Windows', category: 'interior' },

  // Building
  { key: 'elevators', label: 'High-Speed Elevators', category: 'building' },
  { key: 'lobby', label: 'Elegant Lobby & Reception Area', category: 'building' },
  { key: 'covered_parking', label: 'Covered Parking', category: 'building' },
  { key: 'visitor_parking', label: 'Visitor Parking Available', category: 'building' },

  // Tech
  { key: 'broadband', label: 'Broadband Internet Ready', category: 'tech' },
  { key: 'satellite_tv', label: 'Satellite/Cable TV Connection', category: 'tech' },
  { key: 'intercom', label: 'Intercom System', category: 'tech' },

  // Services / security
  { key: 'security_24_7', label: '24/7 Security', category: 'services' },
  { key: 'cctv', label: 'CCTV Surveillance', category: 'services' },
  { key: 'concierge', label: 'Concierge Services', category: 'services' },
  { key: 'maintenance', label: 'Maintenance Services', category: 'services' },
  { key: 'waste_disposal', label: 'Waste Disposal Facilities', category: 'services' },
];

/** Map of key → label, useful for quick lookups. */
export const LISTING_FEATURE_LABELS = LISTING_FEATURE_OPTIONS.reduce((acc, f) => {
  acc[f.key] = f.label;
  return acc;
}, {});

/** Flat array of just the keys, e.g. for initializing form state defaults. */
export const LISTING_FEATURE_KEYS = LISTING_FEATURE_OPTIONS.map((f) => f.key);

export default LISTING_FEATURE_OPTIONS;
