// Encuesta para pacientes — anónima, sin login. Se abre con encuesta.html?id=X
const id = Number(new URLSearchParams(location.search).get('id')) || 0;
let preguntas = [];

function aviso(titulo, texto) {
  $('encuesta').hidden = true;
  $('aviso-titulo').textContent = titulo;
  $('aviso-texto').textContent = texto;
  $('aviso').hidden = false;
  window.scrollTo(0, 0);
}

function armar(e) {
  document.title = `${e.titulo} | S.I.G.S.M. — Hospital de Clínicas`;
  $('enc-cat').textContent = e.categoria;
  $('enc-titulo').textContent = e.titulo;
  $('enc-desc').textContent = e.descripcion || '';
  preguntas = e.lista_preguntas;

  const cont = $('preguntas');
  preguntas.forEach((p, i) => {
    const caja = document.createElement('fieldset');
    caja.className = 'pregunta';
    caja.dataset.id = p.id_pregunta;

    const titulo = document.createElement('legend');
    titulo.textContent = `${i + 1}. ${p.enunciado}`;
    caja.appendChild(titulo);

    if (p.tipo === 'Escala') {
      const escala = document.createElement('div');
      escala.className = 'escala';
      for (let v = 1; v <= 5; v++) {
        const nombre = `p${p.id_pregunta}`;
        escala.insertAdjacentHTML('beforeend',
          `<input type="radio" id="${nombre}_${v}" name="${nombre}" value="${v}"><label for="${nombre}_${v}">${v}</label>`);
      }
      caja.appendChild(escala);
      caja.insertAdjacentHTML('beforeend', '<div class="escala__extremos"><span>1 · Muy malo</span><span>5 · Muy bueno</span></div>');
    } else {
      titulo.insertAdjacentHTML('beforeend', ' <span class="opcional">(opcional)</span>');
      const txt = document.createElement('textarea');
      txt.maxLength = 500;
      txt.name = `p${p.id_pregunta}`;
      txt.setAttribute('aria-label', p.enunciado);
      caja.appendChild(txt);
    }
    cont.appendChild(caja);
  });

  $('encuesta').hidden = false;
}

$('form-respuestas').addEventListener('submit', async (e) => {
  e.preventDefault();
  $('mensaje').textContent = '';

  // Validación: todas las preguntas de escala respondidas
  const respuestas = {};
  let primeraFaltante = null;
  preguntas.forEach((p) => {
    const caja = document.querySelector(`.pregunta[data-id="${p.id_pregunta}"]`);
    if (p.tipo === 'Escala') {
      const elegido = caja.querySelector('input:checked');
      caja.classList.toggle('pregunta--error', !elegido);
      if (elegido) respuestas[p.id_pregunta] = Number(elegido.value);
      else if (!primeraFaltante) primeraFaltante = caja;
    } else {
      const texto = caja.querySelector('textarea').value.trim();
      if (texto) respuestas[p.id_pregunta] = texto;
    }
  });

  if (primeraFaltante) {
    $('mensaje').textContent = 'Elegí un puntaje del 1 al 5 en las preguntas marcadas.';
    primeraFaltante.scrollIntoView({ behavior: 'smooth', block: 'center' });
    return;
  }

  $('btn-enviar').disabled = true;
  try {
    const r = await fetch(`/sigsm/api/encuestas?id=${id}&responder=1`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ respuestas })
    });
    const res = await r.json().catch(() => ({}));
    if (!r.ok) { $('mensaje').textContent = res.error || 'No se pudo enviar. Probá de nuevo.'; return; }
    aviso('¡Gracias!', 'Tu respuesta fue enviada. Nos ayuda a mejorar la atención.');
  } catch (err) {
    $('mensaje').textContent = 'Error de conexión. Probá de nuevo.';
  } finally {
    $('btn-enviar').disabled = false;
  }
});

(async () => {
  if (!id) { aviso('Encuesta no encontrada', 'El enlace no es válido.'); return; }
  try {
    const r = await fetch('/sigsm/api/encuestas?id=' + id);
    if (!r.ok) { aviso('Encuesta no disponible', 'Esta encuesta no existe o ya no está abierta.'); return; }
    armar(await r.json());
  } catch (e) {
    aviso('Sin conexión', 'No se pudo cargar la encuesta. Intentá de nuevo más tarde.');
  }
})();
