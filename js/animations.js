document.addEventListener('DOMContentLoaded', () => {
    const animateOnScrollElements = document.querySelectorAll('[data-animate-on-scroll]');

    const observerOptions = {
        root: null,
        rootMargin: '0px',
        threshold: 0.1
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const animationClass = entry.target.dataset.animateOnScroll;
                entry.target.classList.add(animationClass);
                entry.target.classList.remove('animate-hidden');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    animateOnScrollElements.forEach(element => {
        element.classList.add('animate-hidden');
        observer.observe(element);
    });
});
