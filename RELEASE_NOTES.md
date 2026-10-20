# Pizza Lab v1.5.6 — Measured Cooking Heat Map

- New oven-linked pizza cooking sessions and manually observed oven positions.
- Record optional exact elapsed times, IR stone temperatures, named zones and chronological cooking events.
- View actual zone readings and distinct pizzas per zone, plus scored crispness, rise, and bottom color; missing metrics remain unknown.
- Bounded cooking history is made available to the existing Claude Agent Chat, labeled as observational.
- SQL migration and regression checks included. Source tests do not certify live MySQL, iPhone camera or Claude API.

# Pizza Lab v1.5.5 — Dough Readiness & Oven/Cooking Evidence

- Added dough readiness observations for recipe-linked batches, with container temperatures and evidence-confidence caveats.
- Added desktop oven floor builder (NS/EW floor ellipse, centered inscribed circle, inside height, fire location, zones, real temperature readings).
- Added standalone pizza camera tracker with assisted low-confidence box detection and manual correction, private photo evidence, and Claude review.
- **Voice command:** With the tracker active, “Pizza, take photo” captures and analyzes the selected pizza. User start/stop timer commands remain in the existing scanner; “rotate,” “pull back,” and “time left” remain agent announcements.
- Added immutable photo box metadata and server-side ownership/evidence validation.
- Added automated PHP/Node syntax and feature contract tests.

Live MySQL, iPhone camera/microphone and Claude API behavior still require field tests; source/CI checks are not production certification.
