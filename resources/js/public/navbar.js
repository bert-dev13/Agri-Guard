/**
 * AGRIGUARD Public navbar: scroll shadow, mobile menu (landing + login + register)
 */
document.addEventListener('DOMContentLoaded', function () {
    const navbar = document.getElementById('navbar');
    if (navbar && navbar.classList.contains('user-navbar')) {
        return;
    }
    if (navbar) {
        function onScroll() {
            navbar.classList.toggle('scrolled', window.scrollY > 12);
        }
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }

    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const mobileMenu = document.getElementById('mobile-menu');
    const menuIcon = document.getElementById('menu-icon');
    const closeIcon = document.getElementById('close-icon');
    if (mobileMenuBtn && navbar && mobileMenu && menuIcon && closeIcon) {
        function setMenuOpen(open) {
            if (open) {
                navbar.classList.add('menu-open');
                menuIcon.classList.add('hidden');
                closeIcon.classList.remove('hidden');
            } else {
                navbar.classList.remove('menu-open');
                menuIcon.classList.remove('hidden');
                closeIcon.classList.add('hidden');
            }
        }
        mobileMenuBtn.addEventListener('click', function () {
            setMenuOpen(!navbar.classList.contains('menu-open'));
        });
        document.querySelectorAll('.nav-mobile-link').forEach(function (link) {
            link.addEventListener('click', function () {
                setMenuOpen(false);
            });
        });
    }

    // Smooth scroll + active link highlighting for in-page sections
    const desktopLinks = document.querySelectorAll('.nav-link-modern');
    const mobileLinks = document.querySelectorAll('.nav-mobile-link');

    function getSectionIdFromHref(href) {
        if (!href) return null;
        const hashIndex = href.indexOf('#');
        if (hashIndex === -1) return null;
        return href.substring(hashIndex + 1);
    }

    function scrollToSection(event, link) {
        const sectionId = getSectionIdFromHref(link.getAttribute('href'));
        if (!sectionId) return;
        const section = document.getElementById(sectionId);
        if (!section) return;
        event.preventDefault();
        const rect = section.getBoundingClientRect();
        const offset = window.scrollY + rect.top - 96; // approximate navbar height
        window.scrollTo({
            top: offset,
            behavior: 'smooth',
        });
        history.replaceState(null, '', `#${sectionId}`);
    }

    desktopLinks.forEach((link) => {
        if (getSectionIdFromHref(link.getAttribute('href'))) {
            link.addEventListener('click', (event) => scrollToSection(event, link));
        }
    });

    mobileLinks.forEach((link) => {
        if (getSectionIdFromHref(link.getAttribute('href'))) {
            link.addEventListener('click', (event) => scrollToSection(event, link));
        }
    });

    const sectionMap = {};
    desktopLinks.forEach((link) => {
        const sectionId = getSectionIdFromHref(link.getAttribute('href'));
        if (sectionId) {
            sectionMap[sectionId] = sectionMap[sectionId] || { desktop: [], mobile: [] };
            sectionMap[sectionId].desktop.push(link);
        }
    });
    mobileLinks.forEach((link) => {
        const sectionId = getSectionIdFromHref(link.getAttribute('href'));
        if (sectionId) {
            sectionMap[sectionId] = sectionMap[sectionId] || { desktop: [], mobile: [] };
            sectionMap[sectionId].mobile.push(link);
        }
    });

    const sections = Object.keys(sectionMap)
        .map((id) => document.getElementById(id))
        .filter(Boolean);

    function setActiveLinkBySectionId(id) {
        desktopLinks.forEach((link) => link.classList.remove('is-active'));
        mobileLinks.forEach((link) => link.classList.remove('is-active'));

        if (!id || !sectionMap[id]) return;

        (sectionMap[id].desktop || []).forEach((link) => link.classList.add('is-active'));
        (sectionMap[id].mobile || []).forEach((link) => link.classList.add('is-active'));
    }

    function setActiveLinkByLocation() {
        const scrollPos = window.scrollY;
        let chosenId = null;
        let bestTop = -Infinity;

        sections.forEach((section) => {
            const rect = section.getBoundingClientRect();
            const absoluteTop = rect.top + window.scrollY;
            const reference = scrollPos + 120; // navbar height + buffer
            if (reference >= absoluteTop && absoluteTop > bestTop) {
                bestTop = absoluteTop;
                chosenId = section.id;
            }
        });

        if (!chosenId && scrollPos < 80) {
            chosenId = 'home';
        }

        setActiveLinkBySectionId(chosenId);
    }

    if (sections.length) {
        window.addEventListener('scroll', setActiveLinkByLocation, { passive: true });
        setActiveLinkByLocation();
    }
});
