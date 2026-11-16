// Light and dark mode follow the device by default. The header toggle stores an
// explicit choice, which the inline script in the layout applies before first paint.
window.toggleTheme = () => {
    const root = document.documentElement;
    const isDark = root.dataset.theme
        ? root.dataset.theme === 'dark'
        : window.matchMedia('(prefers-color-scheme: dark)').matches;

    root.dataset.theme = isDark ? 'light' : 'dark';

    try {
        localStorage.setItem('theme', root.dataset.theme);
    } catch (error) {
        // Private browsing can block storage; the choice then lasts for this page only.
    }
};
