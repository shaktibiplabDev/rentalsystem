(() => {
    const header = document.getElementById('siteHeader');
    const toggle = document.getElementById('mobileToggle');
    const navLinks = document.getElementById('mainNavLinks');

    if (toggle && navLinks) {
        toggle.addEventListener('click', () => {
            navLinks.classList.toggle('open');
        });

        navLinks.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', () => navLinks.classList.remove('open'));
        });
    }

    if (header) {
        const onScroll = () => {
            if (window.scrollY > 8) {
                header.style.boxShadow = '0 8px 22px rgba(16, 24, 40, 0.07)';
            } else {
                header.style.boxShadow = 'none';
            }
        };

        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
    }
})();
