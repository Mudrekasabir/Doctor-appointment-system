// assets/js/doctor_ui.js
document.addEventListener('DOMContentLoaded', function(){
  var datePicker = document.getElementById('date-picker');
  var loadBtn = document.getElementById('load-btn');
  var dateLabel = document.getElementById('date-label');
  var apptArea = document.getElementById('appointments-area');

  function renderNotice(msg){
    apptArea.innerHTML = '<div class="muted">'+msg+'</div>';
  }

  function fetchAppointments(date){
    fetch('/doctor-appointment/doctor/appointments_ajax.php?date='+encodeURIComponent(date))
      .then(function(r){
        if(!r.ok) throw new Error('Network response not ok');
        return r.json();
      })
      .then(function(d){
        dateLabel.innerText = date;
        if(d.error){
          renderNotice(d.error);
          return;
        }
        if(!d.appointments || d.appointments.length === 0){
          renderNotice('No appointments for ' + date);
          return;
        }
        var html = '<table class="wide-table"><thead><tr><th>Time</th><th>Patient</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
        d.appointments.forEach(function(a){
          html += '<tr>';
          html += '<td>'+a.start.slice(0,5)+' - '+a.end.slice(0,5)+'</td>';
          html += '<td>'+a.patient_name+'</td>';
          html += '<td><span class="status '+a.status+'">'+(a.status.charAt(0).toUpperCase()+a.status.slice(1))+'</span></td>';
          html += '<td><a class="role-btn small-btn" href="/doctor-appointment/doctor/patient_view.php?patient_id='+a.patient_id+'&appointment_id='+a.id+'">View medical details</a></td>';
          html += '</tr>';
        });
        html += '</tbody></table>';
        apptArea.innerHTML = html;
      })
      .catch(function(err){
        renderNotice('Failed to load appointments: '+err.message);
      });
  }

  if(loadBtn){
    loadBtn.addEventListener('click', function(){ fetchAppointments(datePicker.value); });
  }

  // hamburger toggle
  var hambBtn = document.getElementById('hamburger-btn');
  var hambMenu = document.getElementById('hamburger-menu');
  if(hambBtn && hambMenu){
    hambBtn.addEventListener('click', function(e){
      var visible = hambMenu.getAttribute('data-visible') === '1';
      hambMenu.style.display = visible ? 'none' : 'block';
      hambMenu.setAttribute('data-visible', visible ? '0' : '1');
    });
    // click outside to close
    document.addEventListener('click', function(e){
      if(!hambBtn.contains(e.target) && !hambMenu.contains(e.target)){
        hambMenu.style.display = 'none';
        if(hambMenu) hambMenu.setAttribute('data-visible','0');
      }
    });
  }
});
