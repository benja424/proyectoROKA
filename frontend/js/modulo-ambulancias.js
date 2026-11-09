// Módulo Ambulancias — traslados.
// Las reglas reales se validan en el servidor (ReglasTraslado.php); acá solo se replican
// para mostrar/ocultar opciones y filtrar vehículos.

const SOLO_AMBULANCIA = ['Organo', 'Muestra_biologica'];
const SIGUIENTES = {
  Solicitado: ['En_curso', 'Cancelado'],
  En_curso:   ['En_destino', 'Cancelado'],
  En_destino: ['Retornado'],
  Retornado:  [],
  Cancelado:  []
};
const NOMBRES = {
  Organo: 'Órgano', Muestra_biologica: 'Muestra biológica', Insumo_medico: 'Insumo médico',
  Documento: 'Documento', Equipamiento: 'Equipamiento', Paciente: 'Paciente'
};

let editandoId = null;   // id del traslado que se está editando (null = alta)
let traslados = [];      // último historial cargado

const txt = (s) => String(s ?? '').replace(/_/g, ' ');
const clase = (s) => String(s).toLowerCase().replace(/_/g, '-');

async function api(ruta, opciones) {
  const r = await fetch(API + ruta, opciones);
  const datos = await r.json().catch(() => ({}));
  if (r.status === 401) { window.location.href = '/sigsm/frontend/login.html'; throw new Error('sesion'); }
  if (!r.ok) {
    const err = new Error(datos.error || 'Error del servidor');
    err.errores = datos.errores || [];
    throw err;
  }
  return datos;
}

const mensaje = (texto, ok) => mostrarMensaje($('form-mensaje'), texto, ok);

// Fecha y hora actual en el formato del campo datetime-local (AAAA-MM-DDTHH:MM)
function ahoraLocal() {
  const d = new Date();
  d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
  return d.toISOString().slice(0, 16);
}

// No se pueden pedir traslados en el pasado: el calendario no deja elegir antes de ahora
function actualizarMinimo() {
  $('hora_salida').min = ahoraLocal();
}

// ---------- Reglas replicadas (solo UI) ----------
function tipoRequerido() {
  const tipo = $('tipo_elemento').value;
  if (tipo === 'Paciente') {
    const exige = ['requiere_oxigeno', 'requiere_aislamiento', 'requiere_camilla', 'no_autonomo'].some((id) => $(id).checked);
    return exige ? 'Ambulancia' : '';
  }
  return SOLO_AMBULANCIA.includes(tipo) ? 'Ambulancia' : '';
}

// ---------- Flota ----------
async function cargarFlota() {
  const lista = await api('/vehiculos');
  $('flota').innerHTML = lista.map((v) => {
    const disp = v.disponibilidad;
    const info = disp === 'En_traslado'
      ? `Destino: ${esc(v.destino_actual)} — salida ${esc((v.salida_actual || '').slice(0, 16))}`
      : disp === 'No_disponible' ? 'En mantenimiento.' : 'Listo para asignar.';
    return `<div class="ambulancia-card">
      <div class="ambulancia-card__header">
        <span class="ambulancia-card__nombre">${esc(v.tipo)}</span>
        <span class="ambulancia-card__patente">${esc(v.matricula)}</span>
      </div>
      <span class="estado estado--${clase(disp)}">${txt(disp)}</span>
      <p class="ambulancia-card__info">${info}</p>
    </div>`;
  }).join('');

  // Filtro de historial por vehículo
  const sel = $('f-matricula');
  if (sel.options.length === 1) {
    lista.forEach((v) => sel.add(new Option(`${v.matricula} (${v.tipo})`, v.matricula)));
  }
}

// ---------- Selector de vehículo (depende de tipo, requisitos y hora) ----------
async function cargarVehiculos(seleccion) {
  const actual = seleccion ?? $('matricula').value;
  const req = tipoRequerido();
  const q = new URLSearchParams();
  if ($('hora_salida').value) q.set('salida', $('hora_salida').value);
  if (req) q.set('requerido', req);
  if (editandoId) q.set('excluir', editandoId);

  const lista = await api('/vehiculos?' + q);
  const sel = $('matricula');
  sel.innerHTML = '<option value="">Elegí un vehículo</option>';
  lista.filter((v) => v.compatible).forEach((v) => {
    const o = new Option(`${v.matricula} — ${v.tipo}${v.libre ? '' : ' (no disponible)'}`, v.matricula);
    o.disabled = !v.libre;
    sel.add(o);
  });
  if ([...sel.options].some((o) => o.value === actual && !o.disabled)) sel.value = actual;

  const ayuda = $('ayuda-vehiculo');
  ayuda.className = 'ayuda' + (req ? ' ayuda--aviso' : '');
  ayuda.textContent = req
    ? 'Este traslado solo puede hacerse en ambulancia.'
    : (!$('hora_salida').value ? 'Indicá la hora de salida para ver qué vehículos están libres.' : '');
}

