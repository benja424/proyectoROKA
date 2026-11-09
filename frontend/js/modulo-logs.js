// Registro de actividad — solo root, solo lectura

let acciones = []; // [{modulo, accion}] para filtrar acciones según el módulo elegido

// Texto amigable para el registro afectado según el módulo
function registro(l) {
  if (!l.id_registro) return '';
  if (l.modulo === 'Traslados') return `Traslado #${esc(l.id_registro)}`;
  if (l.modulo === 'Usuarios')  return `CI ${esc(l.id_registro)}`;
  return `#${esc(l.id_registro)}`;
}

function formatoFecha(f) {
  const [dia, hora] = String(f).split(' ');
  const [a, m, d] = dia.split('-');
  return `${d}/${m}/${a} ${hora.slice(0, 5)}`;
}

function llenarAcciones() {
  const sel = $('f-accion');
  const actual = sel.value;
  const modulo = $('f-modulo').value;
  sel.innerHTML = '<option value="">Todas</option>';
  [...new Set(acciones.filter((a) => !modulo || a.modulo === modulo).map((a) => a.accion))]
    .forEach((a) => sel.add(new Option(a.replace(/_/g, ' '), a)));
  if ([...sel.options].some((o) => o.value === actual)) sel.value = actual;
}

async function cargarOpciones() {
  const r = await fetch('/sigsm/api/logs?opciones=1');
  if (!r.ok) throw new Error();
  const op = await r.json();
  op.modulos.forEach((m) => $('f-modulo').add(new Option(m, m)));
  op.usuarios.forEach((u) => $('f-usuario').add(new Option(`${u.usuario} (${u.usuario_ci})`, u.usuario_ci)));
  acciones = op.acciones;
  llenarAcciones();
}

async function cargarLogs() {
  const q = new URLSearchParams();
  [['modulo', 'f-modulo'], ['accion', 'f-accion'], ['usuario_ci', 'f-usuario'], ['desde', 'f-desde'], ['hasta', 'f-hasta']]
    .forEach(([k, id]) => { if ($(id).value) q.set(k, $(id).value); });

  const lista = $('lista');
  try {
    const r = await fetch('/sigsm/api/logs?' + q);
    if (r.status === 401 || r.status === 403) { window.location.href = '/sigsm/frontend/portal-admin.html'; return; }
    const logs = await r.json();

    $('resumen').textContent = logs.length === 200
      ? 'Mostrando los 200 registros más recientes. Usá los filtros para acotar.'
      : `${logs.length} registro${logs.length === 1 ? '' : 's'}`;

    if (!logs.length) { lista.innerHTML = '<p class="vacio">No hay registros con esos filtros.</p>'; return; }

    lista.innerHTML = logs.map((l) => `
      <div class="log-fila">
        <span class="log-fila__fecha">${formatoFecha(l.fecha)}</span>
        <span class="log-fila__modulo">${esc(l.modulo)}</span>
        <span class="log-fila__accion">${esc(l.accion.replace(/_/g, ' '))} <span class="log-fila__registro">${registro(l)}</span></span>
        <span class="log-fila__usuario">${esc(l.usuario)} <span class="log-fila__rol">${esc(l.rol.replace(/_/g, ' '))}</span></span>
      </div>`).join('');
  } catch (e) {
    lista.innerHTML = '<p class="vacio">Error al cargar los registros.</p>';
  }
}

$('f-modulo').addEventListener('change', () => { llenarAcciones(); cargarLogs(); });
['f-accion', 'f-usuario', 'f-desde', 'f-hasta'].forEach((id) => $(id).addEventListener('change', cargarLogs));
$('btn-limpiar').addEventListener('click', () => {
  ['f-modulo', 'f-accion', 'f-usuario', 'f-desde', 'f-hasta'].forEach((id) => { $(id).value = ''; });
  llenarAcciones();
  cargarLogs();
});

(async () => {
  const usuario = await verificarSesion();
  if (!usuario) return;
  if (usuario.rol !== 'root') { window.location.href = '/sigsm/frontend/portal-admin.html'; return; }
  try { await cargarOpciones(); } catch (e) { /* los filtros quedan vacíos */ }
  cargarLogs();
})();
