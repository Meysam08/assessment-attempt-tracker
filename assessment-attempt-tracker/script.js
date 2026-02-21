(function () {
    var form = document.getElementById('examForm');
    var navItems = document.querySelectorAll('.nav-item[data-question]');
    var timerEl = document.getElementById('timer');
    var resetBtn = document.getElementById('resetBtn');
    var startTimerBtn = document.getElementById('startTimerBtn');
    var themeToggleBtn = document.getElementById('themeToggleBtn');
    var durationMinutesInput = document.getElementById('durationMinutes');
    var durationSecondsInput = document.getElementById('durationSeconds');
    var body = document.body;

    var timerInterval = null;
    var remaining = 0;
    var running = false;
    var draftKey = '';

    function setDurationFromInput() {
        if (!durationMinutesInput || !durationSecondsInput || !timerEl) return;

        var minutes = parseInt(durationMinutesInput.value || '0', 10);
        if (!minutes || minutes < 1) {
            minutes = 1;
            durationMinutesInput.value = '1';
        }

        var seconds = minutes * 60;
        durationSecondsInput.value = String(seconds);
        timerEl.dataset.duration = String(seconds);

        if (!running) {
            remaining = seconds;
            showTimer();
        }
    }

    function applyTheme(theme) {
        if (!body) return;
        var active = theme === 'dark' ? 'dark' : 'light';
        body.setAttribute('data-theme', active);
        body.classList.toggle('theme-dark', active === 'dark');
        if (themeToggleBtn) {
            themeToggleBtn.textContent = active === 'dark' ? 'Light' : 'Dark';
        }
        try {
            localStorage.setItem('omr_theme', active);
        } catch (_e) {
        }
    }

    function initTheme() {
        var storedTheme = 'light';
        try {
            storedTheme = localStorage.getItem('omr_theme') || 'light';
        } catch (_e) {
        }
        applyTheme(storedTheme);
        if (themeToggleBtn) {
            themeToggleBtn.addEventListener('click', function () {
                var next = body.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                applyTheme(next);
            });
        }
    }

    function persistDraft() {
        if (!form || !draftKey) return;
        var payload = {};
        var selected = form.querySelectorAll('input[type="radio"][data-question]:checked');
        selected.forEach(function (input) {
            payload[input.dataset.question] = parseInt(input.value, 10);
        });
        try {
            localStorage.setItem(draftKey, JSON.stringify(payload));
        } catch (_e) {
        }
    }

    function restoreDraft() {
        if (!form || !draftKey) return;
        var raw = null;
        try {
            raw = localStorage.getItem(draftKey);
        } catch (_e) {
            raw = null;
        }
        if (!raw) return;

        try {
            var payload = JSON.parse(raw);
            Object.keys(payload).forEach(function (q) {
                var value = String(payload[q]);
                var selector = 'input[name="answers[' + q + ']"][value="' + value + '"]';
                var input = form.querySelector(selector);
                if (input) {
                    input.checked = true;
                }
            });
            updateAllNav();
        } catch (_e) {
        }
    }

    function clearDraft() {
        if (!draftKey) return;
        try {
            localStorage.removeItem(draftKey);
        } catch (_e) {
        }
    }

    function updateNav(question) {
        if (!question || !form) return;

        var selected = form.querySelector('input[name="answers[' + question + ']":checked]');
        var item = document.querySelector('.nav-item[data-question="' + question + '"]');
        if (!item) return;

        item.classList.remove('answered', 'blank');
        item.classList.add(selected ? 'answered' : 'blank');
    }

    function updateAllNav() {
        navItems.forEach(function (item) {
            updateNav(item.dataset.question);
        });
    }

    function bindNavigation() {
        navItems.forEach(function (item) {
            item.addEventListener('click', function () {
                var row = document.getElementById('q-' + item.dataset.question);
                if (row) {
                    row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });
        });
    }

    function showTimer() {
        if (!timerEl) return;

        var hh = String(Math.floor(remaining / 3600)).padStart(2, '0');
        var mm = String(Math.floor((remaining % 3600) / 60)).padStart(2, '0');
        var ss = String(remaining % 60).padStart(2, '0');
        timerEl.textContent = hh + ':' + mm + ':' + ss;
    }

    function stopTimer() {
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }
        running = false;
        if (startTimerBtn) {
            startTimerBtn.disabled = false;
            startTimerBtn.textContent = 'Start';
        }
    }

    function startTimer() {
        if (!timerEl || !form || running) return;

        var duration = parseInt(timerEl.dataset.duration || '0', 10);
        if (!duration || duration < 1) return;

        if (remaining <= 0 || remaining > duration) {
            remaining = duration;
        }

        running = true;
        if (startTimerBtn) {
            startTimerBtn.disabled = true;
            startTimerBtn.textContent = 'Running';
        }

        showTimer();
        timerInterval = setInterval(function () {
            remaining -= 1;
            showTimer();

            if (remaining <= 0) {
                stopTimer();
                alert('Time is up. Your exam will be submitted now.');
                form.submit();
            }
        }, 1000);
    }

    if (durationMinutesInput) {
        durationMinutesInput.addEventListener('change', function () {
            setDurationFromInput();
            stopTimer();
        });
    }

    if (startTimerBtn) {
        startTimerBtn.addEventListener('click', function () {
            setDurationFromInput();
            startTimer();
        });
    }

    if (form) {
        var examId = form.dataset.examId || 'default';
        draftKey = 'omr_draft_' + examId;

        form.addEventListener('change', function (event) {
            var input = event.target;
            if (input && input.matches('input[type="radio"][data-question]')) {
                updateNav(input.dataset.question);
                persistDraft();
            }
        });

        form.addEventListener('submit', function (event) {
            setDurationFromInput();
            var ok = window.confirm('Are you sure you want to submit your exam?');
            if (!ok) {
                event.preventDefault();
                return;
            }

            stopTimer();
            clearDraft();
        });
    }

    if (resetBtn && form) {
        resetBtn.addEventListener('click', function () {
            var ok = window.confirm('Reset all answers and reset timer?');
            if (!ok) return;

            form.reset();
            stopTimer();
            setDurationFromInput();
            updateAllNav();
            clearDraft();
        });
    }

    bindNavigation();
    updateAllNav();
    initTheme();
    setDurationFromInput();
    restoreDraft();
})();
