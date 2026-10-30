# Lessons

- When a project specification (PRD, ADR, SDD) or user configuration explicitly declares an infrastructure dependency (such as MariaDB), do not unilaterally fallback or switch to an alternate system (like SQLite) merely because the service is offline or uninitialized; instead, spin up the required service (e.g. via Docker Compose / Docker container) or verify/restore the declared stack.
- When writing code, services, seeders, UI labels, document templates, or task artifacts, always use English exclusively and avoid mixing Indonesian or non-English terminology (such as "Surat Jalan" or local address/label terms), ensuring complete internationalization and consistency across the codebase.
