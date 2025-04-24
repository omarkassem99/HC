let toastBottomOffset = 30;

function showToast(message, type = 'success', time = 4000) {
    console.log('showToast', message, type, time);

    // Decode HTML entities in the message
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = message;
    const decodedMessage = tempDiv.textContent || tempDiv.innerText;

    const toast = document.createElement('div');
    toast.className = 'toast show ' + type;
    toast.textContent = decodedMessage;

    const closeButton = document.createElement('button');
    closeButton.className = 'close-btn';
    closeButton.innerHTML = '&times;';
    closeButton.onclick = () => {
        toast.remove();
        adjustToasts();
    };
    toast.appendChild(closeButton);

    document.body.appendChild(toast);
    toast.style.bottom = `${toastBottomOffset}px`;
    toastBottomOffset += 70;

    if (time) {
        setTimeout(() => {
            toast.remove();
            adjustToasts();
        }, time);
    }
}

function adjustToasts() {
    const toasts = document.querySelectorAll('.toast');
    toastBottomOffset = 30;
    toasts.forEach(toast => {
        toast.style.bottom = `${toastBottomOffset}px`;
        toastBottomOffset += 70;
    });
}
