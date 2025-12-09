// assets/js/patient_ui.js
document.addEventListener('DOMContentLoaded', function(){
  // tabs
  document.querySelectorAll('.tab-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
      document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
      btn.classList.add('active');
      document.querySelectorAll('.tab-panel').forEach(p=>p.classList.remove('active'));
      var target = btn.getAttribute('data-target');
      if (target) {
        document.querySelector(target).classList.add('active');
      }
    });
  });
});
