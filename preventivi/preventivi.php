<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
include __DIR__ . '/../includes/header.php';

if (!$isRoot && !in_gruppo('Reception')) {
    redirect('/dashboard.php');
}

$edifici = [];
$piani = [];

$resE = $mysqli->query("SELECT id, nome FROM struttura_edifici WHERE attivo = 1 ORDER BY nome ASC");
if ($resE) {
    $edifici = $resE->fetch_all(MYSQLI_ASSOC);
}

$resP = $mysqli->query("SELECT id, edificio_id, nome, livello FROM struttura_piani WHERE attivo = 1 ORDER BY livello ASC, nome ASC");
if ($resP) {
    $piani = $resP->fetch_all(MYSQLI_ASSOC);
}

$edificioSel = (int)($_GET['edificio_id'] ?? ($edifici[0]['id'] ?? 0));
$pianoSel = (int)($_GET['piano_id'] ?? 0);

if ($pianoSel === 0 && $edificioSel > 0) {
    foreach ($piani as $p) {
        if ((int)$p['edificio_id'] === $edificioSel) {
            $pianoSel = (int)$p['id'];
            break;
        }
    }
}
?>

<style>
  .filters-card{ border:0; border-radius:16px; box-shadow:0 .35rem 1rem rgba(0,0,0,.08); background:#fff; }
  .filters-card .card-body{ padding:16px; }

  .legend{ display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
  .legend .item{ display:inline-flex; align-items:center; gap:6px; font-size:.9rem; color:#555; }
  .legend .dot{ width:14px; height:14px; border-radius:50%; display:inline-block; box-shadow:0 0 0 1px rgba(0,0,0,.06) inset; }

  .calendar-wrap{ border-radius:16px; border:1px solid rgba(0,0,0,.06); background:#fff; box-shadow:0 .35rem 1rem rgba(0,0,0,.08); overflow:hidden; }
  .calendar-wrap .table{ margin-bottom:0; }
  .calendar-wrap .table-responsive{ border-radius:16px; overflow:hidden; }
  .calendar-header{ padding:16px; border-bottom:1px solid rgba(0,0,0,.06); }
  .calendar-table th{ background:#f8f9fa; font-size:.85rem; text-transform:uppercase; letter-spacing:.03em; }
  .calendar-table th, .calendar-table td{ text-align:center; vertical-align:middle; padding:.55rem .35rem; }
  .calendar-table .room-col{ text-align:left; min-width:180px; font-weight:600; background:#fff; position:sticky; left:0; z-index:2; box-shadow:1px 0 0 rgba(0,0,0,.05); }
  .calendar-table .room-sub{ font-weight:400; color:#6c757d; font-size:.82rem; display:flex; flex-direction:column; gap:2px; }
  .calendar-table .room-note{ font-size:.78rem; color:#495057; }

  .cell{ min-width:80px; border-radius:8px; padding:.35rem .3rem; display:flex; flex-direction:column; gap:4px; align-items:center; justify-content:center; font-size:.78rem; cursor:pointer; }
  .cell-libera{ }
  .cell-libera .btn{ border-radius:999px; padding:.2rem .7rem; font-size:.72rem; font-weight:600; box-shadow:0 .2rem .5rem rgba(13,110,253,.25); }
  .cell-occupata{ background:rgba(13,110,253,.2); color:#084298; font-weight:600; }
  .cell-manutenzione{ background:rgba(220,53,69,.22); color:#842029; font-weight:600; }
  .cell-pulizia{ background:rgba(255,193,7,.35); color:#664d03; font-weight:600; }
  .cell-disattiva{ background:rgba(108,117,125,.2); color:#6c757d; font-weight:600; }
  .cell-checkin:not(.cell-occupata):not(.cell-turnover){ background:linear-gradient(90deg, transparent 0 45%, rgba(25,135,84,.35) 45% 100%); }
  .cell-checkout:not(.cell-occupata):not(.cell-turnover){ background:linear-gradient(90deg, rgba(220,53,69,.25) 0 55%, transparent 55% 100%); }
  .cell-turnover{ background:rgba(111,66,193,.25); color:#3d2a6b; font-weight:600; }
  .cell .cell-label{ font-size:.85rem; font-weight:700; }
  .cell .cell-meta{ display:flex; gap:4px; flex-wrap:wrap; justify-content:center; }
  .cell .badge{ font-size:.62rem; }
  .cell.disabled{ cursor:not-allowed; opacity:.7; }

  .calendar-empty{ padding:24px; text-align:center; color:#6c757d; }
  .calendar-toolbar{ display:flex; flex-wrap:wrap; gap:8px; align-items:center; justify-content:space-between; }
  .calendar-toolbar .btn-group{ box-shadow:0 .2rem .6rem rgba(0,0,0,.08); border-radius:10px; }
  .calendar-toolbar .form-control{ max-width:170px; }

  .booking-modal .form-label span.required{ color:#dc3545; }
  .guest-card{ border:1px solid rgba(0,0,0,.08); border-radius:12px; padding:12px; margin-bottom:12px; }
  .guest-card .guest-title{ font-weight:600; }
  .guest-card .guest-actions{ display:flex; justify-content:flex-end; }
  .guest-search-results{ max-height:200px; overflow:auto; border:1px solid rgba(0,0,0,.08); border-radius:8px; }
</style>

<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h3 class="mb-1"><i class="bi bi-calendar-check"></i> Calendario prenotazioni</h3>
      <div class="text-muted small">Seleziona edificio e piano per vedere la disponibilità camere nel calendario.</div>
    </div>
  </div>

  <div class="card filters-card mb-3">
    <div class="card-body">
      <div class="row g-2 align-items-end">
        <div class="col-12 col-md-4">
          <label class="form-label mb-1">Edificio</label>
          <select id="selEdificio" class="form-select">
            <?php foreach ($edifici as $e): ?>
              <option value="<?= (int)$e['id'] ?>" <?= ((int)$e['id'] === $edificioSel ? 'selected' : '') ?>>
                <?= h($e['nome'] ?? 'Edificio '.$e['id']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label mb-1">Piano</label>
          <select id="selPiano" class="form-select"></select>
        </div>
        <div class="col-12 col-md-4">
          <div class="legend">
            <div class="item"><span class="dot" style="background:#dc3545"></span> Manutenzione</div>
            <div class="item"><span class="dot" style="background:#ffc107"></span> Pulizia</div>
            <div class="item"><span class="dot" style="background:#6c757d"></span> Disattiva</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="calendar-wrap">
    <div class="calendar-header">
      <div class="calendar-toolbar">
        <div class="btn-group" role="group" aria-label="Navigazione calendario">
          <button class="btn btn-outline-secondary" type="button" id="btnPrev"><i class="bi bi-chevron-left"></i></button>
          <button class="btn btn-outline-secondary" type="button" id="btnToday">Oggi</button>
          <button class="btn btn-outline-secondary" type="button" id="btnNext"><i class="bi bi-chevron-right"></i></button>
        </div>
        <div class="d-flex align-items-center gap-2">
          <label class="small text-muted" for="startDate">Da</label>
          <input type="date" id="startDate" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
          <select id="selDays" class="form-select form-select-sm">
            <option value="7">7 giorni</option>
            <option value="14" selected>14 giorni</option>
            <option value="21">21 giorni</option>
          </select>
        </div>
      </div>
    </div>
    <div id="calendarContainer" class="table-responsive"></div>
  </div>
</div>

<div class="modal fade booking-modal" id="bookingModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Dettagli prenotazione</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="bookingForm" class="row g-3">
          <input type="hidden" name="id" id="bookingId">
          <div class="col-12 col-md-4">
            <label class="form-label small">Camera</label>
            <input type="hidden" name="camera_id" id="bookingCamera" required>
            <input type="text" class="form-control" id="bookingCameraLabel" readonly>
          </div>
          <div class="col-6 col-md-4">
            <label class="form-label small">Check-in</label>
            <input type="date" class="form-control" name="data_checkin" id="bookingCheckin" required>
          </div>
          <div class="col-6 col-md-4">
            <label class="form-label small">Check-out</label>
            <input type="date" class="form-control" name="data_checkout" id="bookingCheckout" required>
          </div>
        </form>

        <div class="border-top pt-3 mt-3">
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <div>
              <h6 class="mb-1">Ospiti</h6>
              <div class="text-muted small">Compila i dati richiesti per ogni ospite della camera.</div>
            </div>
            <div class="d-flex gap-2">
              <button class="btn btn-outline-secondary btn-sm" type="button" id="addGuestBtn">
                <i class="bi bi-person-plus"></i> Aggiungi ospite
              </button>
            </div>
          </div>

          <div class="row g-2 align-items-end mb-3">
            <div class="col-12 col-md-6">
              <label class="form-label small">Ricerca ospite già registrato</label>
              <input class="form-control form-control-sm" id="guestSearchInput" placeholder="Nome, cognome, documento o email">
            </div>
            <div class="col-12 col-md-3">
              <button class="btn btn-outline-primary btn-sm w-100" type="button" id="guestSearchBtn">
                <i class="bi bi-search"></i> Cerca
              </button>
            </div>
          </div>

          <div id="guestSearchResults" class="guest-search-results mb-3 d-none"></div>
          <div id="guestsContainer"></div>
          <div id="guestsEmpty" class="text-muted small d-none">Salva la prenotazione per associare ospiti.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Chiudi</button>
        <button class="btn btn-primary" id="saveBookingBtn"><i class="bi bi-save"></i> Salva prenotazione</button>
      </div>
    </div>
  </div>
</div>

<div class="toast-container position-fixed top-0 end-0 p-3" id="toastArea" style="z-index: 1080;"></div>

<script>
  (function(){
    const edifici = <?= json_encode($edifici, JSON_UNESCAPED_UNICODE) ?>;
    const piani = <?= json_encode($piani, JSON_UNESCAPED_UNICODE) ?>;

    const selEdificio = document.getElementById('selEdificio');
    const selPiano = document.getElementById('selPiano');
    const calendarContainer = document.getElementById('calendarContainer');
    const startDateEl = document.getElementById('startDate');
    const selDays = document.getElementById('selDays');
    const btnPrev = document.getElementById('btnPrev');
    const btnNext = document.getElementById('btnNext');
    const btnToday = document.getElementById('btnToday');
    const bookingModalEl = document.getElementById('bookingModal');
    const bookingForm = document.getElementById('bookingForm');
    const bookingIdInput = document.getElementById('bookingId');
    const bookingCamera = document.getElementById('bookingCamera');
    const bookingCameraLabel = document.getElementById('bookingCameraLabel');
    const bookingCheckin = document.getElementById('bookingCheckin');
    const bookingCheckout = document.getElementById('bookingCheckout');
    const saveBookingBtn = document.getElementById('saveBookingBtn');
    const guestsContainer = document.getElementById('guestsContainer');
    const guestsEmpty = document.getElementById('guestsEmpty');
    const addGuestBtn = document.getElementById('addGuestBtn');
    const guestSearchInput = document.getElementById('guestSearchInput');
    const guestSearchBtn = document.getElementById('guestSearchBtn');
    const guestSearchResults = document.getElementById('guestSearchResults');
    const toastArea = document.getElementById('toastArea');

    let edificioSel = <?= (int)$edificioSel ?>;
    let pianoSel = <?= (int)$pianoSel ?>;
    let meta = { camere: [] };
    let currentBookingId = null;

    // ✅ formato locale YYYY-MM-DD (NO UTC, NO toISOString per date "giorno")
    function formatLocalYMD(d) {
      const y = d.getFullYear();
      const m = String(d.getMonth() + 1).padStart(2, '0');
      const day = String(d.getDate()).padStart(2, '0');
      return `${y}-${m}-${day}`;
    }

    function addDaysYMD(ymd, days) {
      const d = new Date(ymd + 'T12:00:00'); // ✅ mezzogiorno: evita edge timezone/DST
      d.setDate(d.getDate() + days);
      return formatLocalYMD(d);
    }

    function renderPianiOptions() {
      const options = piani.filter(p => String(p.edificio_id) === String(edificioSel));
      selPiano.innerHTML = '';
      if (options.length === 0) {
        selPiano.innerHTML = '<option value="">Nessun piano attivo</option>';
        pianoSel = 0;
        calendarContainer.innerHTML = '<div class="calendar-empty">Nessun piano disponibile per questo edificio.</div>';
        return;
      }

      options.forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.id;
        opt.textContent = p.nome || `Piano ${p.id}`;
        if (String(p.id) === String(pianoSel)) opt.selected = true;
        selPiano.appendChild(opt);
      });

      if (!pianoSel || !options.some(p => String(p.id) === String(pianoSel))) {
        pianoSel = options[0].id;
      }
    }

    function shiftDate(days) {
      sstartDateEl.value = formatLocalYMD(new Date());
    }

    function mapSet(list) {
      const out = new Map();
      (list || []).forEach(item => {
        const id = String(item.camera_id);
        if (!out.has(id)) out.set(id, []);
        out.get(id).push(item);
      });
      return out;
    }

    function formatStatus(raw) {
      if (!raw) return '';
      return raw
        .toString()
        .replace(/_/g, ' ')
        .toLowerCase()
        .replace(/\b\w/g, (match) => match.toUpperCase());
    }

    function escapeHtml(value) {
      const div = document.createElement('div');
      div.textContent = value ?? '';
      return div.innerHTML;
    }

    function statusInitial(label) {
      if (!label) return '';
      return label.trim().charAt(0).toUpperCase();
    }

    function dateLabel(dateStr) {
      const d = new Date(dateStr + 'T00:00:00');
      const formatter = new Intl.DateTimeFormat('it-IT', {
        weekday: 'short',
        day: '2-digit',
        month: 'short'
      });
      const parts = formatter.formatToParts(d).reduce((acc, part) => {
        acc[part.type] = part.value;
        return acc;
      }, {});
      const weekday = parts.weekday || '';
      const day = parts.day || '';
      const month = parts.month || '';
      return `${weekday}<br><span class="text-muted">${day} ${month}</span>`;
    }

    function showToast(message, variant = 'primary') {
      const wrapper = document.createElement('div');
      wrapper.innerHTML = `
        <div class="toast align-items-center text-bg-${variant} border-0" role="alert" aria-live="assertive" aria-atomic="true">
          <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
          </div>
        </div>
      `;
      const toastEl = wrapper.firstElementChild;
      toastArea.appendChild(toastEl);
      const toast = new bootstrap.Toast(toastEl, { delay: 4000 });
      toast.show();
      toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
    }

    async function fetchJson(url, options = {}) {
      const res = await fetch(url, Object.assign({
        headers: { 'Content-Type': 'application/json' }
      }, options));
      return res.json();
    }

    async function loadMeta() {
      const data = await fetchJson('prenotazioni_ajax.php?action=metadata');
      if (data.ok) {
        meta = data;
        if (bookingCamera.value) {
          bookingCameraLabel.value = getCameraLabel(bookingCamera.value);
        }
      }
    }

    function getCameraLabel(cameraId) {
      if (!cameraId) return '';
      const camera = (meta.camere || []).find(c => String(c.id) === String(cameraId));
      if (!camera) return `Camera ${cameraId}`;
      return `${camera.codice || ''}${camera.nome ? ' — ' + camera.nome : ''}`.trim();
    }

    function setCameraSelection(cameraId) {
      bookingCamera.value = cameraId || '';
      bookingCameraLabel.value = getCameraLabel(cameraId);
    }

    function applyCheckoutMinFromCheckin(checkinStr) {
      if (!checkinStr) return;
      const d = new Date(checkinStr + 'T00:00:00');
      d.setDate(d.getDate() + 1);
      const minCo = formatLocalYMD(d);
      bookingCheckout.min = minCo;
      if (bookingCheckout.value && bookingCheckout.value < minCo) {
        bookingCheckout.value = '';
      }
    }

    function openBookingModal({ bookingId = null, cameraId, checkin, checkout } = {}) {
      currentBookingId = bookingId;
      bookingIdInput.value = bookingId || '';

      if (cameraId) setCameraSelection(cameraId);
      else setCameraSelection('');

      if (checkin) bookingCheckin.value = checkin;

      if (currentBookingId) {
        if (checkout) bookingCheckout.value = checkout;
        applyCheckoutMinFromCheckin(bookingCheckin.value);
        loadGuests(currentBookingId);
      } else {
        bookingCheckout.value = '';
        guestsContainer.innerHTML = '';
        guestsEmpty.classList.remove('d-none');
        applyCheckoutMinFromCheckin(bookingCheckin.value);
        setTimeout(() => bookingCheckout.focus(), 150);
      }

      guestSearchResults.classList.add('d-none');
      const modal = new bootstrap.Modal(bookingModalEl);
      modal.show();
    }

    function renderGuestCard(guest, isNew = false) {
      const idAttr = guest.id ? `data-cliente="${guest.id}"` : '';
      const card = document.createElement('div');
      card.className = 'guest-card';
      card.dataset.new = isNew ? '1' : '0';
      card.innerHTML = `
        <div class="guest-title mb-2">${escapeHtml(guest.nome || '')} ${escapeHtml(guest.cognome || '')}</div>
        <div class="row g-2">
          <div class="col-6 col-md-3">
            <label class="form-label small">Nome <span class="required">*</span></label>
            <input class="form-control form-control-sm" name="nome" value="${escapeHtml(guest.nome ?? '')}" required>
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label small">Cognome <span class="required">*</span></label>
            <input class="form-control form-control-sm" name="cognome" value="${escapeHtml(guest.cognome ?? '')}" required>
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label small">Data nascita <span class="required">*</span></label>
            <input type="date" class="form-control form-control-sm" name="data_nascita" value="${escapeHtml(guest.data_nascita ?? '')}" required>
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label small">Nazionalità <span class="required">*</span></label>
            <input class="form-control form-control-sm" name="nazionalita" value="${escapeHtml(guest.nazionalita ?? '')}" required>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label small">Indirizzo di residenza <span class="required">*</span></label>
            <input class="form-control form-control-sm" name="indirizzo" value="${escapeHtml(guest.indirizzo ?? '')}" required>
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label small">Tipo documento <span class="required">*</span></label>
            <input class="form-control form-control-sm" name="documento_tipo" value="${escapeHtml(guest.documento_tipo ?? '')}" required>
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label small">N° Documento <span class="required">*</span></label>
            <input class="form-control form-control-sm" name="documento_numero" value="${escapeHtml(guest.documento_numero ?? '')}" required>
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label small">Email</label>
            <input class="form-control form-control-sm" name="email" value="${escapeHtml(guest.email ?? '')}">
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label small">Telefono</label>
            <input class="form-control form-control-sm" name="telefono" value="${escapeHtml(guest.telefono ?? '')}">
          </div>
          <div class="col-12">
            <label class="form-label small">Note</label>
            <input class="form-control form-control-sm" name="note" value="${escapeHtml(guest.note ?? '')}">
          </div>
        </div>
        <div class="guest-actions mt-2">
          <button class="btn btn-outline-primary btn-sm js-save-guest" ${idAttr}>
            <i class="bi bi-save"></i> ${isNew ? 'Crea ospite' : 'Salva ospite'}
          </button>
        </div>
      `;
      guestsContainer.appendChild(card);
    }

    async function loadGuests(bookingId) {
      guestsEmpty.classList.add('d-none');
      guestsContainer.innerHTML = '<div class="text-center py-4 text-muted">Caricamento...</div>';
      const res = await fetchJson('ospiti_ajax.php', {
        method: 'POST',
        body: JSON.stringify({ action: 'list', soggiorno_id: bookingId })
      });
      if (!res.ok) {
        guestsContainer.innerHTML = `<div class="alert alert-danger">${res.message || 'Errore'}</div>`;
        return;
      }

      guestsContainer.innerHTML = '';
      if (!res.ospiti || res.ospiti.length === 0) {
        guestsContainer.innerHTML = '<div class="alert alert-info">Nessun ospite associato.</div>';
        return;
      }

      res.ospiti.forEach(o => renderGuestCard(o));
    }

    async function saveBooking() {
      const data = new FormData(bookingForm);
      const payload = Object.fromEntries(data.entries());
      payload.action = 'save_booking';
      if (currentBookingId) {
        payload.id = currentBookingId;
      }
      const res = await fetchJson('prenotazioni_ajax.php', {
        method: 'POST',
        body: JSON.stringify(payload)
      });
      showToast(res.message || 'Salvataggio completato', res.toast?.variant || (res.ok ? 'success' : 'danger'));
      if (res.ok) {
        currentBookingId = res.id || currentBookingId;
        bookingIdInput.value = currentBookingId || '';
        loadCalendar();
        if (currentBookingId) {
          loadGuests(currentBookingId);
        }
      }
    }

    async function saveGuest(card) {
      if (!currentBookingId) {
        showToast('Salva prima la prenotazione', 'warning');
        return;
      }
      const inputs = card.querySelectorAll('input[name]');
      const payload = { action: 'save_guest', soggiorno_id: currentBookingId };
      const clienteId = card.querySelector('.js-save-guest')?.getAttribute('data-cliente');
      if (clienteId) payload.cliente_id = parseInt(clienteId, 10);
      inputs.forEach(i => payload[i.name] = i.value);
      const res = await fetchJson('ospiti_ajax.php', {
        method: 'POST',
        body: JSON.stringify(payload)
      });
      showToast(res.message || 'Ospite aggiornato', res.toast?.variant || (res.ok ? 'success' : 'danger'));
      if (res.ok) {
        loadGuests(currentBookingId);
      }
    }

    async function searchGuests() {
      const query = guestSearchInput.value.trim();
      if (!query) {
        guestSearchResults.classList.add('d-none');
        guestSearchResults.innerHTML = '';
        return;
      }
      const res = await fetchJson('ospiti_ajax.php', {
        method: 'POST',
        body: JSON.stringify({ action: 'search', query })
      });
      if (!res.ok) {
        showToast(res.message || 'Errore ricerca', 'danger');
        return;
      }
      if (!res.results || res.results.length === 0) {
        guestSearchResults.innerHTML = '<div class="p-2 text-muted small">Nessun ospite trovato.</div>';
        guestSearchResults.classList.remove('d-none');
        return;
      }
      guestSearchResults.innerHTML = res.results.map(r => `
        <div class="d-flex justify-content-between align-items-center px-2 py-2 border-bottom" data-cliente="${r.id}">
          <div>
            <div class="fw-semibold">${escapeHtml(r.nome ?? '')} ${escapeHtml(r.cognome ?? '')}</div>
            <div class="small text-muted">${escapeHtml(r.data_nascita ?? '')} ${r.documento_numero ? '· ' + escapeHtml(r.documento_numero) : ''}</div>
          </div>
          <button class="btn btn-outline-primary btn-sm js-attach-guest">Aggiungi</button>
        </div>
      `).join('');
      guestSearchResults.classList.remove('d-none');
    }

    async function attachGuest(clienteId) {
      if (!currentBookingId) {
        showToast('Salva prima la prenotazione', 'warning');
        return;
      }
      const res = await fetchJson('ospiti_ajax.php', {
        method: 'POST',
        body: JSON.stringify({ action: 'attach_guest', soggiorno_id: currentBookingId, cliente_id: clienteId })
      });
      showToast(res.message || 'Ospite associato', res.toast?.variant || (res.ok ? 'success' : 'danger'));
      if (res.ok) {
        loadGuests(currentBookingId);
      }
    }

    function renderCalendar(data) {
      const rooms = data.rooms || [];
      if (!rooms.length) {
        calendarContainer.innerHTML = '<div class="calendar-empty">Nessuna camera trovata per i filtri selezionati.</div>';
        return;
      }

      const days = data.days || [];
      const manutenzioni = mapSet(data.manutenzioni || []);
      const pulizie = mapSet(data.pulizie || []);
      const bookings = mapSet(data.bookings || []);
      window.currentCalendarBookings = data.bookings || [];

      let html = '<table class="table table-bordered calendar-table">';
      html += '<thead><tr><th class="room-col">Camera</th>';
      days.forEach(day => {
        html += `<th>${dateLabel(day)}</th>`;
      });
      html += '</tr></thead><tbody>';

      rooms.forEach(room => {
        const roomId = String(room.id);
        html += '<tr>';
        const disabili = parseInt(room.accessibile_disabili || 0, 10) > 0;
        const accessibileHtml = disabili ? '<i class="bi bi-person-wheelchair"></i> Accessibile' : '';
        const noteHtml = room.note ? `<div class="room-note">${escapeHtml(room.note)}</div>` : '';
        html += `<td class="room-col">${escapeHtml(room.codice || '')}<div class="room-sub">${noteHtml}${accessibileHtml ? `<div>${accessibileHtml}</div>` : ''}</div></td>`;

        days.forEach(day => {
          let status = 'libera';
          let label = statusInitial('Libera');
          const tooltipParts = [];
          let bookingId = '';
          let bookingPayload = null;
          let checkinMarkers = [];
          let checkoutMarkers = [];

          const occs = bookings.get(roomId) || [];
          const manutList = manutenzioni.get(roomId) || [];
          const puliziaList = pulizie.get(roomId) || [];

          const checkinEvents = occs.filter(b => b.checkin === day);
          const checkoutEvents = occs.filter(b => b.checkout === day);
          const hasCheckin = checkinEvents.length > 0;
          const hasCheckout = checkoutEvents.length > 0;
          const hasTurnover = hasCheckin && hasCheckout;

          checkinMarkers = checkinEvents.map(() => 'CI');
          checkoutMarkers = checkoutEvents.map(() => 'CO');
          bookingPayload = checkinEvents[0] || checkoutEvents[0] || null;

          const dayDate = new Date(day + 'T00:00:00');
          const match = occs.find(b => {
            const start = new Date(b.checkin + 'T00:00:00');
            const end = new Date(b.checkout + 'T00:00:00');
            return dayDate >= start && dayDate < end;
          });

          const attivaVal = parseInt((room.attiva ?? 1), 10);
          const isDisattiva = (Number.isNaN(attivaVal) ? true : attivaVal !== 1);

          if (isDisattiva) tooltipParts.push('Camera disattivata');
          if (manutList.length) {
            const info = manutList[0];
            tooltipParts.push(info?.stato ? `${formatStatus(info.stato)}` : '');
          }
          if (puliziaList.length) {
            const info = puliziaList[0];
            tooltipParts.push(info?.stato ? `${formatStatus(info.stato)}` : '');
          }

          if (match) {
            status = 'occupata';
            label = match.stato ? statusInitial(match.stato) : statusInitial('Occupata');
            tooltipParts.push(`Soggiorno ${match.checkin} → ${match.checkout}`);
            bookingId = match.id;
            bookingPayload = match;
          } else if (bookingPayload) {
            tooltipParts.push(`Check-in ${bookingPayload.checkin} · Check-out ${bookingPayload.checkout}`);
            bookingId = bookingPayload.id;
          }

          if (!match) {
            if (isDisattiva) {
              status = 'disattiva';
              label = statusInitial('Disattiva');
            } else if (manutList.length) {
              status = 'manutenzione';
              label = statusInitial('Manutenzione');
            } else if (puliziaList.length) {
              status = 'pulizia';
              label = statusInitial('Pulizia');
            }
          }

          const tooltip = tooltipParts.join(' · ');
          const tooltipAttr = tooltip ? ` data-bs-toggle="tooltip" title="${tooltip.replace(/"/g, '&quot;')}"` : '';

          // ✅ check-in = il giorno della cella (stringa già corretta)
          const defaultCheckin = day;

          // ✅ checkout suggerito = day + 1, calcolato in locale (no UTC)
          const defaultCheckout = addDaysYMD(day, 1);


          // ✅ se cella libera: checkout vuoto (lo sceglie l'operatore)
          const cellCheckoutValue = (status === 'libera') ? '' : defaultCheckout;

          let metaTags = '';
          const statusBadges = [];
          if (isDisattiva) statusBadges.push('<span class="badge bg-secondary">D</span>');
          if (manutList.length) statusBadges.push('<span class="badge bg-danger">M</span>');
          if (puliziaList.length) statusBadges.push('<span class="badge bg-warning text-dark">P</span>');
          // ❌ niente badge "O"

          const tags = [
            ...statusBadges,
            ...checkinMarkers.map(tag => `<span class="badge bg-success">${tag}</span>`),
            ...checkoutMarkers.map(tag => `<span class="badge bg-danger">${tag}</span>`)
          ].join('');

          if (tags) metaTags = `<div class="cell-meta">${tags}</div>`;

          const disabledClass = isDisattiva ? ' disabled' : '';
          const bookingIdAttr = bookingId ? ` data-booking-id="${bookingId}"` : '';
          const checkinClass = hasCheckin ? ' cell-checkin' : '';
          const checkoutClass = hasCheckout ? ' cell-checkout' : '';
          const turnoverClass = hasTurnover ? ' cell-turnover' : '';

          const labelHtml = status === 'libera'
            ? '<button class="btn btn-outline-primary btn-sm">Prenota</button>'
            : label;

          html += `<td>
            <div class="cell cell-${status}${checkinClass}${checkoutClass}${turnoverClass}${disabledClass}"
                 data-camera-id="${room.id}"
                 data-checkin="${defaultCheckin}"
                 data-checkout="${cellCheckoutValue}"
                 data-checkout-suggest="${defaultCheckout}"
                 ${bookingIdAttr}${tooltipAttr}>
              <div class="cell-label">${labelHtml}</div>
              ${metaTags}
            </div>
          </td>`;
        });

        html += '</tr>';
      });

      html += '</tbody></table>';
      calendarContainer.innerHTML = html;

      const tooltipTriggerList = [].slice.call(calendarContainer.querySelectorAll('[data-bs-toggle="tooltip"]'));
      tooltipTriggerList.forEach(function (tooltipTriggerEl) {
        new bootstrap.Tooltip(tooltipTriggerEl);
      });
    }

    async function loadCalendar() {
      if (!edificioSel || !pianoSel) {
        calendarContainer.innerHTML = '<div class="calendar-empty">Seleziona edificio e piano per visualizzare il calendario.</div>';
        return;
      }

      const params = new URLSearchParams({
        edificio_id: edificioSel,
        piano_id: pianoSel,
        start: startDateEl.value,
        days: selDays.value,
      });

      const res = await fetch('calendario_ajax.php?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });

      const data = await res.json();
      if (!data.ok) {
        calendarContainer.innerHTML = `<div class="calendar-empty">${data.message || 'Errore nel caricamento del calendario.'}</div>`;
        window.currentCalendarBookings = [];
        return;
      }
      renderCalendar(data);
    }

    selEdificio?.addEventListener('change', () => {
      edificioSel = selEdificio.value || 0;
      renderPianiOptions();
      loadCalendar();
    });

    selPiano?.addEventListener('change', () => {
      pianoSel = selPiano.value || 0;
      loadCalendar();
    });

    startDateEl?.addEventListener('change', loadCalendar);
    selDays?.addEventListener('change', loadCalendar);

    btnPrev?.addEventListener('click', () => {
      shiftDate(-parseInt(selDays.value, 10));
      loadCalendar();
    });

    btnNext?.addEventListener('click', () => {
      shiftDate(parseInt(selDays.value, 10));
      loadCalendar();
    });

    btnToday?.addEventListener('click', () => {
      const now = new Date();
      startDateEl.value = formatLocalYMD(now);
      loadCalendar();
    });

    calendarContainer.addEventListener('click', (ev) => {
      const cell = ev.target.closest('.cell');
      if (!cell || cell.classList.contains('disabled')) return;

      const bookingId = cell.dataset.bookingId ? parseInt(cell.dataset.bookingId, 10) : null;
      const cameraId = parseInt(cell.dataset.cameraId, 10);
      const checkin = cell.dataset.checkin;
      const checkout = cell.dataset.checkout;

      if (bookingId) {
        const booking = (window.currentCalendarBookings || []).find(b => parseInt(b.id, 10) === bookingId);
        openBookingModal({
          bookingId,
          cameraId: booking?.camera_id || cameraId,
          checkin: booking?.checkin || checkin,
          checkout: booking?.checkout || checkout
        });
      } else {
        openBookingModal({ bookingId: null, cameraId, checkin, checkout });
      }
    });

    saveBookingBtn?.addEventListener('click', saveBooking);

    // ✅ aggiorna min checkout quando cambia check-in manualmente nel modal
    bookingCheckin?.addEventListener('change', () => {
      applyCheckoutMinFromCheckin(bookingCheckin.value);
    });

    guestsContainer.addEventListener('click', (ev) => {
      const btn = ev.target.closest('.js-save-guest');
      if (!btn) return;
      const card = btn.closest('.guest-card');
      saveGuest(card);
    });

    addGuestBtn?.addEventListener('click', () => {
      if (!currentBookingId) {
        showToast('Salva prima la prenotazione', 'warning');
        return;
      }
      renderGuestCard({}, true);
    });

    guestSearchBtn?.addEventListener('click', searchGuests);
    guestSearchInput?.addEventListener('keydown', (ev) => {
      if (ev.key === 'Enter') {
        ev.preventDefault();
        searchGuests();
      }
    });

    guestSearchResults.addEventListener('click', (ev) => {
      const btn = ev.target.closest('.js-attach-guest');
      if (!btn) return;
      const row = btn.closest('[data-cliente]');
      const clienteId = parseInt(row.dataset.cliente, 10);
      attachGuest(clienteId);
    });

    bookingModalEl.addEventListener('hidden.bs.modal', () => {
      bookingForm.reset();
      guestsContainer.innerHTML = '';
      guestsEmpty.classList.add('d-none');
      currentBookingId = null;
      guestSearchInput.value = '';
      guestSearchResults.classList.add('d-none');
      guestSearchResults.innerHTML = '';
      bookingCameraLabel.value = '';
      bookingCheckout.min = '';
    });

    renderPianiOptions();
    loadMeta();
    loadCalendar();
  })();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
