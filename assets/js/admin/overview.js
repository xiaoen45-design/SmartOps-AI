/**
 * SmartOps Source: assets/js/admin/overview.js
 * Purpose: Js / Admin: Overview client-side interactions and dynamic UI behaviour.
 * Developer Guide: named functions are separated into searchable sections.
 */

(() => {
  'use strict';

  // ---------------------------------------------------------------------------
  // SECTION: Page Initialization
  // ---------------------------------------------------------------------------
  document.addEventListener('DOMContentLoaded', () => {
    initProfileMenu();
    initCardLinks();
    initOverviewPage();
  });

  const API = {
    smart: '../api/smart_maintenance_csv.php',
    hitl: '../api/hitl_csv.php',
    workforce: '../api/workforce_csv.php',
    technicians: '../api/technicians_csv.php'
  };

  const COLORS = {
    blue: '#2563eb',
    green: '#16a34a',
    teal: '#14b8a6',
    gray: '#9ca3af',
    slate: '#6b7280'
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
  // SECTION: Init Card Links
  // ---------------------------------------------------------------------------
  function initCardLinks() {
    document.querySelectorAll('[data-card-link]').forEach(card => {
      const target = card.dataset.cardLink;
      if (!target) return;
      const open = () => { window.location.href = target; };
      card.addEventListener('click', open);
      card.addEventListener('keydown', event => {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          open();
        }
      });
    });
  }

  // ---------------------------------------------------------------------------
  // SECTION: Plain Value Label Plugin
  // ---------------------------------------------------------------------------
  function plainValueLabelPlugin(id) {
    return {
      id,
      afterDatasetsDraw(chart) {
        const { ctx, chartArea } = chart;
        if (!chartArea) return;

        const isLine = chart.config.type === 'line';
        const isHorizontal = chart.config.type === 'bar' && chart.options.indexAxis === 'y';
        const clamp = (value, min, max) => Math.max(min, Math.min(max, value));

        ctx.save();
        ctx.font = isLine ? '700 11px Arial' : '700 11px Arial';

        const drawLabel = (text, x, y, color, align = 'center', baseline = 'middle') => {
          const width = ctx.measureText(text).width;
          if (align === 'left') x = clamp(x, chartArea.left + 4, chartArea.right - width - 4);
          else if (align === 'right') x = clamp(x, chartArea.left + width + 4, chartArea.right - 4);
          else x = clamp(x, chartArea.left + width / 2 + 4, chartArea.right - width / 2 - 4);
          y = clamp(y, chartArea.top + 14, chartArea.bottom - 14);
          ctx.textAlign = align;
          ctx.textBaseline = baseline;
          ctx.fillStyle = color;
          ctx.fillText(text, x, y);
        };

        chart.data.datasets.forEach((dataset, datasetIndex) => {
          const meta = chart.getDatasetMeta(datasetIndex);
          if (meta.hidden) return;

          meta.data.forEach((element, index) => {
            const value = Number(dataset.data[index]);
            if (!Number.isFinite(value)) return;
            if (isLine && value === 0) return;

            const point = element.getProps(['x', 'y'], true);
            const rawColor = dataset.borderColor || dataset.backgroundColor || '#111827';
            const color = Array.isArray(rawColor) ? rawColor[index % rawColor.length] : rawColor;
            const label = value.toLocaleString();

            if (isHorizontal) {
              drawLabel(label, point.x + 9, point.y, color, 'left', 'middle');
              return;
            }

            if (isLine) {
              if (value === 0) return;
              const pointRadius = Number(dataset.pointRadius) || 4;
              const offsetY = datasetIndex === 0 ? (pointRadius + 8) : -(pointRadius + 8);
              const baseline = datasetIndex === 0 ? 'top' : 'bottom';
              drawLabel(label, point.x, point.y + offsetY, color, 'center', baseline);
              return;
            }

            drawLabel(label, point.x, point.y - 10, color, 'center', 'bottom');
          });
        });

        ctx.restore();
      }
    };
  }

  // ---------------------------------------------------------------------------
  // SECTION: Init Overview Page
  // ---------------------------------------------------------------------------
  async function initOverviewPage() {
    try {
      const [smartRows, hitlRows, workforceRows, technicianRows] = await Promise.all([
        loadCSV(API.smart),
        loadCSV(API.hitl),
        loadCSV(API.workforce),
        loadCSV(API.technicians)
      ]);

      const tickets = smartRows.map(normalizeSmartTicket).filter(ticket => ticket.caseId || ticket.createdDate);
      const hitlCases = hitlRows.map(normalizeHitlCase);
      const workOrders = workforceRows.map(normalizeWorkOrder);
      const technicians = normalizeTechnicians(technicianRows, workOrders);
      const page = document.querySelector('[data-overview-page]')?.dataset.overviewPage || 'dashboard';

      if (page === 'trend-cases-detail') {
        renderTrendCasesDetail(tickets);
        return;
      }

      if (page === 'technician-capacity-detail') {
        renderTechnicianCapacityDetail(technicians);
        return;
      }

      renderOverviewKPIs(tickets, hitlCases, workOrders);
      renderComplaintCompletedTrend(tickets);
      renderTechnicianCapacity(technicians);
      renderAssetDistribution(tickets);
    } catch (error) {
      console.error(error);
      alert('Unable to load overview dashboard data. Please check the database connection.');
    }
  }

  // ---------------------------------------------------------------------------
  // SECTION: Load CSV
  // ---------------------------------------------------------------------------
  async function loadCSV(path) {
    const response = await fetch(path, { cache: 'no-store' });
    if (!response.ok) return [];

    const text = await response.text();
    if (!text.trim() || /^\s*</.test(text)) return [];

    const lines = text.trim().split(/\r?\n/);
    const headers = splitCSVLine(lines.shift()).map(header => header.trim());

    return lines.filter(Boolean).map(line => {
      const values = splitCSVLine(line);
      const row = {};
      headers.forEach((header, index) => {
        row[header] = values[index] ? values[index].trim() : '';
      });
      return row;
    });
  }

  // ---------------------------------------------------------------------------
  // SECTION: Split Csvline
  // ---------------------------------------------------------------------------
  function splitCSVLine(line) {
    const values = [];
    let current = '';
    let insideQuotes = false;

    for (let index = 0; index < line.length; index += 1) {
      const character = line[index];
      const next = line[index + 1];

      if (character === '"' && insideQuotes && next === '"') {
        current += '"';
        index += 1;
      } else if (character === '"') {
        insideQuotes = !insideQuotes;
      } else if (character === ',' && !insideQuotes) {
        values.push(current);
        current = '';
      } else {
        current += character;
      }
    }

    values.push(current);
    return values;
  }

  // ---------------------------------------------------------------------------
  // SECTION: Normalize Priority
  // ---------------------------------------------------------------------------
  function normalizePriority(value) {
    const text = String(value || '').trim().toLowerCase().replace(/[_-]+/g, ' ');
    if (/\bp1\b/.test(text) || text.includes('immediate') || text.includes('critical')) return 'Immediate';
    if (/\bp2\b/.test(text) || text.includes('urgent') || text.includes('high')) return 'Urgent';
    if (/\bp3\b/.test(text) || text.includes('standard') || text.includes('medium')) return 'Standard';
    if (/\bp4\b/.test(text) || text.includes('low')) return 'Low';
    return '-';
  }

  // ---------------------------------------------------------------------------
  // SECTION: Normalize Status
  // ---------------------------------------------------------------------------
  function normalizeStatus(value) {
    const text = String(value || '').trim().toLowerCase();
    if (text.includes('complete') || text.includes('resolved')) return 'Completed';
    if (text.includes('progress') || text.includes('processing')) return 'In Progress';
    if (text.includes('assigned')) return 'Assigned';
    if (text.includes('pending') || text.includes('review')) return 'Pending';
    return value || 'Pending';
  }

  // ---------------------------------------------------------------------------
  // SECTION: Normalize Technician Status
  // ---------------------------------------------------------------------------
  function normalizeTechnicianStatus(value) {
    const text = String(value || '').toLowerCase();
    if (text.includes('leave')) return 'On Leave';
    if (text.includes('busy')) return 'Busy';
    return 'Available';
  }

  // ---------------------------------------------------------------------------
  // SECTION: Parse Date
  // ---------------------------------------------------------------------------
  function parseDate(value) {
    if (!value) return null;
    const parsed = new Date(value);
    if (!Number.isNaN(parsed.getTime())) return parsed;

    const fallback = Date.parse(String(value).replace(/-/g, '/'));
    return Number.isNaN(fallback) ? null : new Date(fallback);
  }

  // ---------------------------------------------------------------------------
  // SECTION: Start Of Day
  // ---------------------------------------------------------------------------
  function startOfDay(date) {
    return new Date(date.getFullYear(), date.getMonth(), date.getDate());
  }

  // ---------------------------------------------------------------------------
  // SECTION: Add Days
  // ---------------------------------------------------------------------------
  function addDays(date, days) {
    const copy = new Date(date);
    copy.setDate(copy.getDate() + days);
    return copy;
  }

  // ---------------------------------------------------------------------------
  // SECTION: Same Day
  // ---------------------------------------------------------------------------
  function sameDay(first, second) {
    return Boolean(first && second)
      && first.getFullYear() === second.getFullYear()
      && first.getMonth() === second.getMonth()
      && first.getDate() === second.getDate();
  }

  // ---------------------------------------------------------------------------
  // SECTION: Format Date
  // ---------------------------------------------------------------------------
  function formatDate(date) {
    if (!date) return '-';
    return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
  }

  // ---------------------------------------------------------------------------
  // SECTION: To Isodate
  // ---------------------------------------------------------------------------
  function toISODate(date) {
    if (!date) return '';
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
  }

  // ---------------------------------------------------------------------------
  // SECTION: Get Kuala Lumpur Today
  // ---------------------------------------------------------------------------
  function getKualaLumpurToday() {
    const parts = new Intl.DateTimeFormat('en-CA', {
      timeZone: 'Asia/Kuala_Lumpur',
      year: 'numeric',
      month: '2-digit',
      day: '2-digit'
    }).formatToParts(new Date());
    const values = Object.fromEntries(parts.map(part => [part.type, part.value]));
    return new Date(Number(values.year), Number(values.month) - 1, Number(values.day));
  }

  // ---------------------------------------------------------------------------
  // SECTION: Get Reference Date
  // ---------------------------------------------------------------------------
  function getReferenceDate() {
    return startOfDay(getKualaLumpurToday());
  }

  // ---------------------------------------------------------------------------
  // SECTION: Completion Event Date
  // ---------------------------------------------------------------------------
  function completionEventDate(ticket) {
    if (ticket.completedDate) return startOfDay(ticket.completedDate);
    // Legacy completed rows may not have a completion timestamp. Keep them in
    // the historical trend by falling back to their original complaint date.
    if (isCompleted(ticket) && ticket.createdDate) return startOfDay(ticket.createdDate);
    return null;
  }


  // ---------------------------------------------------------------------------
  // SECTION: Is Open At End Of Day
  // ---------------------------------------------------------------------------
  function isOpenAtEndOfDay(ticket, day) {
    if (!ticket.createdDate || !day) return false;
    const receivedDate = startOfDay(ticket.createdDate);
    const completedDate = completionEventDate(ticket);
    return receivedDate <= day && (!completedDate || completedDate > day);
  }

  // ---------------------------------------------------------------------------
  // SECTION: Format Number
  // ---------------------------------------------------------------------------
  function formatNumber(value) {
    return Number(value || 0).toLocaleString();
  }

  // ---------------------------------------------------------------------------
  // SECTION: Is HITL Required
  // ---------------------------------------------------------------------------
  function isHitlRequired(ticket) {
    return ticket.humanApprovalRequired === 'yes'
      || ticket.escalationRequired === 'yes'
      || ticket.priority === 'Immediate'
      || ticket.priority === 'Urgent';
  }

  // ---------------------------------------------------------------------------
  // SECTION: Is Completed
  // ---------------------------------------------------------------------------
  function isCompleted(ticket) {
    return ticket.status === 'Completed';
  }

  // ---------------------------------------------------------------------------
  // SECTION: Clean Asset Label
  // ---------------------------------------------------------------------------
  function cleanAssetLabel(value, component = '') {
    const raw = String(value || '').trim();
    const normalized = raw.toLowerCase();

    const canonical = [
      { code: 'D10', label: 'Conveying', aliases: ['conveying', 'elevator', 'elevators', 'elevator & lifts', 'lifts'] },
      { code: 'D20', label: 'Plumbing', aliases: ['plumbing'] },
      { code: 'D30', label: 'HVAC', aliases: ['hvac', 'air conditioner', 'air conditioning', 'ventilation'] },
      { code: 'D40', label: 'Fire Protection', aliases: ['fire', 'fire protection', 'fire system'] },
      { code: 'D50', label: 'Electrical', aliases: ['electrical', 'electric'] }
    ];

    // Only explicit Other / OOKB records are allowed to become "Other".
    const explicitOther = ['other', 'out_of_kb', 'out of kb', 'out of knowledge base'];
    if (explicitOther.includes(normalized)) return 'Other';

    if (raw && !['-', 'unknown', 'none', 'null', 'n/a', 'na'].includes(normalized)) {
      const directCode = raw.match(/^D(10|20|30|40|50)(?:\s+|$)/i);
      if (directCode) {
        const item = canonical.find(entry => entry.code === `D${directCode[1]}`);
        if (item) return `${item.code} ${item.label}`;
      }

      const withoutCode = raw
        .replace(/^D\d+(?:\.\d+)?\s*/i, '')
        .replace(/^[-–—:]\s*/, '')
        .trim()
        .toLowerCase();

      const item = canonical.find(entry =>
        entry.aliases.includes(withoutCode) ||
        entry.aliases.some(alias => withoutCode === alias || withoutCode.startsWith(`${alias} `))
      );
      if (item) return `${item.code} ${item.label}`;
    }

    // Recover the hotel asset from a valid component D-code when the asset
    // field is blank, malformed, or supplied under an older schema.
    const componentText = String(component || '').trim();
    const componentCode = componentText.match(/^D(10|20|30|40|50)\d*/i);
    if (componentCode) {
      const item = canonical.find(entry => entry.code === `D${componentCode[1]}`);
      if (item) return `${item.code} ${item.label}`;
    }

    // Do not invent a phantom "Other" case for malformed/missing data.
    return '';
  }

  // ---------------------------------------------------------------------------
  // SECTION: Technician Group Label
  // ---------------------------------------------------------------------------
  function technicianGroupLabel(technician) {
    const role = String(technician.role || '').toLowerCase();
    if (role === 'technical specialist') return 'Technical Specialist';
    return cleanAssetLabel(technician.department || technician.category || 'Other');
  }

  // ---------------------------------------------------------------------------
  // SECTION: Normalize Smart Ticket
  // ---------------------------------------------------------------------------
  function normalizeSmartTicket(row) {
    return {
      caseId: row.case_id || '',
      room: row.room_id || row.room || '',
      asset: cleanAssetLabel(
        row.hotel_asset || row.hotelAsset || row.asset || row.category || '',
        row.component || row.component_name || row.componentName || ''
      ),
      component: row.component || row.component_name || row.componentName || '',
      priority: normalizePriority(row.priority_level || row.priority || row.severity_level || ''),
      status: normalizeStatus(row.ticket_status || row.status || ''),
      escalationRequired: String(row.escalation_required || '').toLowerCase(),
      humanApprovalRequired: String(row.human_approval_required || '').toLowerCase(),
      createdDate: parseDate(row.timestamp || row.created_at || row.created_date || row.date || ''),
      completedDate: parseDate(row.completed_date || row.completed_at || '')
    };
  }

  // ---------------------------------------------------------------------------
  // SECTION: Normalize HITL Case
  // ---------------------------------------------------------------------------
  function normalizeHitlCase(row) {
    return {
      caseId: row.case_id || '',
      room: row.room || '',
      asset: cleanAssetLabel(row.hotel_asset || 'Unknown'),
      severity: normalizePriority(row.severity || ''),
      reason: row.hitl_reason || '',
      reviewStatus: String(row.review_status || 'Pending Review')
    };
  }

  // ---------------------------------------------------------------------------
  // SECTION: Normalize Work Order
  // ---------------------------------------------------------------------------
  function normalizeWorkOrder(row) {
    return {
      caseId: row.case_id || '',
      asset: cleanAssetLabel(row.hotel_asset || 'Unknown'),
      stage: row.work_stage || row.task_status || '',
      slaStatus: String(row.sla_status || ''),
      supportReason: row.support_reason || '',
      assignedTechnicianId: row.assigned_technician_id || ''
    };
  }

  // ---------------------------------------------------------------------------
  // SECTION: Normalize Technicians
  // ---------------------------------------------------------------------------
  function normalizeTechnicians(rows, orders) {
    return rows
      .filter(row => row && (row.technician_id || row.id))
      .map((row, index) => {
        const activeTasks = Number(row.active_tasks || 0);
        const derivedStatus = row.status || (activeTasks > 0 ? 'Busy' : 'Available');
        return {
          id: row.technician_id || row.id || `TECH-${String(index + 1).padStart(3, '0')}`,
          name: row.name || row.technician_id || row.id || `Technician ${index + 1}`,
          status: normalizeTechnicianStatus(derivedStatus),
          department: row.department || 'Other',
          category: row.category || row.department || 'Other',
          role: row.role || 'Technician',
          activeTasks,
          totalTasks: Number(row.total_tasks || activeTasks || 0),
          workloadPercent: Number(row.workload_percent || 0)
        };
      });
  }

  // ---------------------------------------------------------------------------
  // SECTION: Render Overview KPIs
  // ---------------------------------------------------------------------------
  function renderOverviewKPIs(tickets, hitlCases, workOrders) {
    const referenceDate = startOfDay(getReferenceDate());
    const sevenDayStart = addDays(referenceDate, -6);

    // Seven-day performance window: intake, completion and AI automation.
    const ticketsReceivedLast7Days = tickets.filter(ticket => {
      if (!ticket.createdDate) return false;
      const receivedDate = startOfDay(ticket.createdDate);
      return receivedDate >= sevenDayStart && receivedDate <= referenceDate;
    });
    const completedCasesLast7Days = tickets.filter(ticket => {
      const completedDate = completionEventDate(ticket);
      return completedDate && completedDate >= sevenDayStart && completedDate <= referenceDate;
    });
    const automatedCasesLast7Days = ticketsReceivedLast7Days.filter(ticket => !isHitlRequired(ticket));

    const isPendingReview = item => {
      const status = item.reviewStatus.toLowerCase();
      return status === '' || status.includes('pending') || status.includes('review');
    };
    const isActiveOrder = item => item.stage !== '' && !item.stage.toLowerCase().includes('completed');
    const isSlaBreached = item => /breach|violation|exceeded/i.test(item.slaStatus);
    const isSupportRequired = item => {
      const reason = item.supportReason.trim();
      const stage = item.stage.trim();
      if (reason === 'Senior Support') return stage === 'Pending Senior Assignment';
      if (reason === 'Parts Required') return ['Waiting for Parts', 'Pending Reassignment'].includes(stage);
      if (reason === 'Outsourcing') return ['Pending Outsourcing', 'Outsourced', 'Pending Reassignment'].includes(stage);
      return false;
    };

    const pendingHitlCases = hitlCases.filter(isPendingReview);
    const pendingHitl = pendingHitlCases.length;
    const outOfKnowledgeBase = pendingHitlCases.filter(item => /out\s+of\s+knowledge|knowledge\s+base/i.test(item.reason)).length;

    // Current-state metrics are intentionally not restricted to seven days.
    const activeOrders = workOrders.filter(item => isActiveOrder(item)).length;
    const slaBreached = workOrders.filter(item => isActiveOrder(item) && isSlaBreached(item)).length;
    const supportRequired = workOrders.filter(item => isActiveOrder(item) && isSupportRequired(item)).length;
    const automationRate = ticketsReceivedLast7Days.length
      ? ((automatedCasesLast7Days.length / ticketsReceivedLast7Days.length) * 100).toFixed(1)
      : '0.0';

    setText('totalComplaint7', formatNumber(ticketsReceivedLast7Days.length));
    setText('actionWorkOrder', formatNumber(activeOrders));
    setText('completedCases7', formatNumber(completedCasesLast7Days.length));
    setText('aiAutomationRate', `${automationRate}%`);
    setText(
      'aiAutomationText',
      `${formatNumber(automatedCasesLast7Days.length)} / ${formatNumber(ticketsReceivedLast7Days.length)} tickets · Rolling 7 days`
    );
    setText('alertUrgentHitl', formatNumber(pendingHitl));
    setText('alertSla', formatNumber(slaBreached));
    setText('alertSupport', formatNumber(supportRequired));
    setText('alertOutKnowledge', formatNumber(outOfKnowledgeBase));
  }

  // ---------------------------------------------------------------------------
  // SECTION: Compute Trend Axis Range
  // ---------------------------------------------------------------------------
  function computeTrendAxisRange(seriesCollection) {
    const values = seriesCollection
      .flat()
      .map(value => Number(value))
      .filter(value => Number.isFinite(value));

    if (!values.length) return { beginAtZero: true, ticks: { precision: 0 } };

    const minValue = Math.min(...values);
    const maxValue = Math.max(...values);
    const spread = Math.max(1, maxValue - minValue);
    const padding = Math.max(2, Math.ceil(spread * 0.25));
    const roundedMin = Math.max(0, Math.floor((minValue - padding) / 5) * 5);
    const roundedMax = Math.ceil((maxValue + padding) / 5) * 5;

    return { min: roundedMin, max: roundedMax, ticks: { precision: 0 } };
  }

  // ---------------------------------------------------------------------------
  // SECTION: Build Seven Day Cohorts
  // ---------------------------------------------------------------------------
  function buildSevenDayCohorts(tickets) {
    const referenceDate = startOfDay(getReferenceDate());
    const days = Array.from({ length: 7 }, (_, index) => addDays(referenceDate, index - 6));
    const enriched = tickets.map(ticket => ({
      ...ticket,
      analysisDate: ticket.createdDate ? startOfDay(ticket.createdDate) : referenceDate
    }));
    return { days, enriched };
  }

  // ---------------------------------------------------------------------------
  // SECTION: Render Complaint Completed Trend
  // ---------------------------------------------------------------------------
  function renderComplaintCompletedTrend(tickets) {
    const canvas = document.getElementById('complaintCompletedTrendChart');
    if (!canvas || typeof Chart === 'undefined') return;

    const { days, enriched } = buildSevenDayCohorts(tickets);
    const labels = days.map(day => day.toLocaleDateString('en-GB', { day: '2-digit', month: 'short' }));
    // A complaint is counted on its intake date. A completion is counted on
    // the date the technician completed the case, including older backlog.
    const complaints = days.map(day => enriched.filter(ticket => sameDay(ticket.analysisDate, day)).length);
    const completed = days.map(day => enriched.filter(ticket => sameDay(completionEventDate(ticket), day)).length);
    const yScale = computeTrendAxisRange([complaints, completed]);

    const groupedValueLabels = {
      id: 'overviewGroupedValueLabels',
      afterDatasetsDraw(chart) {
        const { ctx, chartArea } = chart;
        if (!chartArea) return;

        ctx.save();
        ctx.fillStyle = '#111827';
        ctx.font = '700 11px Arial';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'bottom';

        chart.data.datasets.forEach((dataset, datasetIndex) => {
          const meta = chart.getDatasetMeta(datasetIndex);
          if (meta.hidden) return;

          meta.data.forEach((element, index) => {
            const value = Number(dataset.data[index]);
            if (!Number.isFinite(value) || value <= 0) return;

            const point = element.getProps(['x', 'y'], true);
            const labelY = Math.max(chartArea.top + 12, point.y - 7);
            ctx.fillText(value.toLocaleString(), point.x, labelY);
          });
        });

        ctx.restore();
      }
    };

    new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Complaints Received',
            data: complaints,
            backgroundColor: COLORS.blue,
            borderColor: COLORS.blue,
            borderWidth: 1,
            borderRadius: 7,
            borderSkipped: false,
            maxBarThickness: 38,
            categoryPercentage: 0.72,
            barPercentage: 0.84
          },
          {
            label: 'Cases Completed',
            data: completed,
            backgroundColor: COLORS.teal,
            borderColor: COLORS.teal,
            borderWidth: 1,
            borderRadius: 7,
            borderSkipped: false,
            maxBarThickness: 38,
            categoryPercentage: 0.72,
            barPercentage: 0.84
          }
        ]
      },
      plugins: [groupedValueLabels],
      options: {
        responsive: true,
        maintainAspectRatio: false,
        layout: { padding: { top: 22, right: 12, bottom: 4, left: 6 } },
        interaction: { mode: 'nearest', intersect: true, axis: 'xy' },
        onHover(event, elements, chart) {
          const bars = chart.getElementsAtEventForMode(
            event,
            'nearest',
            { intersect: true, axis: 'xy' },
            false
          );
          event.native.target.style.cursor = bars.length ? 'pointer' : 'default';
        },
        onClick(event, elements, chart) {
          const bars = chart.getElementsAtEventForMode(
            event,
            'nearest',
            { intersect: true, axis: 'xy' },
            false
          );
          if (!bars.length) return;
          const bar = bars[0];
          const date = days[bar.index];
          const series = bar.datasetIndex === 1 ? 'completed' : 'complaints';
          window.location.href = `trend_cases.php?date=${encodeURIComponent(toISODate(date))}&series=${encodeURIComponent(series)}`;
        },
        plugins: {
          legend: { display: false },
          tooltip: { enabled: true }
        },
        scales: {
          y: {
            ...yScale,
            beginAtZero: true,
            grid: { color: 'rgba(148, 163, 184, 0.18)' },
            ticks: { ...(yScale.ticks || {}), padding: 8, font: { size: 11, weight: '600' } }
          },
          x: {
            offset: true,
            grid: { display: false },
            ticks: { padding: 10, font: { size: 11, weight: '600' } }
          }
        }
      }
    });
  }

  // ---------------------------------------------------------------------------
  // SECTION: Render Technician Capacity
  // ---------------------------------------------------------------------------
  function renderTechnicianCapacity(technicians) {
    const canvas = document.getElementById('technicianCapacityChart');
    const legend = document.getElementById('technicianCapacityLegend');
    if (!canvas || !legend || typeof Chart === 'undefined') return;

    const available = technicians.filter(item => item.status === 'Available').length;
    const busy = technicians.filter(item => item.status === 'Busy').length;
    const onLeave = technicians.filter(item => item.status === 'On Leave').length;
    const total = available + busy + onLeave;
    const denominator = Math.max(1, total);
    const capacityUsed = (busy / denominator) * 100;

    const centerLabel = {
      id: 'capacityCenterLabel',
      afterDraw(chart) {
        const { ctx, chartArea } = chart;
        if (!chartArea) return;
        const x = (chartArea.left + chartArea.right) / 2;
        const y = chartArea.bottom - 8;
        ctx.save();
        ctx.textAlign = 'center';
        ctx.fillStyle = '#111827';
        ctx.font = '800 23px Arial';
        ctx.fillText(`${capacityUsed.toFixed(0)}%`, x, y - 19);
        ctx.font = '700 10px Arial';
        ctx.fillStyle = '#6b7280';
        ctx.fillText('Capacity Used', x, y);
        ctx.restore();
      }
    };

    new Chart(canvas, {
      type: 'doughnut',
      plugins: [centerLabel],
      data: {
        labels: ['Available', 'Busy', 'On Leave'],
        datasets: [{
          data: [available, busy, onLeave],
          backgroundColor: ['#22c55e', '#ffd400', '#94a3b8'],
          borderWidth: 0
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        rotation: -90,
        circumference: 180,
        cutout: '68%',
        plugins: { legend: { display: false }, tooltip: { enabled: true } }
      }
    });

    const rows = [
      ['#22c55e', 'Available', available],
      ['#ffd400', 'Busy', busy],
      ['#94a3b8', 'On Leave', onLeave]
    ];
    legend.innerHTML = rows.map(([color, label, value]) => `
      <div class="capacity-row">
        <i class="capacity-dot" style="background:${color}"></i>
        <span>${label}</span>
        <strong>${formatNumber(value)} (${((value / denominator) * 100).toFixed(0)}%)</strong>
      </div>`).join('') + `
      <div class="capacity-total"><span>Total Technicians</span><strong>${formatNumber(total)}</strong></div>`;
  }

  // ---------------------------------------------------------------------------
  // SECTION: Render Asset Distribution
  // ---------------------------------------------------------------------------
  function renderAssetDistribution(tickets) {
    const canvas = document.getElementById('overviewAssetDistributionChart');
    if (!canvas || typeof Chart === 'undefined') return;

    const openCases = tickets.filter(ticket => !isCompleted(ticket));
    const counts = {};
    openCases.forEach(ticket => {
      const asset = ticket.asset;
      if (!asset) return;
      counts[asset] = (counts[asset] || 0) + 1;
    });
    const entries = Object.entries(counts).sort((a, b) => b[1] - a[1]);
    const valueLabels = plainValueLabelPlugin('overviewAssetValueLabels');

    new Chart(canvas, {
      type: 'bar',
      plugins: [valueLabels],
      data: {
        labels: entries.map(entry => entry[0]),
        datasets: [{
          label: 'Open cases',
          data: entries.map(entry => entry[1]),
          backgroundColor: COLORS.blue,
          borderRadius: 8
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        onHover(event, elements, chart) {
          const points = clickedElements(chart, event, elements);
          event.native.target.style.cursor = points.length ? 'pointer' : 'default';
        },
        onClick(event, elements, chart) {
          const points = clickedElements(chart, event, elements);
          if (!points.length) return;
          const asset = entries[points[0].index][0];
          window.location.href = `../smart/asset_detail.php?asset=${encodeURIComponent(asset)}&source=overview`;
        },
        layout: { padding: { top: 18, right: 10, left: 4, bottom: 0 } },
        plugins: { legend: { display: false } },
        scales: {
          y: { beginAtZero: true, grace: '14%', ticks: { precision: 0 } },
          x: { grid: { display: false }, ticks: { maxRotation: 0, minRotation: 0, autoSkip: false, font: { size: 9.5, weight: '600' }, padding: 4 } }
        }
      }
    });
  }

  // ---------------------------------------------------------------------------
  // SECTION: Render Trend Cases Detail
  // ---------------------------------------------------------------------------
  function renderTrendCasesDetail(tickets) {
    const params = new URLSearchParams(window.location.search);
    const dateValue = params.get('date');
    const selectedSeries = params.get('series') || 'complaints';
    const selectedDate = dateValue ? parseDate(`${dateValue}T00:00:00`) : startOfDay(getReferenceDate());

    const receivedCases = tickets
      .filter(ticket => sameDay(ticket.createdDate ? startOfDay(ticket.createdDate) : null, selectedDate));
    const completedCases = tickets
      .filter(ticket => sameDay(completionEventDate(ticket), selectedDate));
    // Carry unfinished cases forward into each following day until the
    // completion date. This is the end-of-day operational backlog.
    const openBacklogAtEndOfDay = tickets.filter(ticket => isOpenAtEndOfDay(ticket, selectedDate));
    const displayedCases = selectedSeries === 'completed' ? completedCases : receivedCases;
    const eventDate = ticket => selectedSeries === 'completed'
      ? completionEventDate(ticket)
      : (ticket.createdDate ? startOfDay(ticket.createdDate) : null);

    displayedCases.sort((a, b) => (eventDate(b)?.getTime() || 0) - (eventDate(a)?.getTime() || 0));

    const seriesLabel = selectedSeries === 'completed'
      ? 'Completed point selected — showing every case completed on this date, including cases received earlier.'
      : 'Complaint point selected — showing every complaint received on this date, regardless of its current status.';
    setText('detailPageTitle', `${formatDate(selectedDate)} Cases`);
    setText('detailPageSubtitle', seriesLabel);
    setText('detailTotalCases', formatNumber(receivedCases.length));
    setText('detailCompletedCases', formatNumber(completedCases.length));
    setText('detailOpenCases', formatNumber(openBacklogAtEndOfDay.length));

    const tbody = document.getElementById('detailCaseBody');
    const search = document.getElementById('detailSearch');
    const reset = document.getElementById('detailReset');
    if (!tbody || !search || !reset) return;

    const renderRows = rows => {
      if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="empty-table-message">No cases were found for the selected date.</td></tr>';
        setText('detailRecordCount', 'Showing 0 record(s)');
        return;
      }

      tbody.innerHTML = rows.map(ticket => `
        <tr>
          <td><strong>${escapeHtml(ticket.caseId || '-')}</strong></td>
          <td>${escapeHtml(formatDate(eventDate(ticket)))}</td>
          <td>${escapeHtml(ticket.room || '-')}</td>
          <td>${escapeHtml(ticket.asset || '-')}</td>
          <td>${escapeHtml(ticket.component || '-')}</td>
          <td>${escapeHtml(ticket.priority || '-')}</td>
          <td>${escapeHtml(ticket.status || '-')}</td>
        </tr>`).join('');
      setText('detailRecordCount', `Showing ${formatNumber(rows.length)} record(s)`);
    };

    const applyFilter = () => {
      const keyword = search.value.trim().toLowerCase();
      const filtered = displayedCases.filter(ticket => {
        if (!keyword) return true;
        return [ticket.caseId, ticket.room, ticket.asset, ticket.component, ticket.priority, ticket.status]
          .join(' ')
          .toLowerCase()
          .includes(keyword);
      });
      renderRows(filtered);
    };

    search.addEventListener('input', applyFilter);
    reset.addEventListener('click', () => {
      search.value = '';
      applyFilter();
    });

    renderRows(displayedCases);
  }

  // ---------------------------------------------------------------------------
  // SECTION: Render Technician Capacity Detail
  // ---------------------------------------------------------------------------
  function renderTechnicianCapacityDetail(technicians) {
    const availableTechs = technicians.filter(item => item.status === 'Available');
    const busyTechs = technicians.filter(item => item.status === 'Busy');
    const leaveTechs = technicians.filter(item => item.status === 'On Leave');

    setText('totalTechniciansCount', formatNumber(technicians.length));
    setText('availableTechniciansCount', formatNumber(availableTechs.length));
    setText('busyTechniciansCount', formatNumber(busyTechs.length));
    setText('leaveTechniciansCount', formatNumber(leaveTechs.length));

    renderDepartmentCapacityTable(technicians);
    initAvailableTechnicianDrilldown(technicians);
  }

  // ---------------------------------------------------------------------------
  // SECTION: Render Department Capacity Table
  // ---------------------------------------------------------------------------
  function renderDepartmentCapacityTable(technicians) {
    const tbody = document.getElementById('departmentCapacityBody');
    if (!tbody) return;

    const departments = ['HVAC', 'Plumbing', 'Electrical', 'Fire Protection', 'Conveying', 'Technical Specialist'];
    const summary = Object.fromEntries(
      departments.map(department => [department, { total: 0, available: 0, busy: 0, onLeave: 0 }])
    );

    technicians.forEach(technician => {
      const department = technicianGroupLabel(technician);
      if (!summary[department]) summary[department] = { total: 0, available: 0, busy: 0, onLeave: 0 };
      summary[department].total += 1;
      if (technician.status === 'Available') summary[department].available += 1;
      else if (technician.status === 'Busy') summary[department].busy += 1;
      else if (technician.status === 'On Leave') summary[department].onLeave += 1;
    });

    tbody.innerHTML = departments.map(department => {
      const counts = summary[department];
      const disabled = counts.total === 0 ? ' disabled aria-disabled="true"' : '';
      return `
        <tr>
          <td><strong>${escapeHtml(department)}</strong></td>
          <td>${formatNumber(counts.total)}</td>
          <td><span class="capacity-count-badge status-available" aria-label="${formatNumber(counts.available)} available">${formatNumber(counts.available)}</span></td>
          <td><span class="capacity-count-badge status-busy" aria-label="${formatNumber(counts.busy)} busy">${formatNumber(counts.busy)}</span></td>
          <td><span class="capacity-count-badge status-on-leave" aria-label="${formatNumber(counts.onLeave)} on leave">${formatNumber(counts.onLeave)}</span></td>
          <td>
            <button type="button" class="capacity-view-btn" data-view-department="${escapeHtml(department)}"${disabled}>
              View Technicians
            </button>
          </td>
        </tr>`;
    }).join('');
  }

  // ---------------------------------------------------------------------------
  // SECTION: Init Available Technician Drilldown
  // ---------------------------------------------------------------------------
  function initAvailableTechnicianDrilldown(technicians) {
    const card = document.getElementById('departmentTechnicianDrilldown');
    const tbody = document.getElementById('departmentTechnicianBody');
    const title = document.getElementById('departmentTechnicianTitle');
    const count = document.getElementById('departmentTechnicianCount');
    const close = document.getElementById('closeDepartmentTechnicians');
    if (!card || !tbody || !title || !count || !close) return;

    const statusRank = { Available: 0, Busy: 1, 'On Leave': 2 };

    const showDepartment = department => {
      const rows = technicians
        .filter(technician => technicianGroupLabel(technician) === department)
        .sort((a, b) => (statusRank[a.status] ?? 9) - (statusRank[b.status] ?? 9) || String(a.id).localeCompare(String(b.id)));

      title.textContent = `${department} Technicians`;
      const available = rows.filter(technician => technician.status === 'Available').length;
      const busy = rows.filter(technician => technician.status === 'Busy').length;
      const onLeave = rows.filter(technician => technician.status === 'On Leave').length;
      count.textContent = `${formatNumber(rows.length)} technician(s) · ${formatNumber(available)} available · ${formatNumber(busy)} busy${onLeave ? ` · ${formatNumber(onLeave)} on leave` : ''}`;

      tbody.innerHTML = rows.length
        ? rows.map(technician => `
            <tr>
              <td><strong>${escapeHtml(technician.id)}</strong></td>
              <td>${escapeHtml(technician.name || technician.id)}</td>
              <td>${escapeHtml(technicianGroupLabel(technician))}</td>
              <td>${escapeHtml(technician.role || 'Technician')}</td>
              <td><span class="capacity-status-text status-${escapeHtml(String(technician.status || '').toLowerCase().replace(/\s+/g, '-'))}"><i aria-hidden="true"></i>${escapeHtml(technician.status)}</span></td>
            </tr>`).join('')
        : '<tr><td colspan="5" class="empty-table-message">No technicians were found in this department.</td></tr>';

      card.hidden = false;
      card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    };

    document.querySelectorAll('[data-view-department]').forEach(button => {
      button.addEventListener('click', () => {
        if (button.disabled) return;
        const department = button.getAttribute('data-view-department');
        if (department) showDepartment(department);
      });
    });

    close.addEventListener('click', () => {
      card.hidden = true;
      tbody.innerHTML = '';
    });
  }

  // ---------------------------------------------------------------------------
  // SECTION: Set Text
  // ---------------------------------------------------------------------------
  function setText(id, value) {
    const element = document.getElementById(id);
    if (element) element.textContent = value;
  }

  // ---------------------------------------------------------------------------
  // SECTION: Escape Html
  // ---------------------------------------------------------------------------
  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }
})();
