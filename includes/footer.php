</main>

<footer class="footer mt-auto py-3 bg-light">
    <div class="container text-center">
        <span class="text-muted">Sistema GED &copy; <?= date('Y') ?></span>
    </div>
</footer>

<!-- Scripts Globais -->
<script>
    $(document).ready(function() {
        // Inicializar Select2
        $('.select2').select2({
            theme: 'bootstrap-5'
        });

        // Inicializar DataTables
        $('.datatable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/pt-BR.json'
            }
        });

        // Auto-hide para alertas
        $('.alert').delay(5000).fadeOut(500);
    });
</script>
</body>
</html>