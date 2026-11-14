import { icons } from './icons.js';
import { token, request } from './api.js';
import { notify } from './ui.js';
import { element, svgIcon } from './dom.js';

/** Build a gallery card or a compact editor-history thumbnail. */
export function createPhotoCard(photo, history) {
    const link = element('a', history ? 'history-item' : 'photo-link');
    link.href = '/photos/' + photo.id;
    const image = element('img');
    image.src = '/media/' + photo.id + '/thumb';
    image.alt = 'A creation by ' + (photo.username || 'you');
    image.width = 320;
    image.height = 240;
    link.appendChild(image);
    if (history) {
        return link;
    }
    const article = element('article', 'photo-card');
    article.appendChild(link);
    if (photo.kind === 'gif') {
        link.appendChild(element('span', 'gif-badge', 'GIF'));
    }
    const meta = element('div', 'card-meta');
    meta.appendChild(element('span', 'author', photo.username));
    if (document.body.classList.contains('authenticated')) {
        const likeForm = element('form', 'card-like-form');
        likeForm.method = 'post';
        likeForm.action = '/photos/' + photo.id + '/' + (photo.liked ? 'unlike' : 'like');
        likeForm.setAttribute('data-ajax', 'like');
        const csrf = element('input');
        csrf.type = 'hidden';
        csrf.name = 'csrf';
        csrf.value = token();
        likeForm.appendChild(csrf);
        const likeButton = element('button');
        likeButton.type = 'submit';
        likeButton.setAttribute('aria-pressed', photo.liked ? 'true' : 'false');
        likeButton.setAttribute('aria-label', (photo.liked ? 'Unlike' : 'Like') + ' this photo');
        likeButton.appendChild(svgIcon(icons.heart));
        likeForm.appendChild(likeButton);
        article.appendChild(likeForm);
    } else {
        const loginLike = element('a', 'card-like-form card-like-link');
        loginLike.href = '/login';
        loginLike.setAttribute('aria-label', 'Sign in to like this photo');
        loginLike.appendChild(svgIcon(icons.heart));
        article.appendChild(loginLike);
    }
    const counts = element('a', 'card-counts');
    counts.href = link.href;
    const likeCount = element('span', 'count-item');
    likeCount.appendChild(svgIcon(icons.heart));
    likeCount.appendChild(document.createTextNode(photo.likes));
    const commentCount = element('span', 'count-item');
    commentCount.appendChild(svgIcon(icons.comment));
    commentCount.appendChild(document.createTextNode(photo.comments));
    counts.appendChild(likeCount);
    counts.appendChild(commentCount);
    meta.appendChild(counts);
    article.appendChild(meta);
    return article;
}

/** Attach cursor-based loading to gallery and personal-history controls. */
export function initGallery() {
    const feeds = document.querySelectorAll('.load-more');
    feeds.forEach(function (button) {
        /** Fetch the next batch, append unseen photos, and advance the cursor. */
        function load() {
            if (button.disabled || button.hidden) {
                return;
            }
            button.disabled = true;
            const cursor = button.getAttribute('data-cursor');
            request(
                button.getAttribute('data-feed') + '?before=' + encodeURIComponent(cursor),
                'GET',
                null,
                function (error, data) {
                    button.disabled = false;
                    if (error) {
                        notify(error);
                        return;
                    }
                    const target = document.getElementById(button.getAttribute('data-target'));
                    data.photos.forEach(function (photo) {
                        if (!target.querySelector('a[href="/photos/' + photo.id + '"]')) {
                            target.appendChild(
                                createPhotoCard(photo, target.id === 'history-grid'),
                            );
                        }
                    });
                    if (!data.cursor) {
                        button.hidden = true;
                    } else {
                        button.setAttribute('data-cursor', data.cursor);
                    }
                },
            );
        }
        button.addEventListener('click', load);
        // The gallery can load automatically; personal history stays user-controlled.
        if (button.getAttribute('data-target') === 'gallery-grid') {
            if (typeof window.IntersectionObserver === 'function') {
                const observer = new IntersectionObserver(
                    function (entries) {
                        if (entries[0].isIntersecting) {
                            load();
                        }
                    },
                    { rootMargin: '100px' },
                );
                observer.observe(button);
            } else {
                let ticking = false;
                window.addEventListener('scroll', function () {
                    if (ticking) {
                        return;
                    }
                    ticking = true;
                    window.setTimeout(function () {
                        ticking = false;
                        if (button.getBoundingClientRect().top < window.innerHeight + 100) {
                            load();
                        }
                    }, 150);
                });
            }
        }
    });
}
