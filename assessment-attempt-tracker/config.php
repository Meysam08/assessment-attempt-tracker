<?php

declare(strict_types=1);

const EXAMS_FILE = __DIR__ . '/exams.json';
const ATTEMPTS_FILE = __DIR__ . '/attempts.json';
const DEFAULT_DURATION_SECONDS = 3 * 60 * 60;

const DEFAULT_SCORING = [
    'correct' => 3,
    'wrong' => -1,
    'blank' => 0,
];

function readJsonFile(string $filePath, $fallback)
{
    if (!is_file($filePath)) {
        return $fallback;
    }

    $raw = file_get_contents($filePath);
    if ($raw === false) {
        return $fallback;
    }

    $decoded = json_decode($raw, true);
    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
        return $fallback;
    }

    return $decoded;
}

function writeJsonFile(string $filePath, $data): bool
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }

    return file_put_contents($filePath, $json . PHP_EOL, LOCK_EX) !== false;
}

function sanitizeId(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9\-]+/', '-', $value) ?? '';
    return trim($value, '-');
}

function sanitizeAnswersFile(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (!preg_match('/^[a-zA-Z0-9_\-\.]+\.json$/', $value)) {
        return '';
    }

    if (str_contains($value, '..') || str_contains($value, ':') || str_starts_with($value, '/')) {
        return '';
    }

    return $value;
}

function normalizeSections($sections): array
{
    if (!is_array($sections)) {
        return [];
    }

    $normalized = [];
    foreach ($sections as $name => $range) {
        if (!is_string($name) || !is_array($range) || count($range) < 2) {
            continue;
        }

        $start = (int) $range[0];
        $end = (int) $range[1];
        if ($start < 1 || $end < $start) {
            continue;
        }

        $normalized[trim($name)] = [$start, $end];
    }

    return $normalized;
}

function normalizeScoring(array $input): array
{
    return [
        'correct' => isset($input['correct']) ? (int) $input['correct'] : DEFAULT_SCORING['correct'],
        'wrong' => isset($input['wrong']) ? (int) $input['wrong'] : DEFAULT_SCORING['wrong'],
        'blank' => isset($input['blank']) ? (int) $input['blank'] : DEFAULT_SCORING['blank'],
    ];
}

function loadCorrectAnswers(string $filePath): array
{
    $decoded = readJsonFile($filePath, []);
    if (!is_array($decoded)) {
        return [];
    }

    $answers = [];
    foreach ($decoded as $question => $option) {
        $q = (int) $question;
        $o = (int) $option;
        if ($q > 0 && $o >= 1 && $o <= 4) {
            $answers[$q] = $o;
        }
    }

    ksort($answers);
    return $answers;
}

function saveCorrectAnswers(string $filePath, array $answers): bool
{
    $normalized = [];
    foreach ($answers as $question => $option) {
        $q = (int) $question;
        $o = (int) $option;
        if ($q > 0 && $o >= 1 && $o <= 4) {
            $normalized[(string) $q] = $o;
        }
    }

    ksort($normalized, SORT_NUMERIC);
    return writeJsonFile($filePath, $normalized);
}

function listAnswerJsonFiles(): array
{
    $files = glob(__DIR__ . '/*.json');
    if ($files === false) {
        return [];
    }

    $names = [];
    foreach ($files as $filePath) {
        $name = basename($filePath);
        if ($name === basename(EXAMS_FILE) || $name === basename(ATTEMPTS_FILE)) {
            continue;
        }
        $names[] = $name;
    }

    sort($names);
    return $names;
}

