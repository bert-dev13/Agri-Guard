/**
 * AGRIGUARD Public navbar: Lucide icons, mobile drawer, scroll shadow, smooth in-page nav
 */
import { createIcons, icons } from 'lucide';

document.addEventListener('DOMContentLoaded', function () {
    const navbar = document.getElementById('navbar');
    if (!navbar || navbar.classList.contains('user-navbar') || !navbar.classList.contains('navbar-modern')) {
        return;
    }

    try {
        /* Whole document: landing + auth guest pages use Lucide outside the header too. */
        createIcons({ icons });
    } catch (e) {
        console.warn('Lucide icons (public shell):', e);
    }

    const mobileBtn = document.getElementById('public-navbar-menu-btn');
    const mobilePanel = document.getElementById('public-navbar-mobile-panel');

    function setMobileMenuOpen(open) {
        if (!mobileBtn || !mobilePanel) return;
        navbar.classList.toggle('menu-open', open);
        mobileBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        mobileBtn.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
        mobilePanel.setAttribute('aria-hidden', open ? 'false' : 'true');
        if (open) {
            mobilePanel.removeAttribute('inert');
        } else {
            mobilePanel.setAttribute('inert', '');
        }
        document.body.classList.toggle('navbar-mobile-lock', open);
    }

    function toggleMobileMenu() {
        const open = !navbar.classList.contains('menu-open');
        setMobileMenuOpen(open);
        if (open) {
            const first = mobilePanel.querySelector('a[href]');
            if (first) {
                window.requestAnimationFrame(function () {
                    first.focus();
                });
            }
        } else {
            mobileBtn.focus();
        }
    }

    if (mobileBtn && mobilePanel) {
        mobileBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            toggleMobileMenu();
        });

        mobilePanel.querySelectorAll('a[href]').forEach(function (link) {
            link.addEventListener('click', function () {
                setMobileMenuOpen(false);
            });
        });

        document.addEventListener(
            'click',
            function (e) {
                if (!navbar.classList.contains('menu-open')) return;
                if (navbar.contains(e.target)) return;
                setMobileMenuOpen(false);
            },
            true
        );

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && navbar.classList.contains('menu-open')) {
                setMobileMenuOpen(false);
                mobileBtn.focus();
            }
        });

        window.addEventListener('resize', function () {
            if (window.matchMedia('(min-width: 1024px)').matches && navbar.classList.contains('menu-open')) {
                setMobileMenuOpen(false);
            }
        });
    }

    function onScroll() {
        navbar.classList.toggle('scrolled', window.scrollY > 12);
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    const desktopLinks = document.querySelectorAll('.nav-link-modern');

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
        const navEl = document.getElementById('navbar');
        const navOffset = navEl ? navEl.getBoundingClientRect().height + 16 : 96;
        const offset = window.scrollY + rect.top - navOffset;
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

    const sectionMap = {};
    desktopLinks.forEach((link) => {
        const sectionId = getSectionIdFromHref(link.getAttribute('href'));
        if (sectionId) {
            sectionMap[sectionId] = sectionMap[sectionId] || [];
            sectionMap[sectionId].push(link);
        }
    });

    const sections = Object.keys(sectionMap)
        .map((id) => document.getElementById(id))
        .filter(Boolean);

    function setActiveLinkBySectionId(id) {
        desktopLinks.forEach((link) => link.classList.remove('is-active'));

        if (!id || !sectionMap[id]) return;

        (sectionMap[id] || []).forEach((link) => link.classList.add('is-active'));
    }

    function setActiveLinkByLocation() {
        const scrollPos = window.scrollY;
        const navEl = document.getElementById('navbar');
        const navBuffer = navEl ? navEl.getBoundingClientRect().height + 24 : 120;
        let chosenId = null;
        let bestTop = -Infinity;

        sections.forEach((section) => {
            const rect = section.getBoundingClientRect();
            const absoluteTop = rect.top + window.scrollY;
            const reference = scrollPos + navBuffer;
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
