// assets/js/admin.js
document.addEventListener('DOMContentLoaded', function () {
  const csrf = document.querySelector('input[name="csrf_token"]')?.value || document.querySelector('meta[name="csrf-token"]')?.content;

  // Approve/reject doctor
  document.querySelectorAll('.approve-doctor').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const id = btn.dataset.id;
      if (!confirm('Approve this doctor?')) return;
      const fd = new FormData();
      fd.append('id', id); fd.append('action','approve'); fd.append('csrf_token', csrf);
      fetch('/doctor-appointment/admin/approve_doctor.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{ if (d.success) location.reload(); else alert(d.error||'Error'); });
    });
  });
  document.querySelectorAll('.reject-doctor').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const id = btn.dataset.id;
      const reason = prompt('Reason for rejection (optional)');
      const fd = new FormData();
      fd.append('id', id); fd.append('action','reject'); fd.append('reason', reason || ''); fd.append('csrf_token', csrf);
      fetch('/doctor-appointment/admin/approve_doctor.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{ if (d.success) location.reload(); else alert(d.error||'Error'); });
    });
  });

  // delete doctor
  document.querySelectorAll('.delete-doctor').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      if (!confirm('Delete this doctor and all related data?')) return;
      const fd = new FormData();
      fd.append('id', btn.dataset.id); fd.append('csrf_token', csrf);
      fetch('/doctor-appointment/admin/delete_doctor.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{ if (d.success) location.reload(); else alert(d.error||'Error'); });
    });
  });

  // toggle patient or delete
  document.querySelectorAll('.toggle-patient').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const fd = new FormData();
      fd.append('id', btn.dataset.id); fd.append('csrf_token', csrf);
      fetch('/doctor-appointment/admin/toggle_patient.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{ if (d.success) location.reload(); else alert(d.error||'Error'); });
    });
  });
  document.querySelectorAll('.delete-patient').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      if (!confirm('Delete patient and data?')) return;
      const fd = new FormData();
      fd.append('id', btn.dataset.id); fd.append('csrf_token', csrf);
      fetch('/doctor-appointment/admin/delete_patient.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{ if (d.success) location.reload(); else alert(d.error||'Error'); });
    });
  });

  // admin cancel appointment
  document.querySelectorAll('.admin-cancel-appt').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const id = btn.dataset.id;
      const reason = prompt('Reason for cancellation (optional)');
      const fd = new FormData();
      fd.append('id', id); fd.append('reason', reason||''); fd.append('csrf_token', csrf);
      fetch('/doctor-appointment/admin/cancel_appointment.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{ if (d.success) location.reload(); else alert(d.error||'Error'); });
    });
  });

  // admin create appointment
  const createForm = document.getElementById('create-appointment-form');
  if (createForm) {
    createForm.addEventListener('submit', function (ev) {
      ev.preventDefault();
      const fd = new FormData(createForm);
      fd.append('csrf_token', csrf);
      fetch('/doctor-appointment/admin/create_appointment.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{ if (d.success) { alert('Created'); location.reload(); } else alert(d.error||'Error'); });
    });
  }

});