function loadExamProfiles(): array
{
    $raw = readJsonFile(EXAMS_FILE, []);
    $profiles = [];

    if (isset($raw['profiles']) && is_array($raw['profiles'])) {
        $profiles = $raw['profiles'];
    } elseif (is_array($raw)) {
        $profiles = $raw;
    }

    $normalized = [];
    foreach ($profiles as $profile) {
        if (!is_array($profile)) {
            continue;
        }

        $id = sanitizeId((string) ($profile['id'] ?? ''));
        $title = trim((string) ($profile['title'] ?? 'Untitled Exam'));
        $answersFile = sanitizeAnswersFile((string) ($profile['answers_file'] ?? ''));
        $durationSeconds = max(60, (int) ($profile['duration_seconds'] ?? DEFAULT_DURATION_SECONDS));
        $subject = trim((string) ($profile['subject'] ?? 'General'));
        $year = trim((string) ($profile['year'] ?? 'N/A'));
        $sections = normalizeSections($profile['sections'] ?? []);
        $defaultScoring = normalizeScoring(is_array($profile['default_scoring'] ?? null) ? $profile['default_scoring'] : []);

        if ($id === '' || $answersFile === '' || empty($sections)) {
            continue;
        }

        $normalized[] = [
            'id' => $id,
            'title' => $title,
            'answers_file' => $answersFile,
            'duration_seconds' => $durationSeconds,
            'sections' => $sections,
            'subject' => $subject,
            'year' => $year,
            'default_scoring' => $defaultScoring,
        ];
    }

    if (!empty($normalized)) {
        return $normalized;
    }

    return [[
        'id' => 'assessment-default',
        'title' => 'Assessment Default Exam',
        'answers_file' => 'answers.json',
        'duration_seconds' => DEFAULT_DURATION_SECONDS,
        'sections' => [
            'English' => [1, 25],
            'Mathematics' => [26, 45],
            'Special 1' => [46, 55],
            'Special 2' => [56, 75],
            'Special 3' => [76, 95],
            'Special 4' => [96, 115],
        ],
        'subject' => 'General',
        'year' => 'N/A',
        'default_scoring' => DEFAULT_SCORING,
    ]];
}

function saveExamProfiles(array $profiles): bool
{
    $normalized = [];
    foreach ($profiles as $profile) {
        if (!is_array($profile)) {
            continue;
        }

        $id = sanitizeId((string) ($profile['id'] ?? ''));
        $answersFile = sanitizeAnswersFile((string) ($profile['answers_file'] ?? ''));
        $sections = normalizeSections($profile['sections'] ?? []);

        if ($id === '' || $answersFile === '' || empty($sections)) {
            continue;
        }

        $normalized[] = [
            'id' => $id,
            'title' => trim((string) ($profile['title'] ?? $id)),
            'subject' => trim((string) ($profile['subject'] ?? 'General')),
            'year' => trim((string) ($profile['year'] ?? 'N/A')),
            'answers_file' => $answersFile,
            'duration_seconds' => max(60, (int) ($profile['duration_seconds'] ?? DEFAULT_DURATION_SECONDS)),
            'sections' => $sections,
            'default_scoring' => normalizeScoring(is_array($profile['default_scoring'] ?? null) ? $profile['default_scoring'] : []),
        ];
    }

    return writeJsonFile(EXAMS_FILE, ['profiles' => $normalized]);
}

function getExamById(array $profiles, string $id): array
{
    foreach ($profiles as $profile) {
        if (($profile['id'] ?? '') === $id) {
            return $profile;
        }
    }

    return $profiles[0];
}

function getAnswersPathForExam(array $exam): string
{
    return __DIR__ . '/' . $exam['answers_file'];
}

function loadExamContext(string $examId): array
{
    $profiles = loadExamProfiles();
    $profile = getExamById($profiles, sanitizeId($examId));
    $answersPath = getAnswersPathForExam($profile);
    $correctAnswers = loadCorrectAnswers($answersPath);

    return [
        'profile' => $profile,
        'profiles' => $profiles,
        'correct_answers' => $correctAnswers,
        'question_count' => getQuestionCount($correctAnswers, $profile['sections']),
    ];
}

function getQuestionCount(array $correctAnswers, array $sections): int
{
    $maxFromAnswers = empty($correctAnswers) ? 0 : max(array_keys($correctAnswers));
    $maxFromSections = 0;

    foreach ($sections as $range) {
        $maxFromSections = max($maxFromSections, (int) $range[1]);
    }

    return max($maxFromAnswers, $maxFromSections);
}

function normalizeUserAnswers(array $input): array
{
    $answers = [];
    foreach ($input as $question => $option) {
        $q = (int) $question;
        $o = (int) $option;
        if ($q > 0 && $o >= 1 && $o <= 4) {
            $answers[$q] = $o;
        }
    }

    return $answers;
}

