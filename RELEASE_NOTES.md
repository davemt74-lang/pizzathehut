# Pizza Lab v1.5.5 — Dough Readiness & Oven/Cooking Evidence

- Added dough readiness observations for recipe-linked batches, with container temperatures and evidence-confidence caveats.
- Added desktop oven floor builder (NS/EW floor ellipse, centered inscribed circle, inside height, fire location, zones, real temperature readings).
- Added standalone pizza camera tracker with assisted low-confidence box detection and manual correction, private photo evidence, and Claude review.
- **Voice command:** With the tracker active, “Pizza, take photo” captures and analyzes the selected pizza. User start/stop timer commands remain in the existing scanner; “rotate,” “pull back,” and “time left” remain agent announcements.
- Added immutable photo box metadata and server-side ownership/evidence validation.
- Added automated PHP/Node syntax and feature contract tests.

Live MySQL, iPhone camera/microphone and Claude API behavior still require field tests; source/CI checks are not production certification.