// ---------- Tipo de elemento ----------
function aplicarTipo() {
  const esPaciente = $('tipo_elemento').value === 'Paciente';
  $('bloque-paciente').hidden = !esPaciente;
  $('bloque-item').hidden = esPaciente;
  cargarVehiculos().catch(() => {});
}

async function buscarPaciente() {
  const doc = $('documento').value.trim();
  const ayuda = $('ayuda-paciente');
  if (!/^\d{6,10}$/.test(doc)) { ayuda.textContent = ''; return; }
  try {
    const r = await api('/pacientes?documento=' + doc);
    ayuda.textContent = r.existe ? 'Paciente ya registrado.' : 'Paciente nuevo: se registrará con este documento.';
  } catch (e) { ayuda.textContent = ''; }
}

// ---------- Guardar ----------
function leerFormulario() {
  const d = {
    tipo_elemento: $('tipo_elemento').value,
    razon: $('razon').value, destino: $('destino').value,
    hora_salida: $('hora_salida').value, matricula: $('matricula').value,
    chofer: $('chofer').value, enfermero: $('enfermero').value,
    observaciones: $('observaciones').value
  };
  if (d.tipo_elemento === 'Paciente') {
    d.documento = $('documento').value.trim();
    d.requiere_oxigeno = $('requiere_oxigeno').checked;
    d.requiere_aislamiento = $('requiere_aislamiento').checked;
    d.requiere_camilla = $('requiere_camilla').checked;
    d.autonomo = !$('no_autonomo').checked;
  } else {
    d.item_nombre = $('item_nombre').value;
    d.item_caracteristicas = $('item_caracteristicas').value;
  }
  return d;
}

// Validación en el navegador (la definitiva la hace el servidor)
function validarFormulario(d) {
  const errores = [];
  const faltan = [['razon', 'la razón'], ['destino', 'el destino'], ['hora_salida', 'la hora de salida'],
                  ['matricula', 'el vehículo'], ['chofer', 'el chofer'], ['enfermero', 'el enfermero']]
    .filter(([campo]) => !String(d[campo] ?? '').trim()).map(([, nombre]) => nombre);
  if (faltan.length) errores.push('Falta indicar ' + faltan.join(', ') + '.');
  if (d.hora_salida && d.hora_salida < ahoraLocal()) errores.push('La hora de salida no puede ser anterior al momento actual.');
  if (d.tipo_elemento === 'Paciente' && !/^\d{6,10}$/.test(d.documento)) errores.push('El documento del paciente debe tener entre 6 y 10 dígitos.');
  if (d.tipo_elemento !== 'Paciente' && !String(d.item_nombre).trim()) errores.push('Indicá el nombre de lo que se traslada.');
  return errores;
}

$('form-traslado').addEventListener('submit', async (e) => {
  e.preventDefault();
  mensaje('');
  const datos = leerFormulario();
  const errores = validarFormulario(datos);
  if (errores.length) { mensaje(errores.join(' '), false); return; }
  try {
    const url = editandoId ? `/traslados?id=${editandoId}` : '/traslados';
    const r = await api(url, {
      method: editandoId ? 'PUT' : 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(datos)
    });
    salirEdicion();
    mensaje(r.mensaje, true);
    await refrescar();
  } catch (err) {
    if (err.message === 'sesion') return;
    mensaje(err.errores.length ? err.errores.join(' ') : err.message, false);
  }
});

function limpiarFormulario() {
  $('form-traslado').reset();
  $('ayuda-paciente').textContent = '';
}

function salirEdicion() {
  editandoId = null;
  limpiarFormulario();
  $('form-titulo').textContent = 'Registrar nuevo pedido de traslado';
  $('btn-guardar').textContent = 'Registrar traslado';
  $('btn-cancelar-edicion').hidden = true;
  aplicarTipo();
}

$('btn-cancelar-edicion').addEventListener('click', () => { mensaje(''); salirEdicion(); });

