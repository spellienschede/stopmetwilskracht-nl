(() => {
  const form = document.getElementById('promo-form');
  if (form) {
    form.addEventListener('submit', () => {
      const btn = form.querySelector('[type="submit"]');
      if (btn) {
        btn.disabled = true;
        btn.textContent = 'Versturen…';
      }
    });
  }

  const mediaForm = document.getElementById('media-request-form');
  if (mediaForm) {
    mediaForm.addEventListener('submit', () => {
      const btn = mediaForm.querySelector('[type="submit"]');
      if (btn) {
        btn.disabled = true;
        btn.textContent = 'Versturen…';
      }
    });
  }

  document.querySelectorAll('[data-copy-target]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const id = btn.getAttribute('data-copy-target');
      const el = id ? document.getElementById(id) : null;
      if (!el) return;
      const text = el.textContent || '';
      try {
        await navigator.clipboard.writeText(text);
      } catch (err) {
        const range = document.createRange();
        range.selectNodeContents(el);
        const sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);
        document.execCommand('copy');
        sel.removeAllRanges();
      }
      const root = btn.closest('[data-copy-root]');
      const status = root ? root.querySelector('[data-copy-status]') : null;
      if (status) {
        status.hidden = false;
        window.setTimeout(() => {
          status.hidden = true;
        }, 1800);
      }
      const original = btn.textContent;
      btn.textContent = 'Gekopieerd';
      window.setTimeout(() => {
        btn.textContent = original;
      }, 1800);
    });
  });

  const releaseRaw = document.body.getAttribute('data-release-date');
  if (!releaseRaw) return;

  const release = new Date(releaseRaw + 'T00:00:00');
  if (Number.isNaN(release.getTime())) return;

  const daysUntil = () => {
    const now = new Date();
    const start = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const end = new Date(release.getFullYear(), release.getMonth(), release.getDate());
    return Math.round((end - start) / 86400000);
  };

  const labelFor = (days) => {
    if (days > 1) return 'Nog ' + days + ' dagen tot de deadline';
    if (days === 1) return 'Nog 1 dag tot de deadline';
    if (days === 0) return 'Laatste dag om te pre-orderen';
    return '';
  };

  const barTextFor = (days) => {
    const months = [
      'januari', 'februari', 'maart', 'april', 'mei', 'juni',
      'juli', 'augustus', 'september', 'oktober', 'november', 'december',
    ];
    const short = release.getDate() + ' ' + months[release.getMonth()] + ' ' + release.getFullYear();
    let countdown = '';
    if (days > 0) countdown = ' · nog ' + days + (days === 1 ? ' dag' : ' dagen');
    else if (days === 0) countdown = ' · laatste dag';
    return 'Nu pre-orderen · tot ' + short + countdown + ' · € 39 i.p.v. € 49 + 2 bonussen';
  };

  const refreshCountdown = () => {
    const days = daysUntil();
    const label = labelFor(days);
    document.querySelectorAll('[data-countdown-label]').forEach((el) => {
      if (label) el.textContent = label;
    });
    const bar = document.querySelector('.urgency-bar-text');
    if (bar && days >= 0) bar.textContent = barTextFor(days);
  };

  refreshCountdown();
})();
