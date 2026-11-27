---
name: Hotel Maintenance PoC
description: Calm, operational, mobile-first tool built on W3.CSS; clear status colours, large touch targets.
designAssets:
  sourceOfTruth: []
  generatedConcepts: []
colors:
  primary: "#1f5f8b"
  primaryText: "#ffffff"
  accent: "#f39c12"
  background: "#f4f6f8"
  surface: "#ffffff"
  text: "#1d2733"
  muted: "#5f6b7a"
  danger: "#c0392b"
status:
  Pendiente: "#7f8c8d"
  Asignado: "#2980b9"
  En resolución: "#f39c12"
  Resuelto: "#27ae60"
  Cancelado: "#95a5a6"
priority:
  Baja: "#7f8c8d"
  Media: "#f39c12"
  Alta: "#c0392b"
typography:
  body:
    fontFamily: "system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif"
    fontSize: 16px
  h1:
    fontSize: 22px
    fontWeight: 600
rounded:
  sm: 4px
  md: 8px
spacing:
  sm: 8px
  md: 16px
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.primaryText}"
    rounded: "{rounded.md}"
  fab-ai:
    backgroundColor: "{colors.accent}"
    textColor: "#ffffff"
    size: 56px
icons:
  family: "Material Symbols Outlined"
  source: "public/assets/vendor/material-symbols/MaterialSymbolsOutlined.woff2 (local, no CDN)"
  stylesheet: "assets/css/icons.css"
  class: "material-symbols-outlined"
  fill: 0
  weight: 400
  size: 24px
---

# Design Direction

## Overview

A practical tool for staff on the move. It should be fast to read on a phone and impossible to mis-tap. W3.CSS classes do the work. Custom CSS only defines the tokens above.

## Existing Design Assets

Stitch screen exports (PNG) live in `docs/design/stitch/` (`mobile/` and `desktop/`). They are the visual reference for layout. This document wins whenever a screen conflicts with it, and product rules from `docs/build-brief.md` and `docs/domain-model.md` always win over a screen.

| Image (mobile and desktop) | Screen | Notes |
| --- | --- | --- |
| `05-new-work-order-with-ai.png` | 5a. AI description dialog | |
| `05-new-work-order.png` | 5b. Work order form (create; reused for edit) | Ignore the "Adjuntos" block and the create-time "Estado" select (see Core Screens) |

## Product Feel

Calm and operational, not decorative. Colour is used for meaning (status, priority, AI), not for ornament.

## Colors

- The primary colour is used for the header bar, primary buttons and active menu items.
- The accent colour is reserved for AI: the floating AI button, and the highlight on fields prefilled by AI.
- Status and priority are shown as small coloured tags with text (`w3-tag`), never as colour alone.

## Typography

System font stack, 16px base (stops iOS zooming into inputs), with a single heading size per screen.

## Layout

- A top bar with a menu toggle and the screen title. A collapsible side menu (`w3-sidebar`), hidden by default on mobile and visible on wide screens.
- Single-column content with a maximum width of about 720px, centred on desktop.
- The floating AI button sits bottom-right on the work order list screen only.

## Shapes

`w3-round` (4px) for inputs and tags, 8px for cards and buttons. Light shadows only on cards (`w3-card`).

## Components

- **Work order card (list):** location, L3 typology, status tag, priority tag, assignee, created date.
- **Work order form:** one form for create (manual and AI) and edit, shown as a dialog over the current screen (`w3-modal`, header in the primary colour, "Cancelar" and "Guardar" in the footer). Fields in the Stitch order: L1 → L2 → L3 cascaded selects, location, priority (set from the L3 default when L3 changes, editable), assignee, status (edit only), and the description (textarea) at the bottom. One column at 360px, two columns on wide screens (as in the Stitch desktop export).
- **AI-prefilled fields:** a left border in the accent colour and an "IA" label. A **prefill note** appears below a blank field in muted text.
- **Loading state** for AI Prefill: a disabled submit button with a spinner and the text "Analizando…". It must handle about 15 seconds without looking frozen.
- **Errors:** a `w3-panel` in the danger colour at the top of the form.

## Icons

- Use [Material Symbols](https://fonts.google.com/icons) **Outlined** (unfilled, weight 400), hosted locally. Link `assets/css/icons.css` on any page that shows icons. Never load the font from Google Fonts.
- Markup: `<span class="material-symbols-outlined" aria-hidden="true">edit</span>`. The text is the icon name from fonts.google.com/icons.
- Icons support text and never replace it, except in compact icon buttons. An icon-only button needs an `aria-label` in Spanish (for example `aria-label="Editar"`) and a 44px touch target.
- Colour follows the surrounding text or button. Colour still carries meaning only through status and priority tags, never through the icon alone.
- Filled icons and other weights are not available in the vendored file. See its `SOURCE.txt` before changing that.

## Core Screens

1. Login
2. Dashboard (home): one tile per status with its count, and one per priority (Alta, Media, Baja) counting open work orders. Status and priority tag colours, number plus text label. Two-column tile grid at 360px.
3. Work order list (with the floating AI button)
4. Work order detail
5. New work order, in two steps for AI and one step for manual:
   - **5a. AI description dialog** (Stitch `05-new-work-order-with-ai.png`): a small dialog titled "Nuevo parte con IA" with a single "Descripción del parte" textarea, "Cancelar" and "Enviar". "Enviar" requests AI Prefill and shows the loading state ("Analizando…") inside this dialog. On success it closes and opens 5b with the AI values. On error it stays open with the description kept and an error panel. Opened from the floating AI button on the list screen or from the menu. Ignore the "Asistente activo" microphone label in the export: voice input is a non-goal.
   - **5b. Work order form, create** (Stitch `05-new-work-order.png`): the Work order form titled "Nuevo parte". In the AI flow it opens prefilled: the description from 5a, AI-prefilled fields highlighted and prefill notes on blank fields. The manual flow opens it empty. Status is not a field here: it is set on save (Asignado with an assignee, otherwise Pendiente). Attachments are a non-goal, so there is no "Adjuntos" block.
6. Edit work order: no Stitch screen. Reuse the 5b form, titled "Editar parte", filled with the order's current values and no AI highlights. Status is shown as a select offering only the valid next statuses. Fields that are not editable (see `api-update-work-order`) are shown read-only. Closed orders (Resuelto, Cancelado) offer no edit; their detail screen is read-only.

## Responsive Baseline

Design for 360px wide first. Touch targets are at least 44px. The side menu becomes persistent at 993px and above (the W3.CSS `large` breakpoint).

## Accessibility Baseline

Every input has a `<label>`. Status and priority are always shown as text. WCAG AA contrast for text on the primary and accent colours. Every action works by keyboard.

## Do's and Don'ts

- Do use W3.CSS utility classes before writing custom CSS.
- Do keep JavaScript limited to the menu toggle, cascading selects, the AI request and form submission.
- Don't add other icon sets, fonts or libraries. Material Symbols Outlined (local) is the only icon source.
- Don't style AI-prefilled values as final. The user must see what to review.

## Open Design Questions

- UI language is assumed to be Spanish only.
- Hotel logo or name in the top bar: not provided yet.
