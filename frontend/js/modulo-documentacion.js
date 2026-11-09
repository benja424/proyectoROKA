// Módulo Documentación — panel de funcionarios (documentos y categorías)
// Las validaciones de acá son para avisar rápido; las reales se hacen en el servidor.

const MAX_BYTES = 10 * 1024 * 1024;
const NUEVA = '__nueva__';      // valor de la opción "+ Crear categoría" (documentos y encuestas)

let editandoId = null;
let documentos = [];

const mensaje = (texto, ok) => mostrarMensaje($('form-mensaje'), texto, ok);

// ---------- Categorías (las comparten documentos y encuestas) ----------
// Llena un <select> con las categorías, manteniendo lo que estaba elegido
function llenarCategorias(select, cats, textoVacio, conCrear) {
  const actual = select.value;
  select.innerHTML = `<option value="">${textoVacio}</option>`;
  cats.forEach((c) => select.add(new Option(c.nombre, c.id_categoria)));
  if (conCrear) select.add(new Option('+ Crear categoría', NUEVA));
  if ([...select.options].some((o) => o.value === actual)) select.value = actual;
}

async function cargarCategorias() {
  const cats = await (await fetch(API + '/categorias')).json();
  llenarCategorias($('doc-categoria'), cats, 'Seleccioná una categoría', true);
  llenarCategorias($('enc-categoria'), cats, 'Seleccioná una categoría', true);
  llenarCategorias($('f-categoria'), cats, 'Todas las categorías', false);
  alternarNueva('doc-categoria', 'campo-nueva', 'doc-nueva');
  alternarNueva('enc-categoria', 'enc-campo-nueva', 'enc-nueva');
}

// Muestra el campo "nombre de la nueva categoría" solo si se eligió "+ Crear categoría"
function alternarNueva(selectId, campoId, inputId) {
  const nueva = $(selectId).value === NUEVA;
  $(campoId).hidden = !nueva;
  if (!nueva) $(inputId).value = '';
}
const mostrarNueva = () => alternarNueva('doc-categoria', 'campo-nueva', 'doc-nueva');

// ---------- Listado ----------
async function cargarDocumentos() {
  const q = new URLSearchParams();
  if ($('f-texto').value.trim()) q.set('texto', $('f-texto').value.trim());
  if ($('f-categoria').value) q.set('id_categoria', $('f-categoria').value);

  const lista = $('lista-docs');
  try {
    documentos = await (await fetch(API + '/documentos?' + q)).json();
  } catch (e) {
    lista.innerHTML = '<p class="vacio">Error al cargar los documentos.</p>';
    return;
  }

  if (!documentos.length) {
    lista.innerHTML = '<p class="vacio">No hay documentos para mostrar.</p>';
    return;
  }

  lista.innerHTML = documentos.map((d) => `
    <div class="doc-fila">
      <div class="doc-fila__info">
        <span class="doc-fila__titulo">${esc(d.nombre)}</span>
        <span class="doc-fila__detalle">${esc(d.categoria)}${d.subcategoria ? ' · ' + esc(d.subcategoria) : ''}
          · ${esc(String(d.fecha_subida).slice(0, 10).split('-').reverse().join('/'))}${d.subido_por ? ' · ' + esc(d.subido_por) : ''}</span>
      </div>
      <button class="btn-editar" data-qr="${d.id_documento}">QR</button>
      <a class="btn-ver" href="/sigsm/api/pdf?id=${d.id_documento}" target="_blank" rel="noopener">Ver</a>
      <button class="btn-editar" data-editar="${d.id_documento}">Editar</button>
      <button class="btn-eliminar" data-eliminar="${d.id_documento}">Eliminar</button>
    </div>`).join('');
}

// ---------- Validación en el navegador ----------
function validar() {
  const errores = [];
  if (!$('doc-nombre').value.trim()) errores.push('Indicá el nombre del documento.');

  const cat = $('doc-categoria').value;
  if (!cat) errores.push('Elegí una categoría.');
  if (cat === NUEVA && !$('doc-nueva').value.trim()) errores.push('Escribí el nombre de la nueva categoría.');

  const archivo = $('doc-archivo').files[0];
  if (!archivo && !editandoId) errores.push('Adjuntá el archivo PDF.');
  if (archivo) {
    const esPdf = archivo.type === 'application/pdf' || archivo.name.toLowerCase().endsWith('.pdf');
    if (!esPdf) errores.push('El archivo debe ser un PDF.');
    if (archivo.size > MAX_BYTES) errores.push('El PDF no puede superar los 10 MB.');
  }
  return errores;
}

