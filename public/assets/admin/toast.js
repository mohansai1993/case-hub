(function () {
  const ICONS = { success: "✓", error: "✕", warning: "!", info: "i" };

  function getContainer() {
    let container = document.getElementById("toast-container");
    if (!container) {
      container = document.createElement("div");
      container.id = "toast-container";
      container.className = "toast-container";
      document.body.appendChild(container);
    }
    return container;
  }

  window.showToast = function (message, type) {
    type = ICONS[type] ? type : "info";
    const container = getContainer();

    const el = document.createElement("div");
    el.className = "toast toast-" + type;
    el.setAttribute("role", "status");
    el.innerHTML =
      '<span class="toast-icon">' + ICONS[type] + "</span>" +
      '<span class="toast-msg"></span>' +
      '<button type="button" class="toast-close" aria-label="Dismiss">&times;</button>';
    el.querySelector(".toast-msg").textContent = message;

    const dismiss = () => {
      el.classList.remove("toast-in");
      el.classList.add("toast-out");
      setTimeout(() => el.remove(), 200);
    };

    el.querySelector(".toast-close").addEventListener("click", dismiss);
    container.appendChild(el);
    requestAnimationFrame(() => el.classList.add("toast-in"));
    setTimeout(dismiss, 3500);
  };
})();
