// Funciones comunes a todas las páginas. Se carga antes que el script de cada página.
const API = '/sigsm/api';

// Atajo para buscar un elemento por su id
const $ = (id) => document.getElementById(id);

// Escapa texto antes de meterlo en el HTML: evita que un dato cargado por un usuario
// se interprete como código (inyección de HTML/JavaScript)
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) =>
  ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

// Muestra un mensaje en un elemento: verde si ok, rojo si es un error, oculto si está vacío
function mostrarMensaje(elemento, texto, ok) {
  elemento.textContent = texto;
  elemento.className = 'mensaje ' + (texto ? (ok ? 'mensaje-ok' : 'mensaje-err') : '');
}
