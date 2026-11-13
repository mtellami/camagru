let toastTimer;
export function notify(message) {
    const box = document.getElementById('toast');
    box.textContent = message;
    box.hidden = false;
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(function () {
        box.hidden = true;
    }, 5500);
}

export function status(form, message, error) {
    const box = form.querySelector('.form-status');
    if (!box) {
        notify(message);
        return;
    }
    box.textContent = message;
    box.className = 'form-status' + (error ? ' error' : '');
}
export function busy(form, on) {
    form.classList[on ? 'add' : 'remove']('is-loading');
    form.setAttribute('aria-busy', on ? 'true' : 'false');
}

export function initUi() {
    const initialToast = document.getElementById('toast');
    if (initialToast && !initialToast.hidden) {
        notify(initialToast.textContent);
    }
    document.addEventListener('click', function (event) {
        document.querySelectorAll('.moment-more[open]').forEach(function (menu) {
            if (!menu.contains(event.target)) {
                menu.open = false;
            }
        });
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            document.querySelectorAll('.moment-more[open]').forEach(function (menu) {
                menu.open = false;
                menu.querySelector('summary').focus();
            });
        }
    });
    document.querySelectorAll('.moment-dialog').forEach(function (dialog) {
        dialog.querySelectorAll('[data-close-dialog]').forEach(function (button) {
            button.addEventListener('click', function () {
                dialog.close();
            });
        });
        dialog.addEventListener('click', function (event) {
            if (event.target !== dialog) {
                return;
            }
            const bounds = dialog.getBoundingClientRect();
            if (
                event.clientX < bounds.left ||
                event.clientX > bounds.right ||
                event.clientY < bounds.top ||
                event.clientY > bounds.bottom
            ) {
                dialog.close();
            }
        });
    });
    const back = document.getElementById('go-back');
    if (back) {
        back.addEventListener('click', function () {
            if (window.history.length > 1) {
                window.history.back();
            } else {
                window.location.assign('/');
            }
        });
    }
}
