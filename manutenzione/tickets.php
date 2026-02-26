<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

if (!function_exists('require_root')) { function require_root(){} }
require_root();

include __DIR__ . '/../includes/header.php';
?>

<style>
  /* ===== Top bar modern ===== */
.topbar{ display:flex; gap:14px; flex-wrap:wrap; align-items:flex-start; justify-content:space-between; }
.topbar .left h3{ margin:0; }
.topbar .left .sub{ color:#6c757d; font-size:.9rem; margin-top:4px; }
.topbar .right{ display:flex; gap:10px; flex-wrap:wrap; align-items:center; justify-content:flex-end; }

/* pill group */
.pillbar{
  display:flex;
  gap:10px;
  align-items:center;
  flex-wrap:wrap;
  padding:10px;
  border-radius:16px;
  background:#fff;
  border:1px solid rgba(0,0,0,.08);
  box-shadow: 0 .20rem .70rem rgba(0,0,0,.04);
}

/* search */
.searchbox{
  display:flex;
  align-items:center;
  gap:8px;
  border:1px solid rgba(0,0,0,.10);
  background:#f9fafb;
  border-radius:14px;
  padding:8px 10px;
  min-width: 320px;
}
.searchbox i{ opacity:.75; }
.searchbox input{
  border:0;
  outline:0;
  background:transparent;
  width:100%;
  font-size:.95rem;
}
@media (max-width: 992px){
  .searchbox{ min-width: 100%; }
  .pillbar{ width:100%; }
}

/* select */
.select-pill{
  border-radius:14px !important;
  background:#f9fafb !important;
  border:1px solid rgba(0,0,0,.10) !important;
  height:42px;
  padding-left:12px;
  padding-right:12px;
}

/* annullati chip */
.ann-chip{
  display:flex;
  align-items:center;
  gap:10px;
  padding:8px 12px;
  border-radius:14px;
  background:#f9fafb;
  border:1px solid rgba(0,0,0,.10);
  height:42px;
}
.ann-chip .form-check{ margin:0; }
.ann-chip .form-check-input{ cursor:pointer; }
.ann-chip .lbl{ font-size:.95rem; color:#111827; font-weight:600; }
  .ann-chip .count{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  min-width: 30px;
  padding:2px 8px;
  border-radius:999px;
  background: rgba(220,53,69,.12);
  border: 1px solid rgba(220,53,69,.20);
  color:#dc3545;
  font-weight:800;
  font-size:.85rem;
}

/* CTA button */
.btn-cta{
  border-radius:14px;
  height:42px;
  padding:0 14px;
  font-weight:800;
  box-shadow: 0 .25rem .85rem rgba(13,110,253,.18);
}
  .btn-cta i{ margin-right:6px; }

  .filter-chip{
    display:flex;
    align-items:center;
    gap:8px;
    padding:8px 12px;
    border-radius:14px;
    background:#f9fafb;
    border:1px solid rgba(0,0,0,.10);
    height:42px;
  }
  .filter-chip .form-check{ margin:0; }
  .filter-chip .form-check-input{ cursor:pointer; }
  .filter-chip .lbl{ font-size:.95rem; color:#111827; font-weight:600; }

  .tickets-top{ display:flex; gap:12px; flex-wrap:wrap; align-items:center; justify-content:space-between; }
  .tickets-filters{ display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
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

  .tcard{
    border:1px solid rgba(0,0,0,.08);
    background:#fff;
    border-radius:16px;
    padding:10px 11px;
    margin-bottom:12px;
    box-shadow:0 .25rem .75rem rgba(0,0,0,.04);
  }
  .tcard:last-child{ margin-bottom:0; }
  .tcard .top{ display:flex; gap:8px; align-items:flex-start; justify-content:space-between; }
  .tcard .title{ font-weight:700; font-size:.95rem; line-height:1.2; }
  .tcard .desc{ margin-top:7px; color:#6c757d; font-size:.88rem; white-space:pre-wrap; }

  .tcard .meta-wrap{
    display:flex;
    flex-wrap:wrap;
    gap:6px;
    margin-top:7px;
    align-items:center;
  }

  .tacts{ display:flex; align-items:center; gap:6px; flex-shrink:0; }
  .btn-mini{
    width:38px; height:34px; padding:0;
    display:inline-flex; align-items:center; justify-content:center;
    border-radius:11px;
  }

  .badge-soft{ background:rgba(0,0,0,.04); border:1px solid rgba(0,0,0,.08); color:#111; }
  .badge-prio-URGENTE{ background:rgba(220,53,69,.12); color:#dc3545; border:1px solid rgba(220,53,69,.25); }
  .badge-prio-ALTA{ background:rgba(255,193,7,.18); color:#b8860b; border:1px solid rgba(255,193,7,.35); }
  .badge-prio-MEDIA{ background:rgba(13,110,253,.12); color:#0d6efd; border:1px solid rgba(13,110,253,.25); }
  .badge-prio-BASSA{ background:rgba(25,135,84,.12); color:#198754; border:1px solid rgba(25,135,84,.25); }

  .muted-empty{ padding:10px; color:#6c757d; font-size:.9rem; }
  .pill{ padding:6px 12px; border-radius:999px; background:rgba(0,0,0,.04); font-size:.82rem; display:flex; align-items:center; gap:8px; }

  .ann-count{ display:inline-flex; align-items:center; gap:6px; }
  .ann-badge{
    display:inline-flex; align-items:center; justify-content:center;
    min-width: 34px;
    padding: 2px 8px;
    border-radius: 999px;
    background: rgba(220,53,69,.10);
    border: 1px solid rgba(220,53,69,.20);
    color: #dc3545;
    font-weight: 700;
    font-size: .8rem;
  }

  .select-slim{ height: 38px; border-radius: 12px; }
  .form-control.select-slim{ padding-top: 6px; padding-bottom: 6px; }
  .form-select.select-slim{ padding-top: 6px; padding-bottom: 6px; }

  .tiny-help{ font-size: .82rem; color: #6c757d; }

  /* badge base */
  .tcard .badge {
    border-radius: 999px;
    padding: .34rem .58rem;
    font-weight: 600;
    font-size: .8rem;
  }

  /* stile "soft" già usato */
  .tcard .badge-soft{
    background: #f3f4f6;
    border: 1px solid rgba(0,0,0,.08);
    color: #111827;
  }

  /* colori priorità */
  .tcard .prio { border: 0; color: #fff; }
  .tcard .prio-bassa   { background: #22c55e; } /* verde */
  .tcard .prio-media   { background: #3b82f6; } /* blu */
  .tcard .prio-alta    { background: #f59e0b; } /* arancio */
  .tcard .prio-urgente { background: #ef4444; } /* rosso */

  @media (max-width: 1400px){
    .tcard .top{ flex-direction:column; }
    .tacts{ width:100%; justify-content:flex-end; }
  }

</style>

<div class="container-fluid">
  <div class="topbar mb-3">
    <div class="left">
      <h3><i class="bi bi-tools"></i> Ticket Manutenzione</h3>
      <div class="sub">Board: <b>Aperto</b> → <b>In Corso</b> → <b>Risolto</b></div>
    </div>

  <div class="topbar-right">
    <div class="pillbar">
      <div class="searchbox">
        <i class="bi bi-search"></i>
        <input type="text" id="q" placeholder="Cerca (titolo, descrizione, camera, ecc.)">
      </div>

      <select class="form-select select-pill" id="priorita" style="width: 170px;">
        <option value="ALL">Priorità: tutte</option>
        <option value="BASSA">BASSA</option>
        <option value="MEDIA">MEDIA</option>
        <option value="ALTA">ALTA</option>
        <option value="URGENTE">URGENTE</option>
      </select>

      <select class="form-select select-pill" id="f_assignee" style="width: 220px;">
        <option value="0">Assegnato a: tutti</option>
      </select>

      <div class="filter-chip">
        <div class="form-check form-switch d-flex align-items-center gap-2">
          <input class="form-check-input" type="checkbox" id="f_my">
          <label class="form-check-label lbl" for="f_my">Solo miei</label>
        </div>
      </div>

      <div class="filter-chip">
        <div class="form-check form-switch d-flex align-items-center gap-2">
          <input class="form-check-input" type="checkbox" id="f_unassigned">
          <label class="form-check-label lbl" for="f_unassigned">Non assegnati</label>
        </div>
      </div>

      <div class="ann-chip">
        <div class="form-check form-switch d-flex align-items-center gap-2">
          <input class="form-check-input" type="checkbox" id="showAnnullati">
          <label class="form-check-label lbl" for="showAnnullati">Annullati</label>
        </div>
        <span class="count" id="cntAnnullati">0</span>
      </div>

      <button class="btn btn-primary btn-cta" id="btnNew">
        <i class="bi bi-plus-lg"></i> Nuovo ticket
      </button>
    </div>
  </div>
</div>


  <div class="board">
    <!-- APERTO -->
    <div class="col-card">
      <div class="col-head">
        <b><i class="bi bi-inbox"></i> Aperti</b>
        <span class="pill"><i class="bi bi-hash"></i> <b id="cntAperti">0</b></span>
      </div>
      <div class="col-body" id="colAperti"><div class="muted-empty">Caricamento…</div></div>
      <div class="col-foot">
        <button class="btn btn-outline-secondary btn-sm" id="prevA"><i class="bi bi-chevron-left"></i></button>
        <div class="small text-muted">Pagina <b id="pageA">1</b></div>
        <button class="btn btn-outline-secondary btn-sm" id="nextA"><i class="bi bi-chevron-right"></i></button>
      </div>
    </div>

    <!-- IN_CORSO -->
    <div class="col-card">
      <div class="col-head">
        <b><i class="bi bi-play-circle"></i> In Corso</b>
        <span class="pill"><i class="bi bi-hash"></i> <b id="cntInCorso">0</b></span>
      </div>
      <div class="col-body" id="colInCorso"><div class="muted-empty">Caricamento…</div></div>
      <div class="col-foot">
        <button class="btn btn-outline-secondary btn-sm" id="prevI"><i class="bi bi-chevron-left"></i></button>
        <div class="small text-muted">Pagina <b id="pageI">1</b></div>
        <button class="btn btn-outline-secondary btn-sm" id="nextI"><i class="bi bi-chevron-right"></i></button>
      </div>
    </div>

    <!-- RISOLTO -->
    <div class="col-card">
      <div class="col-head">
        <b><i class="bi bi-check2-circle"></i> Completate</b>
        <span class="pill"><i class="bi bi-hash"></i> <b id="cntRisolti">0</b></span>
      </div>
      <div class="col-body" id="colRisolti"><div class="muted-empty">Caricamento…</div></div>
      <div class="col-foot">
        <button class="btn btn-outline-secondary btn-sm" id="prevR"><i class="bi bi-chevron-left"></i></button>
        <div class="small text-muted">Pagina <b id="pageR">1</b></div>
        <button class="btn btn-outline-secondary btn-sm" id="nextR"><i class="bi bi-chevron-right"></i></button>
      </div>
    </div>
  </div>
</div>

<!-- MODALE ANNULATI -->
<div class="modal fade" id="modalAnnullati" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content rounded-4">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-x-circle"></i> Ticket annullati</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
      </div>
      <div class="modal-body">
        <div id="annullatiList"><div class="muted-empty">Caricamento…</div></div>

        <div class="d-flex align-items-center justify-content-between mt-3">
          <button class="btn btn-outline-secondary btn-sm" id="prevAnn"><i class="bi bi-chevron-left"></i></button>
          <div class="small text-muted">Pagina <b id="pageAnn">1</b></div>
          <button class="btn btn-outline-secondary btn-sm" id="nextAnn"><i class="bi bi-chevron-right"></i></button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- MODALE CREATE/EDIT -->
<div class="modal fade" id="ticketModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <form class="modal-content rounded-4" id="ticketForm" autocomplete="off">
      <div class="modal-header">
        <h5 class="modal-title" id="ticketTitle"><i class="bi bi-plus-circle"></i> Nuovo ticket</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
      </div>

      <div class="modal-body">
        <input type="hidden" id="mode" value="create">
        <input type="hidden" id="ticketId" value="">
        <input type="hidden" id="currentStato" value="">

        
        <!-- META (solo info) -->
        <div class="card border-0 shadow-sm mb-3" id="ticketMetaCard">
          <div class="card-body py-2">
            <div class="row g-2 small text-muted">
              <div class="col-md-6">
                <i class="bi bi-calendar-event"></i> Inizio: <b id="metaOpened">—</b>
              </div>
              <div class="col-md-6">
                <i class="bi bi-person"></i> Aperto da: <b id="metaOpenfrom">—</b>
              </div>
              <div class="col-md-6">
                <i class="bi bi-calendar-check"></i> Fine:&nbsp&nbsp&nbsp<b id="metaClosed">—</b>
              </div>
              <div class="col-md-6">
                <i class="bi bi-person-check"></i> Chiuso da:<b id="metaClosefrom">—</b>
              </div>
            </div>
          </div>
        </div>
        <div class="row g-3">
          <div class="col-md-8">
            <label class="form-label">Titolo</label>
            <input type="text" class="form-control" id="titolo" maxlength="180" required>
          </div>

          <div class="col-md-4">
            <label class="form-label">Priorità</label>
            <select class="form-select" id="prio" required>
              <option value="BASSA">BASSA</option>
              <option value="MEDIA" selected>MEDIA</option>
              <option value="ALTA">ALTA</option>
              <option value="URGENTE">URGENTE</option>
            </select>
          </div>

          <div class="col-12">
            <label class="form-label">Descrizione</label>
            <textarea class="form-control" id="descrizione" rows="4" placeholder="Descrivi il problema…"></textarea>
          </div>

          <div class="col-md-4">
            <label class="form-label">Edificio</label>
            <select class="form-select" id="edificio_id">
              <option value="0">Altro / Nessun riferimento</option>
            </select>
          </div>

          <div class="col-md-4">
            <label class="form-label">Piano</label>
            <select class="form-select" id="piano_id" disabled>
              <option value="0">—</option>
            </select>
          </div>

          <div class="col-md-4">
            <label class="form-label">Camera</label>
            <select class="form-select" id="camera_id" disabled>
              <option value="0">—</option>
            </select>
          </div>

          <div class="col-md-4">
            <label class="form-label">Assegnato a</label>
            <select class="form-select" id="assegnato_a">
              <option value="0">Non assegnato</option>
            </select>
          </div>

          <!-- SOLO IN EDIT: annullamento / ripristino -->
          <div class="col-md-4 d-none" id="editOnlyBox">
            <label class="form-label">Gestione annullamento</label>
            <div class="d-flex gap-2">
              <select class="form-select" id="editStato">
                <option value="">— nessuna modifica —</option>
                <option value="ANNULLATO">Annulla ticket</option>
                <option value="APERTO">Ripristina in: APERTO</option>
                <option value="IN_CORSO">Ripristina in: IN_CORSO</option>
                <option value="RISOLTO">Ripristina in: RISOLTO</option>
              </select>
            </div>
          </div>
        </div>

        <div class="alert alert-danger d-none mt-3" id="errBox"></div>
        <div class="alert alert-success d-none mt-3" id="okBox"></div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annulla</button>
        <button type="submit" class="btn btn-primary" id="btnSave">
          <span class="spinner-border spinner-border-sm me-2 d-none" id="spinSave"></span>
          Salva
        </button>
      </div>
    </form>
  </div>
</div>

<!-- MODALE CONFERMA CAMBIO STATO -->
<div class="modal fade" id="moveModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-question-circle"></i> Conferma</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
      </div>
      <div class="modal-body">
        <div id="moveText" class="mb-0">Confermi?</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">No</button>
        <button type="button" class="btn btn-primary" id="btnMoveYes">Sì, confermo</button>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  const PER_PAGE = 15;
  const CURRENT_UID = <?= (int)($_SESSION['utente_id'] ?? 0) ?>;

  // filtri
  const qEl = document.getElementById('q');
  const prEl = document.getElementById('priorita');
  const assigneeEl = document.getElementById('f_assignee');
  const myOnlyEl = document.getElementById('f_my');
  const unassignedEl = document.getElementById('f_unassigned');

  // annullati
  const annSwitch = document.getElementById('showAnnullati');
  const cntAnn = document.getElementById('cntAnnullati');
  const modalAnnEl = document.getElementById('modalAnnullati');
  const annList = document.getElementById('annullatiList');
  const pageAnnEl = document.getElementById('pageAnn');
  const prevAnn = document.getElementById('prevAnn');
  const nextAnn = document.getElementById('nextAnn');

  // board containers
  const colA = document.getElementById('colAperti');
  const colI = document.getElementById('colInCorso');
  const colR = document.getElementById('colRisolti');
  const cntA = document.getElementById('cntAperti');
  const cntI = document.getElementById('cntInCorso');
  const cntR = document.getElementById('cntRisolti');

  // paginazione board
  const pageAEl = document.getElementById('pageA');
  const pageIEl = document.getElementById('pageI');
  const pageREl = document.getElementById('pageR');
  const prevA = document.getElementById('prevA');
  const nextA = document.getElementById('nextA');
  const prevI = document.getElementById('prevI');
  const nextI = document.getElementById('nextI');
  const prevR = document.getElementById('prevR');
  const nextR = document.getElementById('nextR');

  // create/edit
  const btnNew = document.getElementById('btnNew');
  const ticketModalEl = document.getElementById('ticketModal');
  const form = document.getElementById('ticketForm');
  const titleEl = document.getElementById('ticketTitle');
  const modeEl = document.getElementById('mode');
  const idEl = document.getElementById('ticketId');
  const currentStatoEl = document.getElementById('currentStato');

  
  const metaCard = document.getElementById('ticketMetaCard');
  const metaOpenedEl = document.getElementById('metaOpened');
  const metaClosedEl = document.getElementById('metaClosed');
  const metaOpenfromEl = document.getElementById('metaOpenfrom');
  const metaClosefromEl = document.getElementById('metaClosefrom');

  const titolo = document.getElementById('titolo');
  const prio = document.getElementById('prio');
  const descr = document.getElementById('descrizione');

  // select cascata
  const selEd = document.getElementById('edificio_id');
  const selPi = document.getElementById('piano_id');
  const selCa = document.getElementById('camera_id');
  const selAs = document.getElementById('assegnato_a');

  // edit-only stato annullamento/ripristino
  const editOnlyBox = document.getElementById('editOnlyBox');
  const editStato = document.getElementById('editStato');

  const errBox = document.getElementById('errBox');
  const okBox = document.getElementById('okBox');
  const spin = document.getElementById('spinSave');
  const btnSave = document.getElementById('btnSave');

  // move confirm modal
  const moveModalEl = document.getElementById('moveModal');
  const moveText = document.getElementById('moveText');
  const btnMoveYes = document.getElementById('btnMoveYes');

  // stato pagine
  const pages = { APERTO: 1, IN_CORSO: 1, RISOLTO: 1 };
  let annPage = 1;

  // enable/disable next buttons in base al backend
  let hasMore = { APERTO:false, IN_CORSO:false, RISOLTO:false, ANNULLATO:false };

  let bsTicket = null;
  let bsAnn = null;
  let bsMove = null;

  const nativeFetch = window.fetch.bind(window);

  function setEditStatoOptions(currentStato){
    // reset
    editStato.innerHTML = '<option value="">— nessuna modifica —</option>';

    if (currentStato === 'ANNULLATO') {
      // ✅ Se è ANNULLATO: solo ripristino (niente annulla)
      editStato.insertAdjacentHTML('beforeend', `
        <option value="APERTO">Ripristina in: APERTO</option>
        <option value="IN_CORSO">Ripristina in: IN_CORSO</option>
        <option value="RISOLTO">Ripristina in: COMPLETATO</option>
      `);
    } else {
      // ✅ Se NON è annullato: solo annulla (niente ripristino)
      editStato.insertAdjacentHTML('beforeend', `
        <option value="ANNULLATO">Annulla ticket</option>
      `);
    }
  }


  function qs(obj){
    const p = new URLSearchParams();
    Object.entries(obj).forEach(([k,v])=>{
      if (v === null || v === undefined || v === '') return;
      p.set(k, String(v));
    });
    return p.toString();
  }

  function clearMsg(){
    errBox.classList.add('d-none'); errBox.textContent='';
    okBox.classList.add('d-none'); okBox.textContent='';
  }
  function showErr(msg){
    errBox.textContent = msg || 'Errore';
    errBox.classList.remove('d-none');
    okBox.classList.add('d-none');
  }
  function showOk(msg){
    okBox.textContent = msg || 'Salvato';
    okBox.classList.remove('d-none');
    errBox.classList.add('d-none');
  }

  function currentFilters(){
    let assignee = parseInt(assigneeEl.value || '0', 10);
    const myOnly = !!myOnlyEl.checked;
    const unassigned = !!unassignedEl.checked;

    if (myOnly && CURRENT_UID > 0) {
      assignee = CURRENT_UID;
    }

    return {
      q: qEl.value.trim(),
      priorita: prEl.value || 'ALL',
      assegnato_a: assignee,
      unassigned: unassigned ? 1 : 0
    };
  }

  async function apiJson(url, options = {}){
    const res = await nativeFetch(url, { headers:{'X-Requested-With':'XMLHttpRequest'}, ...options });
    const txt = await res.text();
    let j;
    try { j = JSON.parse(txt); } catch(e){ throw new Error('Risposta non JSON: ' + txt.slice(0,300)); }
    if (j && j.ok === false) throw new Error(j.msg || 'Errore');
    return j;
  }

  // -------- META (select) --------
  async function apiMeta(type, params = {}) {
    const u = 'ticket_manutenzione_meta_ajax.php?' + qs({ type, ...params });
    const j = await apiJson(u);
    return j.items || [];
  }
  function fillSelect(sel, items, firstOptionHtml) {
    sel.innerHTML = firstOptionHtml;
    for (const it of items) {
      const opt = document.createElement('option');
      opt.value = String(it.id);
      opt.textContent = it.label;
      sel.appendChild(opt);
    }
  }
  async function loadAssegnati(selectedId = 0){
    const items = await apiMeta('assegnati');
    fillSelect(selAs, items, '<option value="0">Non assegnato</option>');
    selAs.value = String(selectedId || 0);
  }

  async function loadAssigneeFilter(selectedId = 0){
    const items = await apiMeta('assegnati');
    fillSelect(assigneeEl, items, '<option value="0">Assegnato a: tutti</option>');
    assigneeEl.value = String(selectedId || 0);
  }
  async function loadEdifici(selectedId = 0) {
    const items = await apiMeta('edifici');
    // "Altro" SOLO QUI (evita duplicati)
    fillSelect(selEd, items, '<option value="0">Altro / Nessun riferimento</option>');
    selEd.value = String(selectedId || 0);
  }
  async function loadPiani(edificioId, selectedId = 0) {
    if (!edificioId) {
      selPi.disabled = true;
      fillSelect(selPi, [], '<option value="0">—</option>');
      return;
    }
    const items = await apiMeta('piani', { edificio_id: edificioId });
    selPi.disabled = false;
    fillSelect(selPi, items, '<option value="0">Seleziona piano…</option>');
    selPi.value = String(selectedId || 0);
  }
  async function loadCamere(pianoId, selectedId = 0) {
    if (!pianoId) {
      selCa.disabled = true;
      fillSelect(selCa, [], '<option value="0">—</option>');
      return;
    }
    const items = await apiMeta('camere', { piano_id: pianoId });
    selCa.disabled = false;
    fillSelect(selCa, items, '<option value="0">Seleziona camera…</option>');
    selCa.value = String(selectedId || 0);
  }

  selEd.addEventListener('change', async () => {
    const eid = parseInt(selEd.value || '0', 10);
    await loadPiani(eid, 0);
    await loadCamere(0, 0);
  });
  selPi.addEventListener('change', async () => {
    const pid = parseInt(selPi.value || '0', 10);
    await loadCamere(pid, 0);
  });

  // -------- BOARD --------
  async function loadBoard(){
    const f = currentFilters();
    const params = {
      ...f,
      per_page: PER_PAGE,
      page_aperto: pages.APERTO,
      page_in_corso: pages.IN_CORSO,
      page_risolto: pages.RISOLTO,
      want_annullati_html: 0
    };

    const j = await apiJson('ticket_manutenzione_ajax.php?' + qs(params));

    colA.innerHTML = j.html?.APERTO ?? "<div class='muted-empty'>—</div>";
    colI.innerHTML = j.html?.IN_CORSO ?? "<div class='muted-empty'>—</div>";
    colR.innerHTML = j.html?.RISOLTO ?? "<div class='muted-empty'>—</div>";

    cntA.textContent = String(j.counts?.APERTO ?? 0);
    cntI.textContent = String(j.counts?.IN_CORSO ?? 0);
    cntR.textContent = String(j.counts?.RISOLTO ?? 0);
    cntAnn.textContent = String(j.counts?.ANNULLATO ?? 0);

    pageAEl.textContent = String(pages.APERTO);
    pageIEl.textContent = String(pages.IN_CORSO);
    pageREl.textContent = String(pages.RISOLTO);

    hasMore.APERTO = !!(j.has_more?.APERTO);
    hasMore.IN_CORSO = !!(j.has_more?.IN_CORSO);
    hasMore.RISOLTO = !!(j.has_more?.RISOLTO);

    prevA.disabled = pages.APERTO <= 1;
    prevI.disabled = pages.IN_CORSO <= 1;
    prevR.disabled = pages.RISOLTO <= 1;

    nextA.disabled = !hasMore.APERTO;
    nextI.disabled = !hasMore.IN_CORSO;
    nextR.disabled = !hasMore.RISOLTO;
  }

  async function loadAnnullati(){
    const f = currentFilters();
    const params = {
      ...f,
      per_page: PER_PAGE,
      annullati_page: annPage,
      want_annullati_html: 1
    };
    const j = await apiJson('ticket_manutenzione_ajax.php?' + qs(params));

    annList.innerHTML = j.html?.ANNULLATO ?? "<div class='muted-empty'>Nessun annullato.</div>";
    cntAnn.textContent = String(j.counts?.ANNULLATO ?? 0);

    hasMore.ANNULLATO = !!(j.has_more?.ANNULLATO);
    pageAnnEl.textContent = String(annPage);

    prevAnn.disabled = annPage <= 1;
    nextAnn.disabled = !hasMore.ANNULLATO;
  }

  function resetPages(){
    pages.APERTO = 1;
    pages.IN_CORSO = 1;
    pages.RISOLTO = 1;
    annPage = 1;
  }

  // debounce filtri
  let t = null;
  function scheduleReload(){
    clearTimeout(t);
    t = setTimeout(async ()=>{
      resetPages();
      await loadBoard().catch(console.error);
      if (annSwitch.checked) await loadAnnullati().catch(console.error);
    }, 220);
  }
  qEl.addEventListener('input', scheduleReload);
  prEl.addEventListener('change', scheduleReload);
  assigneeEl.addEventListener('change', scheduleReload);

  function syncFilterToggles(){
    if (myOnlyEl.checked) {
      unassignedEl.checked = false;
      assigneeEl.disabled = true;
    } else if (unassignedEl.checked) {
      myOnlyEl.checked = false;
      assigneeEl.disabled = true;
    } else {
      assigneeEl.disabled = false;
    }
  }
  myOnlyEl.addEventListener('change', ()=>{ syncFilterToggles(); scheduleReload(); });
  unassignedEl.addEventListener('change', ()=>{ syncFilterToggles(); scheduleReload(); });

  // paginazione board
  prevA.addEventListener('click', async ()=>{ if(pages.APERTO>1){ pages.APERTO--; await loadBoard(); }});
  nextA.addEventListener('click', async ()=>{ if(hasMore.APERTO){ pages.APERTO++; await loadBoard(); }});

  prevI.addEventListener('click', async ()=>{ if(pages.IN_CORSO>1){ pages.IN_CORSO--; await loadBoard(); }});
  nextI.addEventListener('click', async ()=>{ if(hasMore.IN_CORSO){ pages.IN_CORSO++; await loadBoard(); }});

  prevR.addEventListener('click', async ()=>{ if(pages.RISOLTO>1){ pages.RISOLTO--; await loadBoard(); }});
  nextR.addEventListener('click', async ()=>{ if(hasMore.RISOLTO){ pages.RISOLTO++; await loadBoard(); }});

  // annullati modal
  prevAnn.addEventListener('click', async ()=>{ if(annPage>1){ annPage--; await loadAnnullati(); }});
  nextAnn.addEventListener('click', async ()=>{ if(hasMore.ANNULLATO){ annPage++; await loadAnnullati(); }});

  annSwitch.addEventListener('change', async ()=>{
    if (annSwitch.checked){
      annPage = 1;
      await loadAnnullati();
      bsAnn?.show();
    } else {
      bsAnn?.hide();
    }
  });
  modalAnnEl.addEventListener('hidden.bs.modal', ()=>{
    annSwitch.checked = false;
  });

  // -------- CREATE/EDIT --------
  async function openCreate(){
    clearMsg();
    // meta (create: non disponibile)
    modeEl.value = 'create';
    if(metaCard){ metaCard.classList.add('d-none'); metaOpenedEl.textContent='—'; metaClosedEl.textContent='—'; metaOpenfromEl.textContent='—';}
    idEl.value = '';
    currentStatoEl.value = '';
    titleEl.innerHTML = '<i class="bi bi-plus-circle"></i> Nuovo ticket';

    titolo.value = '';
    prio.value = 'MEDIA';
    descr.value = '';
    editOnlyBox.classList.add('d-none');
    editStato.value = '';

    setEditStatoOptions('APERTO');

    await loadEdifici(0);
    await loadPiani(0,0);
    await loadCamere(0,0);
    await loadAssegnati(0);

    bsTicket?.show();
  }
  
  function fmtDt(val){
    if(!val) return '—';
    // val is "YYYY-MM-DD HH:MM:SS"
    const d = new Date(val.replace(' ', 'T'));
    if (isNaN(d.getTime())) return String(val);
    return d.toLocaleString('it-IT', { year:'numeric', month:'2-digit', day:'2-digit', hour:'2-digit', minute:'2-digit' });
  }

  function setMetaFromCard(card){
  if(!metaCard) return;

  if(modeEl.value === 'create'){
      metaOpenedEl.textContent = '—';
      metaClosedEl.textContent = '—';
      metaOpenfromEl.textContent = '—';
      if (metaClosefromEl) metaClosefromEl.textContent = '—';
      metaCard.classList.add('d-none');
      return;
    }

    metaCard.classList.remove('d-none');

    const openedAt   = card.dataset.openedAt || '';
    const closedAt   = card.dataset.closedAt || '';
    const apertoNome = (card.dataset.apertoNome || '').trim();
    const chiusoNome = (card.dataset.chiusoNome || '').trim();

    // Date
    metaOpenedEl.textContent = openedAt ? fmtDt(openedAt) : '—';
    metaClosedEl.textContent = closedAt ? fmtDt(closedAt) : '—';

    // Nomi
    metaOpenfromEl.textContent = apertoNome !== '' ? apertoNome : '—';
    if (metaClosefromEl) metaClosefromEl.textContent = chiusoNome !== '' ? chiusoNome : '—';

    // muted style su "—"
    metaOpenedEl.classList.toggle('muted', metaOpenedEl.textContent === '—');
    metaClosedEl.classList.toggle('muted', metaClosedEl.textContent === '—');
    metaOpenfromEl.classList.toggle('muted', metaOpenfromEl.textContent === '—');
    if (metaClosefromEl) metaClosefromEl.classList.toggle('muted', metaClosefromEl.textContent === '—');
  }




btnNew.addEventListener('click', openCreate);

  async function openEditFromCard(card){
    clearMsg();
    modeEl.value = 'edit';
    const id = card.dataset.id || '';
    idEl.value = id;
    const statoCur = card.dataset.stato || '';
    currentStatoEl.value = statoCur;
    setEditStatoOptions(statoCur);

    titleEl.innerHTML = '<i class="bi bi-pencil-square"></i> Modifica ticket #' + id;

    titolo.value = card.dataset.titolo || '';
    prio.value = card.dataset.priorita || 'MEDIA';
    descr.value = card.dataset.descrizione || '';

    // edit-only box visibile
    editOnlyBox.classList.remove('d-none');
    editStato.value = '';

    const edificioId = parseInt(card.dataset.edificioId || '0', 10);
    const pianoId    = parseInt(card.dataset.pianoId || '0', 10);
    const cameraId   = parseInt(card.dataset.cameraId || '0', 10);
    const assegnato  = parseInt(card.dataset.assegnatoA || '0', 10);

    await loadEdifici(edificioId);
    await loadPiani(edificioId, pianoId);
    await loadCamere(pianoId, cameraId);
    await loadAssegnati(assegnato);

    setMetaFromCard(card);
    bsTicket?.show();
  }

  // submit create/edit
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearMsg();

    const payload = {
      id: idEl.value,
      titolo: titolo.value.trim(),
      descrizione: descr.value.trim(),
      priorita: prio.value,
      edificio_id: parseInt(selEd.value || '0', 10),
      piano_id: parseInt(selPi.value || '0', 10),
      camera_id: parseInt(selCa.value || '0', 10),
      assegnato_a: parseInt(selAs.value || '0', 10),
      edit_stato: (modeEl.value === 'edit') ? (editStato.value || '') : ''
    };

    if(!payload.titolo){
      showErr('Il titolo è obbligatorio.');
      return;
    }

    spin.classList.remove('d-none');
    btnSave.disabled = true;

    try{
      const fd = new FormData();
      Object.entries(payload).forEach(([k,v])=> fd.append(k, String(v)));

      const url = (modeEl.value === 'create')
        ? 'ticket_manutenzione_create_ajax.php'
        : 'ticket_manutenzione_edit_ajax.php';

      const j = await apiJson(url, { method:'POST', body:fd });

      showOk(j.msg || 'Salvato');
      await loadBoard();
      if (annSwitch.checked) await loadAnnullati();
      setTimeout(()=> bsTicket?.hide(), 250);

    } catch(err){
      showErr(err.message || 'Errore');
    } finally {
      spin.classList.add('d-none');
      btnSave.disabled = false;
    }
  });

  // -------- MOVE con conferma --------
  let pendingMove = null;

  function moveMessage(toState){
    if (toState === 'IN_CORSO') return 'Confermi che la manutenzione è <b>in corso d’opera</b>?';
    if (toState === 'RISOLTO') return 'Confermi che la manutenzione è <b>risolta</b>?';
    if (toState === 'APERTO') return 'Confermi che vuoi riportare il ticket in <b>APERTO</b>?';
    if (toState === 'ANNULLATO') return 'Confermi che vuoi <b>annullare</b> questo ticket?';
    return 'Confermi?';
  }

  async function doMove(id, newState){
    const fd = new FormData();
    fd.append('id', String(id));
    fd.append('stato', String(newState));
    await apiJson('ticket_manutenzione_move_ajax.php', { method:'POST', body:fd });
  }

  btnMoveYes.addEventListener('click', async ()=>{
    if (!pendingMove) return;
    const { id, to } = pendingMove;
    btnMoveYes.disabled = true;
    try{
      await doMove(id, to);
      bsMove?.hide();
      await loadBoard();
      if (annSwitch.checked) await loadAnnullati();
    } catch(e){
      alert(e.message || 'Errore');
    } finally {
      btnMoveYes.disabled = false;
      pendingMove = null;
    }
  });

  document.addEventListener('click', async (e) => {
    const btnAssign = e.target.closest('.js-assign-me');
    if (btnAssign){
      const id = parseInt(btnAssign.dataset.id || '0', 10);
      if (!id || !CURRENT_UID) return;
      btnAssign.disabled = true;
      try{
        const fd = new FormData();
        fd.append('id', String(id));
        fd.append('assegnato_a', String(CURRENT_UID));
        await apiJson('ticket_manutenzione_assign_ajax.php', { method:'POST', body:fd });
        await loadBoard();
        if (annSwitch.checked) await loadAnnullati();
      } catch(err){
        alert(err.message || 'Errore');
      } finally {
        btnAssign.disabled = false;
      }
      return;
    }

    const btnEdit = e.target.closest('.js-edit-ticket');
    if (btnEdit){
      const card = btnEdit.closest('.tcard');
      if(card) await openEditFromCard(card);
      return;
    }

    const btnMove = e.target.closest('.js-move');
    if (btnMove){
      const id = parseInt(btnMove.dataset.id || '0', 10);
      const st = btnMove.dataset.to || '';
      if(!id || !st) return;

      pendingMove = { id, to: st };
      moveText.innerHTML = moveMessage(st);
      bsMove?.show();
      return;
    }
  });

  function ensureBootstrap(){
    return new Promise((resolve) => {
      if (window.bootstrap) return resolve(window.bootstrap);

      const s = document.createElement('script');
      s.src = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js';
      s.async = true;
      s.onload = () => resolve(window.bootstrap);
      s.onerror = () => resolve(null);
      document.head.appendChild(s);

      setTimeout(() => resolve(window.bootstrap || null), 2500);
    });
  }

  (async function init(){
    const bs = await ensureBootstrap();
    if (bs) {
      bsTicket = new bs.Modal(ticketModalEl);
      bsAnn = new bs.Modal(modalAnnEl);
      bsMove = new bs.Modal(moveModalEl);
    } else {
      console.warn('[Ticket] Bootstrap JS non disponibile: le modali non possono aprirsi.');
    }

    try { await loadAssigneeFilter(0); } catch(e) { console.error(e); }
    syncFilterToggles();
    await loadBoard().catch(console.error);
  })();

})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
