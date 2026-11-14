/** Attach the photo sharing popover and clipboard actions. */
export function initPhoto() {
    const sharePopover = document.getElementById('share-popover');
    if (sharePopover) {
        const shareStatus = sharePopover.querySelector('.share-status');
        const shareUrl = sharePopover.getAttribute('data-url');
        const shareButton = document.querySelector('.share-button');
        /** Keep the share popover beside its button and inside the viewport. */
        function positionSharePopover() {
            const anchor = shareButton.getBoundingClientRect();
            const width = Math.min(360, window.innerWidth - 36);
            const height = sharePopover.getBoundingClientRect().height;
            const left = Math.max(
                18,
                Math.min(anchor.right - width, window.innerWidth - width - 18),
            );
            let top = anchor.bottom + 10;
            if (top + height > window.innerHeight - 18 && anchor.top - height - 10 >= 18) {
                top = anchor.top - height - 10;
            }
            sharePopover.style.left = left + 'px';
            sharePopover.style.top =
                Math.max(18, Math.min(top, window.innerHeight - height - 18)) + 'px';
        }
        sharePopover.addEventListener('beforetoggle', function (event) {
            if (event.newState === 'open') {
                shareStatus.textContent = '';
            }
        });
        sharePopover.addEventListener('toggle', function (event) {
            const open = event.newState === 'open';
            shareButton.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) {
                positionSharePopover();
            }
        });
        window.addEventListener('resize', function () {
            if (sharePopover.matches(':popover-open')) {
                positionSharePopover();
            }
        });
        document.addEventListener(
            'scroll',
            function () {
                if (sharePopover.matches(':popover-open')) {
                    positionSharePopover();
                }
            },
            true,
        );
        /** Copy a link or embed snippet and report the clipboard result. */
        function copyShareText(value, message) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(value).then(
                    function () {
                        shareStatus.textContent = message;
                        positionSharePopover();
                    },
                    function () {
                        shareStatus.textContent = 'Could not copy. Please try again.';
                    },
                );
            } else {
                shareStatus.textContent = 'Clipboard is unavailable in this browser.';
            }
        }
        document.getElementById('copy-moment-link').addEventListener('click', function () {
            copyShareText(shareUrl, 'Link copied. Ready to pass along.');
        });
        document.getElementById('copy-moment-embed').addEventListener('click', function () {
            copyShareText(
                sharePopover.getAttribute('data-embed'),
                'Embed code copied. Paste it into your website.',
            );
        });
    }
}
