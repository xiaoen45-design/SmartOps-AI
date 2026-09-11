/**
 * SmartOps Source: assets/js/admin/workforce.js
 * Purpose: Js / Admin: Workforce client-side interactions and dynamic UI behaviour.
 * Developer Guide: named functions are separated into searchable sections.
 */

(() => {
  'use strict';

  // ---------------------------------------------------------------------------
  // SECTION: Page Initialization
  // ---------------------------------------------------------------------------
  document.addEventListener('DOMContentLoaded', () => {
    initProfileMenu();
    initSupportActionModal();
    renderWorkforceCharts();
  });


  const valueLabelPlugin = {
      id: 'workforceValueLabels',
      afterDatasetsDraw(chart) {
        const { ctx, chartArea } = chart;
        if (!chartArea) return;
        const isLine = chart.config.type === 'line';
        const isHorizontal = chart.config.type === 'bar' && chart.options.indexAxis === 'y';
        const clamp = (v, min, max) => Math.max(min, Math.min(max, v));
        ctx.save();
        ctx.font = '700 12px Arial';
        ctx.textBaseline = 'middle';
        chart.data.datasets.forEach((dataset, datasetIndex) => {
          const meta = chart.getDatasetMeta(datasetIndex);
          if (meta.hidden) return;
          meta.data.forEach((element, index) => {
            const value = Number(dataset.data[index]);
            if (!Number.isFinite(value)) return;
            const point = element.getProps(['x','y'], true);
            const raw = dataset.borderColor || dataset.backgroundColor || '#111827';
            const color = Array.isArray(raw) ? raw[index % raw.length] : raw;
            const text = value.toLocaleString();
            const width = ctx.measureText(text).width;
            let x = point.x, y = point.y - 18, align = 'center';
            if (isHorizontal) { x = point.x + 9; y = point.y; align = 'left'; }
            else if (isLine) {
              if (chart.data.datasets.length === 2) {
                y = point.y - (datasetIndex === 0 ? 20 : 20);
              } else {
                if (datasetIndex === 0) y = point.y - 24;
                else if (datasetIndex === 1) y = point.y + 24;
                else { x = point.x + (index === chart.data.labels.length - 1 ? -22 : 22); y = point.y - 10; align = index === chart.data.labels.length - 1 ? 'right' : 'left'; }
              }
            }
            if (align === 'center') x = clamp(x, chartArea.left + width/2 + 4, chartArea.right - width/2 - 4);
            else if (align === 'left') x = clamp(x, chartArea.left + 4, chartArea.right - width - 4);
            else x = clamp(x, chartArea.left + width + 4, chartArea.right - 4);
            y = clamp(y, chartArea.top + 12, chartArea.bottom - 12);
            ctx.textAlign = align; ctx.fillStyle = color; ctx.fillText(text, x, y);
          });
        });
        ctx.restore();
      }
  };

  // ---------------------------------------------------------------------------
  // SECTION: Clicked Elements
  // ---------------------------------------------------------------------------
  function clickedElements(chart, event, elements) {
    if (elements && elements.length) return elements;
    if (!chart || !event) return [];
    return chart.getElementsAtEventForMode(
      event,
      'nearest',
      { intersect: false, axis: 'xy' },
      false
    );
  }

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
  // SECTION: Open With Query
  // ---------------------------------------------------------------------------
  function openWithQuery(base, key, value) {
    if (!base || !value) return;
    window.location.href = `${base}?${key}=${encodeURIComponent(value)}`;
  }

  // ---------------------------------------------------------------------------
  // SECTION: Init Support Action Modal
  // ---------------------------------------------------------------------------
  function initSupportActionModal() {
    const data = window.supportActionData || {};
    const modal = document.getElementById('supportActionModal');
    const form = document.getElementById('supportActionForm');

    if (!modal || !form) return;

    const caseGrid = document.getElementById('supportCaseGrid');
    const caseIdInput = document.getElementById('supportCaseId');
    const actionTypeInput = document.getElementById('supportActionType');
    const managerActionSelect = document.getElementById('managerActionSelect');
    const seniorFields = document.getElementById('seniorFields');
    const seniorSelect = document.getElementById('seniorTechnicianSelect');
    const partsFields = document.getElementById('partsFields');
    const outsourcingFields = document.getElementById('outsourcingFields');
    const requestedPartValue = document.getElementById('requestedPartValue');
    const requestedPartsStatus = document.getElementById('requestedPartsStatus');
    const requestedExternalService = document.getElementById('requestedExternalService');
    const requestedOutsourcingReason = document.getElementById('requestedOutsourcingReason');

    // SECTION: Support Modal HTML Escaping
    const escapeHtml = value => String(value ?? '-').replace(
      /[&<>'"]/g,
      character => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        "'": '&#039;',
        '"': '&quot;'
      }[character])
    );

    // SECTION: Manager Action Options
    const managerActionOptions = (item, actionType) => {
      if (actionType === 'Senior Support') {
        return [['assign_senior', 'Assign Senior']];
      }

      if (actionType === 'Parts Required') {
        const partsStatus = String(item.parts_status || '').toLowerCase();

        if (partsStatus === 'ordered') {
          return [['mark_parts_ready', 'Mark Parts Ready']];
        }
        if (partsStatus === 'ready') {
          return [['reassign_parts', 'Reassign Technician']];
        }

        return [
          ['use_stock', 'Use Stock'],
          ['parts_ordered', 'Parts Ordered']
        ];
      }

      const outsourcingStatus = String(item.outsourcing_status || '').toLowerCase();

      if (outsourcingStatus === 'approved') {
        return [['complete_outsourcing', 'Mark Outsourced Work Completed']];
      }
      if (outsourcingStatus === 'rejected') {
        return [['reassign_outsourcing', 'Retry Internal Reassignment']];
      }

      return [
        ['approve_outsourcing', 'Approve Outsourcing'],
        ['reject_outsourcing', 'Reject & Reassign']
      ];
    };

    // SECTION: Open Support Modal
    const openModal = (caseId, actionType) => {
      const item = data.cases?.[caseId];
      if (!item) return;

      caseIdInput.value = caseId;
      actionTypeInput.value = actionType;
      seniorFields.hidden = actionType !== 'Senior Support';
      partsFields.hidden = actionType !== 'Parts Required';
      outsourcingFields.hidden = actionType !== 'Outsourcing';

      managerActionSelect.innerHTML = managerActionOptions(item, actionType)
        .map(([value, label]) => (
          `<option value="${escapeHtml(value)}">${escapeHtml(label)}</option>`
        ))
        .join('');

      const caseSummary = [
        ['Case ID', item.case_id],
        ['Room', item.room],
        ['Hotel Asset', item.hotel_asset],
        ['Component', item.component],
        ['Severity', item.severity],
        ['Priority', item.priority],
        ['Previous Technician', item.previous_technician_id || 'Unassigned'],
        ['Issue', item.issue],
        ['SLA Status', item.sla_status]
      ];

      caseGrid.innerHTML = caseSummary
        .map(([label, value]) => (
          `<div class="support-case-item">`
          + `<span>${escapeHtml(label)}</span>`
          + `<strong>${escapeHtml(value)}</strong>`
          + `</div>`
        ))
        .join('');

      if (actionType === 'Senior Support') {
        const seniorOptions = (item.eligible_seniors || [])
          .map(technician => (
            `<option value="${escapeHtml(technician.technician_id)}">`
            + `${escapeHtml(technician.name)} (${escapeHtml(technician.technician_id)}) — `
            + `${escapeHtml(technician.role)}, ${escapeHtml(technician.level)} — `
            + `${Number(technician.active_tasks || 0)}/1 active`
            + `</option>`
          ))
          .join('');

        seniorSelect.innerHTML = '<option value="">Select a senior technician</option>' + seniorOptions;
        seniorSelect.required = true;
      } else {
        seniorSelect.required = false;
      }

      if (actionType === 'Parts Required') {
        requestedPartValue.textContent = item.requested_part || 'Not provided';
        requestedPartsStatus.textContent = item.parts_status || 'Requested';
      }

      if (actionType === 'Outsourcing') {
        requestedExternalService.textContent = item.required_external_service || 'Not provided';
        requestedOutsourcingReason.textContent = item.outsourcing_reason || 'Not provided';
      }

      modal.classList.add('is-open');
      modal.setAttribute('aria-hidden', 'false');
      document.body.classList.add('modal-open');
    };

    // SECTION: Close Support Modal
    const closeModal = () => {
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('modal-open');
      form.reset();
    };

    document.querySelectorAll('.support-open-btn').forEach(button => {
      button.addEventListener('click', () => {
        openModal(button.dataset.caseId, button.dataset.actionType);
      });
    });

    modal.querySelectorAll('.support-modal-close, .support-cancel-btn').forEach(button => {
      button.addEventListener('click', closeModal);
    });

    modal.addEventListener('click', event => {
      if (event.target === modal) closeModal();
    });

    document.addEventListener('keydown', event => {
      if (event.key === 'Escape' && modal.classList.contains('is-open')) {
        closeModal();
      }
    });
  }

  // ---------------------------------------------------------------------------
  // SECTION: Render Workforce Charts
  // ---------------------------------------------------------------------------
  function renderWorkforceCharts() {
    const data = window.workforceDashboardData || {};
    const statusCanvas = document.getElementById('workOrderStatusChart');
    const capacityCanvas = document.getElementById('capacityWorkloadChart');
    const slaDepartmentCanvas = document.getElementById('slaDepartmentChart');
    if (!statusCanvas || typeof Chart === 'undefined') return;

    new Chart(statusCanvas, {
      plugins: [valueLabelPlugin],
      type: 'bar',
      data: {
        labels: data.statusLabels || [],
        datasets: [{
          label: 'Cases',
          data: data.statusValues || [],
          backgroundColor: '#2563eb',
          borderRadius: 8
        }]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'nearest', intersect: false, axis: 'xy' },
        layout: { padding: { left: 8, right: 56, top: 18, bottom: 8 } },
        onHover: (event, elements) => {
          event.native.target.style.cursor = elements.length ? 'pointer' : 'default';
        },
        onClick: (event, elements, chart) => {
          elements = clickedElements(chart, event, elements);
          if (!elements.length) return;
          const label = chart.data.labels[elements[0].index];
          openWithQuery(data.statusDetailUrl, 'status', label);
        },
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { afterLabel: () => 'Click to view hotel asset summary' } }
        },
        scales: {
          x: { beginAtZero: true, grace: '12%', ticks: { precision: 0 } },
          y: { grid: { display: false } }
        }
      }
    });

    if (capacityCanvas) {
      new Chart(capacityCanvas, {
        plugins: [valueLabelPlugin],
        type: 'bar',
        data: {
          labels: data.capacityLabels || [],
          datasets: [
            { label: 'Available Technicians', data: data.availableTech || [], backgroundColor: '#16a34a', borderRadius: 8 },
            { label: 'Assigned Workload', data: data.assignedTask || [], backgroundColor: '#2563eb', borderRadius: 8 }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: { mode: 'nearest', intersect: false, axis: 'xy' },
          layout: { padding: { left: 8, right: 28, top: 14, bottom: 0 } },
          onHover: (event, elements) => {
            event.native.target.style.cursor = elements.length ? 'pointer' : 'default';
          },
          onClick: (event, elements, chart) => {
            elements = clickedElements(chart, event, elements);
            if (!elements.length) return;
            const label = chart.data.labels[elements[0].index];
            openWithQuery(data.capacityDetailUrl, 'group', label);
          },
          plugins: {
            legend: {
              position: 'bottom',
              align: 'center',
              labels: {
                boxWidth: 18,
                boxHeight: 8,
                padding: 10,
                font: { size: 11, weight: '600' }
              }
            },
            tooltip: { callbacks: { afterBody: () => 'Click to inspect related workload cases.' } }
          },
          scales: {
            y: { beginAtZero: true, grace: '14%', ticks: { precision: 0 } },
            x: {
              grid: { display: false },
              ticks: {
                autoSkip: false,
                maxRotation: 0,
                minRotation: 0,
                callback(value) {
                  const label = this.getLabelForValue(value);
                  return label === 'Technical Specialist' ? ['Technical', 'Specialist'] : label;
                }
              }
            }
          }
        }
      });
    }

    if (slaDepartmentCanvas) {
      new Chart(slaDepartmentCanvas, {
        plugins: [valueLabelPlugin],
        type: 'bar',
        data: {
          labels: data.slaDepartmentLabels || [],
          datasets: [{
            label: 'SLA Breaches',
            data: data.slaDepartmentValues || [],
            backgroundColor: '#2563eb',
            borderRadius: 8
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: { mode: 'nearest', intersect: false, axis: 'xy' },
          layout: { padding: { left: 8, right: 28, top: 28, bottom: 12 } },
          onHover: (event, elements) => {
            event.native.target.style.cursor = elements.length ? 'pointer' : 'default';
          },
          onClick: (event, elements, chart) => {
            elements = clickedElements(chart, event, elements);
            if (!elements.length) return;
            const label = chart.data.labels[elements[0].index];
            if (!data.slaDepartmentDetailUrl) return;
            const url = new URL(data.slaDepartmentDetailUrl, window.location.origin);
            url.searchParams.set('department', label);
            url.searchParams.set('scope', data.slaDepartmentScope || 'current');
            window.location.href = url.toString();
          },
          plugins: {
            legend: { display: false },
            tooltip: { callbacks: { afterLabel: () => 'Click to analyse breached work orders' } }
          },
          scales: {
            y: { beginAtZero: true, grace: '14%', ticks: { precision: 0 } },
            x: {
              grid: { display: false },
              ticks: {
                autoSkip: false,
                maxRotation: 0,
                minRotation: 0,
                callback(value) {
                  const label = this.getLabelForValue(value);
                  return label === 'Out of Knowledge Base' ? ['Out of Knowledge', 'Base'] : label;
                }
              }
            }
          }
        }
      });
    }
  }
})();
