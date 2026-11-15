<script>
    if ('serviceWorker' in navigator && window.isSecureContext) {
        window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {}));
    }

    // Every page opens at the top: the browser must not restore an old scroll position
    // on reload / SPA navigation (long pages like Profil Hotel looked "shifted down").
    (() => {
        try { history.scrollRestoration = 'manual'; } catch (e) {}
        const toTop = () => { if (!location.hash) window.scrollTo({ top: 0, left: 0, behavior: 'instant' }); };
        document.addEventListener('livewire:navigated', toTop);
        window.addEventListener('pageshow', toTop);
        if (document.readyState !== 'loading') toTop(); else document.addEventListener('DOMContentLoaded', toTop);
    })();
</script>
