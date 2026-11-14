{{-- Apply the saved preference before the first stylesheet paint on all layouts. --}}
<meta name="color-scheme" content="light dark">
<script>
(() => {
    const storageKey = 'learncambodia.theme.v1';
    let choice = null;
    try { choice = window.localStorage.getItem(storageKey); } catch (_) { /* Storage may be disabled. */ }
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const dark = choice === 'dark' || (choice !== 'light' && prefersDark);
    document.documentElement.classList.toggle('dark', dark);
    document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
})();
</script>
