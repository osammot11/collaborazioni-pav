(() => {
    const board = document.getElementById('pipeline-board');
    const top = document.querySelector('[data-pipeline-scroll-top]');
    const spacer = top?.querySelector('[data-pipeline-scroll-spacer]');
    if (!board || !top || !spacer) return;

    // Keep touch/trackpad/keyboard scrolling on the cards and the top scrollbar in sync.
    const sync = (source, target) => {
        if (Math.abs(target.scrollLeft - source.scrollLeft) > 1) {
            target.scrollLeft = source.scrollLeft;
        }
    };
    const resize = () => {
        spacer.style.width = `${board.scrollWidth}px`;
        top.hidden = board.scrollWidth <= board.clientWidth + 1;
        top.scrollLeft = board.scrollLeft;
    };

    top.addEventListener('scroll', () => sync(top, board), { passive: true });
    board.addEventListener('scroll', () => sync(board, top), { passive: true });
    window.addEventListener('resize', resize, { passive: true });
    if ('ResizeObserver' in window) {
        const observer = new ResizeObserver(resize);
        observer.observe(board);
        board.querySelectorAll('.pipeline-column').forEach(column => observer.observe(column));
    }
    board.classList.add('pipeline-scroll-enhanced');
    resize();
})();
