export function token() {
    return document.querySelector('meta[name="csrf-token"]').getAttribute('content');
}
export function randomKey() {
    const bytes = new Uint8Array(16);
    window.crypto.getRandomValues(bytes);
    return Array.from(bytes, function (b) {
        return ('0' + b.toString(16)).slice(-2);
    }).join('');
}
export function request(url, method, body, done) {
    const xhr = new XMLHttpRequest();
    xhr.open(method, url, true);
    xhr.setRequestHeader('Accept', 'application/json');
    if (method === 'POST') {
        xhr.setRequestHeader('X-CSRF-Token', token());
    }
    xhr.timeout = 45000;
    xhr.onload = function () {
        let data;
        try {
            data = JSON.parse(xhr.responseText);
        } catch (ignore) {
            done('The server returned an unexpected response. Please try again.');
            return;
        }
        if (!data.ok) {
            done(data.error ? data.error.message : 'Something went wrong.');
            return;
        }
        done(null, data.data);
    };
    xhr.onerror = xhr.ontimeout = function () {
        done('Connection interrupted. Your input is safe; please try again.');
    };
    xhr.send(body);
}
