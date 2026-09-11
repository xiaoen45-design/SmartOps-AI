/**
 * SmartOps Source: assets/js/admin/live_clock.js
 * Purpose: Js / Admin: Live Clock client-side interactions and dynamic UI behaviour.
 * Developer Guide: named functions are separated into searchable sections.
 */

(() => {
  "use strict";

  const clocks = Array.from(document.querySelectorAll("[data-live-clock]"));
  if (clocks.length === 0) return;

  const formatters = new Map();

  // ---------------------------------------------------------------------------
  // SECTION: Get Formatters
  // ---------------------------------------------------------------------------
  function getFormatters(timeZone) {
    if (formatters.has(timeZone)) return formatters.get(timeZone);

    const pair = {
      time: new Intl.DateTimeFormat("en-MY", {
        timeZone,
        hour: "2-digit",
        minute: "2-digit",
        second: "2-digit",
        hour12: true
      }),
      date: new Intl.DateTimeFormat("en-MY", {
        timeZone,
        weekday: "short",
        day: "2-digit",
        month: "short",
        year: "numeric"
      })
    };

    formatters.set(timeZone, pair);
    return pair;
  }

  // ---------------------------------------------------------------------------
  // SECTION: Split Time
  // ---------------------------------------------------------------------------
  function splitTime(formatter, date) {
    const parts = formatter.formatToParts(date);
    const get = (type) => parts.find((part) => part.type === type)?.value || "";

    return {
      time: [get("hour"), get("minute"), get("second")].filter(Boolean).join(":"),
      period: get("dayPeriod").toUpperCase()
    };
  }

  // ---------------------------------------------------------------------------
  // SECTION: Update Clocks
  // ---------------------------------------------------------------------------
  function updateClocks() {
    const now = new Date();

    clocks.forEach((clock) => {
      const timeZone = clock.dataset.timezone || "Asia/Kuala_Lumpur";
      const timeNode = clock.querySelector("[data-clock-time]");
      const periodNode = clock.querySelector("[data-clock-period]");
      const dateNode = clock.querySelector("[data-clock-date]");
      const formatter = getFormatters(timeZone);
      const formattedTime = splitTime(formatter.time, now);

      if (timeNode) {
        timeNode.textContent = formattedTime.time;
        timeNode.dateTime = now.toISOString();
      }

      if (periodNode) periodNode.textContent = formattedTime.period;

      if (dateNode) {
        dateNode.textContent = formatter.date.format(now);
        dateNode.dateTime = now.toISOString().slice(0, 10);
      }

      clock.setAttribute(
        "aria-label",
        `Live Kuala Lumpur time: ${formattedTime.time} ${formattedTime.period}, ${formatter.date.format(now)}`
      );
    });
  }

  updateClocks();
  window.setInterval(updateClocks, 1000);
})();
