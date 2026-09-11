/**
 * SmartOps Source: assets/js/admin/technicians.js
 * Purpose: Js / Admin: Technicians client-side interactions and dynamic UI behaviour.
 * Developer Guide: named functions are separated into searchable sections.
 */

(() => {
  'use strict';

  // ---------------------------------------------------------------------------
  // SECTION: Page Initialization
  // ---------------------------------------------------------------------------
  document.addEventListener('DOMContentLoaded', () => {
    initProfileMenu();
    initTechnicianFilters();
    initTechnicianViewModal();
    initTechnicianFormModal();
    initTechnicianDeactivateModal();
  });

  // ---------------------------------------------------------------------------
  // SECTION: Init Profile Menu
  // ---------------------------------------------------------------------------
  function initProfileMenu() {
    const button = document.querySelector('[data-profile-toggle]');
    const dropdown = document.getElementById('profileDropdown');
    if (!button || !dropdown) return;

    const setOpen = open => {
      dropdown.classList.toggle('is-open', open);
      button.setAttribute('aria-expanded', open ? 'true' : 'false');
    };

    button.addEventListener('click', event => {
      event.stopPropagation();
      setOpen(!dropdown.classList.contains('is-open'));
    });
    dropdown.addEventListener('click', event => event.stopPropagation());
    document.addEventListener('click', () => setOpen(false));
    document.addEventListener('keydown', event => {
      if (event.key === 'Escape') setOpen(false);
    });
  }

  // ---------------------------------------------------------------------------
  // SECTION: Init Technician Filters
  // ---------------------------------------------------------------------------
  function initTechnicianFilters() {
    const table = document.getElementById('technicianManagementTable');
    const search = document.getElementById('technicianSearch');
    const department = document.getElementById('departmentFilter');
    const role = document.getElementById('roleFilter');
    const level = document.getElementById('levelFilter');
    const reset = document.getElementById('resetTechnicianFilters');
    const resultCount = document.getElementById('technicianResultCount');
    if (!table || !search || !department || !role || !level) return;

    const rows = Array.from(table.querySelectorAll('tbody tr[data-technician-id]'));
    const apply = () => {
      const term = search.value.trim().toLowerCase();
      let visible = 0;
      rows.forEach(row => {
        const matchesSearch = !term || String(row.dataset.search || '').includes(term);
        const matchesDepartment = !department.value || row.dataset.department === department.value;
        const matchesRole = !role.value || row.dataset.role === role.value;
        const matchesLevel = !level.value || row.dataset.level === level.value;
        const show = matchesSearch && matchesDepartment && matchesRole && matchesLevel;
        row.hidden = !show;
        if (show) visible += 1;
      });
      if (resultCount) resultCount.textContent = `${visible.toLocaleString()} technician record(s)`;
    };

    [search, department, role, level].forEach(control => {
      control.addEventListener(control === search ? 'input' : 'change', apply);
    });

    reset?.addEventListener('click', () => {
      search.value = '';
      department.value = '';
      role.value = '';
      level.value = '';
      apply();
      search.focus();
    });
  }

  // ---------------------------------------------------------------------------
  // SECTION: Init Technician View Modal
  // ---------------------------------------------------------------------------
  function initTechnicianViewModal() {
    const modal = document.getElementById('technicianSelectModal');
    const grid = document.getElementById('selectedTechnicianGrid');
    const buttons = document.querySelectorAll('[data-view-technician]');
    if (!modal || !grid) return;
    const records = (window.technicianManagementData || {}).records || {};

    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[char]));
    const setOpen = open => {
      modal.classList.toggle('is-open', open);
      modal.setAttribute('aria-hidden', open ? 'false' : 'true');
      document.body.style.overflow = open ? 'hidden' : '';
    };

    buttons.forEach(button => button.addEventListener('click', () => {
      const record = records[button.dataset.viewTechnician];
      if (!record) return;
      const values = [
        ['Technician ID', record.technician_id],
        ['Full Name', record.name || '-'],
        ['Department', record.department || '-'],
        ['Technician Type', record.technician_type || '-'],
        ['Technician Level', record.technician_level || 'Junior'],
        ['Email', record.email || '-'],
        ['Phone', record.phone || '-'],
        ['Account Status', record.account_status || '-']
      ];
      grid.innerHTML = values.map(([label, value]) => `<div><span>${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong></div>`).join('');
      setOpen(true);
    }));

    modal.querySelectorAll('.management-modal-close, [data-close-modal]').forEach(button => button.addEventListener('click', () => setOpen(false)));
    modal.addEventListener('click', event => { if (event.target === modal) setOpen(false); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && modal.classList.contains('is-open')) setOpen(false); });
  }

  // ---------------------------------------------------------------------------
  // SECTION: Init Technician Form Modal
  // ---------------------------------------------------------------------------
  function initTechnicianFormModal() {
    const modal = document.getElementById('technicianFormModal');
    const form = document.getElementById('technicianForm');
    if (!modal || !form) return;

    const data = window.technicianManagementData || {};
    const records = data.records || {};
    const openAdd = document.querySelector('[data-open-add]');
    const editButtons = document.querySelectorAll('[data-edit-technician]');
    const title = document.getElementById('technicianFormTitle');
    const subtitle = document.getElementById('technicianFormSubtitle');
    const action = document.getElementById('technicianFormAction');
    const technicianId = document.getElementById('formTechnicianId');
    const name = document.getElementById('formName');
    const department = document.getElementById('formDepartment');
    const role = document.getElementById('formRole');
    const technicianLevel = document.getElementById('formTechnicianLevel');
    const phone = document.getElementById('formPhone');
    const email = document.getElementById('formEmail');
    const password = document.getElementById('formPassword');
    const passwordHelp = document.getElementById('passwordHelp');
    const submit = document.getElementById('technicianFormSubmit');

    const syncRoleFields = () => {
      const isHitl = role.value === 'Technical Specialist';
      if (isHitl) department.value = 'All Departments';
      department.disabled = isHitl;
      department.required = !isHitl;
    };
    role.addEventListener('change', syncRoleFields);
    form.addEventListener('submit', () => { department.disabled = false; });

    const setOpen = open => {
      modal.classList.toggle('is-open', open);
      modal.setAttribute('aria-hidden', open ? 'false' : 'true');
      document.body.style.overflow = open ? 'hidden' : '';
      if (open) window.setTimeout(() => name?.focus(), 50);
    };

    const prepareAdd = () => {
      form.reset();
      action.value = 'add';
      technicianId.value = data.nextId || '';
      technicianId.readOnly = false;
      role.value = 'Technician';
      technicianLevel.value = 'Junior';
      syncRoleFields();
      title.textContent = 'Add Technician';
      subtitle.textContent = 'Create a technician account and administrative profile.';
      password.placeholder = 'Leave blank to use the Technician ID as the default password';
      passwordHelp.textContent = 'A default password is created from the Technician ID when blank.';
      submit.textContent = 'Add Technician';
      setOpen(true);
    };

    const prepareEdit = id => {
      const record = records[id];
      if (!record || !record.is_active) return;
      form.reset();
      action.value = 'update';
      technicianId.value = record.technician_id || id;
      technicianId.readOnly = true;
      name.value = record.name || '';
      department.value = record.department || '';
      role.value = record.role || 'Technician';
      technicianLevel.value = record.technician_level || 'Junior';
      syncRoleFields();
      phone.value = record.phone || '';
      email.value = record.email || '';
      password.value = '';
      password.placeholder = 'Leave blank to keep the current password';
      passwordHelp.textContent = Number(record.active_tasks || 0) > 0
        ? 'Department and technician type cannot change while this technician has an active assignment.'
        : 'Leave blank to keep the current password.';
      title.textContent = `Edit ${record.technician_id}`;
      subtitle.textContent = 'Update the technician master record and account details.';
      submit.textContent = 'Save Changes';
      setOpen(true);
    };

    openAdd?.addEventListener('click', prepareAdd);
    editButtons.forEach(button => button.addEventListener('click', () => prepareEdit(button.dataset.editTechnician)));
    modal.querySelectorAll('.management-modal-close, [data-close-modal]').forEach(button => button.addEventListener('click', () => setOpen(false)));
    modal.addEventListener('click', event => { if (event.target === modal) setOpen(false); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && modal.classList.contains('is-open')) setOpen(false); });
  }

  // ---------------------------------------------------------------------------
  // SECTION: Init Technician Deactivate Modal
  // ---------------------------------------------------------------------------
  function initTechnicianDeactivateModal() {
    const modal = document.getElementById('technicianDeactivateModal');
    const buttons = document.querySelectorAll('[data-deactivate-technician]');
    const input = document.getElementById('deactivateTechnicianId');
    const summary = document.getElementById('technicianDeactivateSummary');
    if (!modal || !input || !summary) return;

    const records = (window.technicianManagementData || {}).records || {};
    const setOpen = open => {
      modal.classList.toggle('is-open', open);
      modal.setAttribute('aria-hidden', open ? 'false' : 'true');
      document.body.style.overflow = open ? 'hidden' : '';
    };

    buttons.forEach(button => button.addEventListener('click', () => {
      const id = button.dataset.deactivateTechnician;
      const record = records[id] || {};
      input.value = id;
      summary.textContent = `${record.name || id} (${id}) will be moved to Deactivated records. Any active assignment will be safely released for reassignment, while historical records remain preserved.`;
      setOpen(true);
    }));

    modal.querySelectorAll('.management-modal-close, [data-close-modal]').forEach(button => button.addEventListener('click', () => setOpen(false)));
    modal.addEventListener('click', event => { if (event.target === modal) setOpen(false); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && modal.classList.contains('is-open')) setOpen(false); });
  }
})();
