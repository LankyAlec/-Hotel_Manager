<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/struttura_status.php';

/* fallback se in helpers non esiste require_root */
if (!function_exists('require_root')) { function require_root(){} }
require_root();

include __DIR__ . '/../includes/header.php';

$edificio_id = (int)($_GET['edificio_id'] ?? 0);
$piano_id    = (int)($_GET['piano_id'] ?? 0);
?>

<style>
  .structure-grid{ display:grid; grid-template-columns: 1fr 1fr 1.35fr; gap:16px; }
  @media (max-width: 992px){ .structure-grid{ grid-template-columns: 1fr; } }

  /* FIX angoli “strani”: niente overflow hidden sul contenitore,
     lo scroll resta solo su .list */
  .box-card{
    border:0; border-radius:16px;
    box-shadow:0 .35rem 1rem rgba(0,0,0,.08);
    background:#fff;
    overflow: visible;
  }
  .box-head{
    padding:12px 14px;
    border-bottom:1px solid rgba(0,0,0,.06);
    display:flex; align-items:center; justify-content:space-between; gap:10px;
    background: linear-gradient(180deg, rgba(0,0,0,.02), rgba(0,0,0,0));
    border-radius:16px 16px 0 0;
  }
  .box-head .title{ min-width:0; display:flex; flex-direction:column; gap:2px; }
  .box-head .title b{ font-size:.95rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .box-head .subtitle{ font-size:.78rem; color:#6c757d; line-height:1.1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

  .list{
    padding:14px;
    max-height:70vh;
    overflow:auto;
    border-radius:0 0 16px 16px;
  }

  .item{
    display:flex; align-items:center; justify-content:space-between; gap:10px;
    padding:10px 10px; border-radius:14px; cursor:pointer; transition:.15s ease;
    border:1px solid rgba(0,0,0,.06); background:#fff;
    margin-bottom:12px;
  }
  .item:last-child{ margin-bottom:0; }
  .item:hover{ transform: translateY(-1px); box-shadow:0 .25rem .75rem rgba(0,0,0,.06); }
  .item.active{
    border-color: rgba(13,110,253,.45);
    background: rgba(13,110,253,.06);
    box-shadow: 0 .35rem 1rem rgba(13,110,253,.08);
  }

  .item .main{ min-width:0; display:flex; flex-direction:column; gap:2px; }
  .item .main .name{ font-weight:650; font-size:.93rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .item .main .meta{ font-size:.78rem; color:#6c757d; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; display:flex; align-items:center; gap:8px; }

  .acts{ display:flex; align-items:center; gap:8px; flex-shrink:0; }
  .btn-mini{
    width:38px; height:34px; padding:0;
    display:inline-flex; align-items:center; justify-content:center;
    border-radius:10px;
  }

  .muted-empty{ padding:10px; color:#6c757d; font-size:.9rem; }

  .crumb{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; justify-content:flex-end; }
  .pill{ padding:6px 12px; border-radius:999px; background:rgba(0,0,0,.04); font-size:.82rem; display:flex; align-items:center; gap:8px; }
  .pill b{ font-weight:700; }

  .btn-plus{
    width:40px; height:36px; padding:0;
    display:inline-flex; align-items:center; justify-content:center;
    border-radius:12px;
  }

  .hint-sel{
    display:flex; gap:10px; align-items:center;
    padding:10px 12px; border-radius:14px;
    border:1px dashed rgba(0,0,0,.15);
    background:rgba(0,0,0,.02);
    font-size:.9rem; color:#6c757d;
  }

  .badge-stato{ width:92px; display:inline-flex; align-items:center; justify-content:center; }

  /* modale */
  .form-hint{ font-size:.8rem; color:#6c757d; }
</style>

<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-start mb-3">
    <div>
      <h3 class="mb-1"><i class="bi bi-building"></i> Struttura Hotel</h3>
      <div class="text-muted small">Gestisci Edifici → Piani → Camere dalla stessa schermata</div>
    </div>

    <div class="crumb">
      <span class="pill"><i class="bi bi-buildings"></i> Edificio: <b id="crumbEdificio">—</b></span>
      <span class="pill"><i class="bi bi-layers"></i> Piano: <b id="crumbPiano">—</b></span>
    </div>
  </div>

  <div class="structure-grid">
    <!-- EDIFICI -->
    <div class="box-card">
      <div class="box-head">
        <div class="title">
          <b><i class="bi bi-buildings"></i> Edifici</b>
          <div class="subtitle">Seleziona un edificio per vedere i piani</div>
        </div>
        <button class="btn btn-primary btn-plus" id="btnNewEdificio" title="Nuovo edificio">
          <i class="bi bi-plus-circle"></i>
        </button>
      </div>
      <div class="list" id="edifici"><div class="muted-empty">Caricamento…</div></div>
    </div>

    <!-- PIANI -->
    <div class="box-card">
      <div class="box-head">
        <div class="title">
          <b><i class="bi bi-layers"></i> Piani</b>
          <div class="subtitle" id="subtitlePiani">Seleziona un edificio</div>
        </div>
        <button class="btn btn-primary btn-plus" id="btnNewPiano" title="Nuovo piano" disabled>
          <i class="bi bi-plus-circle"></i>
        </button>
      </div>
      <div class="list" id="piani">
        <div class="hint-sel"><i class="bi bi-arrow-left-right"></i> Seleziona un <b>edificio</b> per visualizzare i piani.</div>
      </div>
    </div>

    <!-- CAMERE -->
    <div class="box-card">
      <div class="box-head">
        <div class="title">
          <b><i class="bi bi-door-closed"></i> Camere</b>
          <div class="subtitle" id="subtitleCamere">Seleziona un piano</div>
        </div>
        <button class="btn btn-primary btn-plus" id="btnNewCamera" title="Nuova camera" disabled>
          <i class="bi bi-plus-circle"></i>
        </button>
      </div>
      <div class="list" id="camere">
        <div class="hint-sel"><i class="bi bi-arrow-left-right"></i> Seleziona un <b>piano</b> per visualizzare le camere.</div>
      </div>
    </div>
  </div>
</div>

<!-- Modal cascata (toggle attivo/disattivo) -->
<div class="modal fade" id="modalCascade" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-exclamation-triangle"></i> Conferma operazione</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
      </div>
      <div class="modal-body">
        <div id="cascadeMsg" class="mb-2">…</div>
        <div class="small text-muted" id="cascadeHint">…</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annulla</button>
        <button type="button" class="btn btn-danger" id="btnCascadeConfirm">Conferma</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal EDIT/NEW (unico) -->
<div class="modal fade" id="modalEdit" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="formEdit" autocomplete="off">
      <div class="modal-header">
        <h5 class="modal-title" id="editTitle"><i class="bi bi-pencil-square"></i> Modifica</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
      </div>

      <div class="modal-body">
        <input type="hidden" name="mode" id="editMode" value="edit">
        <input type="hidden" name="tipo" id="editTipo" value="">
        <input type="hidden" name="id" id="editId" value="">
        <input type="hidden" name="edificio_id" id="editEdificioId" value="">
        <input type="hidden" name="piano_id" id="editPianoId" value="">

        <!-- comune -->
        <div class="mb-3" id="groupNome">
          <label class="form-label">Nome</label>
          <input type="text" class="form-control" name="nome" id="editNome" maxlength="100" required>
          <div class="form-hint">Esempio: Depandance, Piano 1, Camera Deluxe…</div>
        </div>

        <!-- CAMERA fields -->
        <div id="cameraFields" style="display:none">
          <div class="mb-3">
            <label class="form-label">Numero / Codice camera</label>
            <input type="text" class="form-control" name="codice" id="editCodice" maxlength="20">
            <div class="form-hint">Esempio: 401</div>
          </div>

          <div class="mb-3">
            <label class="form-label">Capienza base (persone)</label>
            <input type="number" class="form-control" name="capienza_base" id="editPosti" min="1" max="10" step="1">
          </div>

          <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" id="editDisabili" name="accessibile_disabili" value="1">
            <label class="form-check-label" for="editDisabili">Accessibile disabili</label>
          </div>

          <div class="form-hint">Se “Nome” è vuoto, verrà mostrato il numero camera.</div>
        </div>

        <div class="alert alert-danger d-none" id="editErr"></div>
        <div class="alert alert-success d-none" id="editOk"></div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annulla</button>
        <button type="submit" class="btn btn-primary" id="btnEditSave">
          <span class="spinner-border spinner-border-sm me-2 d-none" id="editSpin" role="status" aria-hidden="true"></span>
          Salva
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Modal DELETE -->
<div class="modal fade" id="modalDelete" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-trash"></i> Elimina</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
      </div>
      <div class="modal-body">
        <div id="delMsg">Confermi eliminazione?</div>
        <div class="small text-muted" id="delHint"></div>

        <input type="hidden" id="delTipo" value="">
        <input type="hidden" id="delId" value="">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annulla</button>
        <button type="button" class="btn btn-danger" id="btnDelConfirm">
          <span class="spinner-border spinner-border-sm me-2 d-none" id="delSpin" role="status" aria-hidden="true"></span>
          Elimina
        </button>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  const edificiEl = document.getElementById('edifici');
  const pianiEl   = document.getElementById('piani');
  const camereEl  = document.getElementById('camere');

  const crumbEdificio = document.getElementById('crumbEdificio');
  const crumbPiano    = document.getElementById('crumbPiano');

  const subtitlePiani  = document.getElementById('subtitlePiani');
  const subtitleCamere = document.getElementById('subtitleCamere');

  const btnNewEdificio = document.getElementById('btnNewEdificio');
  const btnNewPiano    = document.getElementById('btnNewPiano');
  const btnNewCamera   = document.getElementById('btnNewCamera');

  window.edificioSel = <?= (int)$edificio_id ?> || null;
  window.pianoSel    = <?= (int)$piano_id ?> || null;

  function qs(obj){
    const p = new URLSearchParams();
    Object.entries(obj).forEach(([k,v]) => { if(v !== null && v !== undefined && v !== '') p.set(k, String(v)); });
    return p.toString();
  }

  function setActive(container, id){
    container.querySelectorAll('.item[data-id]').forEach(el => {
      el.classList.toggle('active', String(el.dataset.id) === String(id));
    });
  }

  function resetPianiUI(){
    pianiEl.innerHTML = `<div class="hint-sel"><i class="bi bi-arrow-left-right"></i> Seleziona un <b>edificio</b> per visualizzare i piani.</div>`;
    subtitlePiani.textContent = 'Seleziona un edificio';
    btnNewPiano.disabled = true;
  }
  function resetCamereUI(){
    camereEl.innerHTML = `<div class="hint-sel"><i class="bi bi-arrow-left-right"></i> Seleziona un <b>piano</b> per visualizzare le camere.</div>`;
    subtitleCamere.textContent = 'Seleziona un piano';
    btnNewCamera.disabled = true;
  }

  async function loadEdifici(){
    const r = await fetch('edifici_ajax.php?' + qs({ edificio_id: window.edificioSel }), { headers:{'X-Requested-With':'XMLHttpRequest'} });
    edificiEl.innerHTML = await r.text();

    edificiEl.querySelectorAll('.item[data-id]').forEach(el => {
      el.addEventListener('click', (e) => {
        if (e.target.closest('button, form, a, input, label')) return;

        window.edificioSel = parseInt(el.dataset.id, 10);
        window.pianoSel = null;

        crumbEdificio.textContent = el.dataset.nome || '—';
        crumbPiano.textContent = '—';

        btnNewPiano.disabled = false;
        resetCamereUI();

        setActive(edificiEl, window.edificioSel);
        loadPiani();

        history.replaceState(null, '', 'struttura.php?' + qs({ edificio_id: window.edificioSel }));
      });
    });

    if (window.edificioSel){
      const el = edificiEl.querySelector('.item[data-id="'+window.edificioSel+'"]');
      if (el){
        crumbEdificio.textContent = el.dataset.nome || '—';
        setActive(edificiEl, window.edificioSel);
        btnNewPiano.disabled = false;
        subtitlePiani.textContent = 'Edificio selezionato';
      } else {
        window.edificioSel = null;
        window.pianoSel = null;
        crumbEdificio.textContent = '—';
        crumbPiano.textContent = '—';
        resetPianiUI(); resetCamereUI();
      }
    } else {
      crumbEdificio.textContent = '—';
      crumbPiano.textContent = '—';
      resetPianiUI(); resetCamereUI();
    }
  }

  async function loadPiani(){
    if (!window.edificioSel){ resetPianiUI(); return; }

    subtitlePiani.textContent = 'Edificio selezionato';
    btnNewPiano.disabled = false;

    const r = await fetch('piani_ajax.php?' + qs({ edificio_id: window.edificioSel, piano_id: window.pianoSel }), { headers:{'X-Requested-With':'XMLHttpRequest'} });
    pianiEl.innerHTML = await r.text();

    pianiEl.querySelectorAll('.item[data-id]').forEach(el => {
      el.addEventListener('click', (e) => {
        if (e.target.closest('button, form, a, input, label')) return;

        window.pianoSel = parseInt(el.dataset.id, 10);

        crumbPiano.textContent = el.dataset.nome || '—';
        subtitleCamere.textContent = 'Piano selezionato';
        btnNewCamera.disabled = false;

        setActive(pianiEl, window.pianoSel);
        loadCamere();

        history.replaceState(null, '', 'struttura.php?' + qs({ edificio_id: window.edificioSel, piano_id: window.pianoSel }));
      });
    });

    if (window.pianoSel){
      const el = pianiEl.querySelector('.item[data-id="'+window.pianoSel+'"]');
      if (el){
        crumbPiano.textContent = el.dataset.nome || '—';
        setActive(pianiEl, window.pianoSel);
        btnNewCamera.disabled = false;
        subtitleCamere.textContent = 'Piano selezionato';
      } else {
        window.pianoSel = null;
        crumbPiano.textContent = '—';
        resetCamereUI();
      }
    } else {
      crumbPiano.textContent = '—';
      resetCamereUI();
    }
  }

  async function loadCamere(){
    if (!window.pianoSel){ resetCamereUI(); return; }

    subtitleCamere.textContent = 'Piano selezionato';
    btnNewCamera.disabled = false;

    const r = await fetch('camere_ajax.php?' + qs({ piano_id: window.pianoSel }), { headers:{'X-Requested-With':'XMLHttpRequest'} });
    camereEl.innerHTML = await r.text();
  }

  window.loadEdifici = loadEdifici;
  window.loadPiani   = loadPiani;
  window.loadCamere  = loadCamere;

  // I pulsanti "+" ora aprono il modale (NO redirect)
  btnNewEdificio.addEventListener('click', () => window.openCreateModal('edificio'));
  btnNewPiano.addEventListener('click', () => { if(window.edificioSel) window.openCreateModal('piano'); });
  btnNewCamera.addEventListener('click', () => { if(window.pianoSel) window.openCreateModal('camera'); });

  (async function init(){
    await loadEdifici();
    if (window.edificioSel) await loadPiani();
    if (window.pianoSel) await loadCamere();
  })();
})();
</script>

<script>
(function(){
  const root = document;

  // --- HARDENING ANTI-ESTENSIONI ---
  const nativeFetch = (typeof window.fetch === 'function') ? window.fetch.bind(window) : null;
  async function safeFetch(url, opts){
    if (!nativeFetch) throw new Error('fetch non disponibile');
    return nativeFetch(url, opts);
  }
  function guard(fn){
    return async (...args) => {
      try { return await fn(...args); }
      catch (e) { console.error('[HotelManager]', e); }
    };
  }

  const CASCADE = 'always';

  // Modale cascata toggle
  const modalEl = document.getElementById('modalCascade');
  const msgEl   = document.getElementById('cascadeMsg');
  const hintEl  = document.getElementById('cascadeHint');
  const btnOk   = document.getElementById('btnCascadeConfirm');

  // Modali edit/create + delete
  const modalEditEl = document.getElementById('modalEdit');
  const formEdit    = document.getElementById('formEdit');
  const editTitle   = document.getElementById('editTitle');
  const editErr     = document.getElementById('editErr');
  const editOk      = document.getElementById('editOk');
  const editSpin    = document.getElementById('editSpin');
  const btnEditSave = document.getElementById('btnEditSave');

  const editMode     = document.getElementById('editMode');
  const editTipo     = document.getElementById('editTipo');
  const editId       = document.getElementById('editId');
  const editEdificio = document.getElementById('editEdificioId');
  const editPiano    = document.getElementById('editPianoId');

  const groupNome    = document.getElementById('groupNome');
  const editNome     = document.getElementById('editNome');

  const cameraFields = document.getElementById('cameraFields');
  const editCodice   = document.getElementById('editCodice');
  const editPosti    = document.getElementById('editPosti');
  const editDisabili = document.getElementById('editDisabili');

  const modalDelEl   = document.getElementById('modalDelete');
  const delMsg       = document.getElementById('delMsg');
  const delHint      = document.getElementById('delHint');
  const delTipo      = document.getElementById('delTipo');
  const delId        = document.getElementById('delId');
  const btnDel       = document.getElementById('btnDelConfirm');
  const delSpin      = document.getElementById('delSpin');

  let modal = null;
  let modalEdit = null;
  let modalDelete = null;
  let pending = null; // { sw, tipo, id, val }

  function showErr(msg){
    editErr.textContent = msg || 'Errore';
    editErr.classList.remove('d-none');
    editOk.classList.add('d-none');
  }
  function showOk(msg){
    editOk.textContent = msg || 'Salvato';
    editOk.classList.remove('d-none');
    editErr.classList.add('d-none');
  }
  function clearMsgs(){
    editErr.classList.add('d-none'); editErr.textContent='';
    editOk.classList.add('d-none'); editOk.textContent='';
  }

  const reloadUI = guard(async function reloadUI(){
    if (typeof window.loadEdifici !== 'function') return;
    await window.loadEdifici();
    if (window.edificioSel && typeof window.loadPiani === 'function') await window.loadPiani();
    else document.getElementById('piani').innerHTML = "<div class='muted-empty'>—</div>";
    if (window.pianoSel && typeof window.loadCamere === 'function') await window.loadCamere();
    else document.getElementById('camere').innerHTML = "<div class='muted-empty'>—</div>";
  });

  async function previewCascade(tipo, id, val){
    const fd = new FormData();
    fd.append('tipo', tipo);
    fd.append('id', String(id));
    fd.append('val', String(val));
    fd.append('cascade', CASCADE);

    const res = await safeFetch('struttura_cascade_preview.php', {
      method: 'POST',
      body: fd,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });

    const txt = await res.text();
    let j;
    try { j = JSON.parse(txt); } catch(e){ throw new Error('Risposta non JSON: ' + txt.slice(0,400)); }
    if(!j || !j.ok) throw new Error(j.msg || 'Errore preview');
    return j;
  }

  async function doToggle(tipo, id, val){
    const fd = new FormData();
    fd.append('tipo', tipo);
    fd.append('id', String(id));
    fd.append('val', String(val));
    fd.append('cascade', CASCADE);

    const res = await safeFetch('struttura_toggle_ajax.php', {
      method: 'POST',
      body: fd,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });

    const txt = await res.text();
    let j;
    try { j = JSON.parse(txt); } catch(e){ throw new Error('Risposta non JSON: ' + txt.slice(0,400)); }
    if(!j || !j.ok) throw new Error(j.msg || 'Errore salvataggio');
    return j;
  }

  async function doUpdate(payload){
    const fd = new FormData();
    Object.entries(payload).forEach(([k,v]) => {
      if (v === null || v === undefined) return;
      fd.append(k, String(v));
    });

    const res = await safeFetch('struttura_edit_ajax.php', {
      method: 'POST',
      body: fd,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });

    const txt = await res.text();
    let j;
    try { j = JSON.parse(txt); } catch(e){ throw new Error('Risposta non JSON: ' + txt.slice(0,400)); }
    if(!j || !j.ok) throw new Error(j.msg || 'Errore salvataggio');
    return j;
  }

  async function doCreate(payload){
    const fd = new FormData();
    Object.entries(payload).forEach(([k,v]) => {
      if (v === null || v === undefined) return;
      fd.append(k, String(v));
    });

    const res = await safeFetch('struttura_create_ajax.php', {
      method: 'POST',
      body: fd,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });

    const txt = await res.text();
    let j;
    try { j = JSON.parse(txt); } catch(e){ throw new Error('Risposta non JSON: ' + txt.slice(0,400)); }
    if(!j || !j.ok) throw new Error(j.msg || 'Errore creazione');
    return j;
  }

  async function doDelete(tipo, id){
    const fd = new FormData();
    fd.append('tipo', tipo);
    fd.append('id', String(id));

    const res = await safeFetch('struttura_delete_ajax.php', {
      method: 'POST',
      body: fd,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });

    const txt = await res.text();
    let j;
    try { j = JSON.parse(txt); } catch(e){ throw new Error('Risposta non JSON: ' + txt.slice(0,400)); }
    if(!j || !j.ok) throw new Error(j.msg || 'Errore eliminazione');
    return j;
  }

  function setSaving(sw, saving){
    sw.disabled = saving;
    sw.closest('.item')?.classList.toggle('opacity-75', saving);
  }

  function makeMessage(pre){
    const turningOff = (pre.val === 0);
    const counts = pre.counts || {piani:0, camere:0};

    if (!pre.doCascade) {
      return {
        title: turningOff ? 'Stai disattivando un elemento.' : 'Stai attivando un elemento.',
        hint: 'L’operazione non coinvolge elementi collegati.'
      };
    }

    if (pre.tipo === 'edificio') {
      return {
        title: turningOff
          ? `Disattivando questo edificio verranno disattivati anche ${counts.piani} piani e ${counts.camere} camere.`
          : `Attivando questo edificio verranno attivati anche ${counts.piani} piani e ${counts.camere} camere.`,
        hint: 'Confermi di procedere?'
      };
    }

    if (pre.tipo === 'piano') {
      return {
        title: turningOff
          ? `Disattivando questo piano verranno disattivate anche ${counts.camere} camere.`
          : `Attivando questo piano verranno attivate anche ${counts.camere} camere.`,
        hint: 'Confermi di procedere?'
      };
    }

    return { title:'Confermi l’operazione?', hint:'' };
  }

  function ensureBootstrap(){
    return new Promise((resolve) => {
      if (window.bootstrap) return resolve(window.bootstrap);

      const done = (bs) => resolve(bs || null);
      const existing = document.querySelector('script[data-bs-autoload]');
      if (existing) {
        existing.addEventListener('load', () => done(window.bootstrap));
        existing.addEventListener('error', () => done(null));
        setTimeout(() => done(window.bootstrap || null), 2500);
        return;
      }

      const s = document.createElement('script');
      s.src = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js';
      s.async = true;
      s.dataset.bsAutoload = '1';
      s.onload = () => done(window.bootstrap);
      s.onerror = () => done(null);
      document.head.appendChild(s);
      setTimeout(() => done(window.bootstrap || null), 2500);
    });
  }

  function configureEditModal(tipo){
    clearMsgs();
    editTipo.value = tipo;

    // reset fields
    editNome.value = '';
    editCodice.value = '';
    editPosti.value = '';
    editDisabili.checked = false;

    cameraFields.style.display = (tipo === 'camera') ? '' : 'none';
    groupNome.querySelector('label').textContent = (tipo === 'camera') ? 'Nome camera (facoltativo)' : 'Nome';
    editNome.required = (tipo !== 'camera'); // camera: nome può essere vuoto

    if (tipo === 'piano') {
      editEdificio.value = String(window.edificioSel || '');
    } else if (tipo === 'camera') {
      editPiano.value = String(window.pianoSel || '');
    }
  }

  // funzioni globali chiamate dai pulsanti "+"
  window.openCreateModal = function(tipo){
    configureEditModal(tipo);
    editMode.value = 'create';
    editId.value = '';
    editTitle.innerHTML = '<i class="bi bi-plus-circle"></i> Nuovo ' + (tipo === 'edificio' ? 'edificio' : (tipo === 'piano' ? 'piano' : 'camera'));

    if (tipo === 'piano' && !window.edificioSel) return;
    if (tipo === 'camera' && !window.pianoSel) return;

    modalEdit?.show();
  };

  function openEditFromButton(btn){
    const tipo = btn.dataset.tipo || '';
    const id   = parseInt(btn.dataset.id || '0', 10);

    configureEditModal(tipo);
    editMode.value = 'edit';
    editId.value = String(id);

    editTitle.innerHTML = '<i class="bi bi-pencil-square"></i> Modifica ' + (tipo === 'edificio' ? 'edificio' : (tipo === 'piano' ? 'piano' : 'camera'));

    // dataset standard (dai tuoi ajax)
    editNome.value = (btn.dataset.nome || btn.dataset.label || '').trim();

    if (tipo === 'camera') {
      editCodice.value = (btn.dataset.codice || '').trim();
      editPosti.value  = (btn.dataset.capienza || btn.dataset.posti || '').trim();
      editDisabili.checked = (String(btn.dataset.disabili || '0') === '1');
    }

    modalEdit?.show();
  }

  function openDeleteFromButton(btn){
    const tipo  = btn.dataset.tipo || '';
    const id    = parseInt(btn.dataset.id || '0', 10);
    const label = (btn.dataset.label || btn.dataset.nome || '').trim();

    delTipo.value = tipo;
    delId.value = String(id);

    delMsg.textContent = label ? `Confermi eliminazione di "${label}"?` : 'Confermi eliminazione?';
    delHint.textContent = (tipo === 'edificio')
      ? 'Verranno eliminati anche piani e camere collegati.'
      : (tipo === 'piano' ? 'Verranno eliminate anche le camere collegate.' : '');

    modalDelete?.show();
  }

  function initInteractions(){
    // Toggle attivo/disattivo
    root.addEventListener('change', async (e) => {
      const sw = e.target.closest('.js-toggle-attivo');
      if (!sw) return;

      const tipo = sw.dataset.tipo;
      const id   = parseInt(sw.dataset.id, 10);
      const val  = sw.checked ? 1 : 0;

      const hasModal = !!modal;

      let mustAsk = false;
      if (tipo === 'edificio' || tipo === 'piano') {
        mustAsk = (CASCADE === 'always') || (CASCADE === 'off_only' && val === 0);
      }

      if (mustAsk && hasModal) {
        setSaving(sw, true);
        try {
          const pre = await previewCascade(tipo, id, val);
          const m = makeMessage(pre);

          msgEl.textContent = m.title;
          hintEl.textContent = m.hint;

          pending = { sw, tipo, id, val };

          btnOk.classList.toggle('btn-danger', val === 0);
          btnOk.classList.toggle('btn-success', val === 1);

          modal.show();
        } catch(err) {
          alert(err.message || 'Errore preview');
          sw.checked = !sw.checked;
          setSaving(sw, false);
        }
        return;
      }

      setSaving(sw, true);
      try {
        await doToggle(tipo, id, val);
        await reloadUI();
      } catch(err) {
        alert(err.message || 'Errore salvataggio');
        sw.checked = !sw.checked;
      } finally {
        setSaving(sw, false);
      }
    });

    // Click: edit/delete buttons
    root.addEventListener('click', guard(async (e) => {
      const editBtn = e.target.closest('.js-edit');
      if (editBtn) {
        e.stopPropagation();
        openEditFromButton(editBtn);
        return;
      }

      const delBtn = e.target.closest('.js-delete');
      if (delBtn) {
        e.stopPropagation();
        openDeleteFromButton(delBtn);
        return;
      }
    }));

    // Salva modale edit/create
    formEdit.addEventListener('submit', guard(async (e) => {
      e.preventDefault();
      clearMsgs();

      const mode = editMode.value;
      const tipo = editTipo.value;

      const payload = {
        tipo,
        id: editId.value,
        nome: editNome.value.trim(),
        edificio_id: editEdificio.value,
        piano_id: editPiano.value
      };

      if (tipo === 'camera') {
        payload.codice = editCodice.value.trim();
        payload.capienza_base = editPosti.value ? parseInt(editPosti.value,10) : '';
        const disVal = editDisabili.checked ? 1 : 0;
        // invio doppio nome campo per compatibilità (DB/colonne diverse)
        payload.accessibile_disabili = disVal;
        payload.disabili = disVal;

        // Nome camera facoltativo: ok anche vuoto
      }

      // validazioni leggere
      if ((tipo === 'edificio' || tipo === 'piano') && !payload.nome) {
        showErr('Il nome è obbligatorio.');
        return;
      }
      if (tipo === 'camera' && !payload.codice && !payload.nome) {
        showErr('Inserisci almeno il numero/codice oppure un nome.');
        return;
      }
      if (mode === 'create') {
        if (tipo === 'piano' && !payload.edificio_id) { showErr('Seleziona un edificio.'); return; }
        if (tipo === 'camera' && !payload.piano_id) { showErr('Seleziona un piano.'); return; }
      }

      editSpin.classList.remove('d-none');
      btnEditSave.disabled = true;

      try {
        if (mode === 'create') {
          const j = await doCreate(payload);
          showOk(j.msg || 'Creato');
        } else {
          const j = await doUpdate(payload);
          showOk(j.msg || 'Salvato');
        }

        await reloadUI();
        setTimeout(() => modalEdit?.hide(), 250);
      } catch(err){
        showErr(err.message || 'Errore salvataggio');
      } finally {
        editSpin.classList.add('d-none');
        btnEditSave.disabled = false;
      }
    }));

    // Conferma delete
    btnDel.addEventListener('click', guard(async () => {
      const tipo = delTipo.value;
      const id   = parseInt(delId.value || '0', 10);
      if (!tipo || !id) return;

      delSpin.classList.remove('d-none');
      btnDel.disabled = true;
      try {
        await doDelete(tipo, id);

        if (tipo === 'edificio' && window.edificioSel === id) { window.edificioSel = null; window.pianoSel = null; }
        if (tipo === 'piano' && window.pianoSel === id) { window.pianoSel = null; }

        await reloadUI();
        modalDelete?.hide();
      } catch(err){
        alert(err.message || 'Errore eliminazione');
      } finally {
        delSpin.classList.add('d-none');
        btnDel.disabled = false;
      }
    }));

    // Conferma modale cascata
    btnOk.addEventListener('click', guard(async () => {
      if (!pending) return;
      const { sw, tipo, id, val } = pending;
      pending = null;
      modal?.hide();

      try {
        await doToggle(tipo, id, val);
        await reloadUI();
      } catch(err) {
        alert(err.message || 'Errore salvataggio');
        sw.checked = !sw.checked;
      } finally {
        setSaving(sw, false);
      }
    }));

    // Annullo modale cascata → rollback
    modalEl?.addEventListener('hidden.bs.modal', () => {
      if (!pending) return;
      const { sw } = pending;
      sw.checked = !sw.checked;
      setSaving(sw, false);
      pending = null;
    });
  }

  initInteractions();

  ensureBootstrap().then((bs) => {
    const B = bs || window.bootstrap;
    if (!B) return;

    modal = modalEl ? new B.Modal(modalEl) : null;
    modalEdit = modalEditEl ? new B.Modal(modalEditEl) : null;
    modalDelete = modalDelEl ? new B.Modal(modalDelEl) : null;
  });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
