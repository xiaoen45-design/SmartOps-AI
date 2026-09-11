/**
 * SmartOps Source: technician_website/assets/js/technician.js
 * Purpose: Technician Website / Js: Technician client-side interactions and dynamic UI behaviour.
 * Developer Guide: named functions are separated into searchable sections.
 */

(() => {
  'use strict';

  // ---------------------------------------------------------------------------
  // SECTION: Boot Technician UI
  // ---------------------------------------------------------------------------
  const bootTechnicianUi = () => {
    initFirstInputFocus();
    initSettingsMenu();
    initMobileNavigation();
    initTaskActionModal();
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootTechnicianUi, { once: true });
  } else {
    bootTechnicianUi();
  }

  // ---------------------------------------------------------------------------
  // SECTION: Init First Input Focus
  // ---------------------------------------------------------------------------
  function initFirstInputFocus() {
    const firstInput = document.querySelector('.center-card input');
    if (firstInput) firstInput.focus();
  }

  // ---------------------------------------------------------------------------
  // SECTION: Init Settings Menu
  // ---------------------------------------------------------------------------
  function initSettingsMenu() {
    const toggle = document.getElementById('settingsToggle');
    const panel = document.getElementById('settingsMenuPanel');
    if (!toggle || !panel) return;

    const setOpen = open => {
      panel.hidden = !open;
      panel.classList.toggle('is-open', open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    };

    toggle.addEventListener('click', event => {
      event.stopPropagation();
      setOpen(panel.hidden);
    });
    panel.addEventListener('click', event => event.stopPropagation());
    document.addEventListener('click', () => setOpen(false));
    document.addEventListener('keydown', event => {
      if (event.key === 'Escape') setOpen(false);
    });
  }


  // ---------------------------------------------------------------------------
  // SECTION: Init Mobile Navigation
  // ---------------------------------------------------------------------------
  function initMobileNavigation() {
    const toggle = document.getElementById('techMenuToggle');
    const drawer = document.getElementById('techMenuDrawer');
    const backdrop = document.getElementById('techMenuBackdrop');
    const closeButton = document.getElementById('techMenuClose');
    if (!toggle || !drawer || !backdrop || !closeButton) return;

    const phoneShell = drawer.closest('.phone-shell');
    const phoneScreen = phoneShell ? phoneShell.closest('.phone-screen') : null;

    const syncDrawerToPhoneShell = () => {
      if (!phoneShell) return;

      const referenceRect = (phoneScreen || phoneShell).getBoundingClientRect();
      const shellRect = phoneShell.getBoundingClientRect();
      const viewportWidth = Math.max(document.documentElement.clientWidth, window.innerWidth || 0);
      const viewportHeight = Math.max(document.documentElement.clientHeight, window.innerHeight || 0);
      const shellLeft = Math.max(0, referenceRect.left);
      const shellRight = Math.max(0, viewportWidth - referenceRect.right);
      const shellTop = Math.max(0, referenceRect.top);
      const shellHeight = Math.min(referenceRect.height, viewportHeight - shellTop);
      const shellWidth = Math.min(referenceRect.width, viewportWidth);
      const drawerWidth = Math.min(shellRect.width * 0.82, 318);

      phoneShell.style.setProperty('--tech-shell-left', `${shellLeft}px`);
      phoneShell.style.setProperty('--tech-shell-right', `${shellRight}px`);
      phoneShell.style.setProperty('--tech-shell-width', `${shellWidth}px`);
      phoneShell.style.setProperty('--tech-shell-top', `${shellTop}px`);
      phoneShell.style.setProperty('--tech-shell-height', `${shellHeight}px`);
      phoneShell.style.setProperty('--tech-drawer-width', `${drawerWidth}px`);
    };

    const setOpen = open => {
      syncDrawerToPhoneShell();
      drawer.classList.toggle('is-open', open);
      drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      toggle.setAttribute('aria-label', open ? 'Close navigation menu' : 'Open navigation menu');
      backdrop.hidden = !open;
      document.body.classList.toggle('tech-menu-open', open);
      if (open) {
        window.requestAnimationFrame(() => closeButton.focus());
      }
    };

    toggle.addEventListener('click', () => setOpen(toggle.getAttribute('aria-expanded') !== 'true'));
    closeButton.addEventListener('click', () => setOpen(false));
    backdrop.addEventListener('click', () => setOpen(false));
    drawer.querySelectorAll('a').forEach(link => link.addEventListener('click', () => setOpen(false)));
    window.addEventListener('resize', syncDrawerToPhoneShell, { passive: true });
    window.addEventListener('orientationchange', syncDrawerToPhoneShell, { passive: true });
    syncDrawerToPhoneShell();
    document.addEventListener('keydown', event => {
      if (event.key === 'Escape' && drawer.classList.contains('is-open')) {
        setOpen(false);
        toggle.focus();
      }
    });
  }

  // ---------------------------------------------------------------------------
  // SECTION: Init Task Action Modal
  // ---------------------------------------------------------------------------
  function initTaskActionModal() {
    const modal = document.getElementById('techActionModal');
    const form = document.getElementById('techActionForm');
    if (!modal || !form) return;

    const assetConfig = window.TECH_SUPPORT_CONFIG || {};
    const actionInput = document.getElementById('modalAction');
    const caseInput = document.getElementById('modalCaseId');
    const title = document.getElementById('techActionTitle');
    const caseLabel = document.getElementById('modalCaseLabel');
    const roomText = document.getElementById('modalRoomText');
    const issueText = document.getElementById('modalIssueText');
    const message = document.getElementById('modalMessage');
    const supportReasonRow = document.getElementById('supportReasonRow');
    const supportReasonInputs = Array.from(document.querySelectorAll('input[name="support_reason"]'));
    const supportCards = Array.from(document.querySelectorAll('[data-support-option]'));

    const partsPanel = document.getElementById('partsDetailPanel');
    const partSelect = document.getElementById('partSelect');
    const otherPartInput = document.getElementById('otherPartInput');
    const otherPartLabel = document.getElementById('otherPartLabel');
    const partQuantity = document.getElementById('partQuantity');

    const seniorPanel = document.getElementById('seniorDetailPanel');
    const seniorReasonSelect = document.getElementById('seniorReasonSelect');
    const seniorOtherReason = document.getElementById('seniorOtherReason');
    const seniorOtherReasonLabel = document.getElementById('seniorOtherReasonLabel');

    const outsourcingPanel = document.getElementById('outsourcingDetailPanel');
    const outsourcingServiceSelect = document.getElementById('outsourcingServiceSelect');
    const outsourcingOtherService = document.getElementById('outsourcingOtherService');
    const outsourcingOtherServiceLabel = document.getElementById('outsourcingOtherServiceLabel');
    const outsourcingReasonSelect = document.getElementById('outsourcingReasonSelect');
    const outsourcingOtherReason = document.getElementById('outsourcingOtherReason');
    const outsourcingOtherReasonLabel = document.getElementById('outsourcingOtherReasonLabel');

    const submitButton = document.getElementById('modalSubmitBtn');
    const closeButton = document.getElementById('modalCloseBtn');
    const cancelButton = document.getElementById('modalCancelBtn');

    const copy = {
      start: { title: 'Start Repair', message: 'Confirm that repair work is starting now.', button: 'Start Repair', support: false },
      complete: { title: 'Complete Repair', message: 'Confirm that the repair work has been completed.', button: 'Complete Repair', support: false },
      support: { title: 'Request Support', message: 'Select the required support and submit it to the Workforce manager.', button: 'Submit Request', support: true }
    };

    const assetKey = raw => {
      const value = String(raw || '').toLowerCase();
      if (value.includes('hvac')) return 'hvac';
      if (value.includes('plumb')) return 'plumbing';
      if (value.includes('elect')) return 'electrical';
      if (value.includes('fire')) return 'fire';
      if (value.includes('elevator') || value.includes('lift')) return 'elevator';
      return 'special';
    };

    const populateSelect = (select, values, placeholder, includeOther = true) => {
      if (!select) return;
      select.innerHTML = '';
      const placeholderOption = document.createElement('option');
      placeholderOption.value = '';
      placeholderOption.textContent = placeholder;
      select.appendChild(placeholderOption);

      values.forEach(value => {
        const option = document.createElement('option');
        option.value = value;
        option.textContent = value;
        select.appendChild(option);
      });

      if (includeOther) {
        const otherOption = document.createElement('option');
        otherOption.value = 'Other';
        otherOption.textContent = 'Other';
        select.appendChild(otherOption);
      }
    };

    const setConditionalInput = (select, input, label) => {
      const show = select.value === 'Other';
      input.hidden = !show;
      label.hidden = !show;
      input.required = show;
      if (!show) input.value = '';
    };

    const resetDetailPanels = () => {
      [partsPanel, seniorPanel, outsourcingPanel].forEach(panel => {
        if (panel) panel.hidden = true;
      });

      [partSelect, seniorReasonSelect, outsourcingServiceSelect, outsourcingReasonSelect].forEach(select => {
        if (select) select.required = false;
      });

      [otherPartInput, seniorOtherReason, outsourcingOtherService, outsourcingOtherReason].forEach(input => {
        if (!input) return;
        input.hidden = true;
        input.required = false;
        input.value = '';
      });

      [otherPartLabel, seniorOtherReasonLabel, outsourcingOtherServiceLabel, outsourcingOtherReasonLabel].forEach(label => {
        if (label) label.hidden = true;
      });

      if (partQuantity) partQuantity.value = '1';
    };

    const configureSupportOptions = rawAsset => {
      const config = assetConfig[assetKey(rawAsset)] || assetConfig.special || {
        parts: [], senior_reasons: [], outsourcing_services: [], outsourcing_reasons: []
      };

      const availability = {
        parts: Array.isArray(config.parts) && config.parts.length > 0,
        senior: true,
        outsourcing: Array.isArray(config.outsourcing_services) && config.outsourcing_services.length > 0
      };

      supportCards.forEach(card => {
        const type = card.dataset.supportOption;
        const input = card.querySelector('input');
        card.hidden = !availability[type];
        if (input) input.disabled = !availability[type];
      });

      populateSelect(partSelect, config.parts || [], 'Select a part');
      populateSelect(seniorReasonSelect, config.senior_reasons || [], 'Select a reason');
      populateSelect(outsourcingServiceSelect, config.outsourcing_services || [], 'Select an external service');
      populateSelect(outsourcingReasonSelect, config.outsourcing_reasons || [], 'Select a reason');
      resetDetailPanels();
    };

    supportReasonInputs.forEach(input => {
      input.addEventListener('change', () => {
        resetDetailPanels();
        if (input.value === 'Parts Required') {
          partsPanel.hidden = false;
          partSelect.required = true;
        } else if (input.value === 'Senior Support') {
          seniorPanel.hidden = false;
          seniorReasonSelect.required = true;
        } else if (input.value === 'Outsourcing') {
          outsourcingPanel.hidden = false;
          outsourcingServiceSelect.required = true;
          outsourcingReasonSelect.required = true;
        }
      });
    });

    partSelect.addEventListener('change', () => setConditionalInput(partSelect, otherPartInput, otherPartLabel));
    seniorReasonSelect.addEventListener('change', () => setConditionalInput(seniorReasonSelect, seniorOtherReason, seniorOtherReasonLabel));
    outsourcingServiceSelect.addEventListener('change', () => setConditionalInput(outsourcingServiceSelect, outsourcingOtherService, outsourcingOtherServiceLabel));
    outsourcingReasonSelect.addEventListener('change', () => setConditionalInput(outsourcingReasonSelect, outsourcingOtherReason, outsourcingOtherReasonLabel));

    const closeModal = () => {
      modal.hidden = true;
      document.body.classList.remove('modal-open');
      form.reset();
      resetDetailPanels();
    };

    const openModal = button => {
      const action = button.dataset.action || 'start';
      const details = copy[action] || copy.start;

      actionInput.value = action;
      caseInput.value = button.dataset.caseId || '';
      title.textContent = details.title;
      caseLabel.textContent = button.dataset.caseId || 'CASE';
      roomText.textContent = button.dataset.room || 'Room';
      issueText.textContent = button.dataset.issue || 'Issue';
      message.textContent = details.message;
      submitButton.textContent = details.button;
      supportReasonRow.hidden = !details.support;

      supportReasonInputs.forEach(input => {
        input.checked = false;
        input.required = false;
      });

      if (details.support) {
        configureSupportOptions(button.dataset.hotelAsset || '');
        const firstAvailable = supportReasonInputs.find(input => !input.disabled);
        if (firstAvailable) firstAvailable.required = true;
      }

      modal.hidden = false;
      document.body.classList.add('modal-open');
      submitButton.focus();
    };

    document.querySelectorAll('.tech-action-trigger').forEach(button => {
      button.addEventListener('click', () => openModal(button));
    });
    if (closeButton) closeButton.addEventListener('click', closeModal);
    if (cancelButton) cancelButton.addEventListener('click', closeModal);
    modal.addEventListener('click', event => {
      if (event.target === modal) closeModal();
    });
    document.addEventListener('keydown', event => {
      if (event.key === 'Escape' && !modal.hidden) closeModal();
    });
  }
})();
