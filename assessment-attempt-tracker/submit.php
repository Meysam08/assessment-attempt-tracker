<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

$attempt = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $examId = sanitizeId((string) ($_POST['exam_id'] ?? ''));
    $context = loadExamContext($examId);
    $exam = $context['profile'];
    $correctAnswers = $context['correct_answers'];
    $sections = $exam['sections'];

    $userAnswers = normalizeUserAnswers($_POST['answers'] ?? []);
    $fallbackScoring = normalizeScoring($exam['default_scoring'] ?? []);
    $scoring = normalizeScoring([
        'correct' => $_POST['scoring']['correct'] ?? $fallbackScoring['correct'],
        'wrong' => $_POST['scoring']['wrong'] ?? $fallbackScoring['wrong'],
        'blank' => $_POST['scoring']['blank'] ?? $fallbackScoring['blank'],
    ]);

    $durationSeconds = max(60, (int) ($_POST['duration_seconds'] ?? $exam['duration_seconds']));
    $maxAllowedQuestion = getQuestionCount($correctAnswers, $sections);
    $userAnswers = array_filter($userAnswers, static function (int $q) use ($maxAllowedQuestion): bool {
        return $q >= 1 && $q <= $maxAllowedQuestion;
    }, ARRAY_FILTER_USE_KEY);
    $results = evaluateExam($correctAnswers, $userAnswers, $sections, $scoring);

    $attempt = [
        'id' => createAttemptId(),
        'exam_id' => $exam['id'],
        'exam_title' => $exam['title'],
        'subject' => $exam['subject'],
        'year' => $exam['year'],
        'submitted_at' => date('Y-m-d H:i:s'),
        'duration_seconds' => $durationSeconds,
        'sections' => $sections,
        'scoring' => $scoring,
        'question_count' => $results['question_count'],
        'user_answers' => $userAnswers,
        'correct_answers' => $correctAnswers,
        'results' => $results,
    ];

    saveAttempt($attempt);
} elseif (isset($_GET['attempt_id'])) {
    $attempt = getAttemptById((string) $_GET['attempt_id']);
}

if ($attempt === null) {
    header('Location: index.php');
    exit;
}

