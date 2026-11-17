# Board Question Solver
AI-powered board-exam preparation plugin for Alom's English Center LMS.

## Features
- Bangladesh education board, year, class, subject and question-type metadata.
- Typed/pasted question solving and optional JPG/PNG/WebP photo input (OCR/vision).
- Full worked solutions, hint-first practice and concept explanation modes.
- Bangla or English response language.
- Student-owned solution history and an Admin activity view.
- Admin-controlled enablement, image input, text/image limits and per-student daily request cap.
- Reuses the encrypted provider credential, model, endpoint allowlist and shared daily token budget from Universal AI Assistant. It does not add a second API-key store.
- Uploaded question images are processed in memory and are not persisted as files.

## Requirements
Universal AI Assistant must be installed and active, with the provider configured and global access enabled. The solver has its own student permission switch; the generic Student chat role switch is not required. For photo questions, select a vision-capable model. Provider token/request budgets are shared with Universal AI Assistant. No Google AI Studio branding or provider lock-in is included.

## Learning and integrity
This is for exam preparation, not for answering an active/timed examination. It asks the model to provide hints only when the learner indicates a live assessment. Generated marking points are suggestions, not official board marking schemes. Students should verify important answers with their teachers and textbooks.

Install and activate the signed plugin through Plugin Manager; grant board-solver.configure to Admin and board-solver.use to Students. Git source is not a signed production package.
