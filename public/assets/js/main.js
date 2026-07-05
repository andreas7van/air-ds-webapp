// assets/js/main.js — shared client-side behaviour for all pages.

document.addEventListener('DOMContentLoaded', () => {
  /* ------------------------------------------------------------------ *
   * 1. Entrance animation for elements with .fade-in-up
   * ------------------------------------------------------------------ */
  document.querySelectorAll('.fade-in-up').forEach((el, idx) => {
    el.style.transition = 'opacity 0.5s ease-out, transform 0.5s ease-out';
    el.style.transitionDelay = `${Math.min(idx, 5) * 0.12}s`;
    requestAnimationFrame(() => el.classList.add('visible'));
  });

  /* ------------------------------------------------------------------ *
   * 2. Mobile navbar toggle
   * ------------------------------------------------------------------ */
  const burger = document.querySelector('.hamburger');
  const links  = document.querySelector('.nav-links');
  if (burger && links) {
    links.addEventListener('click', e => e.stopPropagation());
    burger.addEventListener('click', e => {
      e.stopPropagation();
      const open = links.classList.toggle('open');
      burger.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.addEventListener('click', () => {
      links.classList.remove('open');
      burger.setAttribute('aria-expanded', 'false');
    });
  }

  /* ------------------------------------------------------------------ *
   * 3. Search form (home.php): airport validation + submit gating
   * ------------------------------------------------------------------ */
  const dep    = document.getElementById('departure');
  const arr    = document.getElementById('arrival');
  const errBox = document.getElementById('airport-error');

  if (dep && arr && errBox) {
    const errList = errBox.querySelector('ul');

    const showAirportError = msg => {
      errList.innerHTML = '';
      const li = document.createElement('li');
      li.textContent = msg;
      errList.appendChild(li);
      errBox.style.display = 'block';
    };
    const clearAirportError = () => {
      errBox.style.display = 'none';
      errList.innerHTML = '';
    };

    // Departure and arrival must differ.
    [dep, arr].forEach(el =>
      el.addEventListener('change', () => {
        clearAirportError();
        if (dep.value && arr.value && dep.value === arr.value) {
          showAirportError('Το αεροδρόμιο άφιξης δεν μπορεί να είναι ίδιο με το αναχώρησης.');
          arr.value = '';
        }
      })
    );
  }

  const form      = document.getElementById('flight-form');
  const submitBtn = document.getElementById('submit-btn');
  if (form && submitBtn) {
    const isLoggedIn = form.dataset.loggedIn === '1';
    const updateSubmitButton = () => {
      // Enabled only for a logged-in user with a valid form.
      submitBtn.disabled = !isLoggedIn || !form.checkValidity();
    };
    form.addEventListener('input', updateSubmitButton);
    updateSubmitButton();
  }

  /* ------------------------------------------------------------------ *
   * 4. Auth page (login.php): panel toggle + error modal
   * ------------------------------------------------------------------ */
  const authContainer = document.getElementById('auth-container');
  if (authContainer) {
    document.getElementById('signUp')?.addEventListener('click', () =>
      authContainer.classList.add('right-panel-active'));
    document.getElementById('signIn')?.addEventListener('click', () =>
      authContainer.classList.remove('right-panel-active'));

    document.getElementById('error-ok')?.addEventListener('click', () => {
      window.location.href = window.location.pathname + window.location.search;
    });
  }

  /* ------------------------------------------------------------------ *
   * 5. Seat selection (book.php) — driven by window.BookingData
   * ------------------------------------------------------------------ */
  if (window.BookingData) {
    const { seatsData, unavailable, numSeats, depTax, arrTax, dist } =
      window.BookingData;

    let selected = [];

    // Premium rows 1, 11, 12 → +20€; rows 2–10 → +10€.
    const seatCost = id => {
      const row = parseInt(id, 10);
      if ([1, 11, 12].includes(row)) return 20;
      if (row >= 2 && row <= 10)     return 10;
      return 0;
    };

    // Build one absolutely-positioned button per seat over the plane image.
    function renderSeats() {
      const container = document.getElementById('seat-container');
      container.querySelectorAll('button').forEach(b => b.remove());

      seatsData.forEach(s => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.textContent = s.id;
        btn.style.left = (s.x * 100) + '%';
        btn.style.top  = (s.y * 100) + '%';
        btn.className = 'seat-button';

        if (unavailable.includes(s.id)) {
          btn.disabled = true;
          btn.classList.add('unavailable');
        } else {
          btn.addEventListener('click', () => toggleSeat(s.id, btn));
        }
        container.appendChild(btn);
      });
    }
    window.renderSeats = renderSeats;

    function toggleSeat(id, btn) {
      const idx = selected.indexOf(id);
      if (idx < 0 && selected.length < numSeats) {
        selected.push(id);
        btn.classList.add('selected');
      } else if (idx >= 0) {
        selected.splice(idx, 1);
        btn.classList.remove('selected');
      }
      document.querySelector('#selected .mono').textContent =
        selected.length ? selected.join(', ') : '—';
      // The summary unlocks once one seat per passenger is selected.
      document.getElementById('to-summary').disabled =
        (selected.length !== numSeats);
    }

    // Step 1 → 2: validate passenger names, then reveal the seat map.
    document.getElementById('to-seats').addEventListener('click', () => {
      const passForm = document.getElementById('passengers-form');
      if (!passForm.checkValidity()) {
        passForm.reportValidity();
        return;
      }
      passForm.querySelectorAll('input').forEach(i => (i.disabled = true));
      const sm = document.getElementById('seat-map');
      sm.classList.add('open');
      renderSeats();
      sm.scrollIntoView({ behavior: 'smooth' });
    });

    // Step 2 → 3: cost breakdown.
    document.getElementById('to-summary').addEventListener('click', () => {
      const seatsCost = selected.reduce((sum, id) => sum + seatCost(id), 0);
      const flightCost = dist / 10;
      const taxes = depTax + arrTax;
      const total = numSeats * (flightCost + taxes) + seatsCost;

      document.getElementById('details').innerHTML = `
        <dl class="cost-breakdown">
          <div><dt>Απόσταση</dt><dd class="mono">${dist} km</dd></div>
          <div><dt>Κόστος πτήσης / επιβάτη</dt><dd class="mono">${flightCost.toFixed(2)} €</dd></div>
          <div><dt>Φόροι / επιβάτη</dt><dd class="mono">${depTax} € + ${arrTax} € = ${taxes.toFixed(2)} €</dd></div>
          <div><dt>Επιβάρυνση θέσεων</dt><dd class="mono">${seatsCost.toFixed(2)} €</dd></div>
          <div class="total"><dt>Σύνολο (${numSeats} επιβάτες)</dt><dd class="mono">${total.toFixed(2)} €</dd></div>
        </dl>`;
      const summary = document.getElementById('summary');
      summary.style.display = 'block';
      summary.classList.add('fade-in-up', 'visible');
      summary.scrollIntoView({ behavior: 'smooth' });
    });

    // Final submission through the hidden form.
    document.getElementById('confirm').addEventListener('click', () => {
      document.getElementById('seats-input').value = selected.join(',');
      document.getElementById('confirm-form').submit();
    });
  }
});
