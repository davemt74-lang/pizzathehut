# Pizza Lab v1.5.10 — Chat Navigation and Commands

- Hamburger in Agent Chat header; left slide-out navigation with backdrop, keyboard Escape/focus trapping and Super Admin-only team link.
- Deterministic, safe chat commands for recipe search, batch selection, workday retrieval, in-chat camera and help. The footer microphone uses the same text-command pathway.
- Workday creation requires explicit chat confirmation before making an authenticated, CSRF-protected API request.
- Camera stays in the chat canvas, chat composer remains fixed, original pages continue as optional navigation destinations.
- Restored missing experiment AI dependency so the existing experiment API can load; reviews are stored with an immutable evidence snapshot.
- Added parser, UI safety, navigation contracts and packaging tests. No database schema migration.
- Live MySQL, iPhone microphone/camera, and Claude API certification pending.