function getSectionNameForQuestion(int $questionNumber, array $sections): string
{
    foreach ($sections as $name => $range) {
        if ($questionNumber >= $range[0] && $questionNumber <= $range[1]) {
            return $name;
        }
    }

    return 'Uncategorized';
}

function evaluateExam(array $correctAnswers, array $userAnswers, array $sections, array $scoring): array
{
    $questionCount = getQuestionCount($correctAnswers, $sections);

    $sectionStats = [];
    foreach ($sections as $name => $range) {
        $sectionStats[$name] = [
            'range' => $range,
            'correct' => 0,
            'wrong' => 0,
            'blank' => 0,
            'score' => 0,
            'accuracy' => 0,
        ];
    }

    $sectionStats['Uncategorized'] = [
        'range' => [1, $questionCount],
        'correct' => 0,
        'wrong' => 0,
        'blank' => 0,
        'score' => 0,
        'accuracy' => 0,
    ];

    $totalScore = 0;
    $totalCorrect = 0;
    $totalWrong = 0;
    $totalBlank = 0;

    for ($q = 1; $q <= $questionCount; $q++) {
        if (!isset($correctAnswers[$q])) {
            continue;
        }

        $section = getSectionNameForQuestion($q, $sections);
        if (!isset($sectionStats[$section])) {
            $section = 'Uncategorized';
        }

        if (!isset($userAnswers[$q])) {
            $totalBlank++;
            $sectionStats[$section]['blank']++;
            $totalScore += $scoring['blank'];
            $sectionStats[$section]['score'] += $scoring['blank'];
            continue;
        }

        if ($userAnswers[$q] === $correctAnswers[$q]) {
            $totalScore += $scoring['correct'];
            $totalCorrect++;
            $sectionStats[$section]['score'] += $scoring['correct'];
            $sectionStats[$section]['correct']++;
        } else {
            $totalScore += $scoring['wrong'];
            $totalWrong++;
            $sectionStats[$section]['score'] += $scoring['wrong'];
            $sectionStats[$section]['wrong']++;
        }
    }

    foreach ($sectionStats as $name => &$stats) {
        $answered = $stats['correct'] + $stats['wrong'];
        $stats['accuracy'] = $answered > 0 ? round(($stats['correct'] / $answered) * 100, 2) : 0;

        if ($stats['correct'] === 0 && $stats['wrong'] === 0 && $stats['blank'] === 0) {
            unset($sectionStats[$name]);
        }
    }
    unset($stats);

    $percentage = $questionCount > 0 ? round(($totalCorrect / $questionCount) * 100, 2) : 0;
    $weakestSection = null;

    foreach ($sectionStats as $name => $stats) {
        if ($name === 'Uncategorized') {
            continue;
        }

        if ($weakestSection === null || $stats['accuracy'] < $sectionStats[$weakestSection]['accuracy']) {
            $weakestSection = $name;
        }
    }

    return [
        'question_count' => $questionCount,
        'total_score' => $totalScore,
        'total_correct' => $totalCorrect,
        'total_wrong' => $totalWrong,
        'total_blank' => $totalBlank,
        'total_answered' => $totalCorrect + $totalWrong,
        'percentage' => $percentage,
        'section_stats' => $sectionStats,
        'weakest_section' => $weakestSection,
        'scoring' => $scoring,
    ];
}

function loadAttempts(): array
{
    $attempts = readJsonFile(ATTEMPTS_FILE, []);
    return is_array($attempts) ? $attempts : [];
}

function saveAttempt(array $attempt): bool
{
    $attempts = loadAttempts();
    $attempts[] = $attempt;
    return writeJsonFile(ATTEMPTS_FILE, $attempts);
}

function saveAttempts(array $attempts): bool
{
    return writeJsonFile(ATTEMPTS_FILE, array_values($attempts));
}

function clearAttempts(): bool
{
    return writeJsonFile(ATTEMPTS_FILE, []);
}

