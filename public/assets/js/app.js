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
        var counts = element('a', 'card-counts', '♡ ' + photo.likes + '  ◌ ' + photo.comments);
        counts.href = link.href;
        meta.appendChild(counts); article.appendChild(meta);
        return article;
    }
    document.addEventListener('submit', function (event) {
        var form = event.target;
        var action = form.getAttribute('data-ajax');
        if (!action) { return; }
        event.preventDefault();
        if (form.getAttribute('aria-busy') === 'true') { return; }
        if (action === 'delete' && !window.confirm('Delete this creation? This cannot be undone.')) { return; }
        busy(form, true);
        request(form.action, 'POST', new FormData(form), function (error, data) {
            busy(form, false);
            if (error) { status(form, error, true); return; }
            if (action === 'like') {
                form.action = form.action.replace(/\/(like|unlike)$/, data.liked ? '/unlike' : '/like');
                var button = form.querySelector('button');
                button.setAttribute('aria-pressed', data.liked ? 'true' : 'false');
                button.textContent = (data.liked ? '♥ ' : '♡ ') + data.count + ' likes';
            } else if (action === 'comment') {
                if (data.comment) {
                    var comment = element('article', 'comment');
                    comment.appendChild(element('strong', '', data.comment.username));
                    comment.appendChild(element('p', '', data.comment.body));
                    document.getElementById('comments').appendChild(comment);
                    var empty = document.getElementById('no-comments');
                    if (empty) { empty.hidden = true; }
                }
                form.elements.body.value = '';
                form.elements.submission.value = data.submission;
                status(form, 'Your note is posted.', false);
            } else if (action === 'delete') { window.location.assign(data.redirect); }
            else { status(form, data.message, false); }
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
    Array.prototype.forEach.call(document.querySelectorAll('.share-button'), function (button) {
        function fallback() {
            var box = document.querySelector('.share-fallback');
            box.hidden = false;
            var input = box.querySelector('input'); input.focus(); input.select();
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(input.value).then(function () { notify('Link copied. Share it wherever you like.'); }, function () { notify('Select and copy the link to share it.'); });
            } else { notify('Select and copy the link to share it.'); }
        }
        button.addEventListener('click', function () {
            if (navigator.share) {
                navigator.share({ title: 'A little moment on Camagru', url: button.getAttribute('data-url') }).then(function () {}, function (error) { if (error.name !== 'AbortError') { fallback(); } });
            } else { fallback(); }
        });
    });
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
    var burst = document.getElementById('gif-burst');
    var framesInput = document.getElementById('frames-input');
    var stream = null;
    var source = '';
    var overlay = '';
    var previewUrl = null;
    var cameraUrl = null;
    var cameraGeneration = 0;
    function ready() {
        capture.disabled = !(overlay && source);
        burst.disabled = !(overlay && source === 'camera');
        document.getElementById('gif-upload').disabled = !(overlay && framesInput.files && framesInput.files.length >= 2 && framesInput.files.length <= 8);
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
    function cameraFailure() { cameraStatus.textContent = 'Camera unavailable or permission denied. You can upload a photo instead.'; ready(); }
    document.getElementById('start-camera').addEventListener('click', function () {
        stopCamera();
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
        radio.addEventListener('change', function () { overlay = radio.value; overlayPreview.src = '/assets/images/overlays/' + overlay + '.png'; overlayPreview.hidden = false; ready(); });
    });
    framesInput.addEventListener('change', ready);
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
            status(targetForm, 'Saved to the public gallery! Open your new creation in Your roll.', false);
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
        if (!overlay || framesInput.files.length < 2 || framesInput.files.length > 8 || gifForm.getAttribute('aria-busy') === 'true') { return; }
        busy(gifForm, true); status(gifForm, 'Putting your frames in motion…', false);
        sendCapture(gifForm, new FormData(gifForm));
    });
    burst.addEventListener('click', function () {
        if (!overlay || source !== 'camera' || gifForm.getAttribute('aria-busy') === 'true') { return; }
        busy(gifForm, true);
        var data = new FormData();
        data.append('csrf', token()); data.append('submission', gifForm.elements.submission.value); data.append('overlay', overlay);
        var frame = 0;
        var chosenOverlay = overlay;
        function next() {
            status(gifForm, 'Recording frame ' + (frame + 1) + ' of 8…', false);
            snapshot(480, 360, function (blob) {
                if (!blob || source !== 'camera' || overlay !== chosenOverlay) { busy(gifForm, false); status(gifForm, 'Recording stopped. Keep your camera and overlay active during a burst.', true); return; }
                data.append('frames[]', blob, 'frame-' + frame + '.jpg');
                frame++;
                if (frame < 8) { window.setTimeout(next, 250); }
                else { status(gifForm, 'Rendering your animation…', false); sendCapture(gifForm, data); }
            });
        }
        next();
    });
}());
