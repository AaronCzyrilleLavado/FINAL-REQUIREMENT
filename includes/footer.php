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

  /* ── Restore focus & cursor position after automated filter submission ── */
  const focusName = sessionStorage.getItem('auto_focus_name');
  if (focusName) {
    const targetInput = document.querySelector(`form.auto-filter [name="${focusName}"]`);
    if (targetInput && (targetInput.type === 'search' || targetInput.type === 'text')) {
      targetInput.focus();
      const pos = parseInt(sessionStorage.getItem('auto_focus_cursor') || String(targetInput.value.length), 10);
      try { targetInput.setSelectionRange(pos, pos); } catch (e) {}
    }
    sessionStorage.removeItem('auto_focus_name');
    sessionStorage.removeItem('auto_focus_cursor');
  }

  /* ── Fully Automated Filtering across all filter forms ── */
  document.querySelectorAll('form.auto-filter').forEach((form) => {
    let timer;
    const triggerSubmit = (delay, activeEl) => {
      window.clearTimeout(timer);
      timer = window.setTimeout(() => {
        if (activeEl && (activeEl.type === 'search' || activeEl.type === 'text')) {
          sessionStorage.setItem('auto_focus_name', activeEl.name);
          sessionStorage.setItem('auto_focus_cursor', String(activeEl.selectionStart || activeEl.value.length));
        }
        form.requestSubmit();
      }, delay);
    };

    /* Text & Search inputs: debounced 400ms */
    form.querySelectorAll('input[type="search"], input[name="search"], input[type="text"]').forEach((input) => {
      input.addEventListener('input', () => triggerSubmit(400, input));
    });

    /* Date inputs: automatic submit on change */
    form.querySelectorAll('input[type="date"]').forEach((input) => {
      input.addEventListener('change', () => triggerSubmit(100, null));
    });

    /* Select dropdowns: immediate automatic submit on change */
    form.querySelectorAll('select').forEach((select) => {
      select.addEventListener('change', () => triggerSubmit(20, null));
    });
  });
})();
</script>
</body>
</html>
