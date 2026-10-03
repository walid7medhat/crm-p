/**
 * True for a normal left click. Ctrl/Cmd/Shift/Alt or middle clicks should keep
 * the browser's default link behaviour (open in a new tab / window).
 */
export function isPlainLeftClick(event) {
  if (!event) return true;
  if (event.defaultPrevented) return false;
  if (event.button !== undefined && event.button !== 0) return false;
  return !(event.ctrlKey || event.metaKey || event.shiftKey || event.altKey);
}
