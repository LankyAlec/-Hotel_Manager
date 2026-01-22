<?php
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row g-4">
    <div class="col-12 col-xxl-5">
        <div class="card toolbar-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <h4 class="mb-1">Scheda gruppo in arrivo</h4>
                        <p class="text-muted mb-0">Compila tutti i dati manualmente e genera il PDF in un click.</p>
                    </div>
                    <span class="badge badge-soft"><i class="bi bi-stars"></i> Smart</span>
                </div>

                <form id="gruppoForm" class="vstack gap-4">
                    <div>
                        <h6 class="text-uppercase text-muted">Anagrafica gruppo</h6>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Nome gruppo</label>
                                <input type="text" class="form-control" id="nomeGruppo" placeholder="Es. Gruppo Lago Blu" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Referente principale</label>
                                <input type="text" class="form-control" id="referente" placeholder="Nome e cognome" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Agenzia / Ente</label>
                                <input type="text" class="form-control" id="agenzia" placeholder="Es. Tour Operator" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Telefono</label>
                                <input type="tel" class="form-control" id="telefono" placeholder="+39 ..." required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" placeholder="referente@email.it" required>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h6 class="text-uppercase text-muted">Soggiorno</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Arrivo</label>
                                <input type="date" class="form-control" id="dataArrivo" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Partenza</label>
                                <input type="date" class="form-control" id="dataPartenza" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Numero persone</label>
                                <input type="number" class="form-control" id="numeroPersone" min="1" placeholder="0" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tipologia camere</label>
                                <input type="text" class="form-control" id="tipologiaCamere" placeholder="Es. 10 doppie + 2 singole">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Piano / Area preferita</label>
                                <input type="text" class="form-control" id="areaPreferita" placeholder="Es. 2° piano - vista lago">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Note operative</label>
                                <textarea class="form-control" id="noteOperative" rows="3" placeholder="Richieste speciali, timing check-in, ecc."></textarea>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="d-flex align-items-center justify-content-between">
                            <h6 class="text-uppercase text-muted mb-0">Pasti programmati</h6>
                            <button class="btn btn-outline-primary btn-sm" type="button" id="aggiungiPasto">
                                <i class="bi bi-plus-circle"></i> Aggiungi pasto
                            </button>
                        </div>
                        <div class="table-responsive mt-3">
                            <table class="table table-sm align-middle" id="pastiTable">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Tipo</th>
                                        <th>Ora</th>
                                        <th>Note</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>

                    <div>
                        <div class="d-flex align-items-center justify-content-between">
                            <h6 class="text-uppercase text-muted mb-0">Attività / extra</h6>
                            <button class="btn btn-outline-primary btn-sm" type="button" id="aggiungiExtra">
                                <i class="bi bi-plus-circle"></i> Aggiungi attività
                            </button>
                        </div>
                        <div class="table-responsive mt-3">
                            <table class="table table-sm align-middle" id="extraTable">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Descrizione</th>
                                        <th>Orario</th>
                                        <th>Note</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button class="btn btn-primary" type="button" id="generaPdf">
                            <i class="bi bi-filetype-pdf"></i> Genera PDF
                        </button>
                        <button class="btn btn-outline-secondary" type="reset">
                            <i class="bi bi-arrow-counterclockwise"></i> Reset
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-xxl-7">
        <div class="card table-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <h5 class="mb-1">Anteprima scheda gruppo</h5>
                        <p class="text-muted mb-0">Questa anteprima verrà esportata in PDF.</p>
                    </div>
                    <span class="badge badge-soft"><i class="bi bi-printer"></i> Preview</span>
                </div>

                <div id="schedaPreview" class="p-4 border rounded-4 bg-white">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h3 class="mb-1" id="previewNome">Nome gruppo</h3>
                            <p class="text-muted mb-0" id="previewAgenzia">Agenzia / Ente</p>
                        </div>
                        <div class="text-end">
                            <div class="fw-semibold" id="previewPeriodo">Arrivo - Partenza</div>
                            <small class="text-muted" id="previewPartecipanti">0 partecipanti</small>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="row g-4">
                        <div class="col-md-6">
                            <h6 class="text-uppercase text-muted">Referente</h6>
                            <p class="mb-1" id="previewReferente">Nome referente</p>
                            <p class="mb-1" id="previewTelefono">Telefono</p>
                            <p class="mb-0" id="previewEmail">Email</p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-uppercase text-muted">Logistica camere</h6>
                            <p class="mb-1" id="previewCamere">Tipologia camere</p>
                            <p class="mb-0" id="previewArea">Area preferita</p>
                        </div>
                        <div class="col-12">
                            <h6 class="text-uppercase text-muted">Note operative</h6>
                            <p class="mb-0" id="previewNote">Inserisci eventuali note operative.</p>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div>
                        <h6 class="text-uppercase text-muted">Pasti programmati</h6>
                        <div class="table-responsive">
                            <table class="table table-sm" id="previewPasti">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Tipo</th>
                                        <th>Ora</th>
                                        <th>Note</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="4" class="text-muted">Nessun pasto inserito.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="mt-4">
                        <h6 class="text-uppercase text-muted">Attività / extra</h6>
                        <div class="table-responsive">
                            <table class="table table-sm" id="previewExtra">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Descrizione</th>
                                        <th>Orario</th>
                                        <th>Note</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="4" class="text-muted">Nessuna attività inserita.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
