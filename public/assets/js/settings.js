/** Highlight the settings section selected in the sidebar. */
export function initSettings() {
    document.querySelectorAll('.settings-sidebar nav a').forEach(function (link) {
        link.addEventListener('click', function () {
            document.querySelectorAll('.settings-sidebar nav a').forEach(function (item) {
                item.removeAttribute('aria-current');
            });
            link.setAttribute('aria-current', 'location');
        });
    });
}
