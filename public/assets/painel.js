// Única coisa que precisa de JavaScript: confirmar antes de ações destrutivas.
// (a CSP do painel bloqueia script inline, por isso o arquivo separado)
document.addEventListener('submit', (event) => {
  const message = event.target.dataset.confirm;
  if (message && !window.confirm(message)) {
    event.preventDefault();
  }
});
