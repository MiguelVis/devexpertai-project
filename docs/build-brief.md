# Build Brief

Proof of concept of a web app for a hotel's maintenance staff, focused on creating work orders from free text with AI Prefill. Vocabulary: see [CONTEXT.md](../CONTEXT.md).

## Problem

Writing a complete, well-classified work order (exact location, three-level typology, assignee) is slow on a phone, so staff skip fields or misclassify. Staff can easily describe the problem in their own words ("el aire de la 214 no enfría, asígnamelo").

## Current Workaround / Existing System

No existing system in scope. The proof of concept assumes that work orders are currently created manually or informally.

## Target Users

Hotel maintenance staff (technicians and managers) working mostly from mobile devices. In the proof of concept everyone has one role with the same permissions.

## Goals

- Create a valid work order from a free-text description, with location, typology L1/L2/L3 and assignee prefilled by AI and reviewed by the user.
- Ground the AI in real hotel data through the project's own MCP server, never through invented values.
- Stay provider-agnostic: switching LLM provider is configuration only.
- Run locally on Windows with XAMPP, with no frameworks or libraries beyond W3.CSS (plus two locally hosted fonts, Material Symbols for icons and Source Sans 3 for text, approved 2026-11-27).

## Non-Goals

- Per-endpoint permissions or several roles.
- Several hotels or hierarchical locations.
- Reopening closed work orders.
- AI asking follow-up questions (multi-turn), or AI suggesting assignees by specialty or workload.
- Hosting deployment, PWA, HTTP MCP transport (see [risks and open questions](risks-and-open-questions.md)).
- An OpenAI-compatible adapter (the provider interface allows adding it later).
- Notifications, attachments/photos, voice input, preventive maintenance, reporting.
- Shown in the Stitch designs but out of the PoC: password recovery, a recent activity list on the dashboard, search/filters/sorting/pagination on the list, an online / MCP status indicator, user roles in the UI.

## MVP Slice

One end-to-end flow:

1. Log in (username + password, token issued) and land on the dashboard: work order counts by status, and open (not closed) work orders by priority.
2. Write a description (from the floating AI button on the dashboard or the list).
3. AI Prefill: the backend runs the LLM, which queries the MCP server for context, and returns suggested location, typologies and assignee. Unmatched fields are left blank, with no message to the user (the reason is a prefill note in the AI Prefill log).
4. Review and adjust the form. Priority is prefilled from the L3 typology and can be changed; status is Asignado if there is an assignee, otherwise Pendiente.
5. Save (`create-work-order`). A status history entry is recorded.
6. See the new work order in the list and open its detail.

After the slice: manual edit (`update-work-order`), logout.

## Validation Plan

- Evaluation set of about 20 realistic Spanish descriptions with expected location, typologies and assignee (including self-assignment, named technician, unknown room, ambiguous typology), run against the inference endpoint by a script.
- Backend checks for lifecycle rules and ID validation.
- Manual end-to-end walkthrough of the MVP slice on a mobile viewport.

## Success Criteria

- At least 80% of expected fields prefilled correctly across the evaluation set.
- Zero invalid IDs returned to the frontend (unmatched fields are blank, never invented).
- An AI Prefill response in about 15 seconds or less.
- The MVP slice works end to end locally on XAMPP.

## Notes

- Visual direction: [DESIGN.md](../DESIGN.md). Technology: [technical-discovery.md](technical-discovery.md). Key decision: [ADR 0001](adr/0001-php-backend-runs-agent-loop.md).
