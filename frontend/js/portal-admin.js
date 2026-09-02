document.addEventListener('DOMContentLoaded', async () => {

    const usuario = await verificarSesion();

    // Mostramos nombre y rol en el header
    const nombre = document.querySelector('.header-usuario__nombre');
    const rol    = document.querySelector('.header-usuario__rol');
    const bienvenida = document.getElementById('bienvenida-nombre');

    if (bienvenida) bienvenida.textContent = usuario.nombre;
    if (nombre) nombre.textContent = usuario.nombre + ' ' + usuario.apellido;
    if (rol)    rol.textContent    = usuario.rol.replace(/_/g, ' ');

    // Mostramos solo las cards que corresponden al rol del usuario
    const cards = document.querySelectorAll('.modulo-card');
    cards.forEach(card => {
        const rolesPermitidos = card.dataset.roles.split(',');
        if (!rolesPermitidos.includes(usuario.rol)) {
            card.style.display = 'none';
        }
    });

    // Dark mode
    const toggle = document.getElementById('dark-toggle');
    if (toggle) {
        if (localStorage.getItem('dark') === '1') {
            document.body.classList.add('dark');
            toggle.checked = true;
        }
        toggle.addEventListener('change', () => {
            document.body.classList.toggle('dark', toggle.checked);
            localStorage.setItem('dark', toggle.checked ? '1' : '0');
        });
    }

    // Logout
    const btnLogout = document.querySelector('.btn-logout');
    if (btnLogout) {
        btnLogout.addEventListener('click', async (e) => {
            e.preventDefault();
            await fetch('/sigsm/api/logout');
            window.location.href = '/sigsm/frontend/login.html';
        });
    }
});