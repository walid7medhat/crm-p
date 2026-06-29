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
  // Layout / Position
  { key: 'corner_unit', label: 'Corner Unit', category: 'layout' },
  { key: 'end_unit', label: 'End Unit', category: 'layout' },
  { key: 'mid_unit', label: 'Mid Unit', category: 'layout' },
  { key: 'double_row', label: 'Double Row', category: 'layout' },
  { key: 'single_row', label: 'Single Row', category: 'layout' },

  // Floor
  { key: 'mid_floor', label: 'Mid Floor', category: 'floor' },
  { key: 'high_floor', label: 'High Floor', category: 'floor' },
  { key: 'low_floor', label: 'Low Floor', category: 'floor' },
  { key: 'ground_floor', label: 'Ground Floor', category: 'floor' },

  // Views
  { key: 'sea_view', label: 'Sea View', category: 'view' },
  { key: 'partial_sea_view', label: 'Partial Sea View', category: 'view' },
  { key: 'canal_view', label: 'Canal View', category: 'view' },
  { key: 'partial_canal_view', label: 'Partial Canal View', category: 'view' },
  { key: 'museum_view', label: 'Museum View', category: 'view' },
  { key: 'park_view', label: 'Park View', category: 'view' },
  { key: 'partial_park_view', label: 'Partial Park View', category: 'view' },
  { key: 'city_view', label: 'City View', category: 'view' },
  { key: 'community_view', label: 'Community View', category: 'view' },
  { key: 'road_view', label: 'Road View', category: 'view' },
  { key: 'mall_view', label: 'Mall View', category: 'view' },
  { key: 'mangrove_view', label: 'Mangrove View', category: 'view' },
  { key: 'university_view', label: 'University View', category: 'view' },
  { key: 'pool_view', label: 'Pool View', category: 'view' },
  { key: 'fountain_view', label: 'Fountain View', category: 'view' },

  // Rooms
  { key: 'maid_room', label: "Maid Room", category: 'rooms' },
  { key: 'guest_room', label: 'Guest Room', category: 'rooms' },
  { key: 'laundry_room', label: 'Laundry Room', category: 'rooms' },
  { key: 'study_room', label: 'Study Room', category: 'rooms' },
  { key: 'utility_room', label: 'Utility Room', category: 'rooms' },
  { key: 'storage_room', label: 'Storage Room', category: 'rooms' },
  { key: 'powder_room', label: 'Powder Room', category: 'rooms' },
  { key: 'driver_room', label: 'Driver Room', category: 'rooms' },
  { key: 'majles', label: 'Majles', category: 'rooms' },
  { key: 'dressing_room', label: 'Dressing Room', category: 'rooms' },

  // Outdoor / Spaces
  { key: 'balcony', label: 'Balcony', category: 'spaces' },
  { key: 'terrace', label: 'Terrace', category: 'spaces' },
  { key: 'basement', label: 'Basement', category: 'spaces' },
  { key: 'pod', label: 'Pod', category: 'spaces' },

  // Kitchen
  { key: 'open_kitchen', label: 'Open Kitchen', category: 'kitchen' },
  { key: 'semi_closed_kitchen', label: 'Semi Closed Kitchen', category: 'kitchen' },
  { key: 'closed_kitchen', label: 'Closed Kitchen', category: 'kitchen' },
  { key: 'pantry', label: 'Pantry', category: 'kitchen' },
  { key: 'kitchen_appliances', label: 'Kitchen Appliances', category: 'kitchen' },

  // Furnishing
  { key: 'furnished', label: 'Furnished', category: 'furnishing' },
  { key: 'fully_furnished', label: 'Fully Furnished', category: 'furnishing' },
  { key: 'semi_furnished', label: 'Semi Furnished', category: 'furnishing' },

  // Extras
  { key: 'private_pool', label: 'Private Pool', category: 'extras' },
  { key: 'private_gym', label: 'Private Gym', category: 'extras' },
];

/** Map of key → label, useful for quick lookups. */
export const LISTING_FEATURE_LABELS = LISTING_FEATURE_OPTIONS.reduce((acc, f) => {
  acc[f.key] = f.label;
  return acc;
}, {});

/** Flat array of just the keys, e.g. for initializing form state defaults. */
export const LISTING_FEATURE_KEYS = LISTING_FEATURE_OPTIONS.map((f) => f.key);

export default LISTING_FEATURE_OPTIONS;
