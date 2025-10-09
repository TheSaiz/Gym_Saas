<footer class="bg-dark text-light text-center py-4 mt-5">
    <div class="container">
        <p><?= $config['footer_texto'] ?? '© 2025 Gimnasio System SAAS - Todos los derechos reservados'; ?></p>
        <p>Contacto: <a href="mailto:<?= $config['contacto_email'] ?? 'info@sistema.com'; ?>" class="text-success"><?= $config['contacto_email'] ?? 'info@sistema.com'; ?></a> | Tel: <?= $config['contacto_telefono'] ?? '+54 9 11 9999 9999'; ?></p>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
