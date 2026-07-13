import { useEffect, useRef } from 'react';

/**
 * Weekly OJT hours line chart, ported from the prototype's initHoursChart()
 * in script.js — same colors, grid, and target baseline, but fed real data.
 */
export default function HoursChart({ weeklyHours, targetPerWeek = 20 }) {
  const canvasRef = useRef(null);

  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    const weeks = weeklyHours.map((w) => w.week);
    const hours = weeklyHours.map((w) => w.hours);

    const w = canvas.width;
    const h = canvas.height;
    const pad = { top: 30, right: 20, bottom: 40, left: 50 };
    const chartW = w - pad.left - pad.right;
    const chartH = h - pad.top - pad.bottom;
    const maxVal = Math.max(35, ...hours.map((v) => Math.ceil(v / 5) * 5 + 5));

    ctx.clearRect(0, 0, w, h);

    ctx.strokeStyle = '#e8f5ee';
    ctx.lineWidth = 1;
    for (let i = 0; i <= 5; i++) {
      const y = pad.top + (chartH / 5) * i;
      ctx.beginPath();
      ctx.moveTo(pad.left, y);
      ctx.lineTo(pad.left + chartW, y);
      ctx.stroke();

      ctx.fillStyle = '#7da488';
      ctx.font = '10px Raleway';
      ctx.textAlign = 'right';
      ctx.fillText(Math.round(maxVal - (maxVal / 5) * i), pad.left - 6, y + 4);
    }

    const xAt = (i) =>
      weeks.length > 1 ? pad.left + (chartW / (weeks.length - 1)) * i : pad.left + chartW / 2;
    const yAt = (v) => pad.top + chartH - (v / maxVal) * chartH;

    // Target baseline
    ctx.setLineDash([6, 4]);
    ctx.strokeStyle = '#d4a017';
    ctx.lineWidth = 1.5;
    ctx.beginPath();
    ctx.moveTo(pad.left, yAt(targetPerWeek));
    ctx.lineTo(pad.left + chartW, yAt(targetPerWeek));
    ctx.stroke();
    ctx.setLineDash([]);

    if (!hours.length) return;

    const grad = ctx.createLinearGradient(0, pad.top, 0, pad.top + chartH);
    grad.addColorStop(0, 'rgba(26,122,63,.22)');
    grad.addColorStop(1, 'rgba(26,122,63,0)');

    ctx.fillStyle = grad;
    ctx.beginPath();
    hours.forEach((v, i) => {
      if (i === 0) ctx.moveTo(xAt(i), yAt(v));
      else ctx.lineTo(xAt(i), yAt(v));
    });
    ctx.lineTo(xAt(hours.length - 1), pad.top + chartH);
    ctx.lineTo(xAt(0), pad.top + chartH);
    ctx.closePath();
    ctx.fill();

    ctx.strokeStyle = '#1a7a3f';
    ctx.lineWidth = 2.5;
    ctx.beginPath();
    hours.forEach((v, i) => {
      if (i === 0) ctx.moveTo(xAt(i), yAt(v));
      else ctx.lineTo(xAt(i), yAt(v));
    });
    ctx.stroke();

    hours.forEach((v, i) => {
      ctx.beginPath();
      ctx.arc(xAt(i), yAt(v), 5, 0, Math.PI * 2);
      ctx.fillStyle = '#fff';
      ctx.strokeStyle = '#1a7a3f';
      ctx.lineWidth = 2.5;
      ctx.fill();
      ctx.stroke();

      ctx.fillStyle = '#1a2e1f';
      ctx.font = '10px Raleway';
      ctx.textAlign = 'center';
      ctx.fillText(weeks[i], xAt(i), pad.top + chartH + 18);
    });
  }, [weeklyHours, targetPerWeek]);

  return <canvas ref={canvasRef} id="hoursChart" width="880" height="300"></canvas>;
}
