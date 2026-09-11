/**
 * SmartOps Source: assets/js/admin/settings.js
 * Purpose: Js / Admin: Settings client-side interactions and dynamic UI behaviour.
 * Developer Guide: named functions are separated into searchable sections.
 */

document.addEventListener('DOMContentLoaded', function () {
  const nameInput = document.getElementById('adminName');
  const emailInput = document.getElementById('adminEmail');
  const editButton = document.getElementById('editBtn');
  const saveRow = document.getElementById('saveRow');
  const passwordModal = document.getElementById('passwordModal');
  const passwordMessage = document.getElementById('passwordMessage');
  const currentPassword = document.getElementById('currentPassword');
  const newPassword = document.getElementById('newPassword');
  const confirmPassword = document.getElementById('confirmPassword');
  const backLink = document.getElementById('backToDashboard');

  let originalName = nameInput ? nameInput.value : '';
  let originalEmail = emailInput ? emailInput.value : '';

  // ---------------------------------------------------------------------------
  // SECTION: Set Edit Mode
  // ---------------------------------------------------------------------------
  function setEditMode(enabled) {
    if (nameInput) nameInput.disabled = !enabled;
    if (emailInput) emailInput.disabled = !enabled;
    if (editButton) editButton.style.display = enabled ? 'none' : 'inline-block';
    if (saveRow) saveRow.classList.toggle('hidden', !enabled);
  }

  // ---------------------------------------------------------------------------
  // SECTION: Open Password Modal
  // ---------------------------------------------------------------------------
  function openPasswordModal() {
    if (!passwordModal) return;
    passwordModal.style.display = 'flex';
    passwordModal.setAttribute('aria-hidden', 'false');
  }

  // ---------------------------------------------------------------------------
  // SECTION: Close Password Modal
  // ---------------------------------------------------------------------------
  function closePasswordModal() {
    if (!passwordModal) return;
    passwordModal.style.display = 'none';
    passwordModal.setAttribute('aria-hidden', 'true');
    if (currentPassword) currentPassword.value = '';
    if (newPassword) newPassword.value = '';
    if (confirmPassword) confirmPassword.value = '';
    if (passwordMessage) passwordMessage.textContent = '';
  }

  // ---------------------------------------------------------------------------
  // SECTION: Update Password
  // ---------------------------------------------------------------------------
  function updatePassword() {
    if (!passwordMessage || !currentPassword || !newPassword || !confirmPassword) return;

    if (!currentPassword.value || !newPassword.value || !confirmPassword.value) {
      passwordMessage.className = 'error-message';
      passwordMessage.textContent = 'Please fill in all password fields.';
      return;
    }

    if (newPassword.value !== confirmPassword.value) {
      passwordMessage.className = 'error-message';
      passwordMessage.textContent = 'New password and confirm password do not match.';
      return;
    }

    passwordMessage.className = 'success-message';
    passwordMessage.textContent = 'Password updated successfully.';
    window.setTimeout(closePasswordModal, 1200);
  }

  document.querySelectorAll('[data-settings-action]').forEach(function (button) {
    button.addEventListener('click', function () {
      const action = button.dataset.settingsAction;

      if (action === 'edit') setEditMode(true);
      if (action === 'save') {
        originalName = nameInput ? nameInput.value : originalName;
        originalEmail = emailInput ? emailInput.value : originalEmail;
        setEditMode(false);
        window.alert('Profile updated successfully.');
      }
      if (action === 'cancel-edit') {
        if (nameInput) nameInput.value = originalName;
        if (emailInput) emailInput.value = originalEmail;
        setEditMode(false);
      }
      if (action === 'open-password') openPasswordModal();
      if (action === 'close-password') closePasswordModal();
      if (action === 'update-password') updatePassword();
    });
  });

  if (passwordModal) {
    passwordModal.addEventListener('click', function (event) {
      if (event.target === passwordModal) closePasswordModal();
    });
  }

  // ---------------------------------------------------------------------------
  // SECTION: Page Initialization
  // ---------------------------------------------------------------------------
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') closePasswordModal();
  });

  if (backLink) {
    const returnPage = new URLSearchParams(window.location.search).get('return');
    if (returnPage) backLink.href = returnPage;
  }
});