function editar(id) {
  const t = traslados.find((x) => Number(x.id_peticion) === id);
  if (!t) return;
  editandoId = id;
  mensaje('');
  $('form-titulo').textContent = `Editando traslado #${id}`;
  $('btn-guardar').textContent = 'Guardar cambios';
  $('btn-cancelar-edicion').hidden = false;

  $('tipo_elemento').value = t.tipo_elemento;
  $('razon').value = t.razon;
  $('destino').value = t.destino;
  $('hora_salida').value = String(t.hora_salida).slice(0, 16).replace(' ', 'T');
  $('chofer').value = t.chofer;
  $('enfermero').value = t.enfermero;
  $('observaciones').value = t.observaciones || '';
  $('documento').value = t.documento || '';
  $('requiere_oxigeno').checked = t.requiere_oxigeno == 1;
  $('requiere_aislamiento').checked = t.requiere_aislamiento == 1;
  $('requiere_camilla').checked = t.requiere_camilla == 1;
  $('no_autonomo').checked = t.tipo_elemento === 'Paciente' && t.autonomo == 0;
  $('item_nombre').value = t.item_nombre || '';
  $('item_caracteristicas').value = t.item_caracteristicas || '';

  $('bloque-paciente').hidden = t.tipo_elemento !== 'Paciente';
  $('bloque-item').hidden = t.tipo_elemento === 'Paciente';
  cargarVehiculos(t.matricula).then(() => { $('matricula').value = t.matricula; });
  $('form-titulo').scrollIntoView({ behavior: 'smooth' });
}

// ---------- Historial ----------
async function cargarHistorial() {
  const q = new URLSearchParams();
  if ($('f-documento').value.trim()) q.set('documento', $('f-documento').value.trim());
  if ($('f-matricula').value) q.set('matricula', $('f-matricula').value);
  if ($('f-estado').value) q.set('estado', $('f-estado').value);

  traslados = await api('/traslados?' + q);
  const cont = $('historial');
  if (!traslados.length) { cont.innerHTML = '<p class="vacio">No hay traslados para mostrar.</p>'; return; }

  cont.innerHTML = traslados.map((t) => {
    const que = t.tipo_elemento === 'Paciente'
      ? `Paciente CI ${esc(t.documento)}` : `${NOMBRES[t.tipo_elemento]}: ${esc(t.item_nombre)}`;
    const botones = [];
    if (t.estado === 'Solicitado') botones.push(`<button class="btn-mini" data-editar="${t.id_peticion}">Editar</button>`);
    SIGUIENTES[t.estado].forEach((s) => botones.push(
      `<button class="btn-mini${s === 'Cancelado' ? ' btn-mini--peligro' : ''}" data-id="${t.id_peticion}" data-estado="${s}">${s === 'Cancelado' ? 'Cancelar' : 'Pasar a ' + txt(s).toLowerCase()}</button>`));
    return `<div class="tras-fila">
      <div class="tras-fila__top">
        <span class="tras-fila__titulo">#${t.id_peticion} · ${que}</span>
        <span class="estado estado--${clase(t.estado)}">${txt(t.estado)}</span>
      </div>
      <p class="tras-fila__datos">Destino: ${esc(t.destino)} · Razón: ${esc(t.razon)}<br>
        Salida ${esc(String(t.hora_salida).slice(0, 16))} · ${esc(t.matricula)} (${esc(t.tipo_vehiculo)})<br>
        Chofer: ${esc(t.chofer)} · Enfermero/a: ${esc(t.enfermero)} · Solicitó: ${esc(t.solicitante)}
        ${t.observaciones ? '<br>' + esc(t.observaciones) : ''}</p>
      <div class="tras-fila__acciones">${botones.join('')}</div>
    </div>`;
  }).join('');
}

$('historial').addEventListener('click', async (e) => {
  const b = e.target.closest('button');
  if (!b) return;
  if (b.dataset.editar) { editar(Number(b.dataset.editar)); return; }
  if (b.dataset.estado) {
    if (b.dataset.estado === 'Cancelado' && !confirm('¿Cancelar este traslado?')) return;
    try {
      await api(`/traslados?id=${b.dataset.id}`, {
        method: 'PATCH', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ estado: b.dataset.estado })
      });
      mensaje('Estado actualizado correctamente', true);
      await refrescar();
    } catch (err) { if (err.message !== 'sesion') mensaje(err.message, false); }
  }
});

async function refrescar() {
  await Promise.all([cargarFlota(), cargarHistorial(), cargarVehiculos()]);
}

// ---------- Eventos e inicio ----------
['tipo_elemento'].forEach((id) => $(id).addEventListener('change', aplicarTipo));
['requiere_oxigeno', 'requiere_aislamiento', 'requiere_camilla', 'no_autonomo', 'hora_salida']
  .forEach((id) => $(id).addEventListener('change', () => cargarVehiculos().catch(() => {})));
$('documento').addEventListener('blur', buscarPaciente);
$('hora_salida').addEventListener('focus', actualizarMinimo);
['f-matricula', 'f-estado'].forEach((id) => $(id).addEventListener('change', cargarHistorial));
$('f-documento').addEventListener('input', () => {
  const v = $('f-documento').value.trim();
  if (v === '' || v.length >= 6) cargarHistorial();
});

(async () => {
  const usuario = await verificarSesion();
  if (!usuario) return;
  actualizarMinimo();
  try { await refrescar(); } catch (e) { if (e.message !== 'sesion') mensaje(e.message, false); }
})();
