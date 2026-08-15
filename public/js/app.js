document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-confirm]').forEach((form) => form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm)) event.preventDefault();
    }));
    document.querySelectorAll('[data-copy]').forEach((button) => button.addEventListener('click', async () => {
        const source = document.querySelector(button.dataset.copy);
        await navigator.clipboard.writeText(source.value || source.textContent);
        const old = button.textContent; button.textContent = 'Copiado!'; setTimeout(() => button.textContent = old, 1800);
    }));
    document.querySelectorAll('[data-file-preview]').forEach((input) => input.addEventListener('change', () => {
        const preview = document.querySelector(input.dataset.filePreview);
        if (input.files?.[0] && preview) { preview.src = URL.createObjectURL(input.files[0]); preview.hidden = false; }
    }));
});
