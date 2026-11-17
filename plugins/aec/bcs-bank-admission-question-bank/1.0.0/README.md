# BCS, Bank Job & Admission Question Bank

Admin-only question bank and exam-paper builder for Alom's English Center LMS.

## Features
- Separate BCS, Bank Job and University/College Admission tracks.
- Admin creates, edits, filters and deletes MCQ and written questions.
- Subject, topic, source exam/institution, source year and difficulty metadata.
- MCQ requires four distinct options and a correct option; written questions can store an expected answer and explanation.
- Build a paper by selecting individual questions from a filtered track or randomly selecting a requested count from the selected pool.
- Saved paper snapshots preserve the exact question text, options, answers and explanations even if the source bank question is later edited.
- Admin paper preview includes duration, total marks, answer key and print / Save as PDF.
- Existing paper items protect source questions from accidental deletion.
- All pages and routes are restricted to Admin permission `prep-question-bank.manage`.

## Install
Install and activate the signed plugin through Plugin Manager. Grant `prep-question-bank.manage` to trusted Admin roles. The plugin creates isolated `aec_prep_*` tables and does not modify the LMS's general course Question Bank or student exam tables.

## Scope
This version builds and prints admin-owned question papers. It does not yet publish papers to student accounts, conduct timed online attempts, or automatically grade written answers. Those can be added as a separate student examination workflow after the question bank and paper builder are validated.
