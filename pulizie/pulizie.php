<?php
/* ===========================
   FILE: pulizie.php
   =========================== */
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

if (!function_exists('require_root')) { function require_root(){} }
require_root();

include __DIR__ . '/../includes/header.php';
?>

<style>
  .topbar{ display:flex; gap:14px; flex-wrap:wrap; align-items:flex-start; justify-content:space-between; }
  .topbar .left h3{ margin:0; }
  .topbar .left .sub{ color:#6c757d; font-size:.9rem; margin-top:4px; }
  .topbar .right{ display:flex; gap:10px; flex-wrap:wrap; align-items:center; justify-content:flex-end; }

  .board{ display:grid; grid-template-columns: 1fr 1fr 1fr; gap:14px; }
  @media (max-width: 992px){ .board{ grid-template-columns: 1fr; } }

  .col-card{
    border:0; border-radius:16px;
    box-shadow:0 .35rem 1rem rgba(0,0,0,.08);
    background:#fff;
    overflow:visible;
    display:flex;
    flex-direction:column;
    min-height: 200px;
  }
  .col-head{
    padding:12px 14px;
    border-bottom:1px solid rgba(0,0,0,.06);
    display:flex; align-items:center; justify-content:space-between; gap:10px;
    background: linear-gradient(180deg, rgba(0,0,0,.02), rgba(0,0,0,0));
    border-radius:16px 16px 0 0;
  }
  .col-head b{ font-size:.95rem; }
  .col-body{
    padding:14px;
    max-height:72vh;
    overflow:auto;
    border-radius:0 0 16px 16px;
    flex: 1 1 auto;
  }
  .col-foot{
    padding:10px 14px;
    border-top:1px solid rgba(0,0,0,.06);
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    border-radius:0 0 16px 16px;
    background: rgba(0,0,0,.01);
  }

  .pill{ padding:6px 12px; border-radius:999px; background:rgba(0,0,0,.04); font-size:.82rem; display:flex; align-items:center; gap:8px; }

  .tcard{
    border:1px solid rgba(0,0,0,.08);
    background:#fff;
    border-radius:16px;
    padding:12px 12px;
    margin-bottom:12px;
    box-shadow:0 .25rem .75rem rgba(0,0,0,.04);
  }
  .tcard:last-child{ margin-bottom:0; }
  .tcard .top{ display:flex; gap:10px; align-items:flex-start; justify-content:space-between; }
  .tcard .title{ font-weight:800; font-size:.95rem; line-height:1.2; }
  .tcard .desc{ margin-top:8px; color:#6c757d; font-size:.88rem; white-space:pre-wrap; }

  .tacts{ display:flex; align-items:center; gap:8px; flex-shrink:0; }
  .btn-mini{
    width:42px; height:38px; padding:0;
    display:inline-flex; align-items:center; justify-content:center;
    border-radius:12px;
  }

  .muted-empty{ padding:10px; color:#6c757d; font-size:.9rem; }

  .badge-soft{
    background:#f3f4f6;
    border:1px solid rgba(0,0,0,.08);
    color:#111827;
    border-radius:999px;
    padding:.45rem .65rem;
    font-weight:600;
  }

  /* ===== META GRID (icone carine + testo allineato) ===== */
  .meta-grid{ display:grid; grid-template-columns: 1fr 1fr; gap:10px 18px; margin-top:10px; }
  .meta-item{ display:flex; align-items:center; gap:10px; min-width:0; }
  .meta-item i{ width:18px; text-align:center; opacity:.9; }
  .meta-label{ color:#6c757d; min-width:86px; }
  .meta-value{ font-weight:800; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

  /* Topbar nicer */
  .filters-row{ display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
  .select-slim{ height: 38px; border-radius: 12px; }
  .form-control.select-slim{ padding-top: 6px; padding-bottom: 6px; }
  .form-select.select-slim{ padding-top: 6px; padding-bottom: 6px; }

  .tiny-help{ font-size: .82rem; color: #6c757d; }

  .filter-chip{
    display:flex;
    align-items:center;
    gap:8px;
    padding:6px 10px;
    border-radius:12px;
    background:#f9fafb;
    border:1px solid rgba(0,0,0,.10);
    height:38px;
  }
  .filter-chip .form-check{ margin:0; }
  .filter-chip .form-check-input{ cursor:pointer; }
  .filter-chip .lbl{ font-size:.9rem; color:#111827; font-weight:600; }
</style>

<div class="container-fluid">
  <div class="topbar mb-3">
    <div class="left">
      <h3><i class="bi bi-stars"></i> Pulizie Camere</h3>
      <div class="sub">Board: <b>Da fare</b> → <b>In corso</b> → <b>Completata</b></div>
    </div>

    <div class="right">
      <div class="filters-row">
        <div class="input-group" style="min-width: 290px;">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input type="text" class="form-control" id="q" placeholder="Cerca (note, camera, ecc.)">
        </div>

        <input type="date" class="form-control select-slim" id="f_data" style="width: 170px;">

        <select class="form-select select-slim" id="f_tipo" style="width: 190px;">
          <option value="ALL">Tipo: tutti</option>
          <option value="STANDARD">STANDARD</option>
          <option value="EXTRA">EXTRA</option>
          <option value="CAMBIO_BIANCHERIA">CAMBIO_BIANCHERIA</option>
          <option value="CHECKOUT">CHECKOUT</option>
        </select>

        <select class="form-select select-slim" id="f_edificio" style="width: 190px;">
          <option value="0">Edificio: tutti</option>
        </select>

        <select class="form-select select-slim" id="f_piano" style="width: 170px;" disabled>
          <option value="0">Piano: tutti</option>
        </select>

        <select class="form-select select-slim" id="f_camera" style="width: 190px;" disabled>
          <option value="0">Camera: tutte</option>
        </select>

        <select class="form-select select-slim" id="f_assegnata" style="width: 200px;">
          <option value="0">Assegnata a: tutte</option>
        </select>

        <div class="filter-chip">
          <div class="form-check form-switch d-flex align-items-center gap-2">
            <input class="form-check-input" type="checkbox" id="f_my">
            <label class="form-check-label lbl" for="f_my">Solo mie</label>
          </div>
        </div>

        <div class="filter-chip">
          <div class="form-check form-switch d-flex align-items-center gap-2">
            <input class="form-check-input" type="checkbox" id="f_unassigned">
            <label class="form-check-label lbl" for="f_unassigned">Non assegnate</label>
          </div>
        </div>

        <button class="btn btn-primary" id="btnNew">
          <i class="bi bi-plus-circle"></i> Nuova pulizia
        </button>
      </div>
    </div>
  </div>

  <div class="board">
    <!-- DA_FARE -->
    <div class="col-card">
      <div class="col-head">
        <b><i class="bi bi-inbox"></i> Da fare</b>
        <span class="pill"><i class="bi bi-hash"></i> <b id="cntDaFare">0</b></span>
      </div>
      <div class="col-body" id="colDaFare"><div class="muted-empty">Caricamento…</div></div>
      <div class="col-foot">
        <button class="btn btn-outline-secondary btn-sm" id="prevD"><i class="bi bi-chevron-left"></i></button>
        <div class="small text-muted">Pagina <b id="pageD">1</b></div>
        <button class="btn btn-outline-secondary btn-sm" id="nextD"><i class="bi bi-chevron-right"></i></button>
      </div>
    </div>

    <!-- IN_CORSO -->
    <div class="col-card">
      <div class="col-head">
        <b><i class="bi bi-play-circle"></i> In corso</b>
        <span class="pill"><i class="bi bi-hash"></i> <b id="cntInCorso">0</b></span>
      </div>
      <div class="col-body" id="colInCorso"><div class="muted-empty">Caricamento…</div></div>
      <div class="col-foot">
        <button class="btn btn-outline-secondary btn-sm" id="prevI"><i class="bi bi-chevron-left"></i></button>
        <div class="small text-muted">Pagina <b id="pageI">1</b></div>
        <button class="btn btn-outline-secondary btn-sm" id="nextI"><i class="bi bi-chevron-right"></i></button>
      </div>
    </div>

    <!-- COMPLETATA -->
    <div class="col-card">
      <div class="col-head">
        <b><i class="bi bi-check2-circle"></i> Completate</b>
        <span class="pill"><i class="bi bi-hash"></i> <b id="cntCompletate">0</b></span>
      </div>
      <div class="col-body" id="colCompletate"><div class="muted-empty">Caricamento…</div></div>
      <div class="col-foot">
        <button class="btn btn-outline-secondary btn-sm" id="prevC"><i class="bi bi-chevron-left"></i></button>
        <div class="small text-muted">Pagina <b id="pageC">1</b></div>
        <button class="btn btn-outline-secondary btn-sm" id="nextC"><i class="bi bi-chevron-right"></i></button>
      </div>
    </div>
  </div>
</div>

<!-- MODALE CREATE/EDIT -->
<div class="modal fade" id="taskModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <form class="modal-content rounded-4" id="taskForm" autocomplete="off">
      <div class="modal-header">
        <h5 class="modal-title" id="modalTitle"><i class="bi bi-plus-circle"></i> Nuova pulizia</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
      </div>

      <div class="modal-body">
        <input type="hidden" id="mode" value="create">
        <input type="hidden" id="taskId" value="">
        <input type="hidden" id="currentStato" value="">

        <!-- META sempre visibile -->
        <div class="card border-0 shadow-sm rounded-4 mb-3" id="metaCard">
          <div class="card-body py-2">
            <div class="meta-grid">
              <div class="meta-item">
                <i class="bi bi-calendar-event"></i>
                <span class="meta-label">Inizio</span>
                <span class="meta-value" id="metaInizio">—</span>
              </div>

              <div class="meta-item">
                <i class="bi bi-person"></i>
                <span class="meta-label">Aperto da</span>
                <span class="meta-value" id="metaApertoDa">—</span>
              </div>

              <div class="meta-item">
                <i class="bi bi-calendar-check"></i>
                <span class="meta-label">Fine</span>
                <span class="meta-value" id="metaFine">—</span>
              </div>

              <div class="meta-item">
                <i class="bi bi-person-check"></i>
                <span class="meta-label">Chiuso da</span>
                <span class="meta-value" id="metaChiusoDa">—</span>
              </div>
            </div>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-12">
            <label class="form-label">Struttura</label>
            <div class="row g-2">
              <div class="col-md-4">
                <select class="form-select" id="m_edificio" required>
                  <option value="0">Edificio…</option>
                </select>
              </div>
              <div class="col-md-4">
                <select class="form-select" id="m_piano" required disabled>
                  <option value="0">Piano…</option>
                </select>
              </div>
              <div class="col-md-4">
                <select class="form-select" id="camera_id" required disabled>
                  <option value="0">Camera (codice)…</option>
                </select>
              </div>
            </div>
          </div>

          <div class="col-md-4">
            <label class="form-label">Tipo</label>
            <select class="form-select" id="tipo">
              <option value="STANDARD">STANDARD</option>
              <option value="EXTRA">EXTRA</option>
              <option value="CAMBIO_BIANCHERIA">CAMBIO_BIANCHERIA</option>
              <option value="CHECKOUT">CHECKOUT</option>
            </select>
          </div>

          <div class="col-md-4">
            <label class="form-label">Data</label>
            <input type="date" class="form-control" id="data_task">
          </div>

          <div class="col-md-4">
            <label class="form-label">Assegnata a</label>
            <select class="form-select" id="assegnata_a">
              <option value="0">Non assegnata</option>
            </select>
          </div>

          <div class="col-12">
            <label class="form-label">Note</label>
            <textarea class="form-control" id="note" rows="3"
              placeholder="Es. extra asciugamani / controllo minibar / ecc."></textarea>
          </div>
        </div>

        <div class="alert alert-danger mt-3 d-none" id="errBox"></div>
        <div class="alert alert-success mt-3 d-none" id="okBox"></div>
      </div>


      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Chiudi</button>
        <button type="submit" class="btn btn-primary" id="btnSave">
          <span class="spinner-border spinner-border-sm d-none" id="spin"></span>
          <i class="bi bi-save"></i> Salva
        </button>
      </div>
    </form>
  </div>
</div>

<!-- MODALE MOVE CONFIRM -->
<div class="modal fade" id="moveModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-arrow-left-right"></i> Sposta pulizia</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
      </div>
      <div class="modal-body" id="moveText">Confermi?</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">No</button>
        <button type="button" class="btn btn-primary" id="btnMoveYes">Sì</button>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  const PER_PAGE = 10;
  const CURRENT_UID = <?= (int)($_SESSION['utente_id'] ?? $_SESSION['uid'] ?? $_SESSION['user_id'] ?? 0) ?>;

  const qEl = document.getElementById('q');
  const fData = document.getElementById('f_data');
  const fTipo = document.getElementById('f_tipo');
  const fEd = document.getElementById('f_edificio');
  const fPi = document.getElementById('f_piano');
  const fCa = document.getElementById('f_camera');
  const fAss = document.getElementById('f_assegnata');
  const fMy = document.getElementById('f_my');
  const fUnassigned = document.getElementById('f_unassigned');

  const btnNew = document.getElementById('btnNew');

  const colD = document.getElementById('colDaFare');
  const colI = document.getElementById('colInCorso');
  const colC = document.getElementById('colCompletate');

  const cntD = document.getElementById('cntDaFare');
  const cntI = document.getElementById('cntInCorso');
  const cntC = document.getElementById('cntCompletate');

  const pageDEl = document.getElementById('pageD');
  const pageIEl = document.getElementById('pageI');
  const pageCEl = document.getElementById('pageC');

  const prevD = document.getElementById('prevD');
  const nextD = document.getElementById('nextD');
  const prevI = document.getElementById('prevI');
  const nextI = document.getElementById('nextI');
  const prevC = document.getElementById('prevC');
  const nextC = document.getElementById('nextC');

  const taskModalEl = document.getElementById('taskModal');
  const form = document.getElementById('taskForm');
  const modalTitle = document.getElementById('modalTitle');
  const modeEl = document.getElementById('mode');
  const idEl = document.getElementById('taskId');
  const currentStatoEl = document.getElementById('currentStato');

  const mEdificio = document.getElementById('m_edificio');
  const mPiano    = document.getElementById('m_piano');
  const cameraSel = document.getElementById('camera_id');

  const tipoEl = document.getElementById('tipo');
  const dataEl = document.getElementById('data_task');
  const assegnataSel = document.getElementById('assegnata_a');
  const noteEl = document.getElementById('note');

  const errBox = document.getElementById('errBox');
  const okBox = document.getElementById('okBox');
  const btnSave = document.getElementById('btnSave');
  const spin = document.getElementById('spin');

  // meta
  const metaInizio = document.getElementById('metaInizio');
  const metaFine = document.getElementById('metaFine');
  const metaApertoDa = document.getElementById('metaApertoDa');
  const metaChiusoDa = document.getElementById('metaChiusoDa');

  // move modal
  const moveModalEl = document.getElementById('moveModal');
  const moveText = document.getElementById('moveText');
  const btnMoveYes = document.getElementById('btnMoveYes');

  let bsTask=null, bsMove=null;

  const pages = { DA_FARE:1, IN_CORSO:1, COMPLETATA:1 };
  const hasMore = { DA_FARE:false, IN_CORSO:false, COMPLETATA:false };

  function showErr(msg){ errBox.textContent = msg; errBox.classList.remove('d-none'); okBox.classList.add('d-none'); }
  function showOk(msg){ okBox.textContent = msg; okBox.classList.remove('d-none'); errBox.classList.add('d-none'); }
  function clearMsg(){ errBox.classList.add('d-none'); okBox.classList.add('d-none'); }

  function qs(params){
    const u = new URLSearchParams();
    for (const [k,v] of Object.entries(params)) {
      if (v === undefined || v === null) continue;
      u.append(k, String(v));
    }
    return u.toString();
  }

  async function apiJson(url, opts={}){
    const r = await fetch(url, opts);
    const txt = await r.text();
    let j;
    try { j = JSON.parse(txt); } catch(e){
      throw new Error('Risposta non JSON da ' + url + ': ' + txt.slice(0,200));
    }
    if (!r.ok || j.ok === false) throw new Error(j.msg || 'Errore');
    return j;
  }

  async function apiMeta(type, extra={}){
    const j = await apiJson('task_pulizie_meta_ajax.php?' + qs({ type, ...extra }));
    return j;
  }

  function currentFilters(){
    let ass = parseInt(fAss.value||'0',10);
    const myOnly = !!fMy.checked;
    const unassigned = !!fUnassigned.checked;
    if (myOnly && CURRENT_UID > 0) ass = CURRENT_UID;

    return {
      q: (qEl.value||'').trim(),
      data: (fData.value||'').trim(),
      tipo: (fTipo.value||'ALL'),
      edificio_id: parseInt(fEd.value||'0',10),
      piano_id: parseInt(fPi.value||'0',10),
      camera_id: parseInt(fCa.value||'0',10),
      assegnata_a: ass,
      unassigned: unassigned ? 1 : 0
    };
  }

  function fillSelect(sel, items, firstHtml){
    sel.innerHTML = firstHtml;
    for (const it of items) {
      const opt = document.createElement('option');
      opt.value = String(it.id);
      opt.textContent = it.label;
      sel.appendChild(opt);
    }
  }

  async function loadEdifici(selectedId=0){
    const j = await apiMeta('edifici');
    fillSelect(fEd, j.items || [], '<option value="0">Edificio: tutti</option>');
    fEd.value = String(selectedId||0);
  }

  async function loadPiani(edificioId, selectedId=0){
    if (!edificioId) {
      fPi.disabled = true;
      fillSelect(fPi, [], '<option value="0">Piano: tutti</option>');
      return;
    }
    const j = await apiMeta('piani', { edificio_id: edificioId });
    fPi.disabled = false;
    fillSelect(fPi, j.items || [], '<option value="0">Piano: tutti</option>');
    fPi.value = String(selectedId||0);
  }

  async function loadCamere(pianoId, selectedId=0){
    if (!pianoId) {
      fCa.disabled = true;
      fillSelect(fCa, [], '<option value="0">Camera: tutte</option>');
      return;
    }
    const j = await apiMeta('camere', { piano_id: pianoId });
    fCa.disabled = false;
    fillSelect(fCa, j.items || [], '<option value="0">Camera: tutte</option>');
    fCa.value = String(selectedId||0);
  }

  // ===== MODALE (selezione struttura) =====
  async function loadModalEdifici(selectedId=0){
    const j = await apiMeta('edifici');
    fillSelect(mEdificio, j.items || [], '<option value="0">Edificio…</option>');
    mEdificio.value = String(selectedId||0);
  }

  async function loadModalPiani(edificioId, selectedId=0){
    if (!edificioId) {
      mPiano.disabled = true;
      fillSelect(mPiano, [], '<option value="0">Piano…</option>');
      cameraSel.disabled = true;
      fillSelect(cameraSel, [], '<option value="0">Camera…</option>');
      return;
    }
    const j = await apiMeta('piani', { edificio_id: edificioId });
    mPiano.disabled = false;
    fillSelect(mPiano, j.items || [], '<option value="0">Piano…</option>');
    mPiano.value = String(selectedId||0);
  }

  async function loadModalCamere(pianoId, selectedId=0){
    if (!pianoId) {
      cameraSel.disabled = true;
      fillSelect(cameraSel, [], '<option value="0">Camera…</option>');
      return;
    }
    const j = await apiMeta('camere', { piano_id: pianoId });
    cameraSel.disabled = false;
    fillSelect(cameraSel, j.items || [], '<option value="0">Camera…</option>');
    cameraSel.value = String(selectedId||0);
  }

  mEdificio.addEventListener('change', async ()=>{
    const eid = parseInt(mEdificio.value||'0',10);
    await loadModalPiani(eid, 0);
    await loadModalCamere(0, 0);
  });

  mPiano.addEventListener('change', async ()=>{
    const pid = parseInt(mPiano.value||'0',10);
    await loadModalCamere(pid, 0);
  });

  async function loadAssegnate(selectedId=0){
    const j = await apiMeta('assegnate');
    fillSelect(assegnataSel, j.items || [], '<option value="0">Non assegnata</option>');
    assegnataSel.value = String(selectedId||0);
  }

  async function loadAssigneeFilter(selectedId=0){
    const j = await apiMeta('assegnate');
    fillSelect(fAss, j.items || [], '<option value="0">Assegnata a: tutte</option>');
    fAss.value = String(selectedId||0);
  }

  fEd.addEventListener('change', async ()=>{
    const eid = parseInt(fEd.value||'0',10);
    await loadPiani(eid, 0);
    await loadCamere(0, 0);
    resetPages(); await loadBoard();
  });
  fPi.addEventListener('change', async ()=>{
    const pid = parseInt(fPi.value||'0',10);
    await loadCamere(pid, 0);
    resetPages(); await loadBoard();
  });
  fCa.addEventListener('change', async ()=>{ resetPages(); await loadBoard(); });
  fAss.addEventListener('change', async ()=>{ resetPages(); await loadBoard(); });

  function syncFilterToggles(){
    if (fMy.checked) {
      fUnassigned.checked = false;
      fAss.disabled = true;
    } else if (fUnassigned.checked) {
      fMy.checked = false;
      fAss.disabled = true;
    } else {
      fAss.disabled = false;
    }
  }
  fMy.addEventListener('change', async ()=>{ syncFilterToggles(); resetPages(); await loadBoard(); });
  fUnassigned.addEventListener('change', async ()=>{ syncFilterToggles(); resetPages(); await loadBoard(); });

  // ===== board =====
  async function loadBoard(){
    const f = currentFilters();
    const params = {
      ...f,
      per_page: PER_PAGE,
      page_da_fare: pages.DA_FARE,
      page_in_corso: pages.IN_CORSO,
      page_completata: pages.COMPLETATA
    };
    const j = await apiJson('task_pulizie_ajax.php?' + qs(params));

    colD.innerHTML = j.html?.DA_FARE ?? "<div class='muted-empty'>—</div>";
    colI.innerHTML = j.html?.IN_CORSO ?? "<div class='muted-empty'>—</div>";
    colC.innerHTML = j.html?.COMPLETATA ?? "<div class='muted-empty'>—</div>";

    cntD.textContent = String(j.counts?.DA_FARE ?? 0);
    cntI.textContent = String(j.counts?.IN_CORSO ?? 0);
    cntC.textContent = String(j.counts?.COMPLETATA ?? 0);

    pageDEl.textContent = String(pages.DA_FARE);
    pageIEl.textContent = String(pages.IN_CORSO);
    pageCEl.textContent = String(pages.COMPLETATA);

    hasMore.DA_FARE = !!(j.has_more?.DA_FARE);
    hasMore.IN_CORSO = !!(j.has_more?.IN_CORSO);
    hasMore.COMPLETATA = !!(j.has_more?.COMPLETATA);

    prevD.disabled = pages.DA_FARE <= 1;
    prevI.disabled = pages.IN_CORSO <= 1;
    prevC.disabled = pages.COMPLETATA <= 1;

    nextD.disabled = !hasMore.DA_FARE;
    nextI.disabled = !hasMore.IN_CORSO;
    nextC.disabled = !hasMore.COMPLETATA;
  }

  function resetPages(){
    pages.DA_FARE = 1; pages.IN_CORSO = 1; pages.COMPLETATA = 1;
  }

  // debounce filtri
  let t=null;
  function scheduleReload(){
    clearTimeout(t);
    t = setTimeout(async ()=>{
      resetPages();
      await loadBoard().catch(console.error);
    }, 220);
  }
  qEl.addEventListener('input', scheduleReload);
  fData.addEventListener('change', scheduleReload);
  fTipo.addEventListener('change', scheduleReload);

  prevD.addEventListener('click', async ()=>{ if(pages.DA_FARE>1){ pages.DA_FARE--; await loadBoard(); }});
  nextD.addEventListener('click', async ()=>{ if(hasMore.DA_FARE){ pages.DA_FARE++; await loadBoard(); }});
  prevI.addEventListener('click', async ()=>{ if(pages.IN_CORSO>1){ pages.IN_CORSO--; await loadBoard(); }});
  nextI.addEventListener('click', async ()=>{ if(hasMore.IN_CORSO){ pages.IN_CORSO++; await loadBoard(); }});
  prevC.addEventListener('click', async ()=>{ if(pages.COMPLETATA>1){ pages.COMPLETATA--; await loadBoard(); }});
  nextC.addEventListener('click', async ()=>{ if(hasMore.COMPLETATA){ pages.COMPLETATA++; await loadBoard(); }});

  // ===== create/edit =====

  function fmtDt(val){
    if(!val) return '—';
    const d = new Date(String(val).replace(' ', 'T'));
    if (isNaN(d.getTime())) return String(val);
    return d.toLocaleString('it-IT', { year:'numeric', month:'2-digit', day:'2-digit', hour:'2-digit', minute:'2-digit' });
  }

  function fillMetaFromCard(card){
    const stato = card.dataset.stato || '';
    const createdAt = card.dataset.createdAt || '';
    const startedAt = card.dataset.startedAt || '';
    const completedAt = card.dataset.completedAt || '';

    const createdBy = card.dataset.createdByName || '';
    const startedBy = card.dataset.startedByName || '';
    const completedBy = card.dataset.completedByName || '';

    const inizio = startedAt || createdAt || '';
    const fine = (stato === 'COMPLETATA') ? completedAt : '';
    const apertoDa = createdBy || startedBy || '';
    const chiusoDa = (stato === 'COMPLETATA') ? completedBy : '';

    metaInizio.textContent = fmtDt(inizio);
    metaFine.textContent = fmtDt(fine);
    metaApertoDa.textContent = apertoDa ? apertoDa : '—';
    metaChiusoDa.textContent = chiusoDa ? chiusoDa : '—';
  }

  async function openCreate(){
    clearMsg();
    modeEl.value='create';
    idEl.value='';
    currentStatoEl.value='DA_FARE';
    modalTitle.innerHTML = '<i class="bi bi-plus-circle"></i> Nuova pulizia';

    // meta vuota
    metaInizio.textContent='—';
    metaFine.textContent='—';
    metaApertoDa.textContent='—';
    metaChiusoDa.textContent='—';

    await loadModalEdifici(0);
    await loadModalPiani(0, 0);
    await loadModalCamere(0, 0);
    await loadAssegnate(0);

    tipoEl.value = 'STANDARD';
    noteEl.value = '';
    if (dataEl) {
      dataEl.value = (fData && fData.value) ? fData.value : new Date().toISOString().slice(0,10);
    }

    bsTask?.show();
  }

  btnNew.addEventListener('click', openCreate);

  async function openEditFromCard(card){
    clearMsg();
    modeEl.value='edit';
    const id = card.dataset.id || '';
    idEl.value=id;

    const statoCur = card.dataset.stato || '';
    currentStatoEl.value = statoCur;

    modalTitle.innerHTML = '<i class="bi bi-pencil"></i> Modifica pulizia #' + id;

    const cameraId = parseInt(card.dataset.cameraId||'0',10);
    const path = await apiMeta('camera_path', { camera_id: cameraId });
    const edificioId = parseInt(path?.item?.edificio_id || '0',10);
    const pianoId    = parseInt(path?.item?.piano_id || '0',10);

    await loadModalEdifici(edificioId);
    await loadModalPiani(edificioId, pianoId);
    await loadModalCamere(pianoId, cameraId);
    await loadAssegnate(parseInt(card.dataset.assegnataA||'0',10));

    tipoEl.value = card.dataset.tipo || 'STANDARD';
    noteEl.value = card.dataset.note || '';
    if (dataEl) dataEl.value = card.dataset.data || '';

    fillMetaFromCard(card);

    bsTask?.show();
  }

  // submit
  form.addEventListener('submit', async (e)=>{
    e.preventDefault();
    clearMsg();

    const payload = {
      id: idEl.value,
      camera_id: parseInt(cameraSel.value||'0',10),
      data: (dataEl && dataEl.value) ? dataEl.value : '',
      tipo: tipoEl.value || 'STANDARD',
      assegnata_a: parseInt(assegnataSel.value||'0',10),
      note: noteEl.value.trim(),
    };

    if (!payload.camera_id) { showErr('Camera obbligatoria.'); return; }

    spin.classList.remove('d-none'); btnSave.disabled=true;

    try{
      const fd = new FormData();
      Object.entries(payload).forEach(([k,v])=> fd.append(k, String(v)));

      const url = (modeEl.value==='create')
        ? 'task_pulizie_create_ajax.php'
        : 'task_pulizie_edit_ajax.php';

      const j = await apiJson(url, { method:'POST', body: fd });
      showOk(j.msg || 'Salvato');

      await loadBoard();

      setTimeout(()=> bsTask?.hide(), 250);
    } catch(err){
      showErr(err.message || 'Errore');
    } finally {
      spin.classList.add('d-none'); btnSave.disabled=false;
    }
  });

  // move
  let pendingMove=null;
  function moveMessage(to){
    if (to==='IN_CORSO') return 'Confermi che la pulizia è <b>iniziata</b>?';
    if (to==='COMPLETATA') return 'Confermi che la pulizia è <b>completata</b>?';
    if (to==='DA_FARE') return 'Confermi che vuoi riportare la pulizia in <b>DA FARE</b>?';
    return 'Confermi?';
  }
  async function doMove(id, st){
    const fd = new FormData();
    fd.append('id', String(id));
    fd.append('stato', String(st));
    await apiJson('task_pulizie_move_ajax.php', { method:'POST', body: fd });
  }
  btnMoveYes.addEventListener('click', async ()=>{
    if(!pendingMove) return;
    const {id,to} = pendingMove;
    btnMoveYes.disabled=true;
    try{
      await doMove(id,to);
      bsMove?.hide();
      await loadBoard();
    } catch(e){
      alert(e.message || 'Errore');
    } finally {
      btnMoveYes.disabled=false;
      pendingMove=null;
    }
  });

  document.addEventListener('click', async (e)=>{
    const btnAssign = e.target.closest('.js-assign-me');
    if (btnAssign){
      const id = parseInt(btnAssign.dataset.id||'0',10);
      if (!id || !CURRENT_UID) return;
      btnAssign.disabled = true;
      try{
        const fd = new FormData();
        fd.append('id', String(id));
        fd.append('assegnata_a', String(CURRENT_UID));
        await apiJson('task_pulizie_assign_ajax.php', { method:'POST', body: fd });
        await loadBoard();
      } catch(err){
        alert(err.message || 'Errore');
      } finally {
        btnAssign.disabled = false;
      }
      return;
    }

    const btnEdit = e.target.closest('.js-edit');
    if (btnEdit){
      const card = btnEdit.closest('.tcard');
      if (card) await openEditFromCard(card);
      return;
    }
    const btnMove = e.target.closest('.js-move');
    if (btnMove){
      const id = parseInt(btnMove.dataset.id||'0',10);
      const to = btnMove.dataset.to || '';
      if(!id || !to) return;
      pendingMove = {id,to};
      moveText.innerHTML = moveMessage(to);
      bsMove?.show();
      return;
    }
  });

  function ensureBootstrap(){
    return new Promise((resolve)=>{
      if (window.bootstrap) return resolve(window.bootstrap);
      const s=document.createElement('script');
      s.src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js';
      s.async=true;
      s.onload=()=>resolve(window.bootstrap);
      s.onerror=()=>resolve(null);
      document.head.appendChild(s);
      setTimeout(()=>resolve(window.bootstrap||null),2500);
    });
  }

  (async function init(){
    // Data di default: oggi (l'utente può comunque cambiarla manualmente)
    if (fData && !fData.value) {
      fData.value = new Date().toISOString().slice(0,10);
    }

    // load struttura meta (non bloccare la plancia se fallisce)
    try {
      await loadEdifici(0);
      await loadPiani(0,0);
      await loadCamere(0,0);
      await loadAssigneeFilter(0);
      syncFilterToggles();
    } catch (e) {
      console.error(e);
    }

    const bs = await ensureBootstrap();
    if (bs){
      bsTask = new bs.Modal(taskModalEl);
      bsMove = new bs.Modal(moveModalEl);
    }

    await loadBoard().catch(console.error);
  })();

})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
