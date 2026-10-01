/*
 * Topbar bell icon: polls /admin/alerts every 20s so new system events (a
 * client/lawyer registering, etc.) show up without a page refresh. Not a
 * websocket push - the admin panel has no browser-side real-time client -
 * but 20s is unnoticeable for this kind of alert.
 */
(function () {
  "use strict";

  var root = document.getElementById("alerts");
  if (!root) return;

  var POLL_MS = 20000;
  var INDEX_URL = root.dataset.url;
  var READ_ALL_URL = root.dataset.readAllUrl;
  var CSRF = document.querySelector('meta[name="csrf-token"]').content;
  var toggle = document.getElementById("alerts-toggle");
  var panel = document.getElementById("alerts-panel");
  var list = document.getElementById("alerts-list");
  var dot = document.getElementById("alerts-dot");
  var markAllBtn = document.getElementById("alerts-mark-all");

  function escapeHtml(text) {
    var div = document.createElement("div");
    div.textContent = String(text);
    return div.innerHTML;
  }

  function api(url, options) {
    options = options || {};
    return fetch(url, Object.assign({}, options, {
      headers: {
        "Accept": "application/json",
        "X-CSRF-TOKEN": CSRF,
        "X-Requested-With": "XMLHttpRequest",
      },
    })).then(function (res) { return res.json(); });
  }

  function render(data) {
    dot.hidden = !data.unread_count;

    if (!data.notifications.length) {
      list.innerHTML = '<p class="empty-msg">No notifications yet.</p>';
      return;
    }

    list.innerHTML = data.notifications.map(function (n) {
      return (
        '<a href="' + (n.action_url ? escapeHtml(n.action_url) : "#") + '" class="alert-item' + (n.read ? "" : " is-unread") + '" data-id="' + escapeHtml(n.id) + '">' +
          '<span class="alert-dot"></span>' +
          '<span class="alert-body">' +
            '<strong>' + escapeHtml(n.title) + "</strong>" +
            "<small>" + escapeHtml(n.body) + "</small>" +
            '<span class="alert-time">' + escapeHtml(n.created_at) + "</span>" +
          "</span>" +
        "</a>"
      );
    }).join("");
  }

  function refresh() {
    api(INDEX_URL).then(render).catch(function () {});
  }

  toggle.addEventListener("click", function () {
    var open = panel.hidden;
    panel.hidden = !open;
    toggle.setAttribute("aria-expanded", open ? "true" : "false");
    if (open) refresh();
  });

  document.addEventListener("click", function (e) {
    if (!root.contains(e.target)) panel.hidden = true;
  });

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") panel.hidden = true;
  });

  list.addEventListener("click", function (e) {
    var item = e.target.closest(".alert-item");
    if (!item) return;
    api(INDEX_URL + "/" + item.dataset.id + "/read", { method: "POST" });
  });

  markAllBtn.addEventListener("click", function () {
    api(READ_ALL_URL, { method: "POST" }).then(refresh);
  });

  refresh();
  setInterval(refresh, POLL_MS);
})();
