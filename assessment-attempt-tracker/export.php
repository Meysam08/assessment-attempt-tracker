<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

$attemptId = (string) ($_GET['attempt_id'] ?? '');
$attempt = $attemptId !== '' ? getAttemptById($attemptId) : null;

if ($attempt === null) {
    header('Location: index.php');
    exit;
}

$examTitle = (string) ($attempt['exam_title'] ?? 'Unknown Exam');
$submittedAt = (string) ($attempt['submitted_at'] ?? '');
$questionCount = (int) ($attempt['question_count'] ?? 0);
$userAnswers = normalizeUserAnswers($attempt['user_answers'] ?? []);
$correctAnswers = normalizeUserAnswers($attempt['correct_answers'] ?? []);
$results = is_array($attempt['results'] ?? null) ? $attempt['results'] : [];
$sections = is_array($attempt['sections'] ?? null) ? $attempt['sections'] : [];
$scoring = normalizeScoring($attempt['scoring'] ?? []);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Printable OMR Export</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="print-page">
<div class="page print-wrap">
    <div class="print-controls">
        <a class="btn secondary" href="submit.php?attempt_id=<?= urlencode($attemptId) ?>">Back to Result</a>
        <button class="btn primary" onclick="window.print()">Print / Save PDF</button>
    </div>

    <header class="topbar print-header">
        <div>
            <h1>OMR Result Sheet</h1>
            <p class="sub"><?= htmlspecialchars($examTitle, ENT_QUOTES) ?> | Attempt <?= htmlspecialchars($attemptId, ENT_QUOTES) ?> | <?= htmlspecialchars($submittedAt, ENT_QUOTES) ?></p>
            <p class="sub">Scoring: +<?= $scoring['correct'] ?> / <?= $scoring['wrong'] ?> / <?= $scoring['blank'] ?></p>
        </div>
        <div class="timer-box">
            <span>Total Score</span>
            <strong><?= (int) ($results['total_score'] ?? 0) ?></strong>
        </div>
    </header>

    <section class="table-wrap">
        <h2>Overall Stats</h2>
        <table>
            <tbody>
            <tr><td>Total Correct</td><td><?= (int) ($results['total_correct'] ?? 0) ?></td></tr>
            <tr><td>Total Wrong</td><td><?= (int) ($results['total_wrong'] ?? 0) ?></td></tr>
            <tr><td>Total Blank</td><td><?= (int) ($results['total_blank'] ?? 0) ?></td></tr>
            <tr><td>Accuracy</td><td><?= (float) ($results['percentage'] ?? 0) ?>%</td></tr>
            </tbody>
        </table>
    </section>

    <?php foreach ($sections as $sectionName => $range): ?>
        <section class="table-wrap">
            <h2><?= htmlspecialchars((string) $sectionName, ENT_QUOTES) ?> (Q<?= (int) $range[0] ?>-Q<?= (int) $range[1] ?>)</h2>
            <table class="omr-table">
                <thead>
                <tr>
                    <th>Question</th>
                    <th>1</th>
                    <th>2</th>
                    <th>3</th>
                    <th>4</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php for ($q = (int) $range[0]; $q <= (int) $range[1]; $q++): ?>
                    <?php if ($q > $questionCount || !isset($correctAnswers[$q])): continue; endif; ?>
                    <?php
                    $userOption = $userAnswers[$q] ?? null;
                    $correctOption = $correctAnswers[$q];
                    $status = 'Blank';
                    if ($userOption !== null) {
                        $status = ((int) $userOption === (int) $correctOption) ? 'Correct' : 'Wrong';
                    }
                    ?>
                    <tr>
                        <td>Q<?= $q ?></td>
                        <?php for ($o = 1; $o <= 4; $o++): ?>
                            <?php
                            $classes = ['omr-bubble'];
                            $text = '&#9711;';
                            if ($o === (int) $correctOption) {
                                $classes[] = 'omr-correct';
                            }
                            if ($userOption !== null && $o === (int) $userOption) {
                                $classes[] = 'omr-selected';
                                $text = '&#9679;';
                            }
                            if ($userOption !== null && $o === (int) $userOption && (int) $userOption !== (int) $correctOption) {
                                $classes[] = 'omr-wrong';
                            }
                            ?>
                            <td class="<?= implode(' ', $classes) ?>"><?= $text ?></td>
                        <?php endfor; ?>
                        <td><?= $status ?></td>
                    </tr>
                <?php endfor; ?>
                </tbody>
            </table>
        </section>
    <?php endforeach; ?>
</div>
</body>
</html>
