import '@tabler/core/dist/js/tabler.min.js';
document.addEventListener('click', (event) => {
 const button=event.target.closest('[data-password-toggle]');if(!button)return;
 const input=document.getElementById(button.dataset.passwordToggle);if(!input)return;
 const show=input.type==='password';input.type=show?'text':'password';
 button.setAttribute('aria-pressed',String(show));button.setAttribute('aria-label',show?'Hide password':'Show password');
 button.querySelector('i').className=show?'bi bi-eye-slash':'bi bi-eye';
});
