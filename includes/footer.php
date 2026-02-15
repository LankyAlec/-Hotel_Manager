</div>

<footer class="app-footer mt-5">
    <div class="container py-3">
        <div class="d-flex flex-column flex-md-row align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-2 text-muted small">
                <span class="footer-brand text-dark"><?= APP_NAME ?></span>
                <span>© <?= date('Y') ?></span>
            </div>
            <div class="d-flex align-items-center gap-2 text-muted small">
                <span class="footer-pill"><i class="bi bi-clock"></i> <?= date('d/m/Y') ?></span>
                <span class="footer-pill"><i class="bi bi-lightning-charge"></i> Operatività live</span>
            </div>
            <div class="d-flex align-items-center gap-3 small">
                <a class="footer-link" href="<?= BASE_URL ?>/dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
                <a class="footer-link" href="<?= BASE_URL ?>/prenotazioni/calendario.php"><i class="bi bi-calendar-check"></i> Prenotazioni</a>
                <a class="footer-link" href="<?= BASE_URL ?>/pulizie/pulizie.php"><i class="bi bi-bucket"></i> Pulizie</a>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>