/**
 * Dismisses the server-rendered splash screen (see resources/views/app.blade.php)
 * once React has painted the first page. Fades out, then removes the node so it
 * can never swallow a click.
 */
export function dismissSplash(): void {
    const splash = document.getElementById('app-splash');

    if (splash === null) {
        return;
    }

    // Wait for the frame after mount, so the fade starts over real content.
    requestAnimationFrame(() => {
        splash.classList.add('is-ready');
        window.setTimeout(() => splash.remove(), 300);
    });
}
