# Pizza Lab v1.5.8 — Cooking Patterns and Chat-First Voice UX

- Main screen is a minimal Agent Chat with sticky microphone, camera, message, and send controls.
- Microphone uses tap-to-speak browser recognition; a voice-initiated question can trigger spoken Claude summary. Fallback is manual text input. Replies may require user interaction for device playback.
- Camera footer opens the existing protected pizza tracker; no passive photo capture.
- Lab tools remain available for administration and manual analytics.
- Cooking Patterns provides recorded pizza counts, sample sizes, per-recipe/launch-zone score averages, and confirmed pull-back/rotation timings; agent reminders are not counted as physical movements.
- Existing batch intelligence remains read-only except for saving evidence-grounded reports. No schema migrations are required for this section.
- Code/CI certification does not replace live MySQL, Claude, or iPhone voice/camera acceptance.
