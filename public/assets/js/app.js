(function () {
    'use strict';
    var toastTimer;
    function notify(message) {
        var box = document.getElementById('toast');
        box.textContent = message;
        box.hidden = false;
        window.clearTimeout(toastTimer);
        toastTimer = window.setTimeout(function () { box.hidden = true; }, 5500);
    }
    var initialToast = document.getElementById('toast');
    if (initialToast && !initialToast.hidden) { notify(initialToast.textContent); }
    function token() { return document.querySelector('meta[name="csrf-token"]').getAttribute('content'); }
    function randomKey() {
        var bytes = new Uint8Array(16);
        window.crypto.getRandomValues(bytes);
        return Array.prototype.map.call(bytes, function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
    }
    function request(url, method, body, done) {
        var xhr = new XMLHttpRequest();
        xhr.open(method, url, true);
        xhr.setRequestHeader('Accept', 'application/json');
        if (method === 'POST') { xhr.setRequestHeader('X-CSRF-Token', token()); }
        xhr.timeout = 45000;
        xhr.onload = function () {
            var data;
            try { data = JSON.parse(xhr.responseText); } catch (ignore) { done('The server returned an unexpected response. Please try again.'); return; }
            if (!data.ok) { done(data.error ? data.error.message : 'Something went wrong.'); return; }
            done(null, data.data);
        };
        xhr.onerror = xhr.ontimeout = function () { done('Connection interrupted. Your input is safe; please try again.'); };
        xhr.send(body);
    }
    function status(form, message, error) {
        var box = form.querySelector('.form-status');
        if (!box) { notify(message); return; }
        box.textContent = message;
        box.className = 'form-status' + (error ? ' error' : '');
    }
    function busy(form, on) {
        form.classList[on ? 'add' : 'remove']('is-loading');
        form.setAttribute('aria-busy', on ? 'true' : 'false');
    }
    function element(tag, className, text) {
        var node = document.createElement(tag);
        if (className) { node.className = className; }
        if (typeof text !== 'undefined') { node.textContent = text; }
        return node;
    }
    function svgIcon(path, className) {
        var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('viewBox', '0 0 24 24'); svg.setAttribute('aria-hidden', 'true');
        if (className) { svg.setAttribute('class', className); }
        var shape = document.createElementNS('http://www.w3.org/2000/svg', 'path'); shape.setAttribute('d', path); svg.appendChild(shape); return svg;
    }
    var heartPath = 'M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21l7.8-7.5 1.1-1.1a5.5 5.5 0 0 0-.1-7.8Z';
    function card(photo, history) {
        var link = element('a', history ? 'history-item' : 'photo-link');
        link.href = '/photos/' + photo.id;
        var image = element('img');
        image.src = '/media/' + photo.id + '/thumb';
        image.alt = 'A creation by ' + (photo.username || 'you');
        image.width = 320; image.height = 240;
        link.appendChild(image);
        if (history) { return link; }
        var article = element('article', 'photo-card');
        article.appendChild(link);
        if (photo.kind === 'gif') { link.appendChild(element('span', 'gif-badge', 'GIF')); }
        var meta = element('div', 'card-meta');
        meta.appendChild(element('span', 'author', photo.username));
        if (document.body.classList.contains('authenticated')) {
            var likeForm = element('form', 'card-like-form');
            likeForm.method = 'post'; likeForm.action = '/photos/' + photo.id + '/' + (photo.liked ? 'unlike' : 'like');
            likeForm.setAttribute('data-ajax', 'like');
            var csrf = element('input'); csrf.type = 'hidden'; csrf.name = 'csrf'; csrf.value = token(); likeForm.appendChild(csrf);
            var likeButton = element('button'); likeButton.type = 'submit'; likeButton.setAttribute('aria-pressed', photo.liked ? 'true' : 'false'); likeButton.setAttribute('aria-label', (photo.liked ? 'Unlike' : 'Like') + ' this photo'); likeButton.appendChild(svgIcon(heartPath)); likeForm.appendChild(likeButton); article.appendChild(likeForm);
        } else {
            var loginLike = element('a', 'card-like-form card-like-link'); loginLike.href = '/login'; loginLike.setAttribute('aria-label', 'Sign in to like this photo'); loginLike.appendChild(svgIcon(heartPath)); article.appendChild(loginLike);
        }
        var counts = element('a', 'card-counts');
        counts.href = link.href;
        var likeCount = element('span', 'count-item'); likeCount.appendChild(svgIcon(heartPath)); likeCount.appendChild(document.createTextNode(photo.likes));
        var commentCount = element('span', 'count-item'); commentCount.appendChild(svgIcon('M20 11.5a7.5 7.5 0 0 1-7.5 7.5H6l-3 2v-6.5A7.5 7.5 0 0 1 10.5 7H13a7 7 0 0 1 7 4.5Z')); commentCount.appendChild(document.createTextNode(photo.comments));
        counts.appendChild(likeCount); counts.appendChild(commentCount);
        meta.appendChild(counts); article.appendChild(meta);
        return article;
    }
    var pendingCommentForm = null;
    var confirmedCommentForm = null;
    var deleteCommentDialog = document.getElementById('delete-comment-dialog');
    document.addEventListener('submit', function (event) {
        var form = event.target;
        var action = form.getAttribute('data-ajax');
        if (!action) { return; }
        event.preventDefault();
        if (form.getAttribute('aria-busy') === 'true') { return; }
        if (action === 'delete' && !window.confirm('Delete this creation? This cannot be undone.')) { return; }
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
            if (error) { status(form, error, true); return; }
            if (action === 'like') {
                form.action = form.action.replace(/\/(like|unlike)$/, data.liked ? '/unlike' : '/like');
                var button = form.querySelector('button');
                button.setAttribute('aria-pressed', data.liked ? 'true' : 'false');
                if (button.closest('.card-like-form')) {
                    button.replaceChildren(svgIcon(heartPath));
                    button.setAttribute('aria-label', (data.liked ? 'Unlike' : 'Like') + ' this photo');
                } else { button.querySelector('.like-count').textContent = data.count; }
            } else if (action === 'comment') {
                if (data.comment) {
                    var comment = element('article', 'comment');
                    var avatar = element('span', 'comment-avatar', data.comment.username.charAt(0).toUpperCase());
                    avatar.setAttribute('aria-hidden', 'true');
                    comment.appendChild(avatar);
                    var content = element('div');
                    content.appendChild(element('strong', '', data.comment.username));
                    content.appendChild(element('p', '', data.comment.body));
                    comment.appendChild(content);
                    var deleteForm = element('form', 'comment-delete-form');
                    deleteForm.method = 'post';
                    deleteForm.action = form.action + '/' + data.comment.id + '/delete';
                    deleteForm.setAttribute('data-ajax', 'delete-comment');
                    var csrf = element('input'); csrf.type = 'hidden'; csrf.name = 'csrf'; csrf.value = token();
                    deleteForm.appendChild(csrf);
                    var remove = element('button', 'comment-delete'); remove.type = 'submit';
                    remove.setAttribute('aria-label', 'Delete your comment'); remove.title = 'Delete your comment';
                    remove.appendChild(svgIcon('M4 7h16M10 11v6M14 11v6M6 7l1 12a1 1 0 001 1h8a1 1 0 001-1l1-12M9 7V4h6v3'));
                    deleteForm.appendChild(remove); comment.appendChild(deleteForm);
                    document.getElementById('comments').appendChild(comment);
                    var empty = document.getElementById('no-comments');
                    if (empty) { empty.hidden = true; }
                }
                form.elements.body.value = '';
                form.elements.submission.value = data.submission;
                status(form, 'Your note is posted.', false);
            } else if (action === 'delete-comment') {
                form.closest('.comment').remove();
                var empty = document.getElementById('no-comments');
                if (empty && !document.querySelector('#comments .comment')) { empty.hidden = false; }
                notify('Your comment has been deleted.');
            } else if (action === 'delete') { window.location.assign(data.redirect); }
            else { status(form, data.message, false); }
        });
    });
    document.addEventListener('click', function (event) {
        Array.prototype.forEach.call(document.querySelectorAll('.moment-more[open]'), function (menu) {
            if (!menu.contains(event.target)) { menu.open = false; }
        });
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            Array.prototype.forEach.call(document.querySelectorAll('.moment-more[open]'), function (menu) {
                menu.open = false; menu.querySelector('summary').focus();
            });
        }
    });
    document.addEventListener('click', function (event) {
        var toggle = event.target.closest('.password-visibility');
        if (!toggle) { return; }
        var input = toggle.parentElement.querySelector('input');
        if (!input) { return; }
        var showing = input.type === 'password';
        input.type = showing ? 'text' : 'password';
        toggle.setAttribute('aria-label', showing ? 'Hide password' : 'Show password');
    });
    var authRules = document.querySelector('.auth-password-rules');
    if (authRules) {
        var authForm = authRules.closest('form');
        var authPassword = authForm.elements.password;
        var authConfirmation = authForm.elements.confirmation;
        function checkAuthPassword() {
            var value = authPassword.value;
            var length = Array.from(value).length;
            var rules = { length: length >= 15 && length <= 128, letter: /[A-Za-z]/.test(value), number: /[0-9]/.test(value) };
            Array.prototype.forEach.call(authRules.querySelectorAll('[data-rule]'), function (rule) {
                rule.classList.toggle('is-valid', rules[rule.getAttribute('data-rule')]);
            });
            authConfirmation.setCustomValidity(authConfirmation.value && authConfirmation.value !== value ? 'Passwords must match.' : '');
            authForm.querySelector('button[type="submit"]').disabled = !(rules.length && rules.letter && rules.number && value === authConfirmation.value && authForm.checkValidity());
        }
        authForm.addEventListener('input', checkAuthPassword);
        authForm.addEventListener('change', checkAuthPassword);
        window.addEventListener('pageshow', checkAuthPassword);
        checkAuthPassword();
    }
    document.querySelectorAll('.settings-sidebar nav a').forEach(function (link) {
        link.addEventListener('click', function () {
            document.querySelectorAll('.settings-sidebar nav a').forEach(function (item) { item.removeAttribute('aria-current'); });
            link.setAttribute('aria-current', 'location');
        });
    });
    var feeds = document.querySelectorAll('.load-more');
    Array.prototype.forEach.call(feeds, function (button) {
        function load() {
            if (button.disabled || button.hidden) { return; }
            button.disabled = true;
            var cursor = button.getAttribute('data-cursor');
            request(button.getAttribute('data-feed') + '?before=' + encodeURIComponent(cursor), 'GET', null, function (error, data) {
                button.disabled = false;
                if (error) { notify(error); return; }
                var target = document.getElementById(button.getAttribute('data-target'));
                data.photos.forEach(function (photo) {
                    if (!target.querySelector('a[href="/photos/' + photo.id + '"]')) { target.appendChild(card(photo, target.id === 'history-grid')); }
                });
                if (!data.cursor) { button.hidden = true; }
                else { button.setAttribute('data-cursor', data.cursor); }
            });
        }
        button.addEventListener('click', load);
        // The gallery can load automatically; personal history stays user-controlled.
        if (button.getAttribute('data-target') === 'gallery-grid') {
            if (typeof window.IntersectionObserver === 'function') {
                var observer = new IntersectionObserver(function (entries) { if (entries[0].isIntersecting) { load(); } }, { rootMargin: '100px' });
                observer.observe(button);
            } else {
                var ticking = false;
                window.addEventListener('scroll', function () {
                    if (ticking) { return; }
                    ticking = true;
                    window.setTimeout(function () {
                        ticking = false;
                        if (button.getBoundingClientRect().top < window.innerHeight + 100) { load(); }
                    }, 150);
                });
            }
        }
    });
    Array.prototype.forEach.call(document.querySelectorAll('.moment-dialog'), function (dialog) {
        Array.prototype.forEach.call(dialog.querySelectorAll('[data-close-dialog]'), function (button) {
            button.addEventListener('click', function () { dialog.close(); });
        });
        dialog.addEventListener('click', function (event) {
            if (event.target !== dialog) { return; }
            var bounds = dialog.getBoundingClientRect();
            if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) { dialog.close(); }
        });
    });
    if (deleteCommentDialog) {
        deleteCommentDialog.addEventListener('close', function () { pendingCommentForm = null; });
        document.getElementById('confirm-delete-comment').addEventListener('click', function () {
            var form = pendingCommentForm;
            if (!form) { return; }
            deleteCommentDialog.close();
            confirmedCommentForm = form;
            form.requestSubmit();
        });
    }
    var sharePopover = document.getElementById('share-popover');
    if (sharePopover) {
        var shareStatus = sharePopover.querySelector('.share-status');
        var shareUrl = sharePopover.getAttribute('data-url');
        var shareButton = document.querySelector('.share-button');
        function positionSharePopover() {
            var anchor = shareButton.getBoundingClientRect();
            var width = Math.min(360, window.innerWidth - 36);
            var height = sharePopover.getBoundingClientRect().height;
            var left = Math.max(18, Math.min(anchor.right - width, window.innerWidth - width - 18));
            var top = anchor.bottom + 10;
            if (top + height > window.innerHeight - 18 && anchor.top - height - 10 >= 18) {
                top = anchor.top - height - 10;
            }
            sharePopover.style.left = left + 'px';
            sharePopover.style.top = Math.max(18, Math.min(top, window.innerHeight - height - 18)) + 'px';
        }
        sharePopover.addEventListener('beforetoggle', function (event) {
            if (event.newState === 'open') { shareStatus.textContent = ''; }
        });
        sharePopover.addEventListener('toggle', function (event) {
            var open = event.newState === 'open';
            shareButton.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) { positionSharePopover(); }
        });
        window.addEventListener('resize', function () {
            if (sharePopover.matches(':popover-open')) { positionSharePopover(); }
        });
        document.addEventListener('scroll', function () {
            if (sharePopover.matches(':popover-open')) { positionSharePopover(); }
        }, true);
        function copyShareText(value, message) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(value).then(function () {
                    shareStatus.textContent = message;
                    positionSharePopover();
                }, function () { shareStatus.textContent = 'Could not copy. Please try again.'; });
            } else { shareStatus.textContent = 'Clipboard is unavailable in this browser.'; }
        }
        document.getElementById('copy-moment-link').addEventListener('click', function () {
            copyShareText(shareUrl, 'Link copied. Ready to pass along.');
        });
        document.getElementById('copy-moment-embed').addEventListener('click', function () {
            copyShareText(sharePopover.getAttribute('data-embed'), 'Embed code copied. Paste it into your website.');
        });
    }
    var back = document.getElementById('go-back');
    if (back) { back.addEventListener('click', function () { if (window.history.length > 1) { window.history.back(); } else { window.location.assign('/'); } }); }

    if (!document.getElementById('editor')) { return; }
    var video = document.getElementById('camera');
    var preview = document.getElementById('upload-preview');
    var overlayPreview = document.getElementById('overlay-preview');
    var placeholder = document.getElementById('camera-placeholder');
    var imageInput = document.getElementById('image-input');
    var form = document.getElementById('capture-form');
    var gifForm = document.getElementById('gif-form');
    var cameraStatus = document.getElementById('camera-status');
    var capture = document.getElementById('capture-button');
    var viewfinder = document.getElementById('viewfinder');
    var burst = document.getElementById('gif-burst');
    var framesInput = document.getElementById('frames-input');
    var studio = document.querySelector('.studio');
    var modeTabs = studio.querySelectorAll('[data-studio-mode]');
    var frameUrls = [];
    var gifFrames = [];
    var recording = false;
    var frameIndex = 0;
    var frameTimer = null;
    var gifFramePreview = document.getElementById('gif-frame-preview');
    function stopFramePreview() {
        window.clearInterval(frameTimer); frameTimer = null;
        var play = document.getElementById('gif-preview-play');
        play.setAttribute('aria-pressed', 'false'); play.setAttribute('aria-label', 'Play frame preview');
        play.replaceChildren(svgIcon('m8 5 11 7-11 7Z'));
    }
    function setStudioMode(mode) {
        var gif = mode === 'gif';
        studio.classList.toggle('is-gif', gif);
        placeholder.querySelector('h2').textContent = gif ? 'Create a GIF' : 'Your camera is off.';
        placeholder.querySelector('p').textContent = gif ? 'Turn on your camera for a burst, or upload a sequence below.' : 'It stays that way until you turn it on.';
        Array.prototype.forEach.call(modeTabs, function (tab) {
            var active = tab.getAttribute('data-studio-mode') === mode;
            tab.setAttribute('aria-selected', active ? 'true' : 'false'); tab.tabIndex = active ? 0 : -1;
        });
        document.getElementById('photo-actions').hidden = gif;
        document.getElementById('gif-controls').hidden = !gif;
        gifForm.hidden = !gif;
        if (!gif) { stopFramePreview(); }
        ready();
    }
    Array.prototype.forEach.call(modeTabs, function (tab) {
        tab.addEventListener('click', function () { setStudioMode(tab.getAttribute('data-studio-mode')); });
        tab.addEventListener('keydown', function (event) {
            if (['ArrowLeft', 'ArrowRight', 'Home', 'End'].indexOf(event.key) < 0) { return; }
            event.preventDefault();
            var mode = event.key === 'Home' ? 'photo' : event.key === 'End' ? 'gif' : tab.getAttribute('data-studio-mode') === 'photo' ? 'gif' : 'photo';
            setStudioMode(mode); studio.querySelector('[data-studio-mode="' + mode + '"]').focus();
        });
    });
    function selectGifFrame(index) {
        frameIndex = index; gifFramePreview.src = frameUrls[index]; gifFramePreview.hidden = false;
        Array.prototype.forEach.call(document.querySelectorAll('#gif-frame-strip button'), function (button, i) { button.setAttribute('aria-pressed', i === index ? 'true' : 'false'); });
    }
    function clearGifPreviews() {
        stopFramePreview();
        gifFrames = [];
        frameUrls.forEach(function (url) { URL.revokeObjectURL(url); }); frameUrls = [];
        document.getElementById('gif-frame-strip').replaceChildren(); gifFramePreview.hidden = true; gifFramePreview.removeAttribute('src');
        document.getElementById('gif-sequence').hidden = true; studio.classList.remove('has-gif-frames');
    }
    document.getElementById('gif-clear').addEventListener('click', function () { framesInput.value = ''; clearGifPreviews(); status(gifForm, '', false); ready(); });
    document.getElementById('gif-preview-play').addEventListener('click', function () {
        if (frameTimer) { stopFramePreview(); return; }
        if (!frameUrls.length) { return; }
        this.setAttribute('aria-pressed', 'true'); this.setAttribute('aria-label', 'Pause frame preview'); this.replaceChildren(svgIcon('M8 5v14M16 5v14'));
        frameTimer = window.setInterval(function () { selectGifFrame((frameIndex + 1) % frameUrls.length); }, 250);
    });
    var stream = null;
    var source = '';
    var overlay = 'none';
    var switchSource = document.getElementById('switch-source');
    var previewUrl = null;
    var cameraUrl = null;
    var cameraGeneration = 0;
    function ready() {
        capture.disabled = recording || !(overlay && source);
        Array.prototype.forEach.call(modeTabs, function (tab) { tab.disabled = recording; });
        Array.prototype.forEach.call(form.querySelectorAll('input[name="overlay"]'), function (radio) { radio.disabled = recording; });
        framesInput.disabled = recording;
        switchSource.disabled = recording;
        viewfinder.classList.toggle('has-capture', !!source);
        document.getElementById('source-badge').hidden = !!source;
        var gifMode = studio.classList.contains('is-gif');
        var usingFrames = gifMode && frameUrls.length > 0;
        var offerUpload = source === 'camera' && !usingFrames;
        switchSource.hidden = !source && !usingFrames;
        switchSource.replaceChildren(svgIcon(offerUpload ? 'M12 16V4m-5 5 5-5 5 5M4 16v4h16v-4' : 'M3 6h13v12H3zM16 10l5-3v10l-5-3'));
        switchSource.appendChild(document.createTextNode(offerUpload ? (gifMode ? 'Upload frames' : 'Upload a photo') : 'Turn on camera'));
        burst.disabled = recording || !(overlay && source === 'camera');
        document.getElementById('gif-upload').disabled = recording || !(overlay && gifFrames.length >= 2 && gifFrames.length <= 8);
        document.getElementById('gif-create-hint').hidden = gifFrames.length >= 2 || recording;
        document.getElementById('gif-overlay').value = overlay;
    }
    function stopCamera() {
        cameraGeneration++;
        if (stream) { stream.getTracks().forEach(function (track) { track.stop(); }); stream = null; }
        if (cameraUrl) { URL.revokeObjectURL(cameraUrl); cameraUrl = null; }
        video.hidden = true;
        if (source === 'camera') { source = ''; }
        ready();
    }
    window.addEventListener('pagehide', stopCamera);
    switchSource.addEventListener('click', function () {
        if (studio.classList.contains('is-gif')) {
            if (frameUrls.length) {
                framesInput.value = ''; clearGifPreviews();
                if (source === 'camera') { ready(); }
                else { document.getElementById('start-camera').click(); }
            } else if (source === 'camera') { framesInput.click(); }
            else { document.getElementById('start-camera').click(); }
        } else if (source === 'camera') { imageInput.click(); }
        else { document.getElementById('start-camera').click(); }
    });
    function cameraFailure() { cameraStatus.textContent = 'Camera unavailable or permission denied. You can upload a photo instead.'; cameraStatus.classList.add('is-visible'); ready(); }
    document.getElementById('start-camera').addEventListener('click', function () {
        stopCamera();
        cameraStatus.classList.remove('is-visible');
        var generation = cameraGeneration;
        cameraStatus.textContent = 'Waiting for camera permission…';
        function success(media) {
            if (generation !== cameraGeneration) { media.getTracks().forEach(function (track) { track.stop(); }); return; }
            stream = media;
            if ('srcObject' in video) { video.srcObject = media; }
            else { cameraUrl = URL.createObjectURL(media); video.src = cameraUrl; }
            video.hidden = false; preview.hidden = true; placeholder.hidden = true;
            imageInput.value = '';
            var playing = video.play();
            if (playing && playing['catch']) { playing['catch'](function () { cameraFailure(); }); }
            video.onloadeddata = function () { source = 'camera'; cameraStatus.textContent = 'Camera is on. Pick an overlay, then capture your moment.'; ready(); };
        }
        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            navigator.mediaDevices.getUserMedia({ video: { width: 1280, height: 960 }, audio: false }).then(success, cameraFailure);
        } else {
            var getMedia = navigator.getUserMedia || navigator.webkitGetUserMedia || navigator.mozGetUserMedia;
            if (getMedia) { getMedia.call(navigator, { video: true, audio: false }, success, cameraFailure); }
            else { cameraFailure(); }
        }
    });
    imageInput.addEventListener('change', function () {
        stopCamera();
        source = ''; preview.hidden = true; placeholder.hidden = false;
        if (previewUrl) { URL.revokeObjectURL(previewUrl); previewUrl = null; }
        var file = imageInput.files[0];
        if (!file) { ready(); return; }
        if (['image/jpeg', 'image/png'].indexOf(file.type) < 0 || file.size > 5 * 1024 * 1024) {
            imageInput.value = ''; cameraStatus.textContent = 'Choose a JPEG or PNG image no larger than 5 MiB.'; ready(); return;
        }
        preview.onload = function () { source = 'upload'; preview.hidden = false; placeholder.hidden = true; cameraStatus.textContent = 'Photo loaded. Pick an overlay and make it yours.'; ready(); };
        preview.onerror = function () { source = ''; cameraStatus.textContent = 'This image could not be opened. Try another photo.'; ready(); };
        previewUrl = URL.createObjectURL(file); preview.src = previewUrl;
        ready();
    });
    Array.prototype.forEach.call(form.querySelectorAll('input[name="overlay"]'), function (radio) {
        radio.addEventListener('change', function () {
            overlay = radio.value;
            overlayPreview.hidden = overlay === 'none';
            if (overlay === 'none') { overlayPreview.removeAttribute('src'); }
            else { overlayPreview.src = '/assets/images/overlays/' + overlay + '.png'; }
            document.getElementById('frame-chip').textContent = 'Frame: ' + radio.parentElement.lastElementChild.textContent;
            ready();
        });
    });
    framesInput.addEventListener('change', function () {
        clearGifPreviews();
        var files = Array.prototype.slice.call(framesInput.files || []);
        if (!files.length) { ready(); return; }
        var total = 0;
        var valid = files.length >= 2 && files.length <= 8 && files.every(function (file) {
            total += file.size;
            return ['image/jpeg', 'image/png'].indexOf(file.type) >= 0 && file.size <= 5 * 1024 * 1024;
        }) && total <= 20 * 1024 * 1024;
        if (!valid) {
            framesInput.value = ''; status(gifForm, 'Choose 2–8 JPEG or PNG frames, up to 5 MiB each and 20 MiB total.', true); ready(); return;
        }
        status(gifForm, '', false);
        showGifFrames(files);
    });
    function showGifFrames(files) {
        clearGifPreviews();
        gifFrames = files;
        files.forEach(function (file, index) {
            var url = URL.createObjectURL(file); frameUrls.push(url);
            var button = element('button'); button.type = 'button'; button.setAttribute('aria-label', 'Preview frame ' + (index + 1));
            var image = element('img'); image.src = url; image.alt = ''; button.appendChild(image); button.appendChild(element('span', '', index + 1));
            button.addEventListener('click', function () { stopFramePreview(); selectGifFrame(index); });
            document.getElementById('gif-frame-strip').appendChild(button);
        });
        studio.classList.add('has-gif-frames'); document.getElementById('gif-sequence').hidden = false;
        document.getElementById('gif-sequence-title').textContent = 'Frames (' + files.length + ')';
        selectGifFrame(0); ready();
    }
    window.addEventListener('pagehide', clearGifPreviews);
    ready();
    function snapshot(width, height, done) {
        var canvas = document.createElement('canvas');
        canvas.width = width; canvas.height = height;
        var context = canvas.getContext('2d');
        var sw = video.videoWidth, sh = video.videoHeight;
        if (!sw || !sh) { done(null); return; }
        var cw = Math.min(sw, sh * width / height), ch = Math.min(sh, sw * height / width);
        context.drawImage(video, (sw - cw) / 2, (sh - ch) / 2, cw, ch, 0, 0, width, height);
        if (canvas.toBlob) { canvas.toBlob(done, 'image/jpeg', 0.92); }
        else {
            var encoded = canvas.toDataURL('image/jpeg', 0.92).split(',')[1];
            var binary = window.atob(encoded), bytes = new Uint8Array(binary.length), i;
            for (i = 0; i < binary.length; i++) { bytes[i] = binary.charCodeAt(i); }
            done(new Blob([bytes], { type: 'image/jpeg' }));
        }
    }
    function sendCapture(targetForm, data) {
        request(targetForm.action, 'POST', data, function (error, result) {
            busy(targetForm, false);
            ready();
            if (error) { status(targetForm, error, true); return; }
            targetForm.elements.submission.value = randomKey();
            var history = document.getElementById('history-grid');
            if (!history.querySelector('a[href="/photos/' + result.photo.id + '"]')) { history.insertBefore(card(result.photo, true), history.firstChild); }
            var empty = document.querySelector('.history-empty');
            if (empty) { empty.hidden = true; }
            status(targetForm, '', false);
            notify('A new moment, made by you.');
        });
    }
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        if (!overlay || !source || form.getAttribute('aria-busy') === 'true') { return; }
        busy(form, true);
        status(form, 'Creating your moment…', false);
        var data = new FormData(form);
        if (source === 'camera') {
            snapshot(1280, 960, function (blob) {
                if (!blob) { busy(form, false); status(form, 'Wait for your camera to be ready.', true); return; }
                // FormData.set is not available in the minimum supported browsers.
                var cameraData = new FormData();
                cameraData.append('csrf', token()); cameraData.append('submission', form.elements.submission.value); cameraData.append('overlay', overlay); cameraData.append('image', blob, 'capture.jpg');
                sendCapture(form, cameraData);
            });
        } else { sendCapture(form, data); }
    });
    gifForm.addEventListener('submit', function (event) {
        event.preventDefault();
        if (!overlay || gifFrames.length < 2 || gifFrames.length > 8 || gifForm.getAttribute('aria-busy') === 'true') { return; }
        busy(gifForm, true); status(gifForm, 'Putting your frames in motion…', false);
        var data = new FormData();
        data.append('csrf', token()); data.append('submission', gifForm.elements.submission.value); data.append('overlay', overlay);
        gifFrames.forEach(function (frame, index) { data.append('frames[]', frame, frame.name || 'frame-' + index + '.jpg'); });
        sendCapture(gifForm, data);
    });
    burst.addEventListener('click', function () {
        if (!overlay || source !== 'camera' || recording || gifForm.getAttribute('aria-busy') === 'true') { return; }
        framesInput.value = ''; clearGifPreviews();
        recording = true; busy(gifForm, true); ready();
        var frames = [];
        var chosenOverlay = overlay;
        function next() {
            status(gifForm, 'Recording frame ' + (frames.length + 1) + ' of 8…', false);
            snapshot(480, 360, function (blob) {
                if (!blob || source !== 'camera' || overlay !== chosenOverlay) {
                    recording = false; busy(gifForm, false); ready();
                    status(gifForm, 'Recording stopped. Turn on your camera and try again.', true); return;
                }
                frames.push(blob);
                if (frames.length < 8) { window.setTimeout(next, 250); }
                else {
                    recording = false; busy(gifForm, false);
                    showGifFrames(frames); status(gifForm, '', false);
                }
            });
        }
        next();
    });
}());
