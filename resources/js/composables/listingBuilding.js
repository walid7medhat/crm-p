export const isBuildingType = (type) => {
  const name = typeof type === 'object' && type !== null ? type.name : type;
  return String(name ?? '').trim().toLowerCase() === 'building';
};