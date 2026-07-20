import { nextTick, ref } from 'vue';

/**
 * Shared state for a scroll-snap image carousel: the active index derives
 * from the scroll position, so swipe gestures and arrow buttons stay in
 * sync. Stepping clamps to the ends; there is no wrap-around.
 */
export function useSnapCarousel(count: () => number) {
    const index = ref(0);
    const track = ref<HTMLElement | null>(null);

    function onTrackScroll() {
        const element = track.value;

        if (!element || element.clientWidth === 0) {
            return;
        }

        index.value = Math.min(
            Math.round(element.scrollLeft / element.clientWidth),
            Math.max(count() - 1, 0),
        );
    }

    function scrollToIndex(target: number, behavior: ScrollBehavior) {
        track.value?.scrollTo({
            left: target * (track.value?.clientWidth ?? 0),
            behavior,
        });
    }

    function step(delta: number) {
        const target = Math.min(
            Math.max(index.value + delta, 0),
            Math.max(count() - 1, 0),
        );
        const behavior = window.matchMedia('(prefers-reduced-motion: reduce)')
            .matches
            ? 'auto'
            : 'smooth';

        scrollToIndex(target, behavior);
    }

    function reset(target = 0) {
        index.value = target;

        void nextTick(() => {
            scrollToIndex(target, 'auto');
        });
    }

    return { index, track, onTrackScroll, step, reset };
}
