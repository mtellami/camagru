import { icons } from './icons.js';
import { token, randomKey, request } from './api.js';
import { notify, status, busy } from './ui.js';
import { element, svgIcon } from './dom.js';
import { createPhotoCard } from './gallery.js';
import { captureFrame } from './camera.js';

/** Attach camera, uploads, stickers, previews, and publishing to the editor page. */
export function initEditor() {
    if (!document.getElementById('editor')) {
        return;
    }
    const video = document.getElementById('camera');
    const preview = document.getElementById('upload-preview');
    const overlayPreview = document.getElementById('overlay-preview');
    const placeholder = document.getElementById('camera-placeholder');
    const imageInput = document.getElementById('image-input');
    const form = document.getElementById('capture-form');
    const gifForm = document.getElementById('gif-form');
    const cameraStatus = document.getElementById('camera-status');
    const capture = document.getElementById('capture-button');
    const viewfinder = document.getElementById('viewfinder');
    const burst = document.getElementById('gif-burst');
    const framesInput = document.getElementById('frames-input');
    const studio = document.querySelector('.studio');
    const modeTabs = studio.querySelectorAll('[data-studio-mode]');
    let frameUrls = [];
    let gifFrames = [];
    let recording = false;
    let frameIndex = 0;
    let frameTimer = null;
    const gifFramePreview = document.getElementById('gif-frame-preview');
    let stream = null;
    let source = '';
    let overlays = [];
    const switchSource = document.getElementById('switch-source');
    let previewUrl = null;
    let cameraUrl = null;
    let cameraGeneration = 0;

    /** Stop GIF preview playback and reset the play button. */
    function stopFramePreview() {
        window.clearInterval(frameTimer);
        frameTimer = null;
        const play = document.getElementById('gif-preview-play');
        play.setAttribute('aria-pressed', 'false');
        play.setAttribute('aria-label', 'Play frame preview');
        play.replaceChildren(svgIcon(icons.play));
    }
    /** Switch between photo and GIF controls and refresh enabled actions. */
    function setStudioMode(mode) {
        const gif = mode === 'gif';
        studio.classList.toggle('is-gif', gif);
        placeholder.querySelector('h2').textContent = gif ? 'Create a GIF' : 'Your camera is off.';
        placeholder.querySelector('p').textContent = gif
            ? 'Turn on your camera for a burst, or upload a sequence below.'
            : 'It stays that way until you turn it on.';
        modeTabs.forEach(function (tab) {
            const active = tab.getAttribute('data-studio-mode') === mode;
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
            tab.tabIndex = active ? 0 : -1;
        });
        document.getElementById('photo-actions').hidden = gif;
        document.getElementById('gif-controls').hidden = !gif;
        gifForm.hidden = !gif;
        if (!gif) {
            stopFramePreview();
        }
        ready();
    }
    modeTabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            setStudioMode(tab.getAttribute('data-studio-mode'));
        });
        tab.addEventListener('keydown', function (event) {
            if (['ArrowLeft', 'ArrowRight', 'Home', 'End'].indexOf(event.key) < 0) {
                return;
            }
            event.preventDefault();
            const mode =
                event.key === 'Home'
                    ? 'photo'
                    : event.key === 'End'
                      ? 'gif'
                      : tab.getAttribute('data-studio-mode') === 'photo'
                        ? 'gif'
                        : 'photo';
            setStudioMode(mode);
            studio.querySelector('[data-studio-mode="' + mode + '"]').focus();
        });
    });
    /** Show one GIF frame and update its selection in the thumbnail strip. */
    function selectGifFrame(index) {
        frameIndex = index;
        gifFramePreview.src = frameUrls[index];
        gifFramePreview.hidden = false;
        document.querySelectorAll('#gif-frame-strip button').forEach(function (button, i) {
            button.setAttribute('aria-pressed', i === index ? 'true' : 'false');
        });
    }
    /** Release preview URLs and reset the selected GIF sequence. */
    function clearGifPreviews() {
        stopFramePreview();
        gifFrames = [];
        frameUrls.forEach(function (url) {
            URL.revokeObjectURL(url);
        });
        frameUrls = [];
        document.getElementById('gif-frame-strip').replaceChildren();
        gifFramePreview.hidden = true;
        gifFramePreview.removeAttribute('src');
        document.getElementById('gif-sequence').hidden = true;
        studio.classList.remove('has-gif-frames');
    }
    document.getElementById('gif-clear').addEventListener('click', function () {
        framesInput.value = '';
        clearGifPreviews();
        status(gifForm, '', false);
        ready();
    });
    document.getElementById('gif-preview-play').addEventListener('click', function () {
        if (frameTimer) {
            stopFramePreview();
            return;
        }
        if (!frameUrls.length) {
            return;
        }
        this.setAttribute('aria-pressed', 'true');
        this.setAttribute('aria-label', 'Pause frame preview');
        this.replaceChildren(svgIcon(icons.pause));
        frameTimer = window.setInterval(function () {
            selectGifFrame((frameIndex + 1) % frameUrls.length);
        }, 250);
    });
    /** Refresh available actions from the source, mode, frames, and recording state. */
    function ready() {
        capture.disabled = recording || !source;
        modeTabs.forEach(function (tab) {
            tab.disabled = recording;
        });
        form.querySelectorAll('.overlay-picker input').forEach(function (input) {
            input.disabled = recording;
        });
        framesInput.disabled = recording;
        switchSource.disabled = recording;
        viewfinder.classList.toggle('has-capture', !!source);
        document.getElementById('source-badge').hidden = !!source;
        const gifMode = studio.classList.contains('is-gif');
        const usingFrames = gifMode && frameUrls.length > 0;
        const offerUpload = source === 'camera' && !usingFrames;
        switchSource.hidden = !source && !usingFrames;
        switchSource.replaceChildren(svgIcon(offerUpload ? icons.upload : icons.video));
        switchSource.appendChild(
            document.createTextNode(
                offerUpload ? (gifMode ? 'Upload frames' : 'Upload a photo') : 'Turn on camera',
            ),
        );
        burst.disabled = recording || source !== 'camera';
        document.getElementById('gif-upload').disabled =
            recording || !(gifFrames.length >= 2 && gifFrames.length <= 8);
        document.getElementById('gif-create-hint').hidden = gifFrames.length >= 2 || recording;
    }
    /** Stop media tracks and invalidate any pending camera permission response. */
    function stopCamera() {
        cameraGeneration++;
        if (stream) {
            stream.getTracks().forEach(function (track) {
                track.stop();
            });
            stream = null;
        }
        if (cameraUrl) {
            URL.revokeObjectURL(cameraUrl);
            cameraUrl = null;
        }
        video.hidden = true;
        if (source === 'camera') {
            source = '';
        }
        ready();
    }
    window.addEventListener('pagehide', stopCamera);
    switchSource.addEventListener('click', function () {
        if (studio.classList.contains('is-gif')) {
            if (frameUrls.length) {
                framesInput.value = '';
                clearGifPreviews();
                if (source === 'camera') {
                    ready();
                } else {
                    document.getElementById('start-camera').click();
                }
            } else if (source === 'camera') {
                framesInput.click();
            } else {
                document.getElementById('start-camera').click();
            }
        } else if (source === 'camera') {
            imageInput.click();
        } else {
            document.getElementById('start-camera').click();
        }
    });
    /** Explain camera failure and refresh controls so uploads remain available. */
    function cameraFailure() {
        cameraStatus.textContent =
            'Camera unavailable or permission denied. You can upload a photo instead.';
        cameraStatus.classList.add('is-visible');
        ready();
    }
    document.getElementById('start-camera').addEventListener('click', function () {
        stopCamera();
        cameraStatus.classList.remove('is-visible');
        const generation = cameraGeneration;
        cameraStatus.textContent = 'Waiting for camera permission…';
        /** Accept a camera stream only if its permission request is still current. */
        function success(media) {
            if (generation !== cameraGeneration) {
                media.getTracks().forEach(function (track) {
                    track.stop();
                });
                return;
            }
            stream = media;
            if ('srcObject' in video) {
                video.srcObject = media;
            } else {
                cameraUrl = URL.createObjectURL(media);
                video.src = cameraUrl;
            }
            video.hidden = false;
            preview.hidden = true;
            placeholder.hidden = true;
            imageInput.value = '';
            const playing = video.play();
            if (playing && playing['catch']) {
                playing['catch'](function () {
                    cameraFailure();
                });
            }
            video.onloadeddata = function () {
                source = 'camera';
                cameraStatus.textContent =
                    'Camera is on. Pick your stickers, then capture your moment.';
                ready();
            };
        }
        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            navigator.mediaDevices
                .getUserMedia({ video: { width: 1280, height: 960 }, audio: false })
                .then(success, cameraFailure);
        } else {
            const getMedia =
                navigator.getUserMedia || navigator.webkitGetUserMedia || navigator.mozGetUserMedia;
            if (getMedia) {
                getMedia.call(navigator, { video: true, audio: false }, success, cameraFailure);
            } else {
                cameraFailure();
            }
        }
    });
    imageInput.addEventListener('change', function () {
        stopCamera();
        source = '';
        preview.hidden = true;
        placeholder.hidden = false;
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
            previewUrl = null;
        }
        const file = imageInput.files[0];
        if (!file) {
            ready();
            return;
        }
        if (['image/jpeg', 'image/png'].indexOf(file.type) < 0 || file.size > 5 * 1024 * 1024) {
            imageInput.value = '';
            cameraStatus.textContent = 'Choose a JPEG or PNG image no larger than 5 MiB.';
            ready();
            return;
        }
        preview.onload = function () {
            source = 'upload';
            preview.hidden = false;
            placeholder.hidden = true;
            cameraStatus.textContent = 'Photo loaded. Pick your stickers and make it yours.';
            ready();
        };
        preview.onerror = function () {
            source = '';
            cameraStatus.textContent = 'This image could not be opened. Try another photo.';
            ready();
        };
        previewUrl = URL.createObjectURL(file);
        preview.src = previewUrl;
        ready();
    });
    const stickerInputs = form.querySelectorAll('input[name="overlay[]"]');
    const clearStickers = document.getElementById('clear-stickers');
    /** Rebuild preview layers and selected sticker labels in picker order. */
    function updateStickers() {
        overlays = [];
        const names = [];
        overlayPreview.replaceChildren();
        stickerInputs.forEach(function (input) {
            if (!input.checked) {
                return;
            }
            overlays.push(input.value);
            names.push(input.parentElement.lastElementChild.textContent);
            const layer = element('img');
            layer.src = '/assets/images/overlays/' + input.value + '.png';
            layer.alt = '';
            overlayPreview.appendChild(layer);
        });
        clearStickers.checked = overlays.length === 0;
        overlayPreview.hidden = overlays.length === 0;
        document.getElementById('frame-chip').textContent =
            'Stickers: ' + (names.length > 2 ? names.length + ' selected' : names.join(', ') || 'None');
        document.getElementById('frame-chip').title = names.join(', ');
        ready();
    }
    stickerInputs.forEach(function (input) {
        input.addEventListener('change', updateStickers);
    });
    clearStickers.addEventListener('change', function () {
        stickerInputs.forEach(function (input) {
            input.checked = false;
        });
        updateStickers();
    });
    /** Add the selected sticker IDs to an outgoing multipart payload. */
    function appendStickers(data) {
        overlays.forEach(function (overlay) {
            data.append('overlay[]', overlay);
        });
    }
    updateStickers();
    framesInput.addEventListener('change', function () {
        clearGifPreviews();
        const files = Array.from(framesInput.files || []);
        if (!files.length) {
            ready();
            return;
        }
        let total = 0;
        const valid =
            files.length >= 2 &&
            files.length <= 8 &&
            files.every(function (file) {
                total += file.size;
                return (
                    ['image/jpeg', 'image/png'].indexOf(file.type) >= 0 &&
                    file.size <= 5 * 1024 * 1024
                );
            }) &&
            total <= 20 * 1024 * 1024;
        if (!valid) {
            framesInput.value = '';
            status(
                gifForm,
                'Choose 2–8 JPEG or PNG frames, up to 5 MiB each and 20 MiB total.',
                true,
            );
            ready();
            return;
        }
        status(gifForm, '', false);
        showGifFrames(files);
    });
    /** Build thumbnails and preview URLs for an uploaded or captured frame sequence. */
    function showGifFrames(files) {
        clearGifPreviews();
        gifFrames = files;
        files.forEach(function (file, index) {
            const url = URL.createObjectURL(file);
            frameUrls.push(url);
            const button = element('button');
            button.type = 'button';
            button.setAttribute('aria-label', 'Preview frame ' + (index + 1));
            const image = element('img');
            image.src = url;
            image.alt = '';
            button.appendChild(image);
            button.appendChild(element('span', '', index + 1));
            button.addEventListener('click', function () {
                stopFramePreview();
                selectGifFrame(index);
            });
            document.getElementById('gif-frame-strip').appendChild(button);
        });
        studio.classList.add('has-gif-frames');
        document.getElementById('gif-sequence').hidden = false;
        document.getElementById('gif-sequence-title').textContent = 'Frames (' + files.length + ')';
        selectGifFrame(0);
        ready();
    }
    window.addEventListener('pagehide', clearGifPreviews);
    ready();
    /** Publish media and update personal history after a successful response. */
    function sendCapture(targetForm, data) {
        request(targetForm.action, 'POST', data, function (error, result) {
            busy(targetForm, false);
            ready();
            if (error) {
                status(targetForm, error, true);
                return;
            }
            targetForm.elements.submission.value = randomKey();
            const history = document.getElementById('history-grid');
            if (!history.querySelector('a[href="/photos/' + result.photo.id + '"]')) {
                history.insertBefore(createPhotoCard(result.photo, true), history.firstChild);
            }
            const empty = document.querySelector('.history-empty');
            if (empty) {
                empty.hidden = true;
            }
            status(targetForm, '', false);
            notify('A new moment, made by you.');
        });
    }
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        if (!source || form.getAttribute('aria-busy') === 'true') {
            return;
        }
        busy(form, true);
        status(form, 'Creating your moment…', false);
        const data = new FormData(form);
        if (source === 'camera') {
            captureFrame(video, 1280, 960, function (blob) {
                if (!blob) {
                    busy(form, false);
                    status(form, 'Wait for your camera to be ready.', true);
                    return;
                }
                data.set('image', blob, 'capture.jpg');
                sendCapture(form, data);
            });
        } else {
            sendCapture(form, data);
        }
    });
    gifForm.addEventListener('submit', function (event) {
        event.preventDefault();
        if (
            gifFrames.length < 2 ||
            gifFrames.length > 8 ||
            gifForm.getAttribute('aria-busy') === 'true'
        ) {
            return;
        }
        busy(gifForm, true);
        status(gifForm, 'Putting your frames in motion…', false);
        const data = new FormData();
        data.append('csrf', token());
        data.append('submission', gifForm.elements.submission.value);
        appendStickers(data);
        gifFrames.forEach(function (frame, index) {
            data.append('frames[]', frame, frame.name || 'frame-' + index + '.jpg');
        });
        sendCapture(gifForm, data);
    });
    burst.addEventListener('click', function () {
        if (
            source !== 'camera' ||
            recording ||
            gifForm.getAttribute('aria-busy') === 'true'
        ) {
            return;
        }
        framesInput.value = '';
        clearGifPreviews();
        recording = true;
        busy(gifForm, true);
        ready();
        const frames = [];
        const chosenOverlays = overlays.join(',');
        /** Capture the next burst frame or finish the eight-frame sequence. */
        function next() {
            status(gifForm, 'Recording frame ' + (frames.length + 1) + ' of 8…', false);
            captureFrame(video, 480, 360, function (blob) {
                if (!blob || source !== 'camera' || overlays.join(',') !== chosenOverlays) {
                    recording = false;
                    busy(gifForm, false);
                    ready();
                    status(gifForm, 'Recording stopped. Turn on your camera and try again.', true);
                    return;
                }
                frames.push(blob);
                if (frames.length < 8) {
                    window.setTimeout(next, 250);
                } else {
                    recording = false;
                    busy(gifForm, false);
                    showGifFrames(frames);
                    status(gifForm, '', false);
                }
            });
        }
        next();
    });
}
