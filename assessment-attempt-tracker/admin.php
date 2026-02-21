<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

$message = '';
$error = '';
$profiles = loadExamProfiles();

if (isset($_GET['download'])) {
    $downloadType = (string) $_GET['download'];
    $examIdForDownload = sanitizeId((string) ($_GET['exam'] ?? ($profiles[0]['id'] ?? '')));
    $contextForDownload = loadExamContext($examIdForDownload);
    $examForDownload = $contextForDownload['profile'];

    if ($downloadType === 'answers') {
        $path = getAnswersPathForExam($examForDownload);
        $json = is_file($path) ? file_get_contents($path) : '{}';
        if ($json === false) {
            $json = '{}';
        }

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . basename((string) $examForDownload['answers_file']) . '"');
        echo $json;
        exit;
    }

    if ($downloadType === 'attempts-csv') {
        $csv = attemptsToCsv(getAttemptsByExam($examForDownload['id']));
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="attempts_' . $examForDownload['id'] . '.csv"');
        echo $csv;
        exit;
    }

    if ($downloadType === 'backup-all') {
        $jsonFiles = glob(__DIR__ . '/*.json') ?: [];
        if (class_exists('ZipArchive')) {
            $zipPath = tempnam(sys_get_temp_dir(), 'omr_backup_');
            if ($zipPath === false) {
                $zipPath = __DIR__ . '/omr_backup_temp.zip';
            }
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::OVERWRITE | ZipArchive::CREATE) === true) {
                foreach ($jsonFiles as $filePath) {
                    $zip->addFile($filePath, basename($filePath));
                }
                $zip->close();
                $content = file_get_contents($zipPath);
                if (is_file($zipPath)) {
                    @unlink($zipPath);
                }

                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="omr_backup.zip"');
                echo $content === false ? '' : $content;
                exit;
            }
        }

        $bundle = [];
        foreach ($jsonFiles as $filePath) {
            $bundle[basename($filePath)] = file_get_contents($filePath) ?: '{}';
        }
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="omr_backup.json"');
        echo json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'save_profile') {
        $currentId = sanitizeId((string) ($_POST['current_exam_id'] ?? ''));
        $newId = sanitizeId((string) ($_POST['exam_id'] ?? ''));
        $title = trim((string) ($_POST['title'] ?? 'Untitled Exam'));
        $subject = trim((string) ($_POST['subject'] ?? 'General'));
        $year = trim((string) ($_POST['year'] ?? 'N/A'));
        $answersFile = sanitizeAnswersFile((string) ($_POST['answers_file'] ?? ''));
        $durationSeconds = max(60, (int) ($_POST['duration_seconds'] ?? DEFAULT_DURATION_SECONDS));
        $defaultScoring = normalizeScoring([
            'correct' => (int) ($_POST['default_scoring']['correct'] ?? DEFAULT_SCORING['correct']),
            'wrong' => (int) ($_POST['default_scoring']['wrong'] ?? DEFAULT_SCORING['wrong']),
            'blank' => (int) ($_POST['default_scoring']['blank'] ?? DEFAULT_SCORING['blank']),
        ]);

        $sectionsRaw = (string) ($_POST['sections_json'] ?? '');
        $sectionsDecoded = json_decode($sectionsRaw, true);
        $sections = normalizeSections($sectionsDecoded ?? []);

        if ($newId === '' || $answersFile === '' || empty($sections)) {
            $error = 'Profile validation failed: exam id, answers file, and valid sections are required.';
        } else {
            $idConflict = false;
            foreach ($profiles as $profile) {
                $existingId = (string) ($profile['id'] ?? '');
                if ($existingId === $newId && $existingId !== $currentId) {
                    $idConflict = true;
                    break;
                }
            }

            if ($idConflict) {
                $error = 'Exam ID already exists. Use a unique id.';
            } else {
                $updated = false;
                foreach ($profiles as $index => $profile) {
                    if (($profile['id'] ?? '') === $currentId) {
                        $profiles[$index] = [
                            'id' => $newId,
                            'title' => $title,
                            'subject' => $subject,
                            'year' => $year,
                            'answers_file' => $answersFile,
                            'duration_seconds' => $durationSeconds,
                            'sections' => $sections,
                            'default_scoring' => $defaultScoring,
                        ];
                        $updated = true;
                        break;
                    }
                }

                if (!$updated) {
                    $profiles[] = [
                        'id' => $newId,
                        'title' => $title,
                        'subject' => $subject,
                        'year' => $year,
                        'answers_file' => $answersFile,
                        'duration_seconds' => $durationSeconds,
                        'sections' => $sections,
                        'default_scoring' => $defaultScoring,
                    ];
                }

                if (!saveExamProfiles($profiles)) {
                    $error = 'Failed to save exam profiles.';
                } else {
                    $answersPath = __DIR__ . '/' . $answersFile;
                    if (!is_file($answersPath)) {
                        saveCorrectAnswers($answersPath, []);
                    }

                    $message = $updated ? 'Profile updated successfully.' : 'Profile created successfully.';
                    header('Location: admin.php?exam=' . urlencode($newId) . '&msg=' . urlencode($message));
                    exit;
                }
            }
        }
    }

    if ($action === 'clone_profile') {
        $sourceExamId = sanitizeId((string) ($_POST['exam_id'] ?? ''));
        $sourceContext = loadExamContext($sourceExamId);
        $source = $sourceContext['profile'];

        $newId = sanitizeId($source['id'] . '-copy-' . substr(bin2hex(random_bytes(2)), 0, 3));
        $newAnswersFile = sanitizeAnswersFile(pathinfo((string) $source['answers_file'], PATHINFO_FILENAME) . '_' . $newId . '.json');

        $profiles[] = [
            'id' => $newId,
            'title' => (string) $source['title'] . ' (Copy)',
            'subject' => (string) $source['subject'],
            'year' => (string) $source['year'],
            'answers_file' => $newAnswersFile,
            'duration_seconds' => (int) $source['duration_seconds'],
            'sections' => $source['sections'],
            'default_scoring' => normalizeScoring($source['default_scoring'] ?? []),
        ];

        if (!saveExamProfiles($profiles)) {
            $error = 'Failed to clone profile.';
        } else {
            saveCorrectAnswers(__DIR__ . '/' . $newAnswersFile, $sourceContext['correct_answers']);
            header('Location: admin.php?exam=' . urlencode($newId) . '&msg=' . urlencode('Profile cloned successfully.'));
            exit;
        }
    }

    if ($action === 'delete_profile') {
        $deleteId = sanitizeId((string) ($_POST['exam_id'] ?? ''));

        if (count($profiles) <= 1) {
            $error = 'At least one profile must remain.';
        } else {
            $before = count($profiles);
            $profiles = array_values(array_filter($profiles, static function (array $profile) use ($deleteId): bool {
                return (string) ($profile['id'] ?? '') !== $deleteId;
            }));

            if (count($profiles) === $before) {
                $error = 'Profile not found.';
            } elseif (!saveExamProfiles($profiles)) {
                $error = 'Failed to delete profile.';
            } else {
                $nextId = (string) ($profiles[0]['id'] ?? '');
                header('Location: admin.php?exam=' . urlencode($nextId) . '&msg=' . urlencode('Profile deleted successfully.'));
                exit;
            }
        }
    }

    if ($action === 'save_answers') {
        $examId = sanitizeId((string) ($_POST['exam_id'] ?? ''));
        $context = loadExamContext($examId);
        $exam = $context['profile'];
        $questionCount = getQuestionCount($context['correct_answers'], $exam['sections']);

        $answersInput = is_array($_POST['answers'] ?? null) ? $_POST['answers'] : [];
        $normalized = [];
        for ($q = 1; $q <= $questionCount; $q++) {
            $o = (int) ($answersInput[(string) $q] ?? 0);
            if ($o >= 1 && $o <= 4) {
                $normalized[$q] = $o;
            }
        }

        if (!saveCorrectAnswers(getAnswersPathForExam($exam), $normalized)) {
            $error = 'Failed to save answers.';
        } else {
            $message = 'Answer key saved successfully.';
            header('Location: admin.php?exam=' . urlencode($exam['id']) . '&msg=' . urlencode($message));
            exit;
        }
    }

    if ($action === 'import_answers_json') {
        $examId = sanitizeId((string) ($_POST['exam_id'] ?? ''));
        $context = loadExamContext($examId);
        $exam = $context['profile'];

        $jsonInput = (string) ($_POST['answers_json'] ?? '');
        $decoded = json_decode($jsonInput, true);

        if (!is_array($decoded)) {
            $error = 'Invalid answers JSON payload.';
        } else {
            if (!saveCorrectAnswers(getAnswersPathForExam($exam), $decoded)) {
                $error = 'Failed to import answers JSON.';
            } else {
                header('Location: admin.php?exam=' . urlencode($exam['id']) . '&msg=' . urlencode('Answers JSON imported successfully.'));
                exit;
            }
        }
    }

    if ($action === 'delete_attempt') {
        $examId = sanitizeId((string) ($_POST['exam_id'] ?? ''));
        $attemptId = (string) ($_POST['attempt_id'] ?? '');

        if (!deleteAttemptById($attemptId)) {
            $error = 'Failed to delete attempt.';
        } else {
            header('Location: admin.php?exam=' . urlencode($examId) . '&msg=' . urlencode('Attempt deleted successfully.'));
            exit;
        }
    }

    if ($action === 'purge_exam_attempts') {
        $examId = sanitizeId((string) ($_POST['exam_id'] ?? ''));
        $deleted = purgeAttemptsByExam($examId);
        header('Location: admin.php?exam=' . urlencode($examId) . '&msg=' . urlencode($deleted . ' attempts removed for exam.'));
        exit;
    }

    if ($action === 'clear_attempts') {
        if (!clearAttempts()) {
            $error = 'Failed to clear attempt history.';
        } else {
            $message = 'Attempt history cleared.';
            header('Location: admin.php?msg=' . urlencode($message));
            exit;
        }
    }

    if ($action === 'restore_backup_zip') {
        if (!class_exists('ZipArchive')) {
            $error = 'ZIP restore is not available because ZipArchive is disabled in PHP.';
        } elseif (!isset($_FILES['backup_zip']) || (int) ($_FILES['backup_zip']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $error = 'Please choose a valid ZIP backup file.';
        } else {
            $tmpPath = (string) ($_FILES['backup_zip']['tmp_name'] ?? '');
            $zip = new ZipArchive();
            if ($zip->open($tmpPath) !== true) {
                $error = 'Could not open backup ZIP.';
            } else {
                $restored = 0;
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $entry = $zip->getNameIndex($i);
                    if (!is_string($entry)) {
                        continue;
                    }
                    $name = basename($entry);
                    if (!preg_match('/^[a-zA-Z0-9_\-\.]+\.json$/', $name)) {
                        continue;
                    }
                    $content = $zip->getFromIndex($i);
                    if ($content === false) {
                        continue;
                    }
                    if (file_put_contents(__DIR__ . '/' . $name, $content, LOCK_EX) !== false) {
                        $restored++;
                    }
                }
                $zip->close();

                if ($restored > 0) {
                    header('Location: admin.php?exam=' . urlencode((string) ($_POST['exam_id'] ?? '')) . '&msg=' . urlencode('Backup restored: ' . $restored . ' JSON files.'));
                    exit;
                }
                $error = 'No JSON files restored from ZIP.';
            }
        }
    }
}

