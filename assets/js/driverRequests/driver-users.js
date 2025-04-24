document.addEventListener('DOMContentLoaded', () => {
    // Attach event listener for dynamically loaded elements
    document.addEventListener('input', (event) => {
        if (event.target.name === 'edit_password') {
            const notice = event.target.nextElementSibling;
            if (event.target.value.length > 0 && event.target.value.length < 8) {
                notice.textContent = 'Password must be at least 8 characters.';
                notice.style.color = 'red';
            } else {
                notice.textContent = '';
            }
        }
    });
});
