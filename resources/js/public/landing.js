/**
 * AGRIGUARD Landing page: scroll-reveal only (navbar handled by navbar.js)
 */
document.addEventListener('DOMContentLoaded', function () {
    const revealEls = document.querySelectorAll('.scroll-reveal');
    if (revealEls.length) {
        const observer = new IntersectionObserver(
            function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('revealed');
                    }
                });
            },
            { rootMargin: '0px 0px -60px 0px', threshold: 0.1 }
        );
        revealEls.forEach(function (el) {
            observer.observe(el);
        });
    }
});
