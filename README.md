# Assessment Attempt Tracker

A PHP-based offline simulator for OMR-style multiple-choice exams with analytics, admin tools, and printable OMR exports.

## Requirements
- PHP 8.0+ recommended (PHP 7.4+ may work for many features)
- Apache (XAMPP/WAMP) or PHP built-in server
- Writable project directory for JSON persistence (`attempts.json`, `exams.json`, answer files)

## Quick Setup (XAMPP/WAMP)
1. Clone or download this repository.
2. Place it in your web root (example: `C:\xampp\htdocs\examsheetapp`).
3. Start Apache.
4. Open `http://localhost/examsheetapp/assessment-attempt-tracker/index.php`.

## Quick Setup (Built-in PHP server)
1. Open terminal in repo root.
2. Run:
   - `php -S localhost:8000 -t .`
3. Open `http://localhost:8000/assessment-attempt-tracker/index.php`.

## Offline Notes
- No internet/API dependency is required.
- All data is stored locally in JSON files.
- Do not expose this app publicly without adding authentication and server hardening.

## Main Features
- OMR-style answer-sheet UI
- Multi-exam profiles (`assessment-attempt-tracker/exams.json`)
- Dynamic answer keys (`assessment-attempt-tracker/*.json`)
- Timer + start button + reset
- Auto-grading (+/-/blank rules)
- Per-section analytics + trends
- Local chart rendering (`assessment-attempt-tracker/local-chart.js`) with no CDN
- Admin panel for profile/key/history operations
- Printable OMR-like export page
- Draft autosave in browser localStorage
- Dark mode toggle

## Admin Panel
Open:
- `http://localhost/examsheetapp/assessment-attempt-tracker/admin.php`

Key tools:
- Create/edit/clone/delete exam profiles
- Select existing answer file or type a new one
- Edit answer keys per question
- Import/export answer JSON
- Export attempts CSV
- Backup all JSON files (ZIP)
- Restore JSON files from backup ZIP
- Attempt management (delete single/purge per exam/global clear)

## Adding Exams
1. Use Admin panel profile editor or edit `assessment-attempt-tracker/exams.json` manually.
2. Point `answers_file` to a JSON file in `assessment-attempt-tracker`.
3. Answer key format:

```json
{
  "1": 2,
  "2": 4,
  "3": 1
}
```

Values must be `1..4`.

## Data Files
- `assessment-attempt-tracker/exams.json` - exam profiles
- `assessment-attempt-tracker/attempts.json` - attempt history
- `assessment-attempt-tracker/answers.json`, `assessment-attempt-tracker/answers_2024.json`, ... - answer keys

## Mobile Usage
The app is responsive, but OMR-style grids are dense. For best usability on phones, use landscape orientation.

## Security Reminder
This project intentionally has no login for personal/offline use. Avoid running it on shared/public networks unless you add authentication.


