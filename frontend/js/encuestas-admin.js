// Módulo Documentación — sección de encuestas (panel de funcionarios)
// Usa las funciones de comun.js y las de categorías de modulo-documentacion.js (se cargan antes).
let encuestas = [];
let editandoEnc = null;

const mensajeEnc = (texto, ok) => mostrarMensaje($('enc-mensaje'), texto, ok);

async function pedir(url, opciones) {
  const r = await fetch(API + url, opciones);
  const res = await r.json().catch(() => ({}));
  if (r.status === 401) { window.location.href = '/sigsm/frontend/login.html'; throw new Error('sesion'); }
  if (!r.ok) {
    const err = new Error(res.errores?.length ? res.errores.join(' ') : (res.error || 'Error del servidor'));
    throw err;
  }
  return res;
}

const nuevaCatEnc = () => alternarNueva('enc-categoria', 'enc-campo-nueva', 'enc-nueva');

// ---------- Preguntas dinámicas ----------
function agregarPregunta(enunciado = '', tipo = 'Escala') {
  const fila = document.createElement('div');
  fila.className = 'pregunta';
  fila.innerHTML = `
    <span class="pregunta__num"></span>
    <input type="text" maxlength="200" placeholder="Escribí la pregunta" aria-label="Pregunta">
    <select aria-label="Tipo de pregunta">
      <option value="Escala">Escala 1 a 5</option>
      <option value="Texto">Texto libre</option>
    </select>
    <button type="button" class="btn-quitar" title="Quitar pregunta" aria-label="Quitar pregunta">✕</button>`;
  fila.querySelector('input').value = enunciado;
  fila.querySelector('select').value = tipo;
  $('enc-preguntas').appendChild(fila);
  numerar();
}

function numerar() {
  $('enc-preguntas').querySelectorAll('.pregunta__num').forEach((n, i) => { n.textContent = i + 1; });
}

$('enc-agregar').addEventListener('click', () => { agregarPregunta(); $('enc-preguntas').lastElementChild.querySelector('input').focus(); });
$('enc-preguntas').addEventListener('click', (e) => {
  if (!e.target.closest('.btn-quitar')) return;
  e.target.closest('.pregunta').remove();
  if (!$('enc-preguntas').children.length) agregarPregunta();
  numerar();
});

// ---------- Listado ----------
async function cargarEncuestas() {
  try {
    encuestas = await pedir('/encuestas?todas=1');
  } catch (e) {
    $('lista-enc').innerHTML = '<p class="vacio">Error al cargar las encuestas.</p>';
    return;
  }
  if (!encuestas.length) { $('lista-enc').innerHTML = '<p class="vacio">Todavía no hay encuestas.</p>'; return; }

  $('lista-enc').innerHTML = encuestas.map((e) => {
    const sinResp = Number(e.respuestas) === 0;
    const publica = e.estado === 'Publica';
    return `
    <div class="doc-fila">
      <div class="doc-fila__info">
        <span class="doc-fila__titulo">${esc(e.titulo)}</span>
        <span class="doc-fila__detalle">${esc(e.categoria)} · ${e.preguntas} pregunta${e.preguntas == 1 ? '' : 's'} · ${e.respuestas} respuesta${e.respuestas == 1 ? '' : 's'}</span>
      </div>
      <span class="tag-estado tag-estado--${publica ? 'publica' : 'privada'}">${publica ? 'Pública' : 'Privada'}</span>
      <button class="btn-ver" data-res="${e.id_encuesta}">Resultados</button>
      ${sinResp ? `<button class="btn-editar" data-editar-enc="${e.id_encuesta}">Editar</button>` : ''}
      <button class="btn-editar" data-estado="${publica ? 'Privada' : 'Publica'}" data-id="${e.id_encuesta}">${publica ? 'Ocultar' : 'Publicar'}</button>
      ${sinResp ? `<button class="btn-eliminar" data-eliminar-enc="${e.id_encuesta}">Eliminar</button>` : ''}
    </div>`;
  }).join('');
}

// ---------- Validación en el navegador ----------
function leerEncuesta() {
  const preguntas = [...$('enc-preguntas').querySelectorAll('.pregunta')]
    .map((f) => ({ enunciado: f.querySelector('input').value.trim(), tipo: f.querySelector('select').value }))
    .filter((p) => p.enunciado);

  const datos = {
    titulo: $('enc-titulo').value.trim(),
    descripcion: $('enc-descripcion').value.trim(),
    estado: $('enc-estado').value,
    preguntas
  };
  if ($('enc-categoria').value === NUEVA) datos.nueva_categoria = $('enc-nueva').value.trim();
  else datos.id_categoria = $('enc-categoria').value;

  const errores = [];
  if (!datos.titulo) errores.push('Indicá el título de la encuesta.');
  if (!$('enc-categoria').value) errores.push('Elegí una categoría.');
  if ($('enc-categoria').value === NUEVA && !datos.nueva_categoria) errores.push('Escribí el nombre de la nueva categoría.');
  if (!preguntas.length) errores.push('Agregá al menos una pregunta.');
  return { datos, errores };
}

