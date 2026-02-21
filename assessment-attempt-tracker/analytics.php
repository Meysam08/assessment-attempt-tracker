<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

$profiles = loadExamProfiles();
$selectedExamId = sanitizeId((string) ($_GET['exam'] ?? ($profiles[0]['id'] ?? '')));
$context = loadExamContext($selectedExamId);
$exam = $context['profile'];
$examAnalytics = getExamAnalytics($exam['id']);
$globalAnalytics = getGlobalAnalytics();
$scoreSeries = $examAnalytics['score_series'];
$maxScore = 1.0;

foreach ($scoreSeries as $point) {
    $maxScore = max($maxScore, (float) $point['score']);
}

$scoreLabels = [];
$scoreValues = [];
$accuracyValues = [];
foreach ($scoreSeries as $point) {
    $scoreLabels[] = (string) ($point['submitted_at'] ?? '');
    $scoreValues[] = (float) ($point['score'] ?? 0);
    $accuracyValues[] = (float) ($point['accuracy'] ?? 0);
}

$sectionLabels = array_keys($examAnalytics['section_accuracy']);
$sectionValues = array_values($examAnalytics['section_accuracy']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="page">
    <header class="topbar">
        <div>
            <h1>Analytics Dashboard</h1>
            <p class="sub">Advanced performance intelligence per exam and global overview.</p>
        </div>
        <div class="action-row no-pad">
            <a class="btn secondary" href="index.php?exam=<?= urlencode($exam['id']) ?>">Back to Exam</a>
            <a class="btn secondary" href="admin.php?exam=<?= urlencode($exam['id']) ?>">Admin</a>
        </div>
    </header>

    <section class="stats-grid">
        <article class="stat-card"><h3>Total Attempts</h3><p><?= (int) $globalAnalytics['total_attempts'] ?></p></article>
        <article class="stat-card"><h3>Exam Profiles</h3><p><?= (int) $globalAnalytics['exam_count'] ?></p></article>
        <article class="stat-card"><h3>This Exam Attempts</h3><p><?= (int) $examAnalytics['attempt_count'] ?></p></article>
        <article class="stat-card"><h3>Average Score</h3><p><?= (float) $examAnalytics['average_score'] ?></p></article>
        <article class="stat-card"><h3>Average Accuracy</h3><p><?= (float) $examAnalytics['average_accuracy'] ?>%</p></article>
        <article class="stat-card"><h3>Progress</h3><p><?= (float) $examAnalytics['improvement'] ?></p></article>
    </section>

    <section class="table-wrap">
        <h2>Pick Exam</h2>
        <form method="get" class="settings-grid">
            <label>
                Exam Profile
                <select name="exam" onchange="this.form.submit()">
                    <?php foreach ($profiles as $profile): ?>
                        <option value="<?= htmlspecialchars((string) $profile['id'], ENT_QUOTES) ?>" <?= $profile['id'] === $exam['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) $profile['title'], ENT_QUOTES) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </form>
    </section>

    <section class="table-wrap">
        <h2>Exam Usage Leaderboard</h2>
        <table>
            <thead>
            <tr>
                <th>Exam</th>
                <th>Attempts</th>
                <th>Avg Score</th>
                <th>Avg Accuracy</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($globalAnalytics['exam_stats'] as $row): ?>
                <tr>
                    <td><?= htmlspecialchars((string) $row['title'], ENT_QUOTES) ?></td>
                    <td><?= (int) $row['attempt_count'] ?></td>
                    <td><?= (float) $row['average_score'] ?></td>
                    <td><?= (float) $row['average_accuracy'] ?>%</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section class="table-wrap">
        <h2>Score Timeline (<?= htmlspecialchars((string) $exam['title'], ENT_QUOTES) ?>)</h2>
        <canvas id="scoreChart" class="chart-canvas" width="1100" height="320"></canvas>
        <?php if (empty($scoreSeries)): ?>
            <p class="sub">No attempts yet for this exam.</p>
        <?php else: ?>
            <div class="trend-list">
                <?php foreach ($scoreSeries as $point): ?>
                    <div class="trend-row">
                        <span class="trend-label"><?= htmlspecialchars((string) $point['submitted_at'], ENT_QUOTES) ?></span>
                        <div class="trend-track"><div class="trend-fill" style="width: <?= max(2, min(100, ((float) $point['score'] / $maxScore) * 100)) ?>%"></div></div>
                        <span class="trend-value"><?= (float) $point['score'] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="table-wrap">
        <h2>Section Reliability (Average Accuracy)</h2>
        <canvas id="sectionChart" class="chart-canvas" width="1100" height="320"></canvas>
        <?php if (empty($examAnalytics['section_accuracy'])): ?>
            <p class="sub">No section reliability data yet.</p>
        <?php else: ?>
            <div class="bars">
                <?php foreach ($examAnalytics['section_accuracy'] as $sectionName => $accuracy): ?>
                    <div class="bar-row">
                        <span class="bar-label"><?= htmlspecialchars((string) $sectionName, ENT_QUOTES) ?></span>
                        <div class="bar-track"><div class="bar-fill" style="width: <?= max(1, min(100, (float) $accuracy)) ?>%"></div></div>
                        <span class="bar-value"><?= (float) $accuracy ?>%</span>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="sub">Weakest sections: <?= htmlspecialchars(implode(', ', $examAnalytics['weak_sections']), ENT_QUOTES) ?></p>
        <?php endif; ?>
    </section>
</div>
<script src="local-chart.js"></script>
<script>
    (function () {
        var scoreLabels = <?= json_encode($scoreLabels, JSON_UNESCAPED_SLASHES) ?>;
        var scoreValues = <?= json_encode($scoreValues, JSON_UNESCAPED_SLASHES) ?>;
        var accuracyValues = <?= json_encode($accuracyValues, JSON_UNESCAPED_SLASHES) ?>;
        var sectionLabels = <?= json_encode($sectionLabels, JSON_UNESCAPED_SLASHES) ?>;
        var sectionValues = <?= json_encode($sectionValues, JSON_UNESCAPED_SLASHES) ?>;

        if (window.LocalChart) {
            if (scoreLabels.length > 0) {
                window.LocalChart.line('scoreChart', {
                    labels: scoreLabels,
                    datasets: [
                        { label: 'Score', values: scoreValues, color: '#2b86f0' },
                        { label: 'Accuracy %', values: accuracyValues, color: '#2fb774' }
                    ]
                });
            }

            if (sectionLabels.length > 0) {
                window.LocalChart.bar('sectionChart', {
                    labels: sectionLabels,
                    values: sectionValues,
                    color: '#2b86f0'
                });
            }
        }
    })();
</script>
</body>
</html>
