# Pizza Lab v1.5.7 — Automatic Cooking Activity

- Per-pizza immutable, idempotent structured event log linked to selected oven session when assigned.
- Timer start and stop (manual or voice), pause/resume/reset, timer alerts, manual physical rotation/pull-back confirmation, photo capture and AI analysis.
- Proactive rotation/pull-back reminders never become false recorded physical actions.
- Camera and live scanner save photo IDs to same evidence record; AI turn suggestions remain suggestions.
- Activity timeline displayed on live oven scanner and cooking heat-map detail; linked batch context available to Claude.
- Missing stone temperature, unknown elapsed time and unknown floor coordinates remain unknown.
- Existing customers run upgrade.php after database backup; camera and voice need foreground, HTTPS, permissions.
- GitHub source CI does not certify a live MySQL installation, physical oven, iPhone speech recognition, or Claude responses.