$('form-enc').addEventListener('submit', async (e) => {
  e.preventDefault();
  mensajeEnc('');
  const { datos, errores } = leerEncuesta();
  if (errores.length) { mensajeEnc(errores.join(' '), false); return; }

  try {
    const res = await pedir('/encuestas' + (editandoEnc ? `?id=${editandoEnc}` : ''), {
      method: editandoEnc ? 'PUT' : 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(datos)
    });
    salirEdicionEnc();
    mensajeEnc(res.mensaje, true);
    await cargarCategorias();
    await cargarEncuestas();
  } catch (err) {
    if (err.message !== 'sesion') mensajeEnc(err.message, false);
  }
});

// ---------- Edición ----------
async function editarEncuesta(id) {
  try {
    const e = await pedir(`/encuestas?id=${id}`);
    editandoEnc = id;
    mensajeEnc('');
    $('enc-form-titulo').textContent = `Editando encuesta: ${e.titulo}`;
    $('enc-guardar').textContent = 'Guardar cambios';
    $('enc-cancelar').hidden = false;
    $('enc-titulo').value = e.titulo;
    $('enc-descripcion').value = e.descripcion || '';
    $('enc-estado').value = e.estado;
    $('enc-categoria').value = String(e.id_categoria);
    nuevaCatEnc();
    $('enc-preguntas').innerHTML = '';
    e.lista_preguntas.forEach((p) => agregarPregunta(p.enunciado, p.tipo));
    if (!e.lista_preguntas.length) agregarPregunta();
    $('enc-form-titulo').scrollIntoView({ behavior: 'smooth' });
  } catch (err) {
    if (err.message !== 'sesion') mensajeEnc(err.message, false);
  }
}

function salirEdicionEnc() {
  editandoEnc = null;
  $('form-enc').reset();
  $('enc-form-titulo').textContent = 'Crear encuesta';
  $('enc-guardar').textContent = 'Crear encuesta';
  $('enc-cancelar').hidden = true;
  $('enc-preguntas').innerHTML = '';
  agregarPregunta();
  nuevaCatEnc();
}

$('enc-cancelar').addEventListener('click', () => { mensajeEnc(''); salirEdicionEnc(); });

// ---------- Resultados ----------
async function verResultados(id) {
  try {
    const r = await pedir(`/encuestas?id=${id}&resultados=1`);
    const total = Number(r.encuesta.respuestas);
    $('res-titulo').textContent = `Resultados: ${r.encuesta.titulo}`;

    let html = `<p class="res-total">${total} respuesta${total === 1 ? '' : 's'} recibida${total === 1 ? '' : 's'}</p>`;
    if (total === 0) html += '<p class="vacio">Todavía nadie respondió esta encuesta.</p>';

    if (total > 0) {
      r.encuesta.lista_preguntas.forEach((p) => {
        html += `<div class="res-pregunta"><p class="res-enunciado">${p.orden}. ${esc(p.enunciado)}</p>`;

        if (p.tipo === 'Escala') {
          const e = r.escala.find((x) => x.id_pregunta == p.id_pregunta) || {};
          const cant = Number(e.cantidad || 0);
          html += `<p class="res-promedio">Promedio: ${cant ? e.promedio : '—'} / 5</p>`;
          for (let v = 5; v >= 1; v--) {
            const n = Number(e['v' + v] || 0);
            const pct = cant ? Math.round((n / cant) * 100) : 0;
            html += `<div class="res-barra"><span>${v}</span>
              <span class="res-barra__fondo"><span class="res-barra__relleno" style="width:${pct}%; display:block"></span></span>
              <span class="res-barra__num">${n}</span></div>`;
          }
        } else {
          const textos = r.textos.filter((t) => t.id_pregunta == p.id_pregunta);
          html += textos.length
            ? `<ul class="res-textos">${textos.map((t) => `<li>${esc(t.texto)}</li>`).join('')}</ul>`
            : '<p class="ayuda">Sin comentarios.</p>';
        }
        html += '</div>';
      });
    }

    $('res-contenido').innerHTML = html;
    $('resultados').hidden = false;
    $('resultados').scrollIntoView({ behavior: 'smooth' });
  } catch (err) {
    if (err.message !== 'sesion') alert(err.message);
  }
}

$('res-cerrar').addEventListener('click', () => { $('resultados').hidden = true; });

// ---------- Acciones de la lista ----------
$('lista-enc').addEventListener('click', async (e) => {
  const b = e.target.closest('button');
  if (!b) return;

  if (b.dataset.res) verResultados(b.dataset.res);
  if (b.dataset.editarEnc) editarEncuesta(Number(b.dataset.editarEnc));

  try {
    if (b.dataset.estado) {
      await pedir(`/encuestas?id=${b.dataset.id}`, {
        method: 'PATCH', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ estado: b.dataset.estado })
      });
      await cargarEncuestas();
    }
    if (b.dataset.eliminarEnc) {
      if (!confirm('¿Eliminar esta encuesta?')) return;
      await pedir(`/encuestas?id=${b.dataset.eliminarEnc}`, { method: 'DELETE' });
      if (editandoEnc === Number(b.dataset.eliminarEnc)) salirEdicionEnc();
      await cargarEncuestas();
    }
  } catch (err) {
    if (err.message !== 'sesion') alert(err.message);
  }
});

// ---------- Inicio ----------
$('enc-categoria').addEventListener('change', nuevaCatEnc);
agregarPregunta();
cargarEncuestas();     // las categorías las carga modulo-documentacion.js
