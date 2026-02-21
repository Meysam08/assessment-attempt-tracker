<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

$profiles = loadExamProfiles();
$selectedExamId = sanitizeId((string) ($_GET['exam'] ?? ($profiles[0]['id'] ?? '')));
$context = loadExamContext($selectedExamId);
$exam = $context['profile'];
$sections = $exam['sections'];
$questionCount = $context['question_count'];
$recentAttempts = getRecentAttemptsByExam($exam['id'], 8);
$defaultScoring = normalizeScoring($exam['default_scoring'] ?? []);
$examAnalytics = getExamAnalytics($exam['id']);
$globalAnalytics = getGlobalAnalytics();
$answerWarnings = [];
if (empty($context['correct_answers'])) {
    $answerWarnings[] = 'Answer key not found or empty for this exam. Open Admin Panel and update the answer file.';
}
$answerWarnings = array_merge($answerWarnings, validateSections($sections, $questionCount));

$trendSeries = $examAnalytics['score_series'];
$trendMax = 0.0;
foreach ($trendSeries as $point) {
    $trendMax = max($trendMax, (float) $point['score']);
}
$trendMax = max(1, $trendMax);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assessment Attempt Tracker</title>
    <link rel="stylesheet" href="style.css">
</head>
<body data-theme="light">
<div class="page">
    <header class="topbar">
        <div>
            <h1>Assessment Attempt Tracker</h1>
            <p class="sub"><?= htmlspecialchars($exam['title'], ENT_QUOTES) ?> | <?= htmlspecialchars($exam['year'], ENT_QUOTES) ?></p>
        </div>
        <div class="timer-box top-actions">
            <span>Time Left</span>
            <strong id="timer" data-duration="<?= (int) $exam['duration_seconds'] ?>">03:00:00</strong>
            <div class="timer-actions">
                <button type="button" id="startTimerBtn" class="btn primary mini">Start</button>
                <button type="button" id="themeToggleBtn" class="btn secondary mini">Dark</button>
            </div>
        </div>
    </header>

    <?php foreach ($answerWarnings as $warning): ?>
        <section class="table-wrap notice error"><?= htmlspecialchars($warning, ENT_QUOTES) ?></section>
    <?php endforeach; ?>

    <section class="stats-grid compact">
        <article class="stat-card"><h3>Exam Attempts</h3><p><?= (int) $examAnalytics['attempt_count'] ?></p></article>
        <article class="stat-card"><h3>Average Score</h3><p><?= (float) $examAnalytics['average_score'] ?></p></article>
        <article class="stat-card"><h3>Average Accuracy</h3><p><?= (float) $examAnalytics['average_accuracy'] ?>%</p></article>
        <article class="stat-card"><h3>Progress</h3><p><?= (float) $examAnalytics['improvement'] ?></p></article>
        <article class="stat-card"><h3>Total Attempts</h3><p><?= (int) $globalAnalytics['total_attempts'] ?></p></article>
    </section>

    <main class="layout">
        <aside class="navigator">
            <h2>Exam Setup</h2>
            <form method="get" class="setup-form">
                <label class="setup-label" for="examPicker">Exam Profile</label>
                <select id="examPicker" name="exam" onchange="this.form.submit()">
                    <?php foreach ($profiles as $profile): ?>
                        <option value="<?= htmlspecialchars($profile['id'], ENT_QUOTES) ?>" <?= $profile['id'] === $exam['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($profile['title'], ENT_QUOTES) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <div class="action-row no-pad left">
                <a class="btn secondary mini" href="admin.php?exam=<?= urlencode($exam['id']) ?>">Admin Panel</a>
                <a class="btn secondary mini" href="analytics.php?exam=<?= urlencode($exam['id']) ?>">Analytics</a>
            </div>

            <h3>Questions</h3>
            <div id="questionNav" class="nav-grid">
                <?php for ($q = 1; $q <= $questionCount; $q++): ?>
                    <button type="button" class="nav-item blank" data-question="<?= $q ?>"><?= $q ?></button>
                <?php endfor; ?>
            </div>

            <div class="legend">
                <span><i class="dot answered"></i> Answered</span>
                <span><i class="dot blank"></i> Blank</span>
            </div>
        </aside>

        <section class="sheet-wrap">
            <form id="examForm" method="post" action="submit.php" data-exam-id="<?= htmlspecialchars($exam['id'], ENT_QUOTES) ?>">
                <input type="hidden" name="exam_id" value="<?= htmlspecialchars($exam['id'], ENT_QUOTES) ?>">
                <input type="hidden" id="durationSeconds" name="duration_seconds" value="<?= (int) $exam['duration_seconds'] ?>">

                <section class="section-block settings-block">
                    <h2>Customization</h2>
                    <div class="settings-grid">
                        <label>
                            Duration (minutes)
                            <input type="number" id="durationMinutes" min="1" value="<?= (int) round($exam['duration_seconds'] / 60) ?>">
                        </label>
                        <label>
                            Points Correct
                            <input type="number" name="scoring[correct]" value="<?= $defaultScoring['correct'] ?>">
                        </label>
                        <label>
                            Points Wrong
                            <input type="number" name="scoring[wrong]" value="<?= $defaultScoring['wrong'] ?>">
                        </label>
                        <label>
                            Points Blank
                            <input type="number" name="scoring[blank]" value="<?= $defaultScoring['blank'] ?>">
                        </label>
                    </div>
                </section>

                <?php foreach ($sections as $sectionName => $range): ?>
                    <section class="section-block">
                        <h2><?= htmlspecialchars($sectionName, ENT_QUOTES) ?> (Q<?= $range[0] ?>-Q<?= $range[1] ?>)</h2>
                        <div class="question-list">
                            <?php for ($q = $range[0]; $q <= $range[1]; $q++): ?>
                                <?php if ($q > $questionCount): break; endif; ?>
                                <div class="question-row" id="q-<?= $q ?>">
                                    <div class="q-label">Q<?= $q ?></div>
                                    <div class="options">
                                        <?php for ($o = 1; $o <= 4; $o++): ?>
                                            <label class="option-pill">
                                                <input type="radio" name="answers[<?= $q ?>]" value="<?= $o ?>" data-question="<?= $q ?>">
                                                <span>&#9711;<?= $o ?></span>
                                            </label>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </section>
                <?php endforeach; ?>

                <div class="action-row">
                    <button type="button" id="resetBtn" class="btn secondary">Reset</button>
                    <button type="submit" class="btn primary">Submit Exam</button>
                </div>
            </form>

            <section class="table-wrap">
                <h2>Recent Attempts (This Exam)</h2>
                <?php if (empty($recentAttempts)): ?>
                    <p class="sub">No attempts recorded yet.</p>
                <?php else: ?>
                    <table>
                        <thead>
                        <tr>
                            <th>Date</th>
                            <th>Score</th>
                            <th>Correct</th>
                            <th>Wrong</th>
                            <th>Blank</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recentAttempts as $attempt): ?>
                            <tr>
                                <td><?= htmlspecialchars((string) ($attempt['submitted_at'] ?? ''), ENT_QUOTES) ?></td>
                                <td><?= (int) ($attempt['results']['total_score'] ?? 0) ?></td>
                                <td><?= (int) ($attempt['results']['total_correct'] ?? 0) ?></td>
                                <td><?= (int) ($attempt['results']['total_wrong'] ?? 0) ?></td>
                                <td><?= (int) ($attempt['results']['total_blank'] ?? 0) ?></td>
                                <td>
                                    <a class="inline-link" href="submit.php?attempt_id=<?= urlencode((string) $attempt['id']) ?>">View</a>
                                    |
                                    <a class="inline-link" href="export.php?attempt_id=<?= urlencode((string) $attempt['id']) ?>">Export PDF</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>

            <section class="table-wrap">
                <h2>Performance Trend</h2>
                <?php if (empty($trendSeries)): ?>
                    <p class="sub">No trend data yet. Complete a few attempts to unlock analytics.</p>
                <?php else: ?>
                    <div class="trend-list">
                        <?php foreach ($trendSeries as $point): ?>
                            <div class="trend-row">
                                <span class="trend-label"><?= htmlspecialchars((string) $point['submitted_at'], ENT_QUOTES) ?></span>
                                <div class="trend-track"><div class="trend-fill" style="width: <?= max(2, min(100, ((float) $point['score'] / $trendMax) * 100)) ?>%"></div></div>
                                <span class="trend-value"><?= (float) $point['score'] ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </section>
    </main>
</div>

<script src="script.js"></script>
</body>
</html>

