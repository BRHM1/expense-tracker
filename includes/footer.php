</main>

<footer class="container text-center text-muted small py-4 mt-4 border-top">
    Expense Tracker &middot; PHP <?= e(PHP_VERSION) ?> &rarr; MySQL &rarr; Apache HTTP Server
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Bootstrap client-side validation: block submit and show messages for invalid fields.
    document.querySelectorAll('form.needs-validation').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });
</script>
</body>
</html>
