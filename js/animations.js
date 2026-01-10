document.addEventListener('DOMContentLoaded', () => {
    const animateOnScrollElements = document.querySelectorAll('[data-animate-on-scroll]');

    const observerOptions = {
        root: null, // viewport
        rootMargin: '0px',
        threshold: 0.1 // 10% of the element must be visible
    };

    const observer = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const animationClass = entry.target.dataset.animateOnScroll;
                entry.target.classList.add(animationClass);
                entry.target.classList.remove('animate-hidden');
            } else {
                entry.target.classList.add('animate-hidden');
                const animationClass = entry.target.dataset.animateOnScroll;
                entry.target.classList.remove(animationClass);
            }
        });
    }, observerOptions);

    animateOnScrollElements.forEach(element => {
        element.classList.add('animate-hidden'); // Add initial hidden state
        observer.observe(element);
    });
});
