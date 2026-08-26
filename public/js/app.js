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
    document.querySelectorAll('[data-cpf-mask]').forEach((input) => {
        const formatCpf = (value) => {
            const digits = value.replace(/\D/g, '').slice(0, 11);
            return digits
                .replace(/^(\d{3})(\d)/, '$1.$2')
                .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
                .replace(/\.(\d{3})(\d)/, '.$1-$2');
        };
        input.value = formatCpf(input.value);
        input.addEventListener('input', () => { input.value = formatCpf(input.value); });
    });
});
