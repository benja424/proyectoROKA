// Módulo de gestión de usuarios — solo root
document.addEventListener('DOMContentLoaded', async () => {

    // Verificamos sesión y que sea root
    const usuario = await verificarSesion();
    if (!usuario) return;
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

        // Validación en el navegador (las mismas reglas que ServicioUsuario.php en el servidor)
        const errores = validarUsuario(datos);
        if (errores.length) {
            mostrarMensaje(mensaje, errores.join(' '), false);
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
                mostrarMensaje(mensaje, result.error, false);
                return;
            }

            mostrarMensaje(mensaje, 'Usuario registrado correctamente.', true);
            form.reset();
            await cargarUsuarios();

        } catch (error) {
            console.error(error);
            mostrarMensaje(mensaje, 'Error de conexión con el servidor.', false);
        }
    });
});

// Mismas reglas que el servidor: CI de 7 u 8 dígitos, nombre y apellido con letras,
// usuario de 3 a 30 caracteres y contraseña de al menos 8
function validarUsuario(d) {
    const errores = [];
    const nombreValido = /^[\p{L}][\p{L} '\-]{1,29}$/u;
    if (!/^\d{7,8}$/.test(d.ci))                errores.push('La CI debe tener 7 u 8 dígitos, sin puntos ni guion.');
    if (!nombreValido.test(d.nombre))           errores.push('El nombre debe tener entre 2 y 30 letras.');
    if (!nombreValido.test(d.apellido))         errores.push('El apellido debe tener entre 2 y 30 letras.');
    if (!/^[a-zA-Z0-9._]{3,30}$/.test(d.user_name)) errores.push('El usuario debe tener entre 3 y 30 caracteres: letras, números, punto o guion bajo.');
    if (d.pass.length < 8 || d.pass.length > 72) errores.push('La contraseña debe tener entre 8 y 72 caracteres.');
    if (!d.rol)                                 errores.push('Elegí un rol.');
    return errores;
}

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

        lista.innerHTML = usuarios.map(u => {
            const activo = u.estado === 'Activo';
            return `
            <div class="doc-fila${activo ? '' : ' doc-fila--inactiva'}">
                <div class="doc-fila__info">
                    <div class="doc-fila__nombre">${esc(u.nombre)} ${esc(u.apellido)}${activo ? '' : ' <span class="tag-inactivo">Inhabilitado</span>'}</div>
                    <div class="doc-fila__detalle">CI: ${esc(u.ci)} · Usuario: ${esc(u.user_name)}</div>
                </div>
                <span class="doc-fila__rol">${u.rol.replace(/_/g, ' ')}</span>
                <button class="${activo ? 'btn-eliminar' : 'btn-activar'}" data-ci="${esc(u.ci)}"
                        data-estado="${activo ? 'Inactivo' : 'Activo'}">${activo ? 'Inhabilitar' : 'Activar'}</button>
            </div>`;
        }).join('');

        // Botones de cambio de estado
        lista.querySelectorAll('button[data-estado]').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (btn.dataset.estado === 'Inactivo'
                    && !confirm('¿Inhabilitar este usuario? No podrá iniciar sesión, pero su historial se conserva.')) return;

                try {
                    const response = await fetch('/sigsm/api/usuarios', {
                        method:  'PATCH',
                        headers: { 'Content-Type': 'application/json' },
                        body:    JSON.stringify({ ci: btn.dataset.ci, estado: btn.dataset.estado })
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