<script>
    const form = document.getElementById('gruppoForm');
    const preview = {
        nome: document.getElementById('previewNome'),
        agenzia: document.getElementById('previewAgenzia'),
        periodo: document.getElementById('previewPeriodo'),
        partecipanti: document.getElementById('previewPartecipanti'),
        referente: document.getElementById('previewReferente'),
        telefono: document.getElementById('previewTelefono'),
        email: document.getElementById('previewEmail'),
        camere: document.getElementById('previewCamere'),
        area: document.getElementById('previewArea'),
        note: document.getElementById('previewNote'),
        pasti: document.querySelector('#previewPasti tbody'),
        extra: document.querySelector('#previewExtra tbody')
    };

    const pastiTable = document.querySelector('#pastiTable tbody');
    const extraTable = document.querySelector('#extraTable tbody');

    const creaRigaPasto = () => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td><input type="date" class="form-control form-control-sm" required></td>
            <td>
                <select class="form-select form-select-sm" required>
                    <option value="">Seleziona</option>
                    <option>Colazione</option>
                    <option>Pranzo</option>
                    <option>Cena</option>
                    <option>Brunch</option>
                    <option>Altro</option>
                </select>
            </td>
            <td><input type="time" class="form-control form-control-sm" required></td>
            <td><input type="text" class="form-control form-control-sm" placeholder="Allergie, menù" required></td>
            <td class="text-end">
                <button type="button" class="btn btn-sm btn-outline-danger"><i class="bi bi-x"></i></button>
            </td>
        `;
        row.querySelector('button').addEventListener('click', () => {
            row.remove();
            aggiornaPreview();
        });
        row.querySelectorAll('input, select').forEach((input) => {
            input.addEventListener('input', aggiornaPreview);
        });
        return row;
    };

    const creaRigaExtra = () => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td><input type="date" class="form-control form-control-sm" required></td>
            <td><input type="text" class="form-control form-control-sm" placeholder="Visita guidata, sala meeting" required></td>
            <td><input type="time" class="form-control form-control-sm" required></td>
            <td><input type="text" class="form-control form-control-sm" placeholder="Referente, note" required></td>
            <td class="text-end">
                <button type="button" class="btn btn-sm btn-outline-danger"><i class="bi bi-x"></i></button>
            </td>
        `;
        row.querySelector('button').addEventListener('click', () => {
            row.remove();
            aggiornaPreview();
        });
        row.querySelectorAll('input').forEach((input) => {
            input.addEventListener('input', aggiornaPreview);
        });
        return row;
    };

    const aggiornaPreview = () => {
        preview.nome.textContent = document.getElementById('nomeGruppo').value || 'Nome gruppo';
        preview.agenzia.textContent = document.getElementById('agenzia').value || 'Agenzia / Ente';
        const arrivo = document.getElementById('dataArrivo').value;
        const partenza = document.getElementById('dataPartenza').value;
        preview.periodo.textContent = arrivo && partenza ? `${arrivo} → ${partenza}` : 'Arrivo - Partenza';
        const persone = document.getElementById('numeroPersone').value;
        preview.partecipanti.textContent = persone ? `${persone} partecipanti` : '0 partecipanti';
        preview.referente.textContent = document.getElementById('referente').value || 'Nome referente';
        preview.telefono.textContent = document.getElementById('telefono').value || 'Telefono';
        preview.email.textContent = document.getElementById('email').value || 'Email';
        preview.camere.textContent = document.getElementById('tipologiaCamere').value || 'Tipologia camere';
        preview.area.textContent = document.getElementById('areaPreferita').value || 'Area preferita';
        preview.note.textContent = document.getElementById('noteOperative').value || 'Inserisci eventuali note operative.';

        const pastiRows = Array.from(pastiTable.querySelectorAll('tr')).map((row) => {
            const inputs = row.querySelectorAll('input, select');
            return {
                data: inputs[0].value,
                tipo: inputs[1].value,
                ora: inputs[2].value,
                note: inputs[3].value
            };
        }).filter((row) => row.data || row.tipo || row.ora || row.note);

        preview.pasti.innerHTML = '';
        if (pastiRows.length === 0) {
            preview.pasti.innerHTML = '<tr><td colspan="4" class="text-muted">Nessun pasto inserito.</td></tr>';
        } else {
            pastiRows.forEach((row) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `<td>${row.data}</td><td>${row.tipo}</td><td>${row.ora}</td><td>${row.note}</td>`;
                preview.pasti.appendChild(tr);
            });
        }

        const extraRows = Array.from(extraTable.querySelectorAll('tr')).map((row) => {
            const inputs = row.querySelectorAll('input');
            return {
                data: inputs[0].value,
                descrizione: inputs[1].value,
                ora: inputs[2].value,
                note: inputs[3].value
            };
        }).filter((row) => row.data || row.descrizione || row.ora || row.note);

        preview.extra.innerHTML = '';
        if (extraRows.length === 0) {
            preview.extra.innerHTML = '<tr><td colspan="4" class="text-muted">Nessuna attività inserita.</td></tr>';
        } else {
            extraRows.forEach((row) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `<td>${row.data}</td><td>${row.descrizione}</td><td>${row.ora}</td><td>${row.note}</td>`;
                preview.extra.appendChild(tr);
            });
        }
    };

    document.getElementById('aggiungiPasto').addEventListener('click', () => {
        pastiTable.appendChild(creaRigaPasto());
        aggiornaPreview();
    });

    document.getElementById('aggiungiExtra').addEventListener('click', () => {
        extraTable.appendChild(creaRigaExtra());
        aggiornaPreview();
    });

    form.addEventListener('input', aggiornaPreview);
    form.addEventListener('reset', () => setTimeout(aggiornaPreview, 0));

    document.getElementById('generaPdf').addEventListener('click', async () => {
        aggiornaPreview();
        const element = document.getElementById('schedaPreview');
        const canvas = await html2canvas(element, { scale: 2, backgroundColor: '#ffffff' });
        const imgData = canvas.toDataURL('image/png');
        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF('p', 'mm', 'a4');
        const pageWidth = pdf.internal.pageSize.getWidth();
        const pageHeight = pdf.internal.pageSize.getHeight();
        const imgProps = pdf.getImageProperties(imgData);
        const pdfWidth = pageWidth;
        const pdfHeight = (imgProps.height * pdfWidth) / imgProps.width;
        let position = 0;

        if (pdfHeight <= pageHeight) {
            pdf.addImage(imgData, 'PNG', 0, position, pdfWidth, pdfHeight);
        } else {
            let remainingHeight = pdfHeight;
            let canvasPosition = 0;
            while (remainingHeight > 0) {
                pdf.addImage(imgData, 'PNG', 0, canvasPosition, pdfWidth, pdfHeight);
                remainingHeight -= pageHeight;
                canvasPosition -= pageHeight;
                if (remainingHeight > 0) {
                    pdf.addPage();
                }
            }
        }

        const nome = document.getElementById('nomeGruppo').value || 'scheda-gruppo';
        pdf.save(`${nome.toLowerCase().replace(/\s+/g, '-')}.pdf`);
    });

    aggiungiPasto.click();
    aggiornaPreview();
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