$exam = [
    'id' => (string) ($attempt['exam_id'] ?? 'unknown'),
    'title' => (string) ($attempt['exam_title'] ?? 'Unknown Exam'),
    'subject' => (string) ($attempt['subject'] ?? 'N/A'),
    'year' => (string) ($attempt['year'] ?? 'N/A'),
];
$sections = is_array($attempt['sections'] ?? null) ? $attempt['sections'] : [];
$userAnswers = normalizeUserAnswers($attempt['user_answers'] ?? []);
$correctAnswers = normalizeUserAnswers($attempt['correct_answers'] ?? []);
$results = is_array($attempt['results'] ?? null) ? $attempt['results'] : [];
$questionCount = (int) ($attempt['question_count'] ?? ($results['question_count'] ?? 0));
$attemptId = (string) ($attempt['id'] ?? '');
$submittedAt = (string) ($attempt['submitted_at'] ?? '');
$scoring = normalizeScoring($attempt['scoring'] ?? []);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Results</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="page">
    <header class="topbar">
        <div>
            <h1>Exam Results</h1>
            <p class="sub"><?= htmlspecialchars($exam['title'], ENT_QUOTES) ?> | Attempt <?= htmlspecialchars($attemptId, ENT_QUOTES) ?> | <?= htmlspecialchars($submittedAt, ENT_QUOTES) ?></p>
        </div>
        <div class="timer-box">
            <span>Score</span>
            <strong><?= (int) ($results['total_score'] ?? 0) ?></strong>
        </div>
    </header>

    <section class="stats-grid">
        <article class="stat-card"><h3>Total Score</h3><p><?= (int) ($results['total_score'] ?? 0) ?></p></article>
        <article class="stat-card"><h3>Total Correct</h3><p><?= (int) ($results['total_correct'] ?? 0) ?></p></article>
        <article class="stat-card"><h3>Total Wrong</h3><p><?= (int) ($results['total_wrong'] ?? 0) ?></p></article>
        <article class="stat-card"><h3>Total Unanswered</h3><p><?= (int) ($results['total_blank'] ?? 0) ?></p></article>
        <article class="stat-card"><h3>Accuracy</h3><p><?= (float) ($results['percentage'] ?? 0) ?>%</p></article>
        <article class="stat-card"><h3>Weakest Section</h3><p><?= htmlspecialchars((string) ($results['weakest_section'] ?? 'N/A'), ENT_QUOTES) ?></p></article>
    </section>

    <section class="table-wrap">
        <h2>Scoring Rules Used</h2>
        <p class="sub">Correct: <?= $scoring['correct'] ?> | Wrong: <?= $scoring['wrong'] ?> | Blank: <?= $scoring['blank'] ?></p>
    </section>

    <section class="table-wrap">
        <h2>Performance Breakdown</h2>
        <table>
            <thead>
            <tr>
                <th>Section</th>
                <th>Correct</th>
                <th>Wrong</th>
                <th>Blank</th>
                <th>Score</th>
                <th>Accuracy %</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach (($results['section_stats'] ?? []) as $name => $stats): ?>
                <?php if ($name === 'Uncategorized') { continue; } ?>
                <tr>
                    <td><?= htmlspecialchars((string) $name, ENT_QUOTES) ?></td>
                    <td><?= (int) ($stats['correct'] ?? 0) ?></td>
                    <td><?= (int) ($stats['wrong'] ?? 0) ?></td>
                    <td><?= (int) ($stats['blank'] ?? 0) ?></td>
                    <td><?= (int) ($stats['score'] ?? 0) ?></td>
                    <td><?= (float) ($stats['accuracy'] ?? 0) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section class="chart-wrap">
        <h2>Section Accuracy Chart</h2>
        <div class="bars">
            <?php foreach (($results['section_stats'] ?? []) as $name => $stats): ?>
                <?php if ($name === 'Uncategorized') { continue; } ?>
                <div class="bar-row">
                    <span class="bar-label"><?= htmlspecialchars((string) $name, ENT_QUOTES) ?></span>
                    <div class="bar-track"><div class="bar-fill" style="width: <?= max(0, min(100, (float) ($stats['accuracy'] ?? 0))) ?>%"></div></div>
                    <span class="bar-value"><?= (float) ($stats['accuracy'] ?? 0) ?>%</span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="review-section">
        <h2>Review Mode</h2>
        <p class="sub">Green border: correct option, red border: your wrong choice, gray: unanswered.</p>

        <div class="navigator review-nav">
            <h3>Question Status</h3>
            <div class="nav-grid">
                <?php for ($q = 1; $q <= $questionCount; $q++): ?>
                    <?php
                    $statusClass = 'blank';
                    if (isset($userAnswers[$q]) && isset($correctAnswers[$q])) {
                        $statusClass = $userAnswers[$q] === $correctAnswers[$q] ? 'answered' : 'wrong';
                    }
                    ?>
                    <span class="nav-item <?= $statusClass ?>"><?= $q ?></span>
                <?php endfor; ?>
            </div>
        </div>

        <?php foreach ($sections as $sectionName => $range): ?>
            <section class="section-block">
                <h3><?= htmlspecialchars((string) $sectionName, ENT_QUOTES) ?> (Q<?= (int) $range[0] ?>-Q<?= (int) $range[1] ?>)</h3>
                <div class="question-list">
                    <?php for ($q = (int) $range[0]; $q <= (int) $range[1]; $q++): ?>
                        <?php if ($q > $questionCount || !isset($correctAnswers[$q])): continue; endif; ?>
                        <div class="question-row">
                            <div class="q-label">Q<?= $q ?></div>
                            <div class="options">
                                <?php for ($o = 1; $o <= 4; $o++): ?>
                                    <?php
                                    $classes = ['option-pill', 'review-option'];
                                    if ($o === (int) $correctAnswers[$q]) {
                                        $classes[] = 'is-correct';
                                    }
                                    if (isset($userAnswers[$q]) && $o === (int) $userAnswers[$q] && (int) $userAnswers[$q] !== (int) $correctAnswers[$q]) {
                                        $classes[] = 'is-wrong-choice';
                                    }
                                    if (isset($userAnswers[$q]) && $o === (int) $userAnswers[$q]) {
                                        $classes[] = 'is-selected';
                                    }
                                    ?>
                                    <span class="<?= implode(' ', $classes) ?>">&#9711;<?= $o ?></span>
                                <?php endfor; ?>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </section>

    <div class="action-row">
        <a class="btn secondary" href="index.php?exam=<?= urlencode($exam['id']) ?>">Take Exam Again</a>
        <a class="btn secondary" href="export.php?attempt_id=<?= urlencode($attemptId) ?>">Printable OMR Export</a>
        <a class="btn secondary" href="admin.php?exam=<?= urlencode($exam['id']) ?>">Admin Panel</a>
    </div>
</div>
</body>
</html>
