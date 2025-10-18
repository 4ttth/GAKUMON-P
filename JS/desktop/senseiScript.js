//Gakusensei Bank Modal functionality — show ONCE per user after approval
document.addEventListener('DOMContentLoaded', function () {
  const gakusenseiModal = document.getElementById('gakusenseiBankModal');
  if (!gakusenseiModal) return; // not a Gakusensei or no modal on this page

  // Per-user "done" flag (no backend change needed)
  const uid = Number(window.currentUserId || 0);
  const DONE_KEY = uid ? `gaku_bank_done_${uid}` : 'gaku_bank_done';
  if (localStorage.getItem(DONE_KEY) === '1') return; // already completed -> never show again

  // Ensure the modal sits ABOVE the backdrop (Bootstrap modals use ~1050+)
  gakusenseiModal.style.display = 'block';
  gakusenseiModal.style.position = 'fixed';
  gakusenseiModal.style.zIndex = '1055'; // above backdrop
  // Optional: center if your CSS doesn't already do it
  gakusenseiModal.style.left = gakusenseiModal.style.left || '50%';
  gakusenseiModal.style.top  = gakusenseiModal.style.top  || '50%';
  gakusenseiModal.style.transform = gakusenseiModal.style.transform || 'translate(-50%, -50%)';

  // Backdrop (kept non-clickable so outside click won't close it)
  const backdrop = document.createElement('div');
  backdrop.className = 'gakusensei-modal-backdrop';
  backdrop.style.cssText = `
    position: fixed; inset: 0; background: rgba(0,0,0,0.5);
    z-index: 1050; pointer-events: none;
  `;
  document.body.appendChild(backdrop);

  function closeModal() {
    gakusenseiModal.style.display = 'none';
    const b = document.querySelector('.gakusensei-modal-backdrop');
    if (b) b.remove();
    document.body.style.overflow = ''; // in case your CSS locks scroll
  }

  // Save bank info (ONLY set the "done" flag when the form validates & submits)
  const saveBankInfoBtn = document.getElementById('save-bank-info-btn');
  const form = document.forms['gakusenseiBankForm'];
  if (saveBankInfoBtn && form) {
    saveBankInfoBtn.addEventListener('click', function () {
      if (form.checkValidity()) {
        // mark as done so the modal never shows again for this user
        localStorage.setItem(DONE_KEY, '1');
        form.submit(); // preserve your current submit behavior
        closeModal();
      } else {
        form.reportValidity();
      }
    });
  }

  // “Remind later” just closes; DO NOT set the done flag here
  const remindLaterBtn = document.getElementById('remind-later-btn');
  if (remindLaterBtn) remindLaterBtn.addEventListener('click', closeModal);

  // Close (X) button, if present
  const closeButton = document.querySelector('.gakusensei-modal-close');
  if (closeButton) closeButton.addEventListener('click', closeModal);
});