function getAttemptById(string $attemptId): ?array
{
    foreach (loadAttempts() as $attempt) {
        if (($attempt['id'] ?? '') === $attemptId) {
            return $attempt;
        }
    }

    return null;
}

function getRecentAttemptsByExam(string $examId, int $limit = 8): array
{
    $examId = sanitizeId($examId);
    $filtered = array_values(array_filter(loadAttempts(), static function (array $attempt) use ($examId): bool {
        return ($attempt['exam_id'] ?? '') === $examId;
    }));

    usort($filtered, static function (array $a, array $b): int {
        $aTime = strtotime((string) ($a['submitted_at'] ?? '')) ?: 0;
        $bTime = strtotime((string) ($b['submitted_at'] ?? '')) ?: 0;
        return $bTime <=> $aTime;
    });

    return array_slice($filtered, 0, max(1, $limit));
}

function createAttemptId(): string
{
    return 'att_' . bin2hex(random_bytes(8));
}

function validateSections(array $sections, int $maxQuestion): array
{
    $warnings = [];
    $used = [];

    foreach ($sections as $name => $range) {
        $start = (int) ($range[0] ?? 0);
        $end = (int) ($range[1] ?? 0);

        if ($start < 1 || $end < $start) {
            $warnings[] = 'Section "' . $name . '" has an invalid range.';
            continue;
        }

        if ($maxQuestion > 0 && $end > $maxQuestion) {
            $warnings[] = 'Section "' . $name . '" extends beyond answer key max question (' . $maxQuestion . ').';
        }

        for ($q = $start; $q <= $end; $q++) {
            if (isset($used[$q])) {
                $warnings[] = 'Section overlap detected at question ' . $q . '.';
                break;
            }
            $used[$q] = true;
        }
    }

    return array_values(array_unique($warnings));
}

function getAttemptsByExam(string $examId): array
{
    $examId = sanitizeId($examId);
    return array_values(array_filter(loadAttempts(), static function (array $attempt) use ($examId): bool {
        return (string) ($attempt['exam_id'] ?? '') === $examId;
    }));
}

function deleteAttemptById(string $attemptId): bool
{
    $attemptId = trim($attemptId);
    if ($attemptId === '') {
        return false;
    }

    $attempts = loadAttempts();
    $before = count($attempts);
    $attempts = array_values(array_filter($attempts, static function (array $attempt) use ($attemptId): bool {
        return (string) ($attempt['id'] ?? '') !== $attemptId;
    }));

    if (count($attempts) === $before) {
        return false;
    }

    return saveAttempts($attempts);
}

function purgeAttemptsByExam(string $examId): int
{
    $examId = sanitizeId($examId);
    $attempts = loadAttempts();
    $before = count($attempts);

    $attempts = array_values(array_filter($attempts, static function (array $attempt) use ($examId): bool {
        return (string) ($attempt['exam_id'] ?? '') !== $examId;
    }));

    if (!saveAttempts($attempts)) {
        return 0;
    }

    return $before - count($attempts);
}

function compareSubmittedAtDesc(array $a, array $b): int
{
    $aTime = strtotime((string) ($a['submitted_at'] ?? '')) ?: 0;
    $bTime = strtotime((string) ($b['submitted_at'] ?? '')) ?: 0;
    return $bTime <=> $aTime;
}

function compareSubmittedAtAsc(array $a, array $b): int
{
    return compareSubmittedAtDesc($b, $a);
}

