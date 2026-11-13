import { icons } from './icons.js';
import { token, request } from './api.js';
import { notify, status, busy } from './ui.js';
import { element, svgIcon } from './dom.js';

export function initPhoto() {
    let pendingCommentForm = null;
    let confirmedCommentForm = null;
    const deleteCommentDialog = document.getElementById('delete-comment-dialog');
    document.addEventListener('submit', function (event) {
        const form = event.target;
        const action = form.getAttribute('data-ajax');
        if (!action) {
            return;
        }
        event.preventDefault();
        if (form.getAttribute('aria-busy') === 'true') {
            return;
        }
        if (
            action === 'delete' &&
            !window.confirm('Delete this creation? This cannot be undone.')
        ) {
            return;
        }
        if (action === 'delete-comment') {
            if (confirmedCommentForm !== form) {
                pendingCommentForm = form;
                deleteCommentDialog.showModal();
                return;
            }
            confirmedCommentForm = null;
        }
        busy(form, true);
        request(form.action, 'POST', new FormData(form), function (error, data) {
            busy(form, false);
            if (error) {
                status(form, error, true);
                return;
            }
            if (action === 'like') {
                form.action = form.action.replace(
                    /\/(like|unlike)$/,
                    data.liked ? '/unlike' : '/like',
                );
                const button = form.querySelector('button');
                button.setAttribute('aria-pressed', data.liked ? 'true' : 'false');
                if (button.closest('.card-like-form')) {
                    button.replaceChildren(svgIcon(icons.heart));
                    button.setAttribute(
                        'aria-label',
                        (data.liked ? 'Unlike' : 'Like') + ' this photo',
                    );
                } else {
                    button.querySelector('.like-count').textContent = data.count;
                }
            } else if (action === 'comment') {
                if (data.comment) {
                    const comment = element('article', 'comment');
                    const avatar = element(
                        'span',
                        'comment-avatar',
                        data.comment.username.charAt(0).toUpperCase(),
                    );
                    avatar.setAttribute('aria-hidden', 'true');
                    comment.appendChild(avatar);
                    const content = element('div');
                    content.appendChild(element('strong', '', data.comment.username));
                    content.appendChild(element('p', '', data.comment.body));
                    comment.appendChild(content);
                    const deleteForm = element('form', 'comment-delete-form');
                    deleteForm.method = 'post';
                    deleteForm.action = form.action + '/' + data.comment.id + '/delete';
                    deleteForm.setAttribute('data-ajax', 'delete-comment');
                    const csrf = element('input');
                    csrf.type = 'hidden';
                    csrf.name = 'csrf';
                    csrf.value = token();
                    deleteForm.appendChild(csrf);
                    const remove = element('button', 'comment-delete');
                    remove.type = 'submit';
                    remove.setAttribute('aria-label', 'Delete your comment');
                    remove.title = 'Delete your comment';
                    remove.appendChild(svgIcon(icons.delete));
                    deleteForm.appendChild(remove);
                    comment.appendChild(deleteForm);
                    document.getElementById('comments').appendChild(comment);
                    const empty = document.getElementById('no-comments');
                    if (empty) {
                        empty.hidden = true;
                    }
                }
                form.elements.body.value = '';
                form.elements.submission.value = data.submission;
                status(form, 'Your note is posted.', false);
            } else if (action === 'delete-comment') {
                form.closest('.comment').remove();
                const empty = document.getElementById('no-comments');
                if (empty && !document.querySelector('#comments .comment')) {
                    empty.hidden = false;
                }
                notify('Your comment has been deleted.');
            } else if (action === 'delete') {
                window.location.assign(data.redirect);
            } else {
                status(form, data.message, false);
            }
        });
    });
    if (deleteCommentDialog) {
        deleteCommentDialog.addEventListener('close', function () {
            pendingCommentForm = null;
        });
        document.getElementById('confirm-delete-comment').addEventListener('click', function () {
            const form = pendingCommentForm;
            if (!form) {
                return;
            }
            deleteCommentDialog.close();
            confirmedCommentForm = form;
            form.requestSubmit();
        });
    }
    const sharePopover = document.getElementById('share-popover');
    if (sharePopover) {
        const shareStatus = sharePopover.querySelector('.share-status');
        const shareUrl = sharePopover.getAttribute('data-url');
        const shareButton = document.querySelector('.share-button');
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
