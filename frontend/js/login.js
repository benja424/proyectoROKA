// Capturamos el formulario y el elemento de mensaje de error
const formulario = document.getElementById('loginForm');
const mensaje    = document.getElementById('mensaje');

formulario.addEventListener('submit', async (e) => {
    e.preventDefault(); // Evitamos que recargue la página

    const user = document.getElementById('usuario').value;
    const pass = document.getElementById('contrasena').value;

    try {
        // Llamamos a la API de login
        const response = await fetch('/sigsm/api/login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user, pass })
        });

        const datos = await response.json();

        // Si la respuesta no es 200, mostramos el error
        if (!response.ok) {
            mensaje.textContent = datos.error;
            return;
        }

        // Login correcto — redirigimos al portal admin
        window.location.href = '/sigsm/frontend/portal-admin.html';

    } catch (error) {
        console.error(error);
        mensaje.textContent = 'Error de conexión con el servidor';
    }
});