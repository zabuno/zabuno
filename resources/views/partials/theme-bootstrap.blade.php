<script data-zabuno-theme-bootstrap>
(function () {
    try {
        var stored = window.localStorage.getItem('zabuno-theme');
        var isDark =
            stored === 'dark' ||
            ((stored !== 'light') && window.matchMedia('(prefers-color-scheme: dark)').matches);
        var root = document.documentElement;
        root.classList.toggle('dark', isDark);
        root.setAttribute('data-theme', isDark ? 'dark' : 'light');
        root.style.colorScheme = isDark ? 'dark' : 'light';
    } catch (error) {
        // storage or matchMedia unavailable — leave the document at its default theme
    }
})();
</script>
