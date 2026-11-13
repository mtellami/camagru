export function captureFrame(video, width, height, done) {
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const context = canvas.getContext('2d');
    const sw = video.videoWidth,
        sh = video.videoHeight;
    if (!sw || !sh) {
        done(null);
        return;
    }
    const cw = Math.min(sw, (sh * width) / height),
        ch = Math.min(sh, (sw * height) / width);
    context.drawImage(video, (sw - cw) / 2, (sh - ch) / 2, cw, ch, 0, 0, width, height);
    if (canvas.toBlob) {
        canvas.toBlob(done, 'image/jpeg', 0.92);
    } else {
        const encoded = canvas.toDataURL('image/jpeg', 0.92).split(',')[1];
        const binary = window.atob(encoded);
        const bytes = new Uint8Array(binary.length);
        for (let i = 0; i < binary.length; i++) {
            bytes[i] = binary.charCodeAt(i);
        }
        done(new Blob([bytes], { type: 'image/jpeg' }));
    }
}
