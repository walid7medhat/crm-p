const PAGE_SCROLL_CLASS = 'page-natural-scroll';

export function enablePageNaturalScroll() {
  document.documentElement.classList.add(PAGE_SCROLL_CLASS);
  document.body.classList.add(PAGE_SCROLL_CLASS);
}

export function disablePageNaturalScroll() {
  document.documentElement.classList.remove(PAGE_SCROLL_CLASS);
  document.body.classList.remove(PAGE_SCROLL_CLASS);
  document.body.style.overflow = '';
}

export function usePageNaturalScroll() {
  return { enablePageNaturalScroll, disablePageNaturalScroll };
}
