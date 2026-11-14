/* The charts of the page "گزارش‌ها و آمار".
 *
 * Every chart is drawn with SVG: no library and no request to the outside.
 * The numbers come from the page as json, so the page works even when the
 * javascript is late or blocked.
 */
(function () {
    var NS = 'http://www.w3.org/2000/svg';
    var counter = 0;
    var palette = ['#2563eb', '#06b6d4', '#22c55e', '#f59e0b', '#a855f7', '#ef4444', '#14b8a6', '#f97316', '#6366f1', '#84cc16'];

    function node(name, attrs, parent) {
        var item = document.createElementNS(NS, name);
        Object.keys(attrs || {}).forEach(function (key) {
            item.setAttribute(key, attrs[key]);
        });
        if (parent) {
            parent.appendChild(item);
        }
        return item;
    }

    function number(value) {
        var n = Math.round(Number(value) || 0);
        try {
            return n.toLocaleString('en-US');
        } catch (e) {
            return String(n);
        }
    }

    function compact(value) {
        var abs = Math.abs(value);
        if (abs >= 1e9) return +(value / 1e9).toFixed(1) + 'B';
        if (abs >= 1e6) return +(value / 1e6).toFixed(1) + 'M';
        if (abs >= 1e3) return +(value / 1e3).toFixed(1) + 'K';
        return String(Math.round(value));
    }

    function niceMax(max) {
        if (!(max > 0)) return 4;
        var magnitude = Math.pow(10, Math.floor(Math.log10(max / 4)));
        var steps = [1, 1.5, 2, 2.5, 3, 4, 5, 6, 8, 10];
        for (var i = 0; i < steps.length; i++) {
            if (magnitude * steps[i] * 4 >= max) return magnitude * steps[i] * 4;
        }
        return magnitude * 40;
    }

    function tip(box) {
        var node = document.createElement('div');
        node.className = 'rp-tip';
        box.appendChild(node);
        return node;
    }

    function showTip(box, tipNode, x, y, html) {
        tipNode.innerHTML = html;
        tipNode.style.display = 'block';
        var maxLeft = box.clientWidth - tipNode.offsetWidth - 6;
        var maxTop = box.clientHeight - tipNode.offsetHeight - 6;
        tipNode.style.left = Math.max(4, Math.min(x, maxLeft)) + 'px';
        tipNode.style.top = Math.max(4, Math.min(y, maxTop)) + 'px';
    }

    /* --------------------------------------------------------------- the bars
     * The simple bars: the days of the week, the hours of the day and the
     * gateways.
     */
    function drawBars(box) {
        var items = JSON.parse(box.dataset.items || '[]');
        box.textContent = '';
        if (!items.length) return;
        var width = box.clientWidth - 12;
        var height = box.clientHeight - 12;
        var pad = { left: 44, right: 8, top: 10, bottom: 26 };
        var plotWidth = width - pad.left - pad.right;
        var plotHeight = height - pad.top - pad.bottom;
        var values = items.map(function (item) { return Number(item.value) || 0; });
        var top = niceMax(Math.max.apply(null, values.concat([0])));
        var step = plotWidth / items.length;
        var barWidth = Math.max(4, Math.min(46, step * 0.62));
        var svg = node('svg', { viewBox: '0 0 ' + width + ' ' + height, 'aria-hidden': 'true' }, box);
        for (var t = 0; t <= 4; t++) {
            var value = top / 4 * t;
            var y = pad.top + plotHeight - value / top * plotHeight;
            node('line', { class: 'rp-grid', x1: pad.left, x2: width - pad.right, y1: y, y2: y }, svg);
            node('text', { class: 'rp-axis', x: pad.left - 6, y: y + 3, 'text-anchor': 'end' }, svg).textContent = compact(value);
        }
        var tipNode = tip(box);
        items.forEach(function (item, index) {
            var value = Number(item.value) || 0;
            var barHeight = value > 0 ? Math.max(2, value / top * plotHeight) : 0;
            var x = pad.left + step * index + (step - barWidth) / 2;
            var y = pad.top + plotHeight - barHeight;
            var rect = node('rect', {
                class: 'rp-bar', x: x, y: barHeight > 0 ? y : pad.top + plotHeight - 2,
                width: barWidth, height: barHeight > 0 ? barHeight : 2, rx: Math.min(6, barWidth / 2)
            }, svg);
            var label = item.label !== undefined ? item.label : String(index);
            node('text', { class: 'rp-axis', x: x + barWidth / 2, y: height - 8, 'text-anchor': 'middle' }, svg).textContent = label;
            rect.addEventListener('pointerenter', function () {
                rect.classList.add('rp-bar-hot');
                showTip(box, tipNode, x, y - 10, '<b>' + (item.tip || label) + '</b><br>' + number(value) + ' ' + (box.dataset.unit || ''));
            });
            rect.addEventListener('pointerleave', function () {
                rect.classList.remove('rp-bar-hot');
                tipNode.style.display = 'none';
            });
        });
    }

    /* ------------------------------------------------------- the stacked bars
     * The "ترکیب زمان": the money of the subscriptions and the money of the
     * services that reached their end, day by day.
     */
    function drawStacked(box) {
        var items = JSON.parse(box.dataset.items || '[]');
        var series = JSON.parse(box.dataset.series || '[]');
        box.textContent = '';
        if (!items.length) return;
        var width = box.clientWidth - 12;
        var height = box.clientHeight - 12;
        var pad = { left: 46, right: 8, top: 10, bottom: 26 };
        var plotWidth = width - pad.left - pad.right;
        var plotHeight = height - pad.top - pad.bottom;
        var totals = items.map(function (item) {
            return series.reduce(function (sum, s) { return sum + (Number(item[s.key]) || 0); }, 0);
        });
        var top = niceMax(Math.max.apply(null, totals.concat([0])));
        var step = plotWidth / items.length;
        var barWidth = Math.max(3, Math.min(34, step * 0.68));
        var svg = node('svg', { viewBox: '0 0 ' + width + ' ' + height, 'aria-hidden': 'true' }, box);
        for (var t = 0; t <= 4; t++) {
            var value = top / 4 * t;
            var y = pad.top + plotHeight - value / top * plotHeight;
            node('line', { class: 'rp-grid', x1: pad.left, x2: width - pad.right, y1: y, y2: y }, svg);
            node('text', { class: 'rp-axis', x: pad.left - 6, y: y + 3, 'text-anchor': 'end' }, svg).textContent = compact(value);
        }
        var tipNode = tip(box);
        var every = Math.ceil(items.length / 8);
        items.forEach(function (item, index) {
            var x = pad.left + step * index + (step - barWidth) / 2;
            var cursor = pad.top + plotHeight;
            series.forEach(function (s) {
                var value = Number(item[s.key]) || 0;
                if (value <= 0) return;
                var barHeight = Math.max(1, value / top * plotHeight);
                cursor -= barHeight;
                node('rect', {
                    class: 'rp-bar', x: x, y: cursor, width: barWidth, height: barHeight,
                    fill: s.color, rx: 2, opacity: .92
                }, svg);
            });
            if (index % every === 0) {
                node('text', { class: 'rp-axis', x: x + barWidth / 2, y: height - 8, 'text-anchor': 'middle' }, svg).textContent = item.label;
            }
            var hit = node('rect', { x: pad.left + step * index, y: pad.top, width: step, height: plotHeight, fill: 'transparent' }, svg);
            hit.addEventListener('pointerenter', function () {
                var html = '<b>' + (item.tip || item.label) + '</b>';
                series.forEach(function (s) {
                    html += '<br>' + s.name + ': ' + number(item[s.key] || 0) + ' ' + (box.dataset.unit || '');
                });
                html += '<br>' + number(totals[index]) + ' ' + (box.dataset.unit || '');
                showTip(box, tipNode, x, 12, html);
            });
            hit.addEventListener('pointerleave', function () { tipNode.style.display = 'none'; });
        });
    }

    /* ------------------------------------------------------------- the trend
     * The revenue of every day (the thick line), the average of the last seven
     * days (the dashed line) and the number of the orders (the pale bars).
     */
    function drawTrend(box) {
        var items = JSON.parse(box.dataset.items || '[]');
        box.textContent = '';
        if (!items.length) return;
        var width = box.clientWidth - 12;
        var height = box.clientHeight - 12;
        var pad = { left: 48, right: 42, top: 12, bottom: 26 };
        var plotWidth = width - pad.left - pad.right;
        var plotHeight = height - pad.top - pad.bottom;
        var revenues = items.map(function (item) { return Number(item.revenue) || 0; });
        var counts = items.map(function (item) { return Number(item.count) || 0; });
        var averages = [];
        items.forEach(function (item, index) {
            var from = Math.max(0, index - 6);
            var slice = revenues.slice(from, index + 1);
            averages.push(slice.reduce(function (a, b) { return a + b; }, 0) / slice.length);
        });
        var topRevenue = niceMax(Math.max.apply(null, revenues.concat([0])));
        var topCount = niceMax(Math.max.apply(null, counts.concat([0])));
        var x = function (index) {
            return pad.left + (items.length === 1 ? plotWidth / 2 : index * plotWidth / (items.length - 1));
        };
        var yRevenue = function (value) { return pad.top + plotHeight - value / topRevenue * plotHeight; };
        var yCount = function (value) { return pad.top + plotHeight - value / topCount * plotHeight; };
        var svg = node('svg', { viewBox: '0 0 ' + width + ' ' + height, 'aria-hidden': 'true' }, box);
        for (var t = 0; t <= 4; t++) {
            var value = topRevenue / 4 * t;
            var y = yRevenue(value);
            node('line', { class: 'rp-grid', x1: pad.left, x2: width - pad.right, y1: y, y2: y }, svg);
            node('text', { class: 'rp-axis', x: pad.left - 6, y: y + 3, 'text-anchor': 'end' }, svg).textContent = compact(value);
            node('text', { class: 'rp-axis', x: width - pad.right + 6, y: y + 3, 'text-anchor': 'start' }, svg).textContent = compact(topCount / 4 * t);
        }
        var every = Math.ceil(items.length / 8);
        items.forEach(function (item, index) {
            var barHeight = counts[index] > 0 ? Math.max(1, (yCount(counts[index]) - (pad.top + plotHeight)) * -1) : 0;
            node('rect', {
                class: 'rp-bar-soft', x: x(index) - 5, y: pad.top + plotHeight - barHeight,
                width: 10, height: barHeight, rx: 4
            }, svg);
            if (index % every === 0) {
                node('text', { class: 'rp-axis', x: x(index), y: height - 8, 'text-anchor': 'middle' }, svg).textContent = item.label;
            }
        });
        var line = function (values, yFn) {
            return values.map(function (value, index) {
                return (index ? 'L' : 'M') + x(index).toFixed(1) + ' ' + yFn(value).toFixed(1);
            }).join(' ');
        };
        var gradientId = 'rpTrend' + (counter++);
        var gradient = node('linearGradient', { id: gradientId, x1: '0', y1: '0', x2: '0', y2: '1' }, node('defs', {}, svg));
        node('stop', { offset: '0%', style: 'stop-color:var(--ac);stop-opacity:.30' }, gradient);
        node('stop', { offset: '100%', style: 'stop-color:var(--ac);stop-opacity:0' }, gradient);
        var revenuePath = line(revenues, yRevenue);
        node('path', { d: revenuePath + ' L' + x(items.length - 1).toFixed(1) + ' ' + (pad.top + plotHeight) + ' L' + x(0).toFixed(1) + ' ' + (pad.top + plotHeight) + ' Z', fill: 'url(#' + gradientId + ')' }, svg);
        node('path', { class: 'rp-line', d: revenuePath }, svg);
        node('path', { class: 'rp-line-dash', d: line(averages, yRevenue) }, svg);
        var last = items.length - 1;
        node('circle', { class: 'rp-dot', cx: x(last), cy: yRevenue(revenues[last]), r: 4 }, svg);
        var tipNode = tip(box);
        var cross = node('line', { class: 'rp-cross', y1: pad.top, y2: pad.top + plotHeight, style: 'display:none' }, svg);
        var marker = node('circle', { class: 'rp-dot', r: 5, style: 'display:none' }, svg);
        var hit = node('rect', { x: pad.left, y: 0, width: plotWidth, height: height, fill: 'transparent' }, svg);
        hit.addEventListener('pointermove', function (event) {
            var rect = svg.getBoundingClientRect();
            var pointerX = (event.clientX - rect.left) * width / rect.width;
            var index = Math.max(0, Math.min(last, Math.round((pointerX - pad.left) / plotWidth * last)));
            cross.setAttribute('x1', x(index));
            cross.setAttribute('x2', x(index));
            cross.style.display = '';
            marker.setAttribute('cx', x(index));
            marker.setAttribute('cy', yRevenue(revenues[index]));
            marker.style.display = '';
            showTip(box, tipNode, x(index) * rect.width / width + 8, 10,
                '<b>' + (items[index].tip || items[index].label) + '</b><br>' +
                number(revenues[index]) + ' ' + (box.dataset.unit || '') + '<br>' +
                number(counts[index]) + ' ' + (box.dataset.countUnit || 'سفارش'));
        });
        hit.addEventListener('pointerleave', function () {
            cross.style.display = 'none';
            marker.style.display = 'none';
            tipNode.style.display = 'none';
        });
    }

    /* ----------------------------------------------------------------- donut */
    function drawDonut(box) {
        var parts = JSON.parse(box.dataset.parts || '[]');
        var center = box.dataset.center || '';
        var centerSub = box.dataset.centerSub || '';
        box.textContent = '';
        var size = Math.min(box.clientWidth, box.clientHeight) || 180;
        var radius = size / 2 - 14;
        var inner = radius * 0.62;
        var total = parts.reduce(function (sum, part) { return sum + (Number(part.value) || 0); }, 0);
        var svg = node('svg', { viewBox: '0 0 ' + size + ' ' + size, class: 'rp-donut', 'aria-hidden': 'true' }, box);
        var tipNode = tip(box);
        if (total <= 0) {
            node('circle', {
                cx: size / 2, cy: size / 2, r: (radius + inner) / 2, fill: 'none',
                stroke: 'var(--bd)', 'stroke-width': radius - inner
            }, svg);
        } else {
            var offset = 0;
            parts.forEach(function (part, index) {
                var value = Number(part.value) || 0;
                if (value <= 0) return;
                var angle = value / total * Math.PI * 2;
                var color = part.color || palette[index % palette.length];
                // A part of 100% is a whole circle: an arc can never draw it
                // (the two ends of the arc would be the same point), so the
                // circle itself is drawn.
                if (angle >= Math.PI * 2 - 0.0001) {
                    node('circle', {
                        cx: size / 2, cy: size / 2, r: (radius + inner) / 2, fill: 'none',
                        stroke: color, 'stroke-width': radius - inner
                    }, svg);
                    offset += angle;
                    return;
                }
                var x1 = size / 2 + radius * Math.cos(-offset);
                var y1 = size / 2 + radius * Math.sin(-offset);
                var x2 = size / 2 + radius * Math.cos(-(offset + angle));
                var y2 = size / 2 + radius * Math.sin(-(offset + angle));
                var large = angle > Math.PI ? 1 : 0;
                // The angles of the parts grow counterclockwise, so the flag of
                // the arc is 0. With the flag 1 the arc is drawn around another
                // center and the circle falls apart.
                var path = node('path', {
                    d: 'M' + x1.toFixed(2) + ' ' + y1.toFixed(2) +
                        ' A' + radius + ' ' + radius + ' 0 ' + large + ' 0 ' + x2.toFixed(2) + ' ' + y2.toFixed(2),
                    fill: 'none', stroke: color,
                    'stroke-width': radius - inner, 'stroke-linecap': 'butt'
                }, svg);
                path.setAttribute('data-part', 'rp-part');
                path.addEventListener('pointerenter', function () {
                    path.classList.add('rp-part-hot');
                    showTip(box, tipNode, size / 2 - 40, 6,
                        '<b>' + (part.name || '') + '</b><br>' +
                        number(value) + ' ' + (box.dataset.unit || '') +
                        '<br>' + number(Math.round(value / total * 1000) / 10) + '٪');
                });
                path.addEventListener('pointerleave', function () {
                    path.classList.remove('rp-part-hot');
                    tipNode.style.display = 'none';
                });
                offset += angle;
            });
        }
        if (center) {
            node('text', { class: 'rp-donut-center', x: size / 2, y: size / 2 + 2, 'text-anchor': 'middle' }, svg).textContent = center;
            if (centerSub) {
                node('text', { class: 'rp-donut-sub', x: size / 2, y: size / 2 + 20, 'text-anchor': 'middle' }, svg).textContent = centerSub;
            }
        }
    }

    /* ---------------------------------------------------- the growth of the users
     * The new users of every day (the bars) and the number of the users in
     * total (the line).
     */
    function drawGrowth(box) {
        var items = JSON.parse(box.dataset.items || '[]');
        box.textContent = '';
        if (!items.length) return;
        var width = box.clientWidth - 12;
        var height = box.clientHeight - 12;
        var pad = { left: 46, right: 40, top: 12, bottom: 26 };
        var plotWidth = width - pad.left - pad.right;
        var plotHeight = height - pad.top - pad.bottom;
        var news = items.map(function (item) { return Number(item.new) || 0; });
        var totals = items.map(function (item) { return Number(item.total) || 0; });
        var topNew = niceMax(Math.max.apply(null, news.concat([0])));
        var topTotal = niceMax(Math.max.apply(null, totals.concat([0])));
        var step = plotWidth / items.length;
        var barWidth = Math.max(3, Math.min(22, step * 0.5));
        var x = function (index) { return pad.left + step * index + step / 2; };
        var yNew = function (value) { return pad.top + plotHeight - value / topNew * plotHeight; };
        var yTotal = function (value) { return pad.top + plotHeight - value / topTotal * plotHeight; };
        var svg = node('svg', { viewBox: '0 0 ' + width + ' ' + height, 'aria-hidden': 'true' }, box);
        for (var t = 0; t <= 4; t++) {
            var value = topNew / 4 * t;
            var y = yNew(value);
            node('line', { class: 'rp-grid', x1: pad.left, x2: width - pad.right, y1: y, y2: y }, svg);
            node('text', { class: 'rp-axis', x: pad.left - 6, y: y + 3, 'text-anchor': 'end' }, svg).textContent = compact(value);
            node('text', { class: 'rp-axis', x: width - pad.right + 6, y: y + 3, 'text-anchor': 'start' }, svg).textContent = compact(topTotal / 4 * t);
        }
        var every = Math.ceil(items.length / 8);
        items.forEach(function (item, index) {
            var height2 = news[index] > 0 ? Math.max(1, (pad.top + plotHeight) - yNew(news[index])) : 0;
            node('rect', {
                class: 'rp-bar', x: x(index) - barWidth / 2, y: (pad.top + plotHeight) - height2,
                width: barWidth, height: height2, rx: Math.min(5, barWidth / 2)
            }, svg);
            if (index % every === 0) {
                node('text', { class: 'rp-axis', x: x(index), y: height - 8, 'text-anchor': 'middle' }, svg).textContent = item.label;
            }
        });
        var path = totals.map(function (value, index) {
            return (index ? 'L' : 'M') + x(index).toFixed(1) + ' ' + yTotal(value).toFixed(1);
        }).join(' ');
        node('path', { class: 'rp-line rp-line-green', d: path }, svg);
        var last = items.length - 1;
        node('circle', { class: 'rp-dot rp-dot-green', cx: x(last), cy: yTotal(totals[last]), r: 4 }, svg);
        var tipNode = tip(box);
        var hit = node('rect', { x: pad.left, y: 0, width: plotWidth, height: height, fill: 'transparent' }, svg);
        hit.addEventListener('pointermove', function (event) {
            var rect = svg.getBoundingClientRect();
            var pointerX = (event.clientX - rect.left) * width / rect.width;
            var index = Math.max(0, Math.min(last, Math.floor((pointerX - pad.left) / step)));
            showTip(box, tipNode, x(index) * rect.width / width + 8, 10,
                '<b>' + (items[index].tip || items[index].label) + '</b><br>' +
                number(news[index]) + ' ' + (box.dataset.newUnit || 'کاربر جدید') + '<br>' +
                number(totals[index]) + ' ' + (box.dataset.totalUnit || 'کاربر در total'));
        });
        hit.addEventListener('pointerleave', function () { tipNode.style.display = 'none'; });
    }

    function drawAll() {
        document.querySelectorAll('.rp-bars').forEach(drawBars);
        document.querySelectorAll('.rp-stacked').forEach(drawStacked);
        document.querySelectorAll('.rp-trend').forEach(drawTrend);
        document.querySelectorAll('.rp-donut-box').forEach(drawDonut);
        document.querySelectorAll('.rp-growth').forEach(drawGrowth);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', drawAll);
    } else {
        drawAll();
    }
    var timer;
    window.addEventListener('resize', function () {
        clearTimeout(timer);
        timer = setTimeout(drawAll, 160);
    });
})();
