// Loading states para formularios con [data-loading-form]:
//   - Al hacer submit, deshabilita el boton submit y le cambia el texto al
//     valor de data-loading-text (si existe).
//   - Si la pagina se queda bloqueada por mas de 10s (network error, etc.),
//     re-enabilita el boton. Si todo funciona, el redirect del server llega
//     antes y nunca vemos el restore.
//
// Uso en blade:
//   <form action="..." method="POST" data-loading-form>
//     @csrf
//     <button type="submit" data-loading-text="Procesando...">Hacer algo</button>
//   </form>
document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (!form.hasAttribute('data-loading-form')) return;

    const btn = form.querySelector('button[type="submit"], input[type="submit"]');
    if (!btn) return;

    const originalText = btn.textContent;
    btn.disabled = true;
    if (btn.dataset.loadingText) {
        btn.textContent = btn.dataset.loadingText;
    }

    setTimeout(() => {
        if (btn.disabled) {
            btn.disabled = false;
            btn.textContent = originalText;
        }
    }, 10_000);
});
