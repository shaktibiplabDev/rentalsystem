(() => {
    const header = document.getElementById('siteHeader');
    const toggle = document.getElementById('mobileToggle');
    const navLinks = document.getElementById('mainNavLinks');
    const revealNodes = Array.from(document.querySelectorAll('[data-reveal]'));
    const heroMedia = document.querySelector('.hero-media img');

    if (toggle && navLinks) {
        toggle.addEventListener('click', () => {
            navLinks.classList.toggle('open');
        });

        navLinks.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', () => navLinks.classList.remove('open'));
        });

        document.addEventListener('click', (event) => {
            if (!navLinks.contains(event.target) && !toggle.contains(event.target)) {
                navLinks.classList.remove('open');
            }
        });
    }

    const onScroll = () => {
        if (header) {
            header.classList.toggle('scrolled', window.scrollY > 8);
        }

        if (heroMedia) {
            const y = Math.min(window.scrollY * 0.04, 14);
            heroMedia.style.transform = `translateY(${y}px) scale(1.04)`;
        }
    };

    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });

    if ('IntersectionObserver' in window && revealNodes.length > 0) {
        const io = new IntersectionObserver((entries, observer) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('revealed');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15 });

        revealNodes.forEach((node, index) => {
            node.style.setProperty('--reveal-order', String(index % 6));
            io.observe(node);
        });
    } else {
        revealNodes.forEach((node) => node.classList.add('revealed'));
    }
})();
