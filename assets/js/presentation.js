/**
 * SmartOps Source: assets/js/presentation.js
 * Purpose: Js: Presentation client-side interactions and dynamic UI behaviour.
 * Developer Guide: named functions are separated into searchable sections.
 */

(() => {
  const shell = document.querySelector('.presentation-shell');
  if (!shell) return;

  const stateUrl = shell.dataset.stateUrl;
  const selectUrl = shell.dataset.selectUrl;
  const tabs = [...document.querySelectorAll('[data-view]')];
  const adminStage = document.getElementById('adminStage');
  const technicianStage = document.getElementById('technicianStage');
  const technicianSelector = document.getElementById('technicianSelector');
  const technicianFrame = document.getElementById('technicianFrame');
  const technicianTabLabel = document.getElementById('technicianTabLabel');
  const followLatestButton = document.getElementById('followLatestButton');
  const latestAssignmentPill = document.getElementById('latestAssignmentPill');
  const latestAssignmentText = document.getElementById('latestAssignmentText');
  const selectedTechnicianHeading = document.getElementById('selectedTechnicianHeading');
  const selectedTechnicianMeta = document.getElementById('selectedTechnicianMeta');
  const selectedTechnicianCase = document.getElementById('selectedTechnicianCase');
  const selectedTechnicianEmpty = document.getElementById('selectedTechnicianEmpty');
  const selectedCaseId = document.getElementById('selectedCaseId');
  const selectedCaseIssue = document.getElementById('selectedCaseIssue');
  const selectedCaseStatus = document.getElementById('selectedCaseStatus');

  let state = window.SMARTOPS_PRESENTATION || { technicians: [], latestAssignment: null, selectedTechnicianId: '' };
  let selectedTechnicianId = state.selectedTechnicianId || technicianSelector?.value || '';
  let latestAssignmentSignature = signatureFor(state.latestAssignment);
  let lastTechnicianReloadSignature = '';
  let activeView = 'admin';

  // ---------------------------------------------------------------------------
  // SECTION: Signature For
  // ---------------------------------------------------------------------------
  function signatureFor(item) {
    if (!item) return '';
    return [item.case_id || '', item.technician_id || '', item.task_status || '', item.assigned_time || ''].join('|');
  }

  // ---------------------------------------------------------------------------
  // SECTION: Department Label
  // ---------------------------------------------------------------------------
  function departmentLabel(tech) {
    if (!tech) return '—';
    if ((tech.role || '').toLowerCase() === 'technical specialist') return 'All Departments';
    return tech.department || tech.category || '—';
  }

  // ---------------------------------------------------------------------------
  // SECTION: Option Label
  // ---------------------------------------------------------------------------
  function optionLabel(tech) {
    const base = `${tech.technician_id || ''} — ${tech.name || 'Technician'} · ${departmentLabel(tech)}`;
    return tech.active_case_id ? `${base} · ${tech.active_case_id}` : base;
  }

  // ---------------------------------------------------------------------------
  // SECTION: Find Selected Tech
  // ---------------------------------------------------------------------------
  function findSelectedTech() {
    return (state.technicians || []).find(t => t.technician_id === selectedTechnicianId) || null;
  }

  // ---------------------------------------------------------------------------
  // SECTION: Render Technician Options
  // ---------------------------------------------------------------------------
  function renderTechnicianOptions() {
    if (!technicianSelector || !Array.isArray(state.technicians)) return;
    const previous = selectedTechnicianId || technicianSelector.value;
    technicianSelector.innerHTML = '';
    state.technicians.forEach((tech) => {
      const option = document.createElement('option');
      option.value = tech.technician_id;
      option.textContent = optionLabel(tech);
      technicianSelector.appendChild(option);
    });
    if (state.technicians.some(t => t.technician_id === previous)) {
      technicianSelector.value = previous;
    } else if (state.technicians[0]) {
      technicianSelector.value = state.technicians[0].technician_id;
      selectedTechnicianId = technicianSelector.value;
    }
  }

  // ---------------------------------------------------------------------------
  // SECTION: Render Latest Assignment
  // ---------------------------------------------------------------------------
  function renderLatestAssignment() {
    const latest = state.latestAssignment;
    if (!latest) {
      if (latestAssignmentPill) latestAssignmentPill.hidden = true;
      if (followLatestButton) followLatestButton.disabled = true;
      return;
    }
    if (latestAssignmentPill) latestAssignmentPill.hidden = false;
    if (latestAssignmentText) latestAssignmentText.textContent = `${latest.case_id || 'Case'} → ${latest.technician_id || 'Technician'}`;
    if (followLatestButton) followLatestButton.disabled = false;
  }

  // ---------------------------------------------------------------------------
  // SECTION: Render Selected Technician Context
  // ---------------------------------------------------------------------------
  function renderSelectedTechnicianContext() {
    const tech = findSelectedTech();
    if (!tech) {
      if (technicianTabLabel) technicianTabLabel.textContent = 'Technician View';
      if (selectedTechnicianHeading) selectedTechnicianHeading.textContent = 'Technician';
      if (selectedTechnicianMeta) selectedTechnicianMeta.textContent = 'Select a technician to view the tasks assigned from the Admin interface.';
      if (selectedTechnicianCase) selectedTechnicianCase.hidden = true;
      if (selectedTechnicianEmpty) selectedTechnicianEmpty.hidden = false;
      return;
    }

    if (technicianTabLabel) technicianTabLabel.textContent = `Technician View · ${tech.technician_id}`;
    if (selectedTechnicianHeading) selectedTechnicianHeading.textContent = `${tech.name || 'Technician'} · ${tech.technician_id}`;
    if (selectedTechnicianMeta) {
      const level = tech.technician_level ? `${tech.technician_level} · ` : '';
      selectedTechnicianMeta.textContent = `${level}${departmentLabel(tech)} · ${tech.role || 'Technician'}`;
    }

    if (tech.active_case_id) {
      if (selectedTechnicianCase) selectedTechnicianCase.hidden = false;
      if (selectedTechnicianEmpty) selectedTechnicianEmpty.hidden = true;
      if (selectedCaseId) selectedCaseId.textContent = tech.active_case_id;
      if (selectedCaseIssue) selectedCaseIssue.textContent = tech.active_issue || 'Assigned maintenance task';
      if (selectedCaseStatus) selectedCaseStatus.textContent = tech.active_task_status || 'Assigned';
    } else {
      if (selectedTechnicianCase) selectedTechnicianCase.hidden = true;
      if (selectedTechnicianEmpty) selectedTechnicianEmpty.hidden = false;
    }
  }

  // ---------------------------------------------------------------------------
  // SECTION: Render State
  // ---------------------------------------------------------------------------
  function renderState() {
    renderTechnicianOptions();
    renderLatestAssignment();
    renderSelectedTechnicianContext();
  }

  // ---------------------------------------------------------------------------
  // SECTION: Choose Technician
  // ---------------------------------------------------------------------------
  async function chooseTechnician(technicianId, { reloadFrame = true } = {}) {
    if (!technicianId || !selectUrl) return false;
    const body = new URLSearchParams({ technician_id: technicianId });
    const response = await fetch(selectUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString(),
      cache: 'no-store',
      credentials: 'same-origin'
    });
    const data = await response.json();
    if (!data.ok) throw new Error(data.error || 'Unable to switch technician.');

    selectedTechnicianId = technicianId;
    if (technicianSelector) technicianSelector.value = technicianId;
    renderSelectedTechnicianContext();

    if (reloadFrame && technicianFrame) {
      technicianFrame.src = `${data.url}${data.url.includes('?') ? '&' : '?'}_live=${Date.now()}`;
    }
    return true;
  }

  // ---------------------------------------------------------------------------
  // SECTION: Poll State
  // ---------------------------------------------------------------------------
  async function pollState() {
    if (!stateUrl) return;
    try {
      const response = await fetch(`${stateUrl}${stateUrl.includes('?') ? '&' : '?'}_=${Date.now()}`, {
        cache: 'no-store',
        credentials: 'same-origin'
      });
      const data = await response.json();
      if (!data.ok) return;

      const previousLatestSignature = latestAssignmentSignature;
      state.technicians = data.technicians || [];
      state.latestAssignment = data.latest_assignment || null;
      latestAssignmentSignature = signatureFor(state.latestAssignment);
      renderState();

      // If a fresh assignment arrives for the technician currently on screen,
      // refresh that real technician page once so the new task is visible.
      if (
        activeView === 'technician' &&
        state.latestAssignment &&
        state.latestAssignment.technician_id === selectedTechnicianId &&
        latestAssignmentSignature !== previousLatestSignature &&
        latestAssignmentSignature !== lastTechnicianReloadSignature
      ) {
        lastTechnicianReloadSignature = latestAssignmentSignature;
        if (technicianFrame) technicianFrame.contentWindow?.location.reload();
      }
    } catch (_) {
      // Keep the currently displayed dashboards usable if one poll fails.
    }
  }

  // ---------------------------------------------------------------------------
  // SECTION: Switch View
  // ---------------------------------------------------------------------------
  async function switchView(view) {
    activeView = view;
    tabs.forEach((tab) => {
      const selected = tab.dataset.view === view;
      tab.classList.toggle('is-active', selected);
      tab.setAttribute('aria-selected', selected ? 'true' : 'false');
    });

    if (view === 'admin') {
      adminStage.hidden = false;
      technicianStage.hidden = true;
      adminStage.classList.add('is-active');
      technicianStage.classList.remove('is-active');
      return;
    }

    // Refresh the real DB state just before entering Technician View. If the
    // manager has just assigned a case, default to that exact technician.
    await pollState();
    const latestTech = state.latestAssignment?.technician_id || '';
    if (latestTech) {
      try { await chooseTechnician(latestTech); } catch (_) { /* use current selection */ }
    } else if (selectedTechnicianId) {
      try { await chooseTechnician(selectedTechnicianId, { reloadFrame: false }); } catch (_) { /* keep frame */ }
    }

    adminStage.hidden = true;
    technicianStage.hidden = false;
    adminStage.classList.remove('is-active');
    technicianStage.classList.add('is-active');
  }

  tabs.forEach((tab) => {
    tab.addEventListener('click', () => switchView(tab.dataset.view));
  });

  technicianSelector?.addEventListener('change', async () => {
    try {
      await chooseTechnician(technicianSelector.value);
      await pollState();
    } catch (error) {
      window.alert(error.message || 'Unable to switch technician.');
    }
  });

  followLatestButton?.addEventListener('click', async () => {
    await pollState();
    const latestTech = state.latestAssignment?.technician_id;
    if (!latestTech) return;
    try {
      await chooseTechnician(latestTech);
      renderSelectedTechnicianContext();
    } catch (error) {
      window.alert(error.message || 'Unable to open the latest assigned technician.');
    }
  });

  renderState();
  pollState();
  window.setInterval(pollState, 2500);
})();
