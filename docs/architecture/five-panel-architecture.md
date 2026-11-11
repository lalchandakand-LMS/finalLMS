# Alom's English Center LMS — Five-panel architecture

This document records the approved portal boundary. It is an architecture contract, not a claim that every portal/module is already implemented.

## Primary portals

The product has exactly five primary portals:

1. **Admin Panel** — central system administration across all branches.
2. **Branch Panel** — branch-scoped operations for assigned branch managers.
3. **Teacher Panel** — teaching, class delivery, attendance, assessments and grading.
4. **Student Panel** — learning, classes, assignments, exams, results and fees.
5. **Guardian Panel** — read-only/limited oversight of explicitly linked students.

There is no separate Super Admin portal. A trusted Admin account has central authority through the Admin Panel.

## Admin modules

The following are modules inside Admin Panel, not separate portals: accounting/finance, SEO, website/CMS, media, admissions, user and role management, support, messaging/community moderation, plugins, settings, security, audit logs, backups, restore and Update Center.

Administrative staff may receive granular module permissions. The existence of a single Admin Panel does not mean every staff account receives unrestricted system-wide access.

## Branch Panel boundary

Branch Managers operate only on records belonging to their assigned branch. Admin can oversee all branches, reassign branch managers and inspect cross-branch reporting. Cross-branch isolation must be enforced server-side in every query and mutation, not merely hidden in navigation.

Accountant functionality moves into Admin modules. If a branch accountant is needed, grant only finance permissions scoped to the assigned branch; do not create an Accountant portal.

## Guardian privacy boundary

A guardian account must be explicitly linked to one or more student accounts through a verified relationship record. Guardians may only see permitted information for linked students: attendance, progress, published results, fee/invoice status, notices and approved communications. They must never gain access by guessing a student ID or URL.

## Extensibility

Each module should keep its own routes, controllers/services, policies and tests behind stable permission checks. This allows a future module to be split into a separate portal without rewriting its business logic.

## Migration and safety requirements

- Preserve existing users, role assignments, branches, payments, invoices, attendance and academic records.
- Migrate legacy accountant access into permission-controlled Admin modules without broadening privileges.
- Keep Branch Panel distinct.
- Add guardian relationship management with audit history and revocation.
- Apply server-side authorization and branch scoping to every relevant query and action.
- Test cross-branch isolation, guardian/student relationship privacy, Admin permissions and role migration.
- Do not automatically migrate production roles or deploy this architecture without reviewed CI and an approved release.

## Delivery status

The current codebase already has Admin, Branch Manager, Teacher and Student role-specific functionality in varying degrees. Guardian portal/relationship management and a unified granular Admin permission system remain implementation work. Existing Accountant and Branch Manager route boundaries also need careful consolidation/retention respectively. A passing CI run on an individual PR does not mean the complete five-portal architecture is finished.