// ---------- Guardar (alta o edición) ----------
$('form-doc').addEventListener('submit', async (e) => {
  e.preventDefault();
  mensaje('');

  const errores = validar();
  if (errores.length) { mensaje(errores.join(' '), false); return; }

  const datos = new FormData();
  datos.append('nombre', $('doc-nombre').value.trim());
  datos.append('subcategoria', $('doc-subcategoria').value.trim());
  datos.append('descripcion', $('doc-descripcion').value.trim());
  if ($('doc-categoria').value === NUEVA) datos.append('nueva_categoria', $('doc-nueva').value.trim());
  else datos.append('id_categoria', $('doc-categoria').value);
  if ($('doc-archivo').files[0]) datos.append('archivo', $('doc-archivo').files[0]);

  const boton = $('btn-guardar');
  boton.disabled = true;
  try {
    const r = await fetch(API + '/documentos' + (editandoId ? `?id=${editandoId}` : ''), { method: 'POST', body: datos });
    const res = await r.json().catch(() => ({}));
    if (r.status === 401) { window.location.href = '/sigsm/frontend/login.html'; return; }
    if (!r.ok) { mensaje(res.errores?.length ? res.errores.join(' ') : (res.error || 'Error del servidor'), false); return; }

    salirEdicion();
    mensaje(res.mensaje, true);
    await cargarCategorias();
    await cargarDocumentos();
  } catch (err) {
    mensaje('Error de conexión con el servidor.', false);
  } finally {
    boton.disabled = false;
  }
});

// ---------- Edición ----------
function editar(id) {
  const d = documentos.find((x) => Number(x.id_documento) === id);
  if (!d) return;
  editandoId = id;
  mensaje('');
  $('form-titulo').textContent = `Editando: ${d.nombre}`;
  $('btn-guardar').textContent = 'Guardar cambios';
  $('btn-cancelar').hidden = false;
  $('ayuda-archivo').textContent = 'Dejalo vacío para mantener el PDF actual. Solo PDF, hasta 10 MB.';

  $('doc-nombre').value = d.nombre;
  $('doc-categoria').value = String(d.id_categoria);
  $('doc-subcategoria').value = d.subcategoria || '';
  $('doc-descripcion').value = d.descripcion || '';
  $('doc-archivo').value = '';
  mostrarNueva();
  $('form-titulo').scrollIntoView({ behavior: 'smooth' });
}

function salirEdicion() {
  editandoId = null;
  $('form-doc').reset();
  $('form-titulo').textContent = 'Agregar documento';
  $('btn-guardar').textContent = 'Agregar documento';
  $('btn-cancelar').hidden = true;
  $('ayuda-archivo').textContent = 'Solo PDF, hasta 10 MB.';
  mostrarNueva();
}

$('btn-cancelar').addEventListener('click', () => { mensaje(''); salirEdicion(); });

// ---------- Eliminar ----------
async function eliminar(id) {
  const d = documentos.find((x) => Number(x.id_documento) === id);
  if (!confirm(`¿Eliminar "${d ? d.nombre : 'este documento'}"? También se borra el PDF.`)) return;
  try {
    const r = await fetch(API + `/documentos?id=${id}`, { method: 'DELETE' });
    const res = await r.json().catch(() => ({}));
    if (!r.ok) { mensaje(res.error || 'No se pudo eliminar.', false); return; }
    if (editandoId === id) salirEdicion();
    mensaje(res.mensaje, true);
    await cargarDocumentos();
  } catch (e) {
    mensaje('Error de conexión con el servidor.', false);
  }
}

$('lista-docs').addEventListener('click', (e) => {
  const b = e.target.closest('button');
  if (!b) return;
  if (b.dataset.qr) {
    const d = documentos.find((x) => Number(x.id_documento) === Number(b.dataset.qr));
    if (d) mostrarQr(d.nombre, `/sigsm/frontend/index.html?doc=${d.id_documento}`);
  }
  if (b.dataset.editar) editar(Number(b.dataset.editar));
  if (b.dataset.eliminar) eliminar(Number(b.dataset.eliminar));
});

// ---------- Código QR ----------
// El QR lleva la dirección con la que se abrió el panel (location.origin). Para que funcione
// desde un celular, el panel tiene que abrirse con la IP o el dominio del servidor, no con localhost.
function mostrarQr(titulo, ruta) {
  const url = location.origin + ruta;
  const qr = qrcode(0, 'M');          // 0 = tamaño automático, M = corrección de errores media
  qr.addData(url);
  qr.make();
  $('qr-imagen').innerHTML = qr.createSvgTag({ cellSize: 6, margin: 2, scalable: true });
  $('qr-titulo').textContent = titulo;
  $('qr-url').textContent = url;
  $('qr-fondo').hidden = false;
}

$('btn-qr-portal').addEventListener('click', () => mostrarQr('Documentación para pacientes', '/sigsm/frontend/index.html'));
$('qr-cerrar').addEventListener('click', () => { $('qr-fondo').hidden = true; });
$('qr-fondo').addEventListener('click', (e) => { if (e.target === $('qr-fondo')) $('qr-fondo').hidden = true; });
$('qr-imprimir').addEventListener('click', () => window.print());

// ---------- Inicio ----------
$('doc-categoria').addEventListener('change', mostrarNueva);
$('f-categoria').addEventListener('change', cargarDocumentos);
let espera;
$('f-texto').addEventListener('input', () => { clearTimeout(espera); espera = setTimeout(cargarDocumentos, 250); });

(async () => {
  const usuario = await verificarSesion();
  if (!usuario) return;
  if (!['root', 'Gestion_General', 'Division_Enfermeria'].includes(usuario.rol)) {
    window.location.href = '/sigsm/frontend/portal-admin.html';
    return;
  }
  try {
    await cargarCategorias();
    await cargarDocumentos();
  } catch (e) {
    mensaje('Error de conexión con el servidor.', false);
  }
})();
