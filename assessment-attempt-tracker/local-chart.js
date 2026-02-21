(function () {
    function getCanvas(id) {
        var canvas = document.getElementById(id);
        if (!canvas || !canvas.getContext) return null;
        return canvas;
    }

    function clear(ctx, w, h) {
        ctx.clearRect(0, 0, w, h);
    }

    function drawAxes(ctx, w, h, pad) {
        ctx.strokeStyle = '#8aa0b9';
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(pad, pad);
        ctx.lineTo(pad, h - pad);
        ctx.lineTo(w - pad, h - pad);
        ctx.stroke();
    }

    function maxValue(list) {
        var m = 0;
        for (var i = 0; i < list.length; i++) {
            m = Math.max(m, Number(list[i]) || 0);
        }
        return m;
    }

    function drawLegend(ctx, datasets, x, y) {
        var offset = 0;
        ctx.font = '12px Segoe UI';
        for (var i = 0; i < datasets.length; i++) {
            var ds = datasets[i];
            ctx.fillStyle = ds.color;
            ctx.fillRect(x + offset, y - 9, 12, 12);
            ctx.fillStyle = '#5b6c83';
            ctx.fillText(ds.label || '', x + offset + 18, y + 1);
            offset += 130;
        }
    }

    function line(id, cfg) {
        var canvas = getCanvas(id);
        if (!canvas) return;
        var ctx = canvas.getContext('2d');
        var w = canvas.width;
        var h = canvas.height;
        var pad = 36;
        var datasets = cfg.datasets || [];
        if (!datasets.length) return;

        clear(ctx, w, h);
        drawAxes(ctx, w, h, pad);
        drawLegend(ctx, datasets, pad, 18);

        var all = [];
        for (var d = 0; d < datasets.length; d++) {
            all = all.concat(datasets[d].values || []);
        }
        var maxY = Math.max(1, maxValue(all));
        var count = (cfg.labels || []).length;
        if (count < 2) count = 2;
        var stepX = (w - pad * 2) / (count - 1);

        for (var j = 0; j < datasets.length; j++) {
            var ds = datasets[j];
            var values = ds.values || [];
            ctx.strokeStyle = ds.color || '#2b86f0';
            ctx.fillStyle = ds.color || '#2b86f0';
            ctx.lineWidth = 2;
            ctx.beginPath();

            for (var i = 0; i < values.length; i++) {
                var x = pad + (stepX * i);
                var y = h - pad - ((Number(values[i]) || 0) / maxY) * (h - pad * 2);
                if (i === 0) {
                    ctx.moveTo(x, y);
                } else {
                    ctx.lineTo(x, y);
                }
            }
            ctx.stroke();

            for (var k = 0; k < values.length; k++) {
                var px = pad + (stepX * k);
                var py = h - pad - ((Number(values[k]) || 0) / maxY) * (h - pad * 2);
                ctx.beginPath();
                ctx.arc(px, py, 3, 0, Math.PI * 2);
                ctx.fill();
            }
        }
    }

    function bar(id, cfg) {
        var canvas = getCanvas(id);
        if (!canvas) return;
        var ctx = canvas.getContext('2d');
        var w = canvas.width;
        var h = canvas.height;
        var pad = 36;
        var values = cfg.values || [];

        clear(ctx, w, h);
        drawAxes(ctx, w, h, pad);

        var maxY = Math.max(1, maxValue(values));
        var bars = Math.max(1, values.length);
        var full = (w - pad * 2) / bars;
        var barW = Math.max(10, full * 0.62);

        for (var i = 0; i < values.length; i++) {
            var val = Number(values[i]) || 0;
            var x = pad + (full * i) + (full - barW) / 2;
            var barH = (val / maxY) * (h - pad * 2);
            var y = h - pad - barH;

            ctx.fillStyle = cfg.color || '#2b86f0';
            ctx.fillRect(x, y, barW, barH);

            ctx.fillStyle = '#5b6c83';
            ctx.font = '12px Segoe UI';
            var label = (cfg.labels && cfg.labels[i]) ? String(cfg.labels[i]) : '';
            ctx.fillText(label.substring(0, 10), x, h - pad + 14);
            ctx.fillText(String(Math.round(val * 100) / 100), x, y - 6);
        }
    }

    window.LocalChart = {
        line: line,
        bar: bar
    };
})();
