// Portal de pacientes (index.html): lista pública de documentos, filtros y visor de PDF.
// Un QR apunta a index.html?doc=ID y abre directamente ese documento.
const sinTildes = (s) => String(s ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

let documentos = [];
let categoria  = '';   // id de la categoría elegida ('' = todas)

// PDF.js dibuja el PDF dentro de la página (sin botones de descargar ni imprimir)
pdfjsLib.GlobalWorkerOptions.workerSrc = '/sigsm/frontend/js/lib/pdfjs/pdf.worker.min.js';
let carga = 0;   // evita que se mezclen páginas si se abren dos documentos seguidos

// ---------- Lista ----------
function filtrar() {
  const texto = sinTildes($('buscador-input').value.trim());
  const visibles = documentos.filter((d) =>
    (!categoria || String(d.id_categoria) === categoria) &&
    (!texto || sinTildes(`${d.nombre} ${d.subcategoria || ''} ${d.descripcion || ''}`).includes(texto)));

  $('doc-lista').innerHTML = visibles.map((d) => `
    <a class="doc-fila" href="?doc=${d.id_documento}" data-doc="${d.id_documento}">
      <span class="doc-fila__info">
        <span class="doc-fila__titulo">${esc(d.nombre)}</span>
        <span class="doc-fila__detalle">${esc(d.categoria)}${d.subcategoria ? ' · ' + esc(d.subcategoria) : ''}</span>
      </span>
      <span class="doc-fila__ver">Ver →</span>
    </a>`).join('');

  $('doc-lista').hidden = visibles.length === 0;
  $('sin-resultados').style.display = visibles.length ? 'none' : 'block';
}

// Chips: solo las categorías que tienen documentos
function armarChips() {
  const usadas = new Map();
  documentos.forEach((d) => usadas.set(String(d.id_categoria), d.categoria));
  const cont = $('filtros');
  [...usadas].sort((a, b) => a[1].localeCompare(b[1])).forEach(([id, nombre]) => {
    const b = document.createElement('button');
    b.className = 'filtro-chip';
    b.dataset.filtro = id;
    b.setAttribute('aria-pressed', 'false');
    b.textContent = nombre;
    cont.appendChild(b);
  });
}

$('filtros').addEventListener('click', (e) => {
  const chip = e.target.closest('.filtro-chip');
  if (!chip) return;
  document.querySelectorAll('.filtro-chip').forEach((c) => c.setAttribute('aria-pressed', 'false'));
  chip.setAttribute('aria-pressed', 'true');
  categoria = chip.dataset.filtro;
  filtrar();
});

$('buscador-input').addEventListener('input', filtrar);

// ---------- Visor ----------
// Dibuja cada página del PDF en un <canvas>, al ancho del visor y con la nitidez de la pantalla
async function dibujarPdf(id) {
  const esta = ++carga;
  const marco = $('visor-marco');
  marco.innerHTML = '';
  $('visor-estado').textContent = 'Cargando documento…';

  try {
    const pdf = await pdfjsLib.getDocument('/sigsm/api/pdf?id=' + id).promise;
    const ancho = marco.clientWidth || 800;
    const nitidez = window.devicePixelRatio || 1;

    for (let n = 1; n <= pdf.numPages; n++) {
      if (esta !== carga) return;                  // se abrió otro documento
      const pagina = await pdf.getPage(n);
      const escala = ancho / pagina.getViewport({ scale: 1 }).width;
      const vista = pagina.getViewport({ scale: escala * nitidez });

      const canvas = document.createElement('canvas');
      canvas.width = vista.width;
      canvas.height = vista.height;
      canvas.setAttribute('aria-label', `Página ${n} de ${pdf.numPages}`);
      marco.appendChild(canvas);
      await pagina.render({ canvasContext: canvas.getContext('2d'), viewport: vista }).promise;
      if (n === 1) $('visor-estado').textContent = pdf.numPages > 1 ? `${pdf.numPages} páginas` : '';
    }
  } catch (e) {
    if (esta === carga) $('visor-estado').textContent = 'No se pudo mostrar el documento.';
  }
}

function mostrarVisor(d) {
  $('visor-titulo').textContent = d.nombre;
  $('visor-cat').textContent = d.categoria + (d.subcategoria ? ' · ' + d.subcategoria : '');
  $('visor-desc').textContent = d.descripcion || '';

  $('visor').hidden = false;
  $('seccion-lista').hidden = true;
  document.title = `${d.nombre} | S.I.G.S.M. — Hospital de Clínicas`;
  $('visor').scrollIntoView({ behavior: 'smooth' });
  dibujarPdf(d.id_documento);
}

function ocultarVisor() {
  carga++;                                         // corta un dibujo en curso
  $('visor').hidden = true;
  $('visor-marco').innerHTML = '';
  $('visor-estado').textContent = '';
  $('seccion-lista').hidden = false;
  document.title = 'Portal Usuario | S.I.G.S.M. — Hospital de Clínicas';
}

// Muestra lo que indique la URL (?doc=ID); así funcionan el QR y el botón "atrás" del navegador
function segunUrl() {
  const id = new URLSearchParams(location.search).get('doc');
  const d = id && documentos.find((x) => String(x.id_documento) === id);
  if (d) mostrarVisor(d);
  else ocultarVisor();
}

$('doc-lista').addEventListener('click', (e) => {
  const fila = e.target.closest('.doc-fila');
  if (!fila) return;
  e.preventDefault();
  history.pushState(null, '', '?doc=' + fila.dataset.doc);
  segunUrl();
});

$('visor-volver').addEventListener('click', () => {
  history.pushState(null, '', location.pathname);
  segunUrl();
});

window.addEventListener('popstate', segunUrl);

// Sin menú contextual sobre el documento (no ofrece "Guardar imagen como…")
$('visor-marco').addEventListener('contextmenu', (e) => e.preventDefault());

// ---------- Encuestas públicas ----------
async function cargarEncuestas() {
  try {
    const r = await fetch('/sigsm/api/encuestas');
    if (!r.ok) return;
    const lista = await r.json();
    if (!lista.length) return;                       // sin encuestas públicas, la sección no se muestra
    $('encuestas-grid').innerHTML = lista.map((e) => `
      <a href="/sigsm/frontend/encuesta.html?id=${e.id_encuesta}" class="encuesta-card">
        <span class="encuesta-card__cat">${esc(e.categoria)}</span>
        <span class="encuesta-card__nombre">${esc(e.titulo)}</span>
        <span class="encuesta-card__accion">Responder →</span>
      </a>`).join('');
    $('seccion-encuestas').hidden = false;
  } catch (e) { /* si falla, la sección queda oculta */ }
}

// ---------- Inicio ----------
cargarEncuestas();
(async () => {
  try {
    const r = await fetch('/sigsm/api/documentos');
    if (!r.ok) throw new Error();
    documentos = await r.json();
  } catch (e) {
    $('sin-resultados').textContent = 'No se pudieron cargar los documentos. Intentá de nuevo más tarde.';
  }
  armarChips();
  filtrar();
  segunUrl();
})();