function getExamAnalytics(string $examId): array
{
    $attempts = getAttemptsByExam($examId);
    usort($attempts, 'compareSubmittedAtAsc');

    if (empty($attempts)) {
        return [
            'attempt_count' => 0,
            'average_score' => 0,
            'best_score' => 0,
            'worst_score' => 0,
            'average_accuracy' => 0,
            'improvement' => 0,
            'score_series' => [],
            'section_accuracy' => [],
            'weak_sections' => [],
        ];
    }

    $sumScore = 0.0;
    $sumAccuracy = 0.0;
    $bestScore = null;
    $worstScore = null;
    $scoreSeries = [];
    $sectionSums = [];
    $sectionCounts = [];

    foreach ($attempts as $attempt) {
        $score = (float) (($attempt['results']['total_score'] ?? 0));
        $accuracy = (float) (($attempt['results']['percentage'] ?? 0));
        $sumScore += $score;
        $sumAccuracy += $accuracy;
        $bestScore = $bestScore === null ? $score : max($bestScore, $score);
        $worstScore = $worstScore === null ? $score : min($worstScore, $score);

        $scoreSeries[] = [
            'attempt_id' => (string) ($attempt['id'] ?? ''),
            'submitted_at' => (string) ($attempt['submitted_at'] ?? ''),
            'score' => $score,
            'accuracy' => $accuracy,
        ];

        $sectionStats = is_array($attempt['results']['section_stats'] ?? null) ? $attempt['results']['section_stats'] : [];
        foreach ($sectionStats as $name => $stats) {
            if ($name === 'Uncategorized') {
                continue;
            }
            $sectionSums[$name] = ($sectionSums[$name] ?? 0.0) + (float) ($stats['accuracy'] ?? 0);
            $sectionCounts[$name] = ($sectionCounts[$name] ?? 0) + 1;
        }
    }

    $sectionAccuracy = [];
    foreach ($sectionSums as $name => $sum) {
        $sectionAccuracy[$name] = round($sum / max(1, (int) ($sectionCounts[$name] ?? 1)), 2);
    }
    asort($sectionAccuracy);

    $weakSections = array_slice(array_keys($sectionAccuracy), 0, 3);

    $firstScore = (float) ($scoreSeries[0]['score'] ?? 0);
    $lastScore = (float) ($scoreSeries[count($scoreSeries) - 1]['score'] ?? 0);

    return [
        'attempt_count' => count($attempts),
        'average_score' => round($sumScore / count($attempts), 2),
        'best_score' => (float) $bestScore,
        'worst_score' => (float) $worstScore,
        'average_accuracy' => round($sumAccuracy / count($attempts), 2),
        'improvement' => round($lastScore - $firstScore, 2),
        'score_series' => $scoreSeries,
        'section_accuracy' => $sectionAccuracy,
        'weak_sections' => $weakSections,
    ];
}

function getGlobalAnalytics(): array
{
    $profiles = loadExamProfiles();
    $allAttempts = loadAttempts();
    $examStats = [];

    foreach ($profiles as $profile) {
        $examId = (string) ($profile['id'] ?? '');
        $stats = getExamAnalytics($examId);
        $examStats[] = [
            'exam_id' => $examId,
            'title' => (string) ($profile['title'] ?? $examId),
            'attempt_count' => (int) $stats['attempt_count'],
            'average_score' => (float) $stats['average_score'],
            'average_accuracy' => (float) $stats['average_accuracy'],
        ];
    }

    usort($examStats, static function (array $a, array $b): int {
        return $b['attempt_count'] <=> $a['attempt_count'];
    });

    return [
        'total_attempts' => count($allAttempts),
        'exam_count' => count($profiles),
        'exam_stats' => $examStats,
    ];
}

function attemptsToCsv(array $attempts): string
{
    $stream = fopen('php://temp', 'r+');
    if ($stream === false) {
        return '';
    }

    fputcsv($stream, [
        'attempt_id',
        'exam_id',
        'exam_title',
        'submitted_at',
        'total_score',
        'total_correct',
        'total_wrong',
        'total_blank',
        'accuracy',
    ]);

    foreach ($attempts as $attempt) {
        fputcsv($stream, [
            (string) ($attempt['id'] ?? ''),
            (string) ($attempt['exam_id'] ?? ''),
            (string) ($attempt['exam_title'] ?? ''),
            (string) ($attempt['submitted_at'] ?? ''),
            (string) ($attempt['results']['total_score'] ?? 0),
            (string) ($attempt['results']['total_correct'] ?? 0),
            (string) ($attempt['results']['total_wrong'] ?? 0),
            (string) ($attempt['results']['total_blank'] ?? 0),
            (string) ($attempt['results']['percentage'] ?? 0),
        ]);
    }

    rewind($stream);
    $content = stream_get_contents($stream);
    fclose($stream);
    return $content === false ? '' : $content;
}

