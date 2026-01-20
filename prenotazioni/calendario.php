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

  /* ✅ CONTINUITÀ: niente padding nelle celle */
  .calendar-table th, .calendar-table td{
    text-align:center;
    vertical-align:middle;
    padding: 0 !important;
  }

  .calendar-table .room-col{
    text-align:left;
    min-width:180px;
    font-weight:600;
    background:#fff;
    position:sticky;
    left:0;
    z-index:2;
    box-shadow:1px 0 0 rgba(0,0,0,.05);
    padding:.55rem .35rem !important; /* ripristina padding solo colonna camere */
  }
  .calendar-table .room-sub{ font-weight:400; color:#6c757d; font-size:.82rem; display:flex; flex-direction:column; gap:2px; }
  .calendar-table .room-note{ font-size:.78rem; color:#495057; }

  /* =========================
   * CELLA (contenitore)
   * ======================= */
  .cell{
    position: relative;
    height: 54px;
    background: transparent !important;
  }

  .cell.disabled{ cursor:not-allowed; opacity:.7; }

  /* wrapper interno */
  .cell .barwrap{
    position: absolute;
    inset: 0;           /* top/right/bottom/left = 0 */
  }

  /* =========================
   * SEGMENTI SLOPE-LIKE
   * ======================= */
  .stayseg{
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    height: 34px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-weight: 800;
    font-size: .85rem;
    letter-spacing: .02em;
    user-select:none;
    overflow:hidden;
    box-shadow: 0 .25rem .6rem rgba(0,0,0,.10);
    white-space: nowrap;
  }

  /* colori */
  .stayseg.occ { background: rgba(13,110,253,.18); color:#084298; }
  .stayseg.mnt { background: rgba(220,53,69,.18);  color:#842029; }
  .stayseg.pul { background: rgba(255,193,7,.26);  color:#664d03; }
  .stayseg.dis { background: rgba(108,117,125,.18);color:#6c757d; }

  /* 100% cella (giorni in mezzo) */
  .stayseg.full{
    left: 0;
    width: 100%;
  }

  /* ✅ CHECK-IN: dal 66% a fine cella (34%), punta a DESTRA */
  .stayseg.ci{
    left: 66%;
    width: 34%;
    clip-path: polygon(
      0 0,     /* alto: corpo rientrato */
      100% 0,     /* alto prima della punta */
      100% 100%,  /* basso dopo la punta */
      0 100%,  /* basso: corpo rientrato */
      25% 50%      /* CODA: incavo centrale */
    );
  }

  /* ✅ CHECK-OUT: da inizio cella al 33%, punta a SINISTRA */
  .stayseg.co{
    left: 0;
    width: 33%;
    clip-path: polygon(
      0 0,
      66% 0,
      100% 50%,
      66% 100%,
      0 100%
    );
  }

  /* turnover: 2 pezzi nello stesso giorno */
  .stayseg.co.turnover{ left:0; width:33%; }
  .stayseg.ci.turnover{ left:66%; width:34%; }

  /* =========================
   * PRENOTA al centro
   * ======================= */
  .cell-libera .btn{
    position:absolute;
    top:50%; left:50%;
    transform:translate(-50%,-50%);
    border-radius:999px;
    padding:.25rem .9rem;
    font-size:.72rem;
    font-weight:800;
    box-shadow:0 .25rem .65rem rgba(13,110,253,.18);
  }

  /* meta badges */
  .cell .cell-meta{
    position:absolute;
    left:50%;
    bottom:2px;
    transform:translateX(-50%);
    display:flex;
    gap:4px;
    flex-wrap:wrap;
    justify-content:center;
    z-index: 3;
  }
  .cell .badge{ font-size:.62rem; }

  /* disattiva vecchi overlay */
  .cell::before, .cell::after{ content:none !important; }
  .cell.cell-checkout::before,
  .cell.cell-checkin::after{ content:none !important; }
  .cell.cell-turnover{ background:transparent !important; }

  .calendar-empty{ padding:24px; text-align:center; color:#6c757d; }
  .calendar-toolbar{ display:flex; flex-wrap:wrap; gap:8px; align-items:center; justify-content:space-between; }
  .calendar-toolbar .btn-group{ box-shadow:0 .2rem .6rem rgba(0,0,0,.08); border-radius:10px; }
  .calendar-toolbar .form-control{ max-width:170px; }

  .booking-modal .form-label span.required{ color:#dc3545; }

  /* layer overlay sopra la tabella per le etichette centrate */
  #calendarContainer{ position:relative; }

  .booking-label-layer{
    position:absolute;
    inset:0;
    pointer-events:none;
    z-index: 50;
  }

  .booking-label{
    position:absolute;
    height:34px;              /* uguale a .stayseg */
    display:flex;
    align-items:center;
    justify-content:center;
    font-weight:800;
    font-size:.85rem;
    letter-spacing:.02em;
    color:#084298;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
    padding:0 10px;
    filter: drop-shadow(0 .25rem .35rem rgba(0,0,0,.08));
  }


  .guest-card{ border:1px solid rgba(0,0,0,.08); border-radius:12px; padding:12px; margin-bottom:12px; }
  .guest-card .guest-title{ font-weight:600; }
  .guest-card .guest-actions{ display:flex; justify-content:space-between; align-items:center; gap:8px; }
  .guest-card .guest-remove{ color:#6c757d; }
  .guest-search-card{
    border:1px solid rgba(0,0,0,.08);
    border-radius:12px;
    background:#f8f9fa;
    padding:12px;
  }
  .guest-search-results{
    max-height:220px;
    overflow:auto;
    border:1px solid rgba(0,0,0,.08);
    border-radius:10px;
    background:#fff;
  }
  .guest-search-results .result-row:hover{ background:#f1f3f5; }
  .services-card{
    border:1px solid rgba(0,0,0,.08);
    border-radius:12px;
    padding:12px;
    background:#fff;
  }
  .services-grid{
    display:grid;
    gap:12px;
  }
  .service-row{
    border:1px solid rgba(0,0,0,.08);
    border-radius:12px;
    padding:12px;
    background:#f8f9fa;
  }
  .service-row .service-header{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
  }
  .service-row .service-name{ font-weight:600; }
  .service-row .service-children{
    display:flex;
    flex-wrap:wrap;
    gap:6px;
    margin-top:8px;
  }
  .service-row .service-children .badge{
    background:#fff;
    border:1px solid rgba(0,0,0,.08);
    color:#495057;
  }
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
          <div class="col-12 col-md-4">
            <label class="form-label small">Tipo soggiorno</label>
            <select class="form-select" name="piano_pasto_sigla" id="bookingPasto">
              <option value="">—</option>
              <option value="BB">BB</option>
              <option value="HB">HB</option>
              <option value="FB">FB</option>
            </select>
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label small">Tipologia camera</label>
            <select class="form-select" name="tipologia_camera" id="bookingTipologia">
              <option value="">—</option>
              <option value="Singola">Singola</option>
              <option value="Doppia">Doppia</option>
              <option value="Matrimoniale">Matrimoniale</option>
              <option value="Tripla">Tripla</option>
              <option value="Quadrupla">Quadrupla</option>
              <option value="Family">Family</option>
              <option value="Suite">Suite</option>
            </select>
          </div>

          <div class="col-12 col-md-4" id="hbBox" style="display:none;">
            <label class="form-label small">HB: tipo pasto</label>
            <select class="form-select" name="hb_servizio" id="bookingHb">
              <option value="">—</option>
              <option value="PRANZO">Pranzo</option>
              <option value="CENA">Cena</option>
            </select>
          </div>

          <div class="col-6 col-md-2" id="hbDaBox" style="display:none;">
            <label class="form-label small">HB: data pasto</label>
            <input type="date" class="form-control" name="hb_da" id="bookingHbDa">
          </div>

          <div class="col-6 col-md-2" id="hbABBox" style="display:none;">
            <label class="form-label small">HB: ripeti fino al</label>
            <input type="date" class="form-control" name="hb_a" id="bookingHbA">
          </div>

          <div class="col-12" id="bookingNotesBox" style="display:none;">
            <label class="form-label small">Note soggiorno (HB/FB)</label>
            <textarea class="form-control" name="note" id="bookingNote" rows="2" placeholder="Note per il servizio pasti o richieste specifiche"></textarea>
          </div>

          <div class="col-12">
            <label class="form-label small">Servizi</label>
            <div class="services-card">
              <div id="servicesContainer" class="services-grid"></div>
              <div id="servicesEmpty" class="text-muted small d-none">Nessun servizio attivo disponibile.</div>
            </div>
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

          <div class="guest-search-card mb-3">
            <div class="row g-2 align-items-end">
              <div class="col-12 col-md-8">
                <label class="form-label small">Ricerca ospite già registrato</label>
                <input class="form-control form-control-sm" id="guestSearchInput" placeholder="Nome, cognome, documento o email">
              </div>
              <div class="col-12 col-md-4">
                <button class="btn btn-outline-primary btn-sm w-100" type="button" id="guestSearchBtn">
                  <i class="bi bi-search"></i> Cerca
                </button>
              </div>
            </div>
            <div id="guestSearchResults" class="guest-search-results mt-2 d-none"></div>
          </div>
          <div id="guestsContainer"></div>
          <div id="guestsEmpty" class="text-muted small d-none">
            Inserisci almeno 1 ospite (nome e cognome) prima di salvare la prenotazione.
          </div>
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
    const bookingPasto = document.getElementById('bookingPasto');
    const bookingTipologia = document.getElementById('bookingTipologia');
    const hbBox = document.getElementById('hbBox');
    const hbDaBox = document.getElementById('hbDaBox');
    const hbABBox = document.getElementById('hbABBox');
    const bookingHb = document.getElementById('bookingHb');
    const bookingHbDa = document.getElementById('bookingHbDa');
    const bookingHbA = document.getElementById('bookingHbA');
    const bookingNotesBox = document.getElementById('bookingNotesBox');
    const bookingNote = document.getElementById('bookingNote');
    const servicesContainer = document.getElementById('servicesContainer');
    const servicesEmpty = document.getElementById('servicesEmpty');
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

    function shiftDate(deltaDays) {
      const d = new Date(startDateEl.value + 'T12:00:00');
      d.setDate(d.getDate() + deltaDays);
      startDateEl.value = formatLocalYMD(d);
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
        renderServices(meta.servizi || []);
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

    function applyHbDateBounds() {
      if (!bookingCheckin.value || !bookingCheckout.value) return;
      bookingHbDa.min = bookingCheckin.value;
      bookingHbA.min = bookingCheckin.value;
      bookingHbDa.max = bookingCheckout.value;
      bookingHbA.max = bookingCheckout.value;
    }

    function toggleHbFields() {
      const show = bookingPasto.value === 'HB';
      hbBox.style.display = show ? '' : 'none';
      hbDaBox.style.display = show ? '' : 'none';
      hbABBox.style.display = show ? '' : 'none';
      if (!show) {
        bookingHb.value = '';
        bookingHbDa.value = '';
        bookingHbA.value = '';
        bookingHbDa.required = false;
      } else {
        applyHbDateBounds();
        bookingHbDa.required = true;
      }
    }

    function toggleNotesField() {
      const show = bookingPasto.value === 'HB' || bookingPasto.value === 'FB';
      bookingNotesBox.style.display = show ? '' : 'none';
      if (!show) {
        bookingNote.value = '';
      }
    }

    function renderServices(list) {
      servicesContainer.innerHTML = '';
      if (!list || list.length === 0) {
        servicesEmpty.classList.remove('d-none');
        return;
      }
      servicesEmpty.classList.add('d-none');
      list.forEach(service => {
        const row = document.createElement('div');
        row.className = 'service-row';
        row.dataset.serviceId = service.id;
        const children = (service.children || []).map(child => `
          <span class="badge rounded-pill">${escapeHtml(child.nome || '')}</span>
        `).join('');
        row.innerHTML = `
          <div class="service-header">
            <div class="service-name">${escapeHtml(service.nome || '')}</div>
            <select class="form-select form-select-sm service-mode" style="max-width:160px;">
              <option value="">—</option>
              <option value="INCLUSO">Incluso</option>
              <option value="EXTRA">Extra</option>
            </select>
          </div>
          ${children ? `<div class="service-children">${children}</div>` : ''}
        `;
        servicesContainer.appendChild(row);
      });
    }

    function setServicesSelection(selected) {
      const map = new Map();
      (selected || []).forEach(item => map.set(String(item.id), item.mode));
      servicesContainer.querySelectorAll('.service-row').forEach(row => {
        const id = row.dataset.serviceId;
        const sel = row.querySelector('.service-mode');
        sel.value = map.get(String(id)) || '';
      });
    }

    function collectServicesFromUI() {
      const out = [];
      servicesContainer.querySelectorAll('.service-row').forEach(row => {
        const id = parseInt(row.dataset.serviceId, 10);
        const mode = row.querySelector('.service-mode')?.value || '';
        if (id && mode) {
          out.push({ id, mode });
        }
      });
      return out;
    }

    function openBookingModal({ bookingId = null, cameraId, checkin, checkout, booking = null } = {}) {
      currentBookingId = bookingId;
      bookingIdInput.value = bookingId || '';

      if (cameraId) setCameraSelection(cameraId);
      else setCameraSelection('');

      if (checkin) bookingCheckin.value = checkin;
      if (booking?.pasto) bookingPasto.value = booking.pasto;
      if (booking?.tipologia_camera) bookingTipologia.value = booking.tipologia_camera;
      if (booking?.hb_servizio) bookingHb.value = booking.hb_servizio;
      if (booking?.hb_da) bookingHbDa.value = booking.hb_da;
      if (booking?.hb_a) bookingHbA.value = booking.hb_a;
      if (booking?.note) bookingNote.value = booking.note;

      if (booking?.servizi) {
        setServicesSelection(booking.servizi);
      } else {
        setServicesSelection([]);
      }
      toggleHbFields();
      toggleNotesField();
      applyHbDateBounds();

      if (currentBookingId) {
        if (checkout) bookingCheckout.value = checkout;
        applyCheckoutMinFromCheckin(bookingCheckin.value);
        loadGuests(currentBookingId);
      } else {
        bookingCheckout.value = checkout || '';
        guestsContainer.innerHTML = '';
        guestsEmpty.classList.add('d-none');
        renderGuestCard({}, true);
        applyCheckoutMinFromCheckin(bookingCheckin.value);
        setTimeout(() => bookingCheckout.focus(), 150);
      }


      guestSearchResults.classList.add('d-none');
      const modal = new bootstrap.Modal(bookingModalEl);
      modal.show();
    }

    function renderGuestCard(guest, isNew = false) {
      const idAttr = guest.id ? `data-cliente="${guest.id}"` : '';
      const docValue = (guest.documento_tipo || '').toString().toUpperCase();
      const docOptions = ['CARTA D\'IDENTITÀ', 'PATENTE', 'PASSAPORTO'];
      const docList = docOptions.map(opt => {
        const selected = docValue === opt ? 'selected' : '';
        return `<option value="${opt}" ${selected}>${opt}</option>`;
      }).join('');
      const customOption = docValue && !docOptions.includes(docValue)
        ? `<option value="${escapeHtml(docValue)}" selected>${escapeHtml(docValue)}</option>`
        : '';
      const card = document.createElement('div');
      card.className = 'guest-card';
      card.dataset.new = isNew ? '1' : '0';
      if (guest.id) card.dataset.guestId = guest.id;
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
            <select class="form-select form-select-sm" name="documento_tipo" required>
              <option value="">—</option>
              ${customOption}
              ${docList}
            </select>
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
          <button class="btn btn-outline-secondary btn-sm guest-remove js-remove-guest" type="button">
            <i class="bi bi-x-lg"></i> Chiudi
          </button>
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

    function collectGuestsFromUI() {
      const cards = Array.from(guestsContainer.querySelectorAll('.guest-card'));
      const out = [];
      for (const card of cards) {
        const obj = {};
        card.querySelectorAll('input[name]').forEach(i => obj[i.name] = i.value);
        if (card.dataset.guestId) {
          obj.id = card.dataset.guestId;
        }

        // minimo richiesto
        const nome = (obj.nome || '').trim();
        const cognome = (obj.cognome || '').trim();
        if (!nome || !cognome) continue;

        out.push(obj);
      }
      return out;
    }

    async function saveBooking(ev) {
      ev?.preventDefault();

      const data = new FormData(bookingForm);
      const payload = Object.fromEntries(data.entries());
      payload.action = 'save_booking';

      if (currentBookingId) payload.id = currentBookingId;

      // ✅ ospiti sempre raccolti (servono soprattutto per nuova prenotazione)
      const guests = collectGuestsFromUI();
      if (!currentBookingId && guests.length < 1) {
        showToast('Devi inserire almeno 1 ospite (nome e cognome) prima di salvare', 'warning');
        guestsEmpty.classList.remove('d-none');
        return;
      }
      payload.ospiti = guests;
      payload.servizi = collectServicesFromUI();

      const res = await fetchJson('prenotazioni_ajax.php', {
        method: 'POST',
        body: JSON.stringify(payload)
      });

      showToast(res.message || 'Salvataggio completato', res.toast?.variant || (res.ok ? 'success' : 'danger'));

      if (res.ok) {
        currentBookingId = res.id || currentBookingId;
        bookingIdInput.value = currentBookingId || '';
        loadCalendar();
        if (currentBookingId) loadGuests(currentBookingId);
      }
    }


    async function saveGuest(card) {
      if (!currentBookingId) {
        // ✅ prima del salvataggio: gli ospiti restano "locali" e verranno inviati con saveBooking()
        showToast('Ospite aggiunto. Ora salva la prenotazione.', 'info');
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
        <div class="d-flex justify-content-between align-items-center px-2 py-2 border-bottom result-row" data-cliente="${r.id}">
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
      // se non ho ancora salvato, non posso associare lato DB: creo card locale
      if (!currentBookingId) {
        const row = guestSearchResults.querySelector(`[data-cliente="${clienteId}"]`);
        const nome = row?.querySelector('.fw-semibold')?.textContent?.trim() || '';
        const meta = row?.querySelector('.small')?.textContent?.trim() || '';

        const parts = nome.split(' ');
        const guest = {
          nome: parts.slice(1).join(' ') ? parts.slice(1).join(' ') : (parts[0] || ''),
          cognome: parts[0] || '',
          data_nascita: '',
          nazionalita: '',
          indirizzo: '',
          documento_tipo: '',
          documento_numero: (meta.includes('·') ? meta.split('·')[1].trim() : '')
        };

        guestsEmpty.classList.add('d-none');
        renderGuestCard(guest, true);
        showToast('Ospite copiato. Ora salva la prenotazione.', 'info');
        return;
      }

      const res = await fetchJson('ospiti_ajax.php', {
        method: 'POST',
        body: JSON.stringify({ action: 'attach_guest', soggiorno_id: currentBookingId, cliente_id: clienteId })
      });
      showToast(res.message || 'Ospite associato', res.toast?.variant || (res.ok ? 'success' : 'danger'));
      if (res.ok) loadGuests(currentBookingId);
    }


    function bookingLabel(b) {
      if (!b) return '';
      const ref = (b.referente || '').toString().trim();
      if (ref) return ref;

      const cognome = (b.cognome || b.cliente_cognome || '').toString().trim();
      const nome    = (b.nome || b.cliente_nome || '').toString().trim();
      const full = `${cognome} ${nome}`.trim();
      if (full) return full;

      const generic = (b.intestatario || b.cliente || b.nominativo || '').toString().trim();
      return generic || `Pren. #${b.intestatario || ''}`.trim();
    }

    function daysBetween(aYmd, bYmd) {
      const a = new Date(aYmd + 'T12:00:00');
      const b = new Date(bYmd + 'T12:00:00');
      return Math.round((b - a) / (1000 * 60 * 60 * 24));
    }

    // mostra testo solo nel “giorno centrale” del soggiorno, limitato alla finestra visibile
    function isFullDayOfStay(b, day) {
      // giorno occupato ma NON è il giorno di check-in (segmento parziale "ci")
      // e NON è un giorno di turnover che vuoi evitare
      if (!b) return false;
      if (day === b.checkin) return false;
      // l'ultimo giorno occupato (notte finale) di solito è full, quindi lo lasciamo ok
      return true;
    }

    function labelDayForBooking(b, windowStart, windowEndExclusive) {
      if (!b) return null;

      const start = b.checkin;
      const endEx = b.checkout;

      const visStart = (start > windowStart) ? start : windowStart;
      const visEndEx = (endEx < windowEndExclusive) ? endEx : windowEndExclusive;

      const len = daysBetween(visStart, visEndEx);
      if (len <= 0) return null;

      // lista dei giorni visibili occupati
      const days = [];
      for (let i = 0; i < len; i++) days.push(addDaysYMD(visStart, i));

      // preferisci un giorno "full" (non check-in)
      const candidates = days.filter(d => isFullDayOfStay(b, d));
      const pick = candidates.length ? candidates : days;

      // ✅ centro: per len pari scegliamo il "middle di destra"
      const idx = Math.floor(pick.length / 2);
      return pick[idx] || null;
    }

    function shouldShowLabelOnDay(b, day, windowStart, windowEndExclusive) {
      const midDay = labelDayForBooking(b, windowStart, windowEndExclusive);
      return midDay ? (day === midDay) : false;
    }


    function ensureLabelLayer(){
      let layer = calendarContainer.querySelector('.booking-label-layer');
      if (!layer) {
        layer = document.createElement('div');
        layer.className = 'booking-label-layer';
        calendarContainer.appendChild(layer);
      }
      return layer;
    }

    function placeBookingLabels(days){
      const layer = ensureLabelLayer();
      layer.innerHTML = '';

      const table = calendarContainer.querySelector('table.calendar-table');
      if (!table) return;

      const tableRect = table.getBoundingClientRect();
      const layerRect = calendarContainer.getBoundingClientRect();

      // per ogni booking, trova la prima e l'ultima cella visibile (per quella camera)
      const bookings = window.currentCalendarBookings || [];

      bookings.forEach(b => {
        const id = String(b.id);
        const label = bookingLabel(b);
        if (!label) return;

        // tutte le celle di quel booking (nella tabella corrente)
        const cells = Array.from(calendarContainer.querySelectorAll(`.cell[data-booking-id="${id}"]`));
        if (!cells.length) return;

        // prendi prima e ultima cella (in ordine DOM sono già left->right)
        const first = cells[0];
        const last  = cells[cells.length - 1];

        // se è un booking di 1 solo giorno visibile, ok comunque
        const r1 = first.getBoundingClientRect();
        const r2 = last.getBoundingClientRect();

        // calcola left/right RELATIVI al calendarContainer
        const scrollX = calendarContainer.scrollLeft || 0;

        const left  = (r1.left - layerRect.left) + scrollX;
        const right = (r2.right - layerRect.left) + scrollX;


        const width = Math.max(40, right - left);

        // top: allineato al centro verticale della barra
        const cellH = first.getBoundingClientRect().height;
        const barH  = 34;
        const top = (r1.top - layerRect.top) + (cellH/2) - (barH/2);
         // 54 cell height, 34 bar height

        const el = document.createElement('div');
        el.className = 'booking-label';
        el.style.left = `${left}px`;
        el.style.top = `${top}px`;
        el.style.width = `${width}px`;
        el.textContent = label;

        layer.appendChild(el);
      });
    }



    function renderCalendar(data) {
      const rooms = data.rooms || [];
      if (!rooms.length) {
        calendarContainer.innerHTML = '<div class="calendar-empty">Nessuna camera trovata per i filtri selezionati.</div>';
        return;
      }

      const days = data.days || [];
      window.currentCalendarDays = days;
      const windowStart = days[0];
      const windowEndExclusive = addDaysYMD(days[days.length - 1], 1);
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

        const occs = bookings.get(roomId) || [];
        const manutListAll = manutenzioni.get(roomId) || [];
        const puliziaListAll = pulizie.get(roomId) || [];

        // attiva/disattiva camera
        const attivaVal = parseInt((room.attiva ?? 1), 10);
        const isDisattivaCamera = (Number.isNaN(attivaVal) ? true : attivaVal !== 1);

        days.forEach(day => {
          let status = 'libera';
          let bookingId = '';
          let bookingPayload = null;
          const tooltipParts = [];

          // lookup occupazione (checkin <= day < checkout)
          const dayDate = new Date(day + 'T00:00:00');
          const match = occs.find(b => {
            const start = new Date(b.checkin + 'T00:00:00');
            const end = new Date(b.checkout + 'T00:00:00');
            return dayDate >= start && dayDate < end;
          });

          // per tooltip (solo informativo)
          if (isDisattivaCamera) tooltipParts.push('Camera disattivata');
          if (manutListAll.length) tooltipParts.push(manutListAll[0]?.stato ? `${formatStatus(manutListAll[0].stato)}` : 'Manutenzione');
          if (puliziaListAll.length) tooltipParts.push(puliziaListAll[0]?.stato ? `${formatStatus(puliziaListAll[0].stato)}` : 'Pulizia');

          // Stato cella: prima la prenotazione (se c'è), altrimenti disattiva/manut/pulizia/libera
          if (match) {
            status = 'occupata';
            bookingId = match.id;
            bookingPayload = match;
            tooltipParts.push(`Soggiorno ${match.checkin} → ${match.checkout}`);
          } else {
            if (isDisattivaCamera) status = 'disattiva';
            else if (manutListAll.length) status = 'manutenzione';
            else if (puliziaListAll.length) status = 'pulizia';
            else status = 'libera';
          }

          // tooltip
          const tooltip = tooltipParts.filter(Boolean).join(' · ');
          const tooltipAttr = tooltip ? ` data-bs-toggle="tooltip" title="${tooltip.replace(/"/g, '&quot;')}"` : '';

          // data-attr per click
          const defaultCheckin = day;
          const defaultCheckout = addDaysYMD(day, 1);

          // Se occupata: per sicurezza settiamo checkout reale nel dataset (così in modal è sempre giusto)
          const datasetCheckout = (status === 'occupata' && bookingPayload?.checkout) ? bookingPayload.checkout : '';

          // checkout suggerito (solo per celle libere, utile se vuoi pre-compilare)
          const checkoutSuggest = defaultCheckout;

          // meta badges (solo M / PU / D)
          const statusBadges = [];
          if (isDisattivaCamera) statusBadges.push('<span class="badge bg-secondary">D</span>');
          if (manutListAll.length) statusBadges.push('<span class="badge bg-danger">M</span>');
          if (puliziaListAll.length) statusBadges.push('<span class="badge bg-warning text-dark">PU</span>');

          const metaTags = statusBadges.length ? `<div class="cell-meta">${statusBadges.join('')}</div>` : '';

          // ---- SLOPE BAR LOGIC ----
          // start = giorno check-in
          // end   = ultima notte (giorno prima del checkout)
          let isStart = false;
          let isEndNight = false;
          if (match) {
            isStart = (day === match.checkin);
            isEndNight = (addDaysYMD(day, 1) === match.checkout);
          }

          let insideHtml = '';

          // eventi sul giorno (servono per CI/CO parziali)
          const occsForRoom = occs || [];
          const hasCI = occsForRoom.some(b => b.checkin === day);
          const hasCO = occsForRoom.some(b => b.checkout === day);
          const isTurnover = hasCI && hasCO;

          // scegli classe colore segmento
          let segColor = 'occ';
          if (status === 'manutenzione') segColor = 'mnt';
          if (status === 'pulizia') segColor = 'pul';
          if (status === 'disattiva') segColor = 'dis';

          insideHtml += `<div class="barwrap">`;

          if (status === 'libera') {
            // se è libera ma ha un CO (check-out) vogliamo comunque disegnarlo a sinistra
            if (hasCO) {
              insideHtml += `<div class="stayseg ${segColor} co"> </div>`;
            }
            // se è libera ma ha un CI (caso raro: booking che parte ma match non trovato) disegna CI
            if (hasCI) {
              insideHtml += `<div class="stayseg ${segColor} ci"> </div>`;
            }
            // bottone prenota
            insideHtml += `<button class="btn btn-outline-primary btn-sm">Prenota</button>`;
          } else if (status === 'occupata') {
            const labelHtml = '';

            if (hasCI && !hasCO) {
              insideHtml += `<div class="stayseg occ ci">${labelHtml}</div>`;
            }
            else if (!hasCI && !hasCO) {
              insideHtml += `<div class="stayseg occ full">${labelHtml}</div>`;
            }
            else if (isTurnover) {
              insideHtml += `<div class="stayseg occ co turnover"></div>`;
              insideHtml += `<div class="stayseg occ ci turnover">${labelHtml}</div>`;
            }
            else {
              insideHtml += `<div class="stayseg occ full">${labelHtml}</div>`;
            }
          }


          insideHtml += `</div>`;

          const disabledClass = isDisattivaCamera ? ' disabled' : '';
          const bookingIdAttr = bookingId ? ` data-booking-id="${bookingId}"` : '';

          html += `<td>
            <div class="cell cell-${status}${disabledClass}"
                 data-camera-id="${room.id}"
                 data-checkin="${defaultCheckin}"
                 data-checkout="${datasetCheckout}"
                 data-checkout-suggest="${checkoutSuggest}"
                 ${bookingIdAttr}${tooltipAttr}>
              ${insideHtml}
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

      placeBookingLabels(days);
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
      const checkout = cell.dataset.checkout || cell.dataset.checkoutSuggest || '';

      if (bookingId) {
        const booking = (window.currentCalendarBookings || []).find(b => parseInt(b.id, 10) === bookingId);
        openBookingModal({
          bookingId,
          cameraId: booking?.camera_id || cameraId,
          checkin: booking?.checkin || checkin,
          checkout: booking?.checkout || checkout,
          booking
        });
      } else {
        openBookingModal({ bookingId: null, cameraId, checkin, checkout });
      }
    });

    calendarContainer.addEventListener('scroll', () => {
      if (window.currentCalendarDays) placeBookingLabels(window.currentCalendarDays);
    });

    window.addEventListener('resize', () => {
      if (window.currentCalendarDays) placeBookingLabels(window.currentCalendarDays);
    });

    saveBookingBtn?.addEventListener('click', saveBooking);

    // ✅ aggiorna min checkout quando cambia check-in manualmente nel modal
    bookingCheckin?.addEventListener('change', () => {
      applyCheckoutMinFromCheckin(bookingCheckin.value);
      applyHbDateBounds();
    });
    bookingCheckout?.addEventListener('change', applyHbDateBounds);

    bookingPasto?.addEventListener('change', toggleHbFields);
    bookingPasto?.addEventListener('change', toggleNotesField);

    guestsContainer.addEventListener('click', (ev) => {
      const btn = ev.target.closest('.js-save-guest');
      if (!btn) return;
      const card = btn.closest('.guest-card');
      saveGuest(card);
    });

    guestsContainer.addEventListener('click', async (ev) => {
      const btn = ev.target.closest('.js-remove-guest');
      if (!btn) return;
      const card = btn.closest('.guest-card');
      if (!card) return;
      const guestId = card.dataset.guestId ? parseInt(card.dataset.guestId, 10) : null;
      if (!guestId || !currentBookingId) {
        card.remove();
        if (!guestsContainer.querySelector('.guest-card')) {
          guestsEmpty.classList.remove('d-none');
        }
        return;
      }
      const res = await fetchJson('ospiti_ajax.php', {
        method: 'POST',
        body: JSON.stringify({ action: 'delete_guest', soggiorno_id: currentBookingId, cliente_id: guestId })
      });
      showToast(res.message || 'Ospite rimosso', res.toast?.variant || (res.ok ? 'success' : 'danger'));
      if (res.ok) loadGuests(currentBookingId);
    });

    addGuestBtn?.addEventListener('click', () => {
      // ✅ ora posso aggiungere ospiti anche prima del salvataggio prenotazione
      guestsEmpty.classList.add('d-none');
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
      renderServices(meta.servizi || []);
      toggleHbFields();
      toggleNotesField();
    });

    renderPianiOptions();
    loadMeta();
    loadCalendar();
  })();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
