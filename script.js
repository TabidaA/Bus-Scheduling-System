// Auto-hide alerts after 5 seconds
document.addEventListener("DOMContentLoaded", function () {
  const alerts = document.querySelectorAll(".alert:not(.alert-permanent)");
  alerts.forEach((alert) => {
    setTimeout(() => {
      const bsAlert = new bootstrap.Alert(alert);
      bsAlert.close();
    }, 5000);
  });
});

// Confirm before delete
function confirmDelete(message = "Are you sure you want to delete this item?") {
  return confirm(message);
}

// Format currency
function formatCurrency(amount) {
  return (
    "৳" +
    parseFloat(amount)
      .toFixed(2)
      .replace(/\d(?=(\d{3})+\.)/g, "$&,")
  );
}

// Date validation
function validateDate(dateString) {
  const date = new Date(dateString);
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  return date >= today;
}

// Form validation helper
function validateForm(formId) {
  const form = document.getElementById(formId);
  if (!form.checkValidity()) {
    form.classList.add("was-validated");
    return false;
  }
  return true;
}

// Loading spinner
function showLoading() {
  const spinner = document.createElement("div");
  spinner.className = "spinner-overlay";
  spinner.innerHTML =
    '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>';
  document.body.appendChild(spinner);
}

function hideLoading() {
  const spinner = document.querySelector(".spinner-overlay");
  if (spinner) {
    spinner.remove();
  }
}

// Seat selection helper
function toggleSeatSelection(seatElement) {
  if (seatElement.classList.contains("seat-booked")) {
    return false;
  }

  seatElement.classList.toggle("seat-selected");
  seatElement.classList.toggle("seat-available");
  return true;
}

// Print ticket
function printTicket() {
  window.print();
}

// Copy to clipboard
function copyToClipboard(text) {
  navigator.clipboard
    .writeText(text)
    .then(() => {
      alert("Copied to clipboard!");
    })
    .catch((err) => {
      console.error("Failed to copy: ", err);
    });
}
