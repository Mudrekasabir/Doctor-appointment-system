// assets/js/admin_charts.js - small canvas bar chart (no libraries)
document.addEventListener('DOMContentLoaded', function () {
  const cfg = window.__APPT_CHART;
  if (!cfg) return;
  const labels = cfg.labels || [];
  const data = cfg.data || [];
  const canvas = document.getElementById('apptsChart');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');

  // responsive resize
  function resize() {
    const ratio = window.devicePixelRatio || 1;
    const w = canvas.clientWidth;
    const h = canvas.clientHeight;
    canvas.width = Math.floor(w * ratio);
    canvas.height = Math.floor(h * ratio);
    ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
    draw();
  }

  function draw() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    const w = canvas.clientWidth;
    const h = canvas.clientHeight;
    const padding = 30;
    const chartW = w - padding * 2;
    const chartH = h - padding * 2;
    const max = Math.max(1, ...data);
    const barW = chartW / Math.max(1, data.length) * 0.7;
    const gap = (chartW - (barW * data.length)) / Math.max(1, data.length - 1 || 1);

    // axes
    ctx.strokeStyle = '#e6eefc';
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.moveTo(padding, padding + chartH);
    ctx.lineTo(padding + chartW, padding + chartH);
    ctx.stroke();

    // bars
    data.forEach((v, i) => {
      const x = padding + i * (barW + gap);
      const barH = (v / max) * (chartH - 10);
      const y = padding + chartH - barH;
      // gradient
      const g = ctx.createLinearGradient(x, y, x, y + barH);
      g.addColorStop(0, '#3b82f6');
      g.addColorStop(1, '#7dd3fc');
      ctx.fillStyle = g;
      roundRect(ctx, x, y, barW, barH, 6);
      ctx.fill();

      // label
      ctx.fillStyle = '#374151';
      ctx.font = '12px Arial';
      ctx.textAlign = 'center';
      ctx.fillText(labels[i].slice(5), x + barW / 2, padding + chartH + 16); // show MM-DD
    });

    // values on top
    ctx.fillStyle = '#0f172a';
    ctx.font = '12px Arial';
    ctx.textAlign = 'center';
    data.forEach((v,i) => {
      const x = padding + i * (barW + gap) + barW/2;
      const barH = (v / max) * (chartH - 10);
      const y = padding + chartH - barH - 6;
      ctx.fillText(String(v), x, y);
    });
  }

  function roundRect(ctx, x, y, width, height, radius) {
    ctx.beginPath();
    ctx.moveTo(x + radius, y);
    ctx.lineTo(x + width - radius, y);
    ctx.quadraticCurveTo(x + width, y, x + width, y + radius);
    ctx.lineTo(x + width, y + height - radius);
    ctx.quadraticCurveTo(x + width, y + height, x + width - radius, y + height);
    ctx.lineTo(x + radius, y + height);
    ctx.quadraticCurveTo(x, y + height, x, y + height - radius);
    ctx.lineTo(x, y + radius);
    ctx.quadraticCurveTo(x, y, x + radius, y);
    ctx.closePath();
  }

  window.addEventListener('resize', resize);
  resize();
});
