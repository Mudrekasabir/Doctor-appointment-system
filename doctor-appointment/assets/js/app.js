// assets/js/app.js
document.addEventListener('DOMContentLoaded', function () {

  // ----- SLOT LOADING -----
  const dateInput = document.getElementById('booking-date');
  const slotsArea = document.getElementById('slots-area');

  if (dateInput && slotsArea) {
    dateInput.addEventListener('change', function () {
      const date = this.value;
      const params = new URLSearchParams(location.search);
      const doctor_id = params.get('doctor_id');
      if (!doctor_id) {
        slotsArea.innerHTML = '<div class="error">Missing doctor ID.</div>';
        return;
      }

      slotsArea.innerHTML = '<div class="muted">Loading slots...</div>';

      fetch('/doctor-appointment/patient/slots_ajax.php?doctor_id=' + doctor_id + '&date=' + date)
        .then(r => r.json())
        .then(data => {
          if (data.error) {
            slotsArea.innerHTML = '<div class="error">' + data.error + '</div>';
            return;
          }

          if (!data.slots || data.slots.length === 0) {
            slotsArea.innerHTML = '<div class="muted">No slots available.</div>';
            return;
          }

          let html = '<div class="slots-grid">';
          data.slots.forEach(s => {
            html += `
              <button class="slot-btn"
                data-start="${s.start}"
                data-end="${s.end}"
                data-date="${date}">
                ${s.start} - ${s.end}
              </button>`;
          });
          html += '</div>';
          slotsArea.innerHTML = html;

          document.querySelectorAll('.slot-btn').forEach(b => {
            b.addEventListener('click', () => openBookingModal(b));
          });
        })
        .catch(() => {
          slotsArea.innerHTML = '<div class="error">Failed to load slots.</div>';
        });
    });
  }

  // ----- MODAL LOGIC -----
  const modal = document.getElementById('booking-modal');
  const modalBg = document.getElementById('modal-bg');
  const modalStart = document.getElementById('modal-start');
  const modalEnd = document.getElementById('modal-end');
  const modalDate = document.getElementById('modal-date');
  const modalFee = document.getElementById('modal-fee');
  const modalConfirmBtn = document.getElementById('modal-confirm');
  const csrfToken = document.querySelector("meta[name='csrf-token']")?.content;

  function openBookingModal(btn) {
    modalStart.textContent = btn.dataset.start;
    modalEnd.textContent = btn.dataset.end;
    modalDate.textContent = btn.dataset.date;

    modal.classList.add('active');
    modalBg.classList.add('active');

    modalConfirmBtn.onclick = () => confirmBooking(btn);
  }

  document.getElementById('modal-close')?.addEventListener('click', closeModal);
  modalBg?.addEventListener('click', closeModal);

  function closeModal() {
    modal.classList.remove('active');
    modalBg.classList.remove('active');
  }

  // ----- CONFIRM BOOKING -----
  function confirmBooking(btn) {
    const params = new URLSearchParams(location.search);
    const doctor_id = params.get('doctor_id');

    const payload = {
      doctor_id: doctor_id,
      date: btn.dataset.date,
      start_time: btn.dataset.start,
      end_time: btn.dataset.end,
      csrf: csrfToken
    };

    modalConfirmBtn.disabled = true;
    modalConfirmBtn.textContent = 'Booking...';

    fetch('/doctor-appointment/patient/book_ajax.php', {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload)
    })
      .then(r => r.json())
      .then(res => {
        modalConfirmBtn.disabled = false;
        modalConfirmBtn.textContent = 'Confirm Booking';

        if (res.success) {
          alert("Appointment booked!");
          window.location.href = "/doctor-appointment/patient/appointments.php";
        } else {
          alert("Booking failed: " + (res.error || "Unknown error"));
        }
      })
      .catch(() => {
        alert("Server error.");
        modalConfirmBtn.disabled = false;
        modalConfirmBtn.textContent = 'Confirm Booking';
      });
  }

});
// Notification "mark all as read"
document.addEventListener("DOMContentLoaded", function () {
  var btn = document.getElementById("mark-read-btn");
  if (btn) {
    btn.addEventListener("click", function () {
      var token = document.querySelector('input[name="csrf_token"]')?.value;
      if (!token) {
        alert("Missing CSRF token.");
        return;
      }

      var fd = new FormData();
      fd.append("csrf_token", token);

      fetch("/doctor-appointment/notifications_mark_read.php", {
        method: "POST",
        body: fd
      })
      .then(r => r.json())
      .then(d => {
        if (d.success) {
          location.reload();
        }
      });
    });
  }
});