if (isset($_GET['msg'])) {
    $message = (string) $_GET['msg'];
}

$profiles = loadExamProfiles();
$newMode = isset($_GET['new']) && $_GET['new'] === '1';
$selectedExamId = sanitizeId((string) ($_GET['exam'] ?? ($profiles[0]['id'] ?? '')));
$context = loadExamContext($selectedExamId);
$selectedExam = $context['profile'];

if ($newMode) {
    $editExam = [
        'id' => '',
        'title' => '',
        'subject' => 'General',
        'year' => date('Y'),
        'answers_file' => 'answers_new.json',
        'duration_seconds' => DEFAULT_DURATION_SECONDS,
        'sections' => [
            'English' => [1, 25],
            'Mathematics' => [26, 45],
        ],
        'default_scoring' => DEFAULT_SCORING,
    ];
    $currentExamId = '';
} else {
    $editExam = $selectedExam;
    $currentExamId = (string) $selectedExam['id'];
}

$selectedExamForAnswers = $selectedExam;
$correctAnswers = $context['correct_answers'];
$questionCount = $context['question_count'];
$sectionsJson = json_encode($editExam['sections'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
$answersJson = json_encode($correctAnswers, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
$attemptsCount = count(loadAttempts());
$defaultScoring = normalizeScoring($editExam['default_scoring'] ?? []);
$answerFileChoices = listAnswerJsonFiles();
$sectionWarnings = validateSections($editExam['sections'], $questionCount);

$examAttempts = getAttemptsByExam($selectedExamForAnswers['id']);
usort($examAttempts, 'compareSubmittedAtDesc');
$examAnalytics = getExamAnalytics($selectedExamForAnswers['id']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="page">
    <header class="topbar">
        <div>
            <h1>Admin Panel</h1>
            <p class="sub">Manage profiles, sections, keys, attempts, exports, and analytics.</p>
        </div>
        <div class="action-row no-pad">
            <a class="btn secondary" href="index.php?exam=<?= urlencode($selectedExamForAnswers['id']) ?>">Back to Exam</a>
            <a class="btn secondary" href="analytics.php?exam=<?= urlencode($selectedExamForAnswers['id']) ?>">Analytics</a>
        </div>
    </header>

    <?php if ($message !== ''): ?>
        <section class="table-wrap notice success"><?= htmlspecialchars($message, ENT_QUOTES) ?></section>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <section class="table-wrap notice error"><?= htmlspecialchars($error, ENT_QUOTES) ?></section>
    <?php endif; ?>
    <?php foreach ($sectionWarnings as $warning): ?>
        <section class="table-wrap notice error"><?= htmlspecialchars($warning, ENT_QUOTES) ?></section>
    <?php endforeach; ?>

    <section class="stats-grid compact">
        <article class="stat-card"><h3>This Exam Attempts</h3><p><?= (int) $examAnalytics['attempt_count'] ?></p></article>
        <article class="stat-card"><h3>Avg Score</h3><p><?= (float) $examAnalytics['average_score'] ?></p></article>
        <article class="stat-card"><h3>Avg Accuracy</h3><p><?= (float) $examAnalytics['average_accuracy'] ?>%</p></article>
        <article class="stat-card"><h3>Global Attempts</h3><p><?= (int) $attemptsCount ?></p></article>
    </section>

    <section class="table-wrap">
        <h2>Exam Profiles</h2>
        <div class="action-row left no-pad">
            <a class="btn secondary mini" href="admin.php?new=1">New Profile</a>
        </div>
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Year</th>
                <th>Subject</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($profiles as $profile): ?>
                <tr>
                    <td><?= htmlspecialchars((string) $profile['id'], ENT_QUOTES) ?></td>
                    <td><?= htmlspecialchars((string) $profile['title'], ENT_QUOTES) ?></td>
                    <td><?= htmlspecialchars((string) $profile['year'], ENT_QUOTES) ?></td>
                    <td><?= htmlspecialchars((string) $profile['subject'], ENT_QUOTES) ?></td>
                    <td>
                        <a class="inline-link" href="admin.php?exam=<?= urlencode((string) $profile['id']) ?>">Edit</a>
                        |
                        <form method="post" style="display:inline" onsubmit="return confirm('Clone this profile and answer key?');">
                            <input type="hidden" name="action" value="clone_profile">
                            <input type="hidden" name="exam_id" value="<?= htmlspecialchars((string) $profile['id'], ENT_QUOTES) ?>">
                            <button type="submit" class="inline-btn">Clone</button>
                        </form>
                        <?php if (count($profiles) > 1): ?>
                            |
                            <form method="post" style="display:inline" onsubmit="return confirm('Delete this profile?');">
                                <input type="hidden" name="action" value="delete_profile">
                                <input type="hidden" name="exam_id" value="<?= htmlspecialchars((string) $profile['id'], ENT_QUOTES) ?>">
                                <button type="submit" class="inline-btn">Delete</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section class="table-wrap">
        <h2><?= $newMode ? 'Create Profile' : 'Profile Editor' ?></h2>
        <form method="post">
            <input type="hidden" name="action" value="save_profile">
            <input type="hidden" name="current_exam_id" value="<?= htmlspecialchars($currentExamId, ENT_QUOTES) ?>">

            <div class="settings-grid">
                <label>Exam ID (slug)
                    <input type="text" name="exam_id" value="<?= htmlspecialchars((string) $editExam['id'], ENT_QUOTES) ?>" required>
                </label>
                <label>Title
                    <input type="text" name="title" value="<?= htmlspecialchars((string) $editExam['title'], ENT_QUOTES) ?>" required>
                </label>
                <label>Subject
                    <input type="text" name="subject" value="<?= htmlspecialchars((string) $editExam['subject'], ENT_QUOTES) ?>">
                </label>
                <label>Year
                    <input type="text" name="year" value="<?= htmlspecialchars((string) $editExam['year'], ENT_QUOTES) ?>">
                </label>
                <label>Answers File
                    <input type="text" name="answers_file" value="<?= htmlspecialchars((string) $editExam['answers_file'], ENT_QUOTES) ?>" list="answerFilesList" required>
                </label>
                <label>Duration Seconds
                    <input type="number" min="60" name="duration_seconds" value="<?= (int) $editExam['duration_seconds'] ?>">
                </label>
                <label>Default Correct Points
                    <input type="number" name="default_scoring[correct]" value="<?= $defaultScoring['correct'] ?>">
                </label>
                <label>Default Wrong Points
                    <input type="number" name="default_scoring[wrong]" value="<?= $defaultScoring['wrong'] ?>">
                </label>
                <label>Default Blank Points
                    <input type="number" name="default_scoring[blank]" value="<?= $defaultScoring['blank'] ?>">
                </label>
            </div>

            <datalist id="answerFilesList">
                <?php foreach ($answerFileChoices as $file): ?>
                    <option value="<?= htmlspecialchars($file, ENT_QUOTES) ?>"></option>
                <?php endforeach; ?>
            </datalist>

            <label class="block-label">Sections JSON</label>
            <textarea class="code-area" name="sections_json" rows="12"><?= htmlspecialchars((string) $sectionsJson, ENT_QUOTES) ?></textarea>

            <div class="action-row">
                <button class="btn primary" type="submit"><?= $newMode ? 'Create Profile' : 'Save Profile' ?></button>
            </div>
        </form>
    </section>

    <section class="table-wrap">
        <h2>Answer Key Editor (<?= htmlspecialchars($selectedExamForAnswers['title'], ENT_QUOTES) ?>)</h2>
        <div class="action-row left no-pad">
            <a class="btn secondary mini" href="admin.php?exam=<?= urlencode($selectedExamForAnswers['id']) ?>&download=answers">Download Answers JSON</a>
        </div>
        <p class="sub">Set each question to option 1-4. Leave as 0 for no key.</p>
        <form method="post">
            <input type="hidden" name="action" value="save_answers">
            <input type="hidden" name="exam_id" value="<?= htmlspecialchars($selectedExamForAnswers['id'], ENT_QUOTES) ?>">

            <div class="answer-grid">
                <?php for ($q = 1; $q <= $questionCount; $q++): ?>
                    <label class="answer-cell">
                        <span>Q<?= $q ?></span>
                        <select name="answers[<?= $q ?>]">
                            <option value="0">0</option>
                            <?php for ($o = 1; $o <= 4; $o++): ?>
                                <option value="<?= $o ?>" <?= (($correctAnswers[$q] ?? 0) === $o) ? 'selected' : '' ?>><?= $o ?></option>
                            <?php endfor; ?>
                        </select>
                    </label>
                <?php endfor; ?>
            </div>

            <div class="action-row">
                <button class="btn primary" type="submit">Save Answer Key</button>
            </div>
        </form>

        <h3>Bulk JSON Import</h3>
        <form method="post">
            <input type="hidden" name="action" value="import_answers_json">
            <input type="hidden" name="exam_id" value="<?= htmlspecialchars($selectedExamForAnswers['id'], ENT_QUOTES) ?>">
            <textarea class="code-area" name="answers_json" rows="10"><?= htmlspecialchars((string) $answersJson, ENT_QUOTES) ?></textarea>
            <div class="action-row">
                <button class="btn secondary" type="submit">Import JSON Into Answer Key</button>
            </div>
        </form>
    </section>

    <section class="table-wrap">
        <h2>Attempt Management (<?= htmlspecialchars($selectedExamForAnswers['title'], ENT_QUOTES) ?>)</h2>
        <div class="action-row left no-pad">
            <a class="btn secondary mini" href="admin.php?exam=<?= urlencode($selectedExamForAnswers['id']) ?>&download=attempts-csv">Download Attempts CSV</a>
            <form method="post" onsubmit="return confirm('Delete all attempts for this exam?');">
                <input type="hidden" name="action" value="purge_exam_attempts">
                <input type="hidden" name="exam_id" value="<?= htmlspecialchars($selectedExamForAnswers['id'], ENT_QUOTES) ?>">
                <button class="btn secondary mini" type="submit">Purge This Exam Attempts</button>
            </form>
        </div>

        <?php if (empty($examAttempts)): ?>
            <p class="sub">No attempts for this exam.</p>
        <?php else: ?>
            <table>
                <thead>
                <tr>
                    <th>Date</th>
                    <th>Attempt ID</th>
                    <th>Score</th>
                    <th>Correct</th>
                    <th>Wrong</th>
                    <th>Blank</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($examAttempts as $attempt): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) ($attempt['submitted_at'] ?? ''), ENT_QUOTES) ?></td>
                        <td><?= htmlspecialchars((string) ($attempt['id'] ?? ''), ENT_QUOTES) ?></td>
                        <td><?= (int) ($attempt['results']['total_score'] ?? 0) ?></td>
                        <td><?= (int) ($attempt['results']['total_correct'] ?? 0) ?></td>
                        <td><?= (int) ($attempt['results']['total_wrong'] ?? 0) ?></td>
                        <td><?= (int) ($attempt['results']['total_blank'] ?? 0) ?></td>
                        <td>
                            <a class="inline-link" href="submit.php?attempt_id=<?= urlencode((string) ($attempt['id'] ?? '')) ?>">View</a>
                            |
                            <a class="inline-link" href="export.php?attempt_id=<?= urlencode((string) ($attempt['id'] ?? '')) ?>">PDF</a>
                            |
                            <form method="post" style="display:inline" onsubmit="return confirm('Delete this attempt?');">
                                <input type="hidden" name="action" value="delete_attempt">
                                <input type="hidden" name="exam_id" value="<?= htmlspecialchars($selectedExamForAnswers['id'], ENT_QUOTES) ?>">
                                <input type="hidden" name="attempt_id" value="<?= htmlspecialchars((string) ($attempt['id'] ?? ''), ENT_QUOTES) ?>">
                                <button type="submit" class="inline-btn">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <section class="table-wrap">
        <h2>Global History Tools</h2>
        <p class="sub">Saved attempts (all exams): <?= $attemptsCount ?></p>
        <div class="action-row left no-pad">
            <a class="btn secondary mini" href="admin.php?exam=<?= urlencode($selectedExamForAnswers['id']) ?>&download=backup-all">Download Full Backup</a>
        </div>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="restore_backup_zip">
            <input type="hidden" name="exam_id" value="<?= htmlspecialchars($selectedExamForAnswers['id'], ENT_QUOTES) ?>">
            <div class="settings-grid">
                <label>Restore Backup ZIP
                    <input type="file" name="backup_zip" accept=".zip,application/zip">
                </label>
            </div>
            <div class="action-row">
                <button class="btn secondary" type="submit">Restore Backup ZIP</button>
            </div>
        </form>
        <form method="post" onsubmit="return confirm('Clear all attempts across every exam? This cannot be undone.');">
            <input type="hidden" name="action" value="clear_attempts">
            <button class="btn secondary" type="submit">Clear Entire Attempt History</button>
        </form>
    </section>
</div>
</body>
</html>
