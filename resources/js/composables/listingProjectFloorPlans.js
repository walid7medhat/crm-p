/**
 * Projects whose floor plans are offered for every area (no area filter) on the
 * listing create/edit forms — e.g. Al Reef Downtown / Al Reef Villas /The Row, whose plans
 * have no area_id.
 */
export const ALL_AREAS_FLOOR_PLAN_PROJECT_IDS = [1788, 1833, 924];

/** @param {number|string|null|undefined} projectId */
export const showsAllAreaFloorPlans = (projectId) =>
  projectId != null && ALL_AREAS_FLOOR_PLAN_PROJECT_IDS.includes(Number(projectId));
