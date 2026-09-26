/*
 * Account moderation buttons (suspend / activate / approve / reject).
 *
 * Any element with data-moderate="<action>" is handled here:
 *   data-url   POST endpoint
 *   data-name  the person's name, for the dialog text
 *
 * Every confirmation, error and success message is a SweetAlert2 dialog,
 * never a native browser dialog. The request runs inside the dialog's
 * preConfirm so the button shows a spinner and server-side validation errors
 * appear inside the dialog instead of losing what the admin typed.
 */
(function () {
  "use strict";

  var CSRF = document.querySelector('meta[name="csrf-token"]');
  var COLORS = { primary: "#5547f5", danger: "#e0413a", cancel: "#8b8b94" };

  var ACTIONS = {
    suspend: {
      title: function (n) { return "Suspend " + n + "?"; },
      text: "They will be signed out of every device and will not be able to log in until you activate the account again.",
      icon: "warning",
      confirm: "Yes, suspend",
      danger: true,
      reason: { label: "Reason for suspension", placeholder: "Explain why this account is being suspended..." }
    },
    activate: {
      title: function (n) { return "Activate " + n + "?"; },
      text: "They will be able to log in again.",
      icon: "question",
      confirm: "Yes, activate"
    },
    approve: {
      title: function (n) { return "Approve " + n + "?"; },
      text: "The lawyer will be marked as verified.",
      icon: "question",
      confirm: "Yes, approve"
    },
    reject: {
      title: function (n) { return "Reject " + n + "?"; },
      text: "The lawyer's verification will be marked as rejected.",
      icon: "warning",
      confirm: "Yes, reject",
      danger: true,
      reason: { label: "Reason for rejection", placeholder: "Explain why this lawyer cannot be verified..." }
    }
  };

  function send(url, payload) {
    return fetch(url, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "Accept": "application/json",
        "X-CSRF-TOKEN": CSRF ? CSRF.content : "",
        "X-Requested-With": "XMLHttpRequest"
      },
      body: JSON.stringify(payload)
    }).then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (body) {
        return { ok: res.ok, status: res.status, body: body };
      });
    });
  }

  function errorMessage(result) {
    var b = result.body || {};
    if (b.errors) { return Object.values(b.errors)[0][0]; }
    if (result.status === 403) { return "You do not have permission to do this."; }
    if (result.status === 419) { return "Your session expired. Please refresh the page and try again."; }
    if (result.status === 429) { return "Too many requests. Please wait a moment and try again."; }
    return b.message || "Something went wrong. Please try again.";
  }

  function moderate(button) {
    var action = ACTIONS[button.dataset.moderate];
    if (!action) { return; }

    var name = button.dataset.name || "this account";

    Swal.fire({
      title: action.title(name),
      text: action.text,
      icon: action.icon,
      input: action.reason ? "textarea" : undefined,
      inputLabel: action.reason ? action.reason.label : undefined,
      inputPlaceholder: action.reason ? action.reason.placeholder : undefined,
      inputAttributes: action.reason ? { maxlength: 500, "aria-label": action.reason.label } : undefined,
      showCancelButton: true,
      confirmButtonText: action.confirm,
      cancelButtonText: "Cancel",
      confirmButtonColor: action.danger ? COLORS.danger : COLORS.primary,
      cancelButtonColor: COLORS.cancel,
      reverseButtons: true,
      focusCancel: !!action.danger,
      showLoaderOnConfirm: true,
      allowOutsideClick: function () { return !Swal.isLoading(); },
      preConfirm: function (reason) {
        var payload = action.reason ? { reason: (reason || "").trim() } : {};

        if (action.reason && payload.reason.length < 3) {
          Swal.showValidationMessage("Please give a reason (at least 3 characters).");
          return false;
        }

        return send(button.dataset.url, payload).then(function (result) {
          if (!result.ok) {
            Swal.showValidationMessage(errorMessage(result));
            return false;
          }
          return result.body;
        }).catch(function () {
          Swal.showValidationMessage("Could not reach the server. Check your connection and try again.");
          return false;
        });
      }
    }).then(function (result) {
      if (!result.isConfirmed || !result.value) { return; }

      Swal.fire({
        icon: "success",
        title: result.value.message || "Done",
        timer: 1400,
        timerProgressBar: true,
        showConfirmButton: false
      }).then(function () { window.location.reload(); });
    });
  }

  document.addEventListener("click", function (event) {
    var button = event.target.closest("[data-moderate]");
    if (button && !button.disabled) {
      event.preventDefault();
      moderate(button);
    }
  });
})();
