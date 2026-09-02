// Verifica si hay sesión activa — si no, redirige al login
async function verificarSesion() {
    try {
        const response = await fetch('/sigsm/api/sesion');
        const datos    = await response.json();

        if (!datos.autenticado) {
            window.location.href = '/sigsm/frontend/login.html';
            return null;
        }

        return datos.usuario;

    } catch (error) {
        console.error(error);
        window.location.href = '/sigsm/frontend/login.html';
        return null;
    }
}