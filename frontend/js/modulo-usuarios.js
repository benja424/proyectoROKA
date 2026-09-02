// Módulo de gestión de usuarios — solo root
document.addEventListener('DOMContentLoaded', async () => {

    // Verificamos sesión y que sea root
    const usuario = await verificarSesion();
    if (usuario.rol !== 'root') {
        window.location.href = '/sigsm/frontend/portal-admin.html';
        return;
    }

    // Cargar lista de usuarios
    await cargarUsuarios();

    // Formulario de registro
    const form     = document.getElementById('formUsuario');
    const mensaje  = document.getElementById('mensaje-form');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const datos = {
            ci:        document.getElementById('ci').value.trim(),
            nombre:    document.getElementById('nombre').value.trim(),
            apellido:  document.getElementById('apellido').value.trim(),
            user_name: document.getElementById('user_name').value.trim(),
            pass:      document.getElementById('pass').value,
            rol:       document.getElementById('rol').value
        };

        // Validación básica
        if (!datos.ci || !datos.nombre || !datos.apellido || !datos.user_name || !datos.pass || !datos.rol) {
            mensaje.textContent = 'Completá todos los campos.';
            mensaje.className   = 'mensaje-err';
            return;
        }

        try {
            const response = await fetch('/sigsm/api/usuarios', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify(datos)
            });

            const result = await response.json();

            if (!response.ok) {
                mensaje.textContent = result.error;
                mensaje.className   = 'mensaje-err';
                return;
            }

            mensaje.textContent = 'Usuario registrado correctamente.';
            mensaje.className   = 'mensaje-ok';
            form.reset();
            await cargarUsuarios();

        } catch (error) {
            console.error(error);
            mensaje.textContent = 'Error de conexión con el servidor.';
            mensaje.className   = 'mensaje-err';
        }
    });
});

// Carga y renderiza la lista de usuarios
async function cargarUsuarios() {
    const lista = document.getElementById('lista-usuarios');

    try {
        const response = await fetch('/sigsm/api/usuarios');
        const usuarios = await response.json();

        if (usuarios.length === 0) {
            lista.innerHTML = '<p class="cargando">No hay usuarios registrados.</p>';
            return;
        }

        lista.innerHTML = usuarios.map(u => `
            <div class="doc-fila">
                <div class="doc-fila__info">
                    <div class="doc-fila__nombre">${u.nombre} ${u.apellido}</div>
                    <div class="doc-fila__detalle">CI: ${u.ci} · Usuario: ${u.user_name}</div>
                </div>
                <span class="doc-fila__rol">${u.rol.replace(/_/g, ' ')}</span>
                <button class="btn-eliminar" data-ci="${u.ci}">Eliminar</button>
            </div>
        `).join('');

        // Botones de eliminar
        lista.querySelectorAll('.btn-eliminar').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (!confirm('¿Seguro que querés eliminar este usuario?')) return;

                try {
                    const response = await fetch('/sigsm/api/usuarios', {
                        method:  'DELETE',
                        headers: { 'Content-Type': 'application/json' },
                        body:    JSON.stringify({ ci: btn.dataset.ci })
                    });

                    const result = await response.json();

                    if (!response.ok) {
                        alert(result.error);
                        return;
                    }

                    await cargarUsuarios();

                } catch (error) {
                    console.error(error);
                    alert('Error de conexión con el servidor.');
                }
            });
        });

    } catch (error) {
        console.error(error);
        lista.innerHTML = '<p class="cargando">Error al cargar usuarios.</p>';
    }
}