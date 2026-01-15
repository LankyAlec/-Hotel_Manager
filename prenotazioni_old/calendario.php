<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
include __DIR__ . '/../includes/header.php';

if (!$isRoot && !in_gruppo('Reception')) {
    redirect('/dashboard.php');
}

$edifici = [];
$piani = [];

$resE = $mysqli->query("SELECT id, nome FROM edifici WHERE attivo = 1 ORDER BY nome ASC");
if ($resE) {
    $edifici = $resE->fetch_all(MYSQLI_ASSOC);
}

$resP = $mysqli->query("SELECT id, edificio_id, nome, livello FROM piani WHERE attivo = 1 ORDER BY livello ASC, nome ASC");
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

  .calendar-wrap{ border-radius:16px; border:1px solid rgba(0,0,0,.06); background:#fff; box-shadow:0 .35rem 1rem rgba(0,0,0,.08); }
  .calendar-wrap .table{ margin-bottom:0; }
  .calendar-table th{ background:#f8f9fa; font-size:.85rem; text-transform:uppercase; letter-spacing:.03em; }
  .calendar-table th, .calendar-table td{ text-align:center; vertical-align:middle; padding:.55rem .35rem; }
  .calendar-table .room-col{ text-align:left; min-width:180px; font-weight:600; background:#fff; position:sticky; left:0; z-index:2; box-shadow:1px 0 0 rgba(0,0,0,.05); }
  .calendar-table .room-sub{ font-weight:400; color:#6c757d; font-size:.82rem; }

  .cell{ min-width:80px; border-radius:8px; padding:.35rem .3rem; display:flex; flex-direction:column; gap:2px; align-items:center; justify-content:center; font-size:.78rem; }
  .cell-libera{ background:#f8f9fa; color:#6c757d; }
  .cell-occupata{ background:rgba(13,110,253,.18); color:#084298; font-weight:600; }
  .cell-manutenzione{ background:rgba(220,53,69,.22); color:#842029; font-weight:600; }
  .cell-pulizia{ background:rgba(255,193,7,.35); color:#664d03; font-weight:600; }
  .cell-disattiva{ background:rgba(108,117,125,.2); color:#6c757d; font-weight:600; }
  .cell .btn-book{ font-size:.7rem; padding:.1rem .35rem; line-height:1.2; }

  .calendar-empty{ padding:24px; text-align:center; color:#6c757d; }
  .calendar-toolbar{ display:flex; flex-wrap:wrap; gap:8px; align-items:center; justify-content:space-between; }
  .calendar-toolbar .btn-group{ box-shadow:0 .2rem .6rem rgba(0,0,0,.08); border-radius:10px; }
  .calendar-toolbar .form-control{ max-width:170px; }
</style>

<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h3 class="mb-1"><i class="bi bi-calendar-check"></i> Calendario prenotazioni</h3>
      <div class="text-muted small">Seleziona edificio e piano per vedere la disponibilità camere nel calendario.</div>
    </div>
    <a class="btn btn-outline-primary" href="<?= BASE_URL ?>/prenotazioni/lista.php">
      <i class="bi bi-list"></i> Vai all'elenco
    </a>
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
            <div class="item"><span class="dot" style="background:#0d6efd"></span> Occupata</div>
            <div class="item"><span class="dot" style="background:#6c757d"></span> Disattiva</div>
          </div>
        </div>
      </div>
      <div class="calendar-toolbar mt-3">
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
  </div>

  <div class="calendar-wrap">
    <div id="calendarContainer" class="table-responsive"></div>
  </div>
</div>

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

    let edificioSel = <?= (int)$edificioSel ?>;
    let pianoSel = <?= (int)$pianoSel ?>;

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
      const base = new Date(startDateEl.value + 'T00:00:00');
      base.setDate(base.getDate() + days);
      startDateEl.value = base.toISOString().slice(0, 10);
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
        html += `<td class="room-col">${room.codice || ''}<div class="room-sub">${accessibileHtml}</div></td>`;

        days.forEach(day => {
          let status = 'libera';
          let label = 'Libera';
          let tooltip = '';
          if (!parseInt(room.attiva || 1, 10)) {
            status = 'disattiva';
            label = 'Disattiva';
          } else if (manutenzioni.has(roomId)) {
            status = 'manutenzione';
            label = 'Manutenzione';
            const info = manutenzioni.get(roomId)[0];
            tooltip = info?.stato ? `Ticket: ${formatStatus(info.stato)}` : '';
          } else if (pulizie.has(roomId)) {
            status = 'pulizia';
            label = 'Pulizia';
            const info = pulizie.get(roomId)[0];
            tooltip = info?.stato ? `Pulizia: ${formatStatus(info.stato)}` : '';
          } else {
            const occs = bookings.get(roomId) || [];
            const dayDate = new Date(day + 'T00:00:00');
            const match = occs.find(b => {
              const start = new Date(b.checkin + 'T00:00:00');
              const end = new Date(b.checkout + 'T00:00:00');
              return dayDate >= start && dayDate < end;
            });
            if (match) {
              status = 'occupata';
              label = match.codice ? `Pren. ${match.codice}` : 'Occupata';
              tooltip = `Check-in ${match.checkin} · Check-out ${match.checkout}`;
            }
          }

          const tooltipAttr = tooltip ? ` data-bs-toggle="tooltip" title="${tooltip.replace(/"/g, '&quot;')}"` : '';
          let action = '';
          if (status === 'libera') {
            const checkinDate = new Date(day + 'T00:00:00');
            const checkoutDate = new Date(day + 'T00:00:00');
            checkoutDate.setDate(checkoutDate.getDate() + 1);
            const checkin = checkinDate.toISOString().slice(0, 10);
            const checkout = checkoutDate.toISOString().slice(0, 10);
            action = `<a class="btn btn-sm btn-outline-primary btn-book" href="<?= BASE_URL ?>/prenotazioni/lista.php?prenota=1&camera_id=${room.id}&data_checkin=${checkin}&data_checkout=${checkout}">Prenota</a>`;
          }
          html += `<td><div class="cell cell-${status}"${tooltipAttr}>${label}${action ? `<div>${action}</div>` : ''}</div></td>`;
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
      startDateEl.value = new Date().toISOString().slice(0, 10);
      loadCalendar();
    });

    renderPianiOptions();
    loadCalendar();
  })();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>