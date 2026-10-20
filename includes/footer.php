</main>
<footer class="app-footer text-center text-muted small py-3 border-top">The Yo's Restaurant Information &amp; Online Ordering System</footer>
</div>
</div>
<script src="bootstrap-5.3.2-dist/js/bootstrap.bundle.min.js"></script>
<script>
(() => {
  const body = document.body;
  const toggles = document.querySelectorAll('.sidebar-toggle');
  const backdrop = document.querySelector('.sidebar-backdrop');
  const mobile = window.matchMedia('(max-width: 991.98px)');
  const setOpen = (open) => {
    if (mobile.matches) body.classList.toggle('sidebar-open', open);
    else body.classList.toggle('sidebar-collapsed', !open);
    toggles.forEach((toggle) => toggle.setAttribute('aria-expanded', String(open)));
  };
  toggles.forEach((toggle) => toggle.addEventListener('click', () => {
    const open = mobile.matches ? !body.classList.contains('sidebar-open') : body.classList.contains('sidebar-collapsed');
    setOpen(open);
  }));
  backdrop.addEventListener('click', () => setOpen(false));
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') setOpen(false);
  });
  mobile.addEventListener('change', () => {
    body.classList.remove('sidebar-open', 'sidebar-collapsed');
    toggles.forEach((toggle) => toggle.setAttribute('aria-expanded', String(!mobile.matches)));
  });
})();
</script>
</body>
</html>
