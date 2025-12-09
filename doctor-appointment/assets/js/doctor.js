// assets/js/doctor.js
document.addEventListener('DOMContentLoaded', function () {
  // toggle date_for on repeat selection
  var repeatSelect = document.getElementById('repeat_weekly');
  var dateFor = document.getElementById('date_for');
  function toggleDateFor() {
    dateFor.style.display = (repeatSelect.value === '0') ? 'inline-block' : 'none';
  }
  if (repeatSelect) {
    toggleDateFor();
    repeatSelect.addEventListener('change', toggleDateFor);
  }

  var addBtn = document.getElementById('add-availability-btn');
  if (addBtn) addBtn.addEventListener('click', function (ev) {
    ev.preventDefault();
    var form = document.getElementById('add-availability-form');
    var fd = new FormData(form);
    fd.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    fetch('/doctor-appointment/doctor/save_availability.php', {
      method: 'POST', body: fd
    }).then(r=>r.json()).then(function (d) {
      if (d.error) alert('Error: '+d.error);
      else location.reload();
    }).catch(()=>alert('Server error'));
  });

  // delete availability
  document.querySelectorAll('.del-avail-btn').forEach(function (b) {
    b.addEventListener('click', function () {
      if (!confirm('Delete this availability?')) return;
      var id = this.dataset.id;
      var fd = new FormData();
      fd.append('id', id);
      fd.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
      fetch('/doctor-appointment/doctor/delete_availability.php', { method:'POST', body:fd })
        .then(r=>r.json()).then(function(d){
          if (d.error) alert('Error: '+d.error); else location.reload();
        }).catch(()=>alert('Server error'));
    });
  });

  // dayoff add
  var addDayoffBtn = document.getElementById('add-dayoff-btn');
  if (addDayoffBtn) addDayoffBtn.addEventListener('click', function(ev){
    ev.preventDefault();
    var date = document.getElementById('dayoff-date').value;
    var reason = document.getElementById('dayoff-reason').value || '';
    if (!date) { alert('Pick a date'); return; }
    var fd = new FormData();
    fd.append('date', date);
    fd.append('reason', reason);
    fd.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    fetch('/doctor-appointment/doctor/add_dayoff.php', { method:'POST', body:fd })
      .then(r=>r.json()).then(function(d){
        if (d.error) alert('Error: '+d.error); else {
          alert('Day off added. Appointments cancelled: ' + (d.cancelled || 0));
          location.reload();
        }
      }).catch(()=>alert('Server error'));
  });
});
