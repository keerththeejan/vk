(() => {
  const fab = document.querySelector('.qtn-fab');
  document.addEventListener('keydown', (e) => {
    if (e.key === 'n' || e.key === 'N') {
      const tag = (e.target && e.target.tagName) || '';
      if (['INPUT', 'TEXTAREA', 'SELECT'].includes(tag) || e.target?.isContentEditable) return;
      if (fab) {
        e.preventDefault();
        window.location.href = fab.href;
      }
    }
  });

  const form = document.getElementById('qtnFilterForm');
  if (!form) return;

  let timer = null;
  const submitLive = () => {
    clearTimeout(timer);
    timer = setTimeout(() => form.requestSubmit(), 450);
  };

  form.querySelectorAll('#qtnLiveSearch, .qtn-live-field').forEach((el) => {
    el.addEventListener('input', submitLive);
  });
  form.querySelectorAll('.qtn-auto-submit').forEach((el) => {
    el.addEventListener('change', () => form.requestSubmit());
  });
})();
