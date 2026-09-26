# BarangGabay

BarangGabay is a PHP/MySQL web application for barangay announcements, events, ordinances, and AI-assisted policy summaries.

## Setup

1. Copy `.env.example` to `.env` and fill database and API values.
2. Run `composer install`.
3. Import `database/schema.sql` into MySQL.
4. Open the app at `http://localhost/BarangGabay`.

## Notes

- Use `public/` as the document root if possible.
- Upload files are saved to `public/uploads`.
- AI routes require `ANTHROPIC_API_KEY`.

## Testing

- The project now includes PHPUnit scaffolding under `tests/`.
- Run tests after installing dev dependencies:

```bash
composer install
composer test
```

## Continuous Integration

- A GitHub Actions workflow is configured in `.github/workflows/phpunit.yml`.
- It installs dependencies, strips BOMs, and runs PHPUnit on push / pull request.
