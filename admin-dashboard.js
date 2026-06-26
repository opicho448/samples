document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form[data-loading]').forEach(function (form) {
        form.addEventListener('submit', function () {
            var btn = form.querySelector('button[type="submit"], input[type="submit"]');
            if (btn) {
                btn.classList.add('is-loading');
                btn.disabled = true;
            }
        });
    });

    var modal = document.getElementById('approvalsModal');
    var openButtons = document.querySelectorAll('[data-open-approvals-modal]');
    var closeButtons = document.querySelectorAll('[data-close-approvals-modal]');

    function openModal() {
        if (modal) {
            modal.classList.add('is-open');
            document.body.style.overflow = 'hidden';
        }
    }

    function closeModal() {
        if (modal) {
            modal.classList.remove('is-open');
            document.body.style.overflow = '';
        }
    }

    openButtons.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            openModal();
        });
    });

    closeButtons.forEach(function (btn) {
        btn.addEventListener('click', closeModal);
    });

    if (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                closeModal();
            }
        });
    }

    if (window.location.hash === '#approvals-modal') {
        openModal();
    }

    var canvas = document.getElementById('registrationTrendChart');
    var chartData = window.adminChartData || [];
    if (canvas && chartData.length) {
        drawBarChart(canvas, chartData);
    }

    window.addEventListener('resize', function () {
        if (canvas && chartData.length) {
            drawBarChart(canvas, chartData);
        }
    });
});

function drawBarChart(canvas, data) {
    var ctx = canvas.getContext('2d');
    var dpr = window.devicePixelRatio || 1;
    var rect = canvas.getBoundingClientRect();
    canvas.width = rect.width * dpr;
    canvas.height = rect.height * dpr;
    ctx.scale(dpr, dpr);

    var width = rect.width;
    var height = rect.height;
    var padding = { top: 20, right: 16, bottom: 36, left: 40 };
    var chartW = width - padding.left - padding.right;
    var chartH = height - padding.top - padding.bottom;
    var maxVal = Math.max.apply(null, data.map(function (d) { return d.count; }).concat([1]));
    var barGap = 4;
    var barWidth = Math.max(6, (chartW / data.length) - barGap);

    ctx.clearRect(0, 0, width, height);

    ctx.strokeStyle = '#e2e8f0';
    ctx.lineWidth = 1;
    for (var i = 0; i <= 4; i++) {
        var y = padding.top + (chartH / 4) * i;
        ctx.beginPath();
        ctx.moveTo(padding.left, y);
        ctx.lineTo(width - padding.right, y);
        ctx.stroke();
    }

    data.forEach(function (point, index) {
        var barH = (point.count / maxVal) * chartH;
        var x = padding.left + index * (barWidth + barGap);
        var y = padding.top + chartH - barH;

        var gradient = ctx.createLinearGradient(0, y, 0, y + barH);
        gradient.addColorStop(0, '#60a5fa');
        gradient.addColorStop(1, '#2563eb');
        ctx.fillStyle = gradient;
        ctx.fillRect(x, y, barWidth, barH);
    });

    ctx.fillStyle = '#64748b';
    ctx.font = '11px Segoe UI, sans-serif';
    ctx.textAlign = 'center';
    var labelEvery = Math.ceil(data.length / 6);
    data.forEach(function (point, index) {
        if (index % labelEvery !== 0 && index !== data.length - 1) {
            return;
        }
        var x = padding.left + index * (barWidth + barGap) + barWidth / 2;
        ctx.fillText(point.label, x, height - 10);
    });
}
