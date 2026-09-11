/**
 * SmartOps Source: assets/js/admin/hitl.js
 * Purpose: Js / Admin: HITL client-side interactions and dynamic UI behaviour.
 * Developer Guide: named functions are separated into searchable sections.
 */

(() => {
  'use strict';

  // ---------------------------------------------------------------------------
  // SECTION: Page Initialization
  // ---------------------------------------------------------------------------
  document.addEventListener('DOMContentLoaded', () => {
    initProfileMenu();
    renderHitlCharts();
    initPendingReviewPage();
    initReviewModal();
    initHitlTrendSummaryModal();
  });


  const valueLabelPlugin = {
    id: 'hitlValueLabels',
    afterDatasetsDraw(chart) {
      const { ctx, chartArea } = chart;
      if (!chartArea) return;

      const clamp = (value, min, max) => Math.max(min, Math.min(max, value));
      const isLine = chart.config.type === 'line';
      const isHorizontalBar = chart.config.type === 'bar' && chart.options.indexAxis === 'y';

      ctx.save();
      ctx.font = '700 12px Arial';
      ctx.textBaseline = 'middle';

      const drawText = (text, x, y, color, align = 'center', baseline = 'middle') => {
        const width = ctx.measureText(text).width;
        if (align === 'left') x = clamp(x, chartArea.left + 4, chartArea.right - width - 4);
        else if (align === 'right') x = clamp(x, chartArea.left + width + 4, chartArea.right - 4);
        else x = clamp(x, chartArea.left + width / 2 + 4, chartArea.right - width / 2 - 4);
        y = clamp(y, chartArea.top + 12, chartArea.bottom - 12);
        ctx.textAlign = align;
        ctx.textBaseline = baseline;
        ctx.fillStyle = color;
        ctx.fillText(text, x, y);
      };

      if (isLine && chart.data.datasets.length >= 3) {
        const pointCount = chart.data.labels.length;
        const offsets = [-24, 24, -12, 12, -36, 36];

        for (let pointIndex = 0; pointIndex < pointCount; pointIndex += 1) {
          const items = chart.data.datasets.map((dataset, datasetIndex) => {
            const element = chart.getDatasetMeta(datasetIndex).data[pointIndex];
            if (!element) return null;
            const point = element.getProps(['x', 'y'], true);
            const rawColor = dataset.borderColor || dataset.backgroundColor || '#111827';
            return {
              value: Number(dataset.data[pointIndex]),
              x: point.x,
              y: point.y,
              color: Array.isArray(rawColor) ? rawColor[pointIndex % rawColor.length] : rawColor
            };
          }).filter(item => item && Number.isFinite(item.value)).sort((a, b) => a.y - b.y);

          items.forEach((item, itemIndex) => {
            const offset = offsets[itemIndex] ?? ((itemIndex % 2 === 0 ? -1 : 1) * (18 + itemIndex * 6));
            drawText(item.value.toLocaleString(), item.x, item.y + offset, item.color);
          });
        }

        ctx.restore();
        return;
      }

      chart.data.datasets.forEach((dataset, datasetIndex) => {
        const meta = chart.getDatasetMeta(datasetIndex);
        if (meta.hidden) return;
        meta.data.forEach((element, index) => {
          const value = Number(dataset.data[index]);
          if (!Number.isFinite(value)) return;
          const point = element.getProps(['x', 'y'], true);
          const rawColor = dataset.borderColor || dataset.backgroundColor || '#111827';
          const color = Array.isArray(rawColor) ? rawColor[index % rawColor.length] : rawColor;
          if (isHorizontalBar) {
            drawText(value.toLocaleString(), point.x + 9, point.y, color, 'left', 'middle');
          } else if (isLine) {
            if (value === 0) return;
            drawText(value.toLocaleString(), point.x, point.y - 14, color, 'center', 'bottom');
          } else {
            drawText(value.toLocaleString(), point.x, point.y - 14, color, 'center', 'bottom');
          }
        });
      });

      ctx.restore();
    }
  };


  const stackedTotalLabelPlugin = {
    id: 'hitlStackedTotalLabels',
    afterDatasetsDraw(chart) {
      const { ctx, chartArea } = chart;
      if (!chartArea || chart.config.type !== 'bar') return;

      const totals = (chart.data.labels || []).map((_, index) =>
        chart.data.datasets.reduce((sum, dataset, datasetIndex) => {
          if (chart.getDatasetMeta(datasetIndex).hidden) return sum;
          return sum + (Number(dataset.data[index]) || 0);
        }, 0)
      );

      ctx.save();
      ctx.font = '800 12px Arial';
      ctx.fillStyle = '#374151';
      ctx.textAlign = 'center';
      ctx.textBaseline = 'bottom';

      totals.forEach((total, index) => {
        if (total <= 0) return;
        let topY = chartArea.bottom;
        let x = null;
        chart.data.datasets.forEach((_, datasetIndex) => {
          const element = chart.getDatasetMeta(datasetIndex).data[index];
          if (!element) return;
          const props = element.getProps(['x', 'y', 'base'], true);
          x = props.x;
          topY = Math.min(topY, props.y, props.base);
        });
        if (x === null) return;
        ctx.fillText(total.toLocaleString(), x, Math.max(chartArea.top + 14, topY - 6));
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
    document.addEventListener('keydown', event => { if (event.key === 'Escape') setOpen(false); });
  }


  // ---------------------------------------------------------------------------
  // SECTION: Render HITL Charts
  // ---------------------------------------------------------------------------
  function renderHitlCharts() {
    if (typeof Chart === 'undefined') return;
    const data = window.hitlDashboardData || {};

    const triggerCanvas = document.getElementById('hitlTriggerChart');
    if (triggerCanvas) {
      new Chart(triggerCanvas, {
        plugins: [valueLabelPlugin],
        type: 'bar',
        data: { labels: data.triggerLabels || [], datasets: [{ label: 'Cases', data: data.triggerValues || [], backgroundColor: ['#991b1b', '#ef4444', '#f59e0b', '#7c3aed'], borderRadius: 8 }] },
        options: {
          indexAxis: 'y', responsive: true, maintainAspectRatio: false, interaction: { mode: 'nearest', intersect: false, axis: 'xy' }, layout: { padding: { left: 8, right: 66, top: 22, bottom: 8 } },
          onHover: (event, elements) => { event.native.target.style.cursor = elements.length ? 'pointer' : 'default'; },
          onClick: (event, elements, chart) => {
            elements = clickedElements(chart, event, elements);
            if (!elements.length || !window.hitlTriggerUrl) return;
            const index = elements[0].index;
            const route = (data.triggerRoutes || [])[index] || chart.data.labels[index];
            window.location.href = `${window.hitlTriggerUrl}?trigger=${encodeURIComponent(route)}`;
          },
          plugins: { legend: { display: false } },
          scales: { x: { beginAtZero: true, grace: '12%', ticks: { precision: 0 } }, y: { grid: { display: false } } }
        }
      });
    }

    const assetCanvas = document.getElementById('hitlAssetChart');
    if (assetCanvas) {
      const sortedAssets = (data.assetLabels || []).map((label, index) => ({
        label,
        value: Number((data.assetValues || [])[index]) || 0
      }));

      new Chart(assetCanvas, {
        plugins: [valueLabelPlugin],
        type: 'bar',
        data: {
          labels: sortedAssets.map(item => item.label),
          datasets: [{ label: 'Cases', data: sortedAssets.map(item => item.value), backgroundColor: '#2563eb', borderRadius: 8 }]
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          interaction: { mode: 'nearest', intersect: false, axis: 'xy' },
          layout: { padding: { left: 8, right: 32, top: 30, bottom: 8 } },
          onHover: (event, elements) => { event.native.target.style.cursor = elements.length ? 'pointer' : 'default'; },
          onClick: (event, elements, chart) => {
            elements = clickedElements(chart, event, elements);
            if (!elements.length || !window.hitlAssetUrl) return;
            const label = chart.data.labels[elements[0].index];
            window.location.href = `${window.hitlAssetUrl}?asset=${encodeURIComponent(label)}`;
          },
          plugins: { legend: { display: false } },
          scales: { y: { beginAtZero: true, grace: '18%', ticks: { precision: 0 } }, x: { grid: { display: false }, ticks: { autoSkip: false, maxRotation: 0, minRotation: 0, font: { size: 11 } } } }
        }
      });
    }

    const trendCanvas = document.getElementById('hitlTrendChart');
    if (trendCanvas) {
      const critical = (data.trendCritical || []).map(value => Number(value) || 0);
      const urgent = (data.trendHigh || []).map(value => Number(value) || 0);
      const lowConfidence = (data.trendLowConfidence || []).map(value => Number(value) || 0);
      const outOfKnowledgeBase = (data.trendOokb || []).map(value => Number(value) || 0);
      const totalByDay = (data.trendLabels || []).map((_, index) =>
        critical[index] + urgent[index] + lowConfidence[index] + outOfKnowledgeBase[index]
      );

      new Chart(trendCanvas, {
        plugins: [valueLabelPlugin],
        type: 'line',
        data: {
          labels: data.trendLabels || [],
          datasets: [{
            label: 'Total HITL Escalations',
            data: totalByDay,
            borderColor: '#dc2626',
            backgroundColor: 'rgba(220, 38, 38, 0.08)',
            fill: true,
            tension: 0.34,
            borderWidth: 3,
            pointBackgroundColor: '#dc2626',
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2,
            pointRadius: context => Number(context.raw) > 0 ? 6 : 3,
            pointHoverRadius: 8,
            pointHitRadius: 18
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          layout: { padding: { top: 24, right: 26, left: 10, bottom: 4 } },
          interaction: { mode: 'index', intersect: false, axis: 'x' },
          onHover: (event, elements, chart) => {
            const points = chart.getElementsAtEventForMode(event, 'index', { intersect: false, axis: 'x' }, true);
            event.native.target.style.cursor = points.length ? 'pointer' : 'default';
          },
          onClick: (event, elements, chart) => {
            const points = chart.getElementsAtEventForMode(event, 'index', { intersect: false, axis: 'x' }, true);
            if (!points.length) return;
            openHitlTrendSummary(points[0].index);
          },
          plugins: {
            legend: { display: false },
            tooltip: {
              enabled: true,
              mode: 'index',
              intersect: false,
              displayColors: false,
              callbacks: {
                label(context) {
                  return `Total HITL escalations: ${Number(context.raw) || 0}`;
                },
                afterBody(items) {
                  if (!items.length) return [];
                  const index = items[0].dataIndex;
                  return [
                    `Immediate: ${critical[index] || 0}`,
                    `Urgent: ${urgent[index] || 0}`,
                    `Low Confidence: ${lowConfidence[index] || 0}`,
                    `Out of Knowledge Base: ${outOfKnowledgeBase[index] || 0}`
                  ];
                }
              }
            }
          },
          scales: {
            y: {
              beginAtZero: true,
              grace: '20%',
              ticks: { precision: 0 }
            },
            x: {
              offset: true,
              grid: { display: false }
            }
          }
        }
      });
    }
  }


  // ---------------------------------------------------------------------------
  // SECTION: Init HITL Trend Summary Modal
  // ---------------------------------------------------------------------------
  function initHitlTrendSummaryModal() {
    const modal = document.getElementById('hitlTrendSummaryModal');
    if (!modal) return;

    const close = () => {
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden', 'true');
    };

    modal.querySelectorAll('.hitl-trend-summary-close, .hitl-trend-summary-close-button')
      .forEach(button => button.addEventListener('click', close));
    modal.addEventListener('click', event => {
      if (event.target === modal) close();
    });
    document.addEventListener('keydown', event => {
      if (event.key === 'Escape' && modal.classList.contains('is-open')) close();
    });
  }

  // ---------------------------------------------------------------------------
  // SECTION: Open HITL Trend Summary
  // ---------------------------------------------------------------------------
  function openHitlTrendSummary(index) {
    const modal = document.getElementById('hitlTrendSummaryModal');
    const subtitle = document.getElementById('hitlTrendSummarySubtitle');
    const grid = document.getElementById('hitlTrendSummaryGrid');
    const note = document.getElementById('hitlTrendSummaryNote');
    if (!modal || !grid) return;

    const data = window.hitlDashboardData || {};
    const date = (data.trendLabels || [])[index] || '-';
    const isoDate = (data.trendDates || [])[index] || '';
    const critical = Number((data.trendCritical || [])[index] || 0);
    const high = Number((data.trendHigh || [])[index] || 0);
    const low = Number((data.trendLowConfidence || [])[index] || 0);
    const ookb = Number((data.trendOokb || [])[index] || 0);
    const total = critical + high + low + ookb;
    const categories = [
      ['Immediate', 'Critical', critical, 'is-immediate'],
      ['Urgent', 'High', high, 'is-urgent'],
      ['Low Confidence', 'Low Confidence', low, 'is-low-confidence'],
      ['Out of Knowledge Base', 'Out of Knowledge Base', ookb, 'is-ookb']
    ];
    const leading = categories.reduce((best, current) => current[2] > best[2] ? current : best, categories[0]);
    const baseUrl = window.hitlDailySummaryUrl || 'daily_summary.php';
    const summaryUrl = queue => `${baseUrl}?date=${encodeURIComponent(isoDate)}&queue=${encodeURIComponent(queue)}`;

    if (subtitle) subtitle.textContent = `${date} escalation overview — select a card to open its summary table`;

    grid.innerHTML = `
      <div class="hitl-summary-overview-band" role="group" aria-label="Daily escalation overview">
        <div class="hitl-summary-overview-item">
          <span>Selected Date</span>
          <strong>${escapeHtml(date)}</strong>
        </div>
        <a class="hitl-summary-overview-item hitl-summary-total is-clickable" href="${summaryUrl('All')}" aria-label="View all ${total} HITL cases for ${escapeHtml(date)}">
          <span>Total HITL Escalations</span>
          <strong>${total.toLocaleString()}</strong>
          <small>View full summary →</small>
        </a>
        <a class="hitl-summary-overview-item is-clickable" href="${summaryUrl(leading[1])}" aria-label="View ${escapeHtml(leading[0])} summary for ${escapeHtml(date)}">
          <span>Largest Escalation Group</span>
          <strong>${escapeHtml(leading[0])}</strong>
          <small>View group summary →</small>
        </a>
      </div>

      <div class="hitl-summary-breakdown-grid" role="group" aria-label="Clickable escalation queue summaries">
        ${categories.map(([label, queue, count, className]) => `
          <a class="hitl-summary-link-card ${className}" href="${summaryUrl(queue)}" aria-label="View ${escapeHtml(label)} summary with ${count} cases">
            <span>${escapeHtml(label)}</span>
            <strong>${count.toLocaleString()}</strong>
            <small>View category summary →</small>
          </a>`).join('')}
      </div>`;

    if (note) {
      note.innerHTML = `<strong>How to use:</strong> Select any queue card to open a category summary table, then choose a maintenance category to view its component breakdown and case list.`;
    }

    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
  }

  // ---------------------------------------------------------------------------
  // SECTION: Init Pending Review Page
  // ---------------------------------------------------------------------------
  function initPendingReviewPage() {
    const table = document.getElementById('pendingReviewTable');
    const cards = document.querySelectorAll('[data-case-filter]');
    const count = document.getElementById('pendingReviewCount');
    if (!table || !cards.length) return;
    let active = '';
    const apply = () => {
      let visible = 0;
      table.querySelectorAll('tbody tr').forEach(row => {
        const matches = !active || (row.dataset.caseTypes || '').split(/\s+/).includes(active);
        row.hidden = !matches;
        if (matches) visible += 1;
      });
      if (count) count.textContent = `${visible.toLocaleString()} case(s) waiting for manager review.`;
      cards.forEach(card => card.classList.toggle('is-active', card.dataset.caseFilter === active));
    };
    cards.forEach(card => card.addEventListener('click', () => {
      const selected = card.dataset.caseFilter || '';
      active = active === selected ? '' : selected;
      apply();
    }));
  }


  // ---------------------------------------------------------------------------
  // SECTION: Init Review Modal
  // ---------------------------------------------------------------------------
  function initReviewModal() {
    const modal = document.getElementById('reviewModal');
    const grid = document.getElementById('reviewDetailGrid');
    const form = document.getElementById('reviewAssignmentForm');
    const caseInput = document.getElementById('reviewCaseId');
    const triggerInput = document.getElementById('reviewEscalationTrigger');
    const select = document.getElementById('technicianSelect');
    if (!modal || !grid) return;

    const closeModal = () => {
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden', 'true');
    };
    document.querySelectorAll('.review-modal-close, .review-modal-close-button').forEach(button => button.addEventListener('click', closeModal));
    modal.addEventListener('click', event => { if (event.target === modal) closeModal(); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape') closeModal(); });

    document.querySelectorAll('[data-review]').forEach(button => {
      button.addEventListener('click', () => {
        let detail = {};
        try { detail = JSON.parse(button.dataset.review || '{}'); } catch (_) { detail = {}; }
        const fields = detail.isRestricted || detail.isOokb
          ? [
              ['Case ID', detail.caseId],
              ['Room', detail.room],
              ['Issue', detail.issue],
              ['HITL Reason', detail.trigger || detail.reason || 'Human Review Required'],
              ['Review Status', 'Pending Review']
            ]
          : [
              ['Case ID', detail.caseId],
              ['Room', detail.room],
              ['Hotel Asset', detail.asset],
              ['Component', detail.component],
              ['Severity', detail.severity],
              ['Priority', detail.priority],
              ['AI Confidence', detail.confidence],
              ['Safety Status', detail.safety],
              ['Issue', detail.issue],
              ['Failure Mode', detail.failureMode]
            ];
        grid.innerHTML = fields.map(([label, value]) => `<div><span>${escapeHtml(label)}</span><strong>${escapeHtml(value || '-')}</strong></div>`).join('');
        if (caseInput) caseInput.value = detail.caseId || '';
        if (triggerInput) triggerInput.value = detail.trigger || (detail.isOokb ? 'Out of Knowledge Base' : '');
        if (select) populateTechnicians(select, detail.assetRaw || detail.asset || '');
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
      });
    });

    if (form) form.addEventListener('submit', event => {
      if (select && !select.value) {
        event.preventDefault();
        select.focus();
      }
    });
  }

  // ---------------------------------------------------------------------------
  // SECTION: Populate Technicians
  // ---------------------------------------------------------------------------
  function populateTechnicians(select, asset) {
    const technicians = Array.isArray(window.hitlTechnicians) ? window.hitlTechnicians : [];
    const normalizeDepartment = value => {
      const clean = String(value || '').replace(/^D\d+\s+/, '').trim().toLowerCase();
      if (['fire', 'fire protection', 'fire system'].includes(clean)) return 'fire protection';
      if (['elevator', 'conveying', 'elevator & lifts', 'lifts'].includes(clean)) return 'conveying';
      if (['hitl', 'technical specialist', 'out of knowledge base'].includes(clean)) return 'technical specialist';
      return clean;
    };
    const target = normalizeDepartment(asset);
    const sameDepartment = technicians.filter(tech => normalizeDepartment(tech.department || tech.category) === target);
    const specialists = sameDepartment.filter(tech => String(tech.role || '').toLowerCase() === 'technical specialist');
    const allSpecialists = technicians.filter(tech => String(tech.role || '').toLowerCase() === 'technical specialist');
    const list = specialists.length ? specialists : allSpecialists;
    select.innerHTML = '<option value="">Select technician</option>' + list.map(tech => {
      const name = tech.name || tech.technician_id;
      const status = tech.status || 'Available';
      const active = tech.active_tasks || '0';
      const role = String(tech.role || '').toLowerCase() === 'technical specialist' ? 'HITL Technician' : 'AI Automation Technician';
      return `<option value="${escapeHtml(tech.technician_id)}">${escapeHtml(name)} (${escapeHtml(tech.technician_id)}) — ${escapeHtml(role)}, ${escapeHtml(status)}, ${escapeHtml(active)}/1 active</option>`;
    }).join('');
  }

  // ---------------------------------------------------------------------------
  // SECTION: Escape Html
  // ---------------------------------------------------------------------------
  function escapeHtml(value) {
    return String(value).replace(/[&<>'"]/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[char]));
  }
})();
