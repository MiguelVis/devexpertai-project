---
name: MaintOps AI (Hotel Maintenance PoC)
hotelName: "Demo Hotel"
description: Calm, operational, mobile-first tool built on W3.CSS, styled after the Stitch designs; teal brand, clear status colours, large touch targets.
designAssets:
  sourceOfTruth: []
  layoutReference: "docs/design/stitch/ (mobile/ and desktop/ PNG exports, ui-styles.png)"
  brand: "public/assets/img/brand/ (favicon.png, logo-mark.png, logo-white.png, logo.png)"
  generatedConcepts: []
colors:
  primary: "#009688"        # Stitch primary. Bars and dialog headers only, with large bold white text (3.67:1)
  primaryStrong: "#00796B"  # buttons, FAB, links, active menu text, small text on white (5.32:1)
  primaryTint: "#E0F2F1"    # active menu item background
  primaryText: "#ffffff"
  secondary: "#2196F3"      # Stitch secondary. Decorative only (no text)
  secondaryStrong: "#1976D2"
  tertiary: "#607D8B"       # Stitch tertiary. Icon-button backgrounds with icons only
  tertiaryStrong: "#546E7A" # secondary text and icon-button text (5.4:1)
  neutral: "#282A35"        # Stitch neutral. Body text, desktop top bar, inverted buttons
  background: "#f4f6f8"
  surface: "#ffffff"
  border: "#cfd8dc"
  muted: "#5f6b7a"
  danger: "#C62828"
  ai: "#1976D2"             # AI-prefilled field highlight and "IA" label
status:                     # tag background / text, all >= 4.5:1
  Pendiente: { background: "#616161", text: "#ffffff" }
  Asignado: { background: "#1976D2", text: "#ffffff" }
  En resolución: { background: "#00796B", text: "#ffffff" }
  Resuelto: { background: "#2E7D32", text: "#ffffff" }
  Cancelado: { background: "#455A64", text: "#ffffff" }
priority:
  Baja: { background: "#ffffff", text: "#282A35", border: "#282A35" }
  Media: { background: "#FFC107", text: "#282A35" }
  Alta: { background: "#C62828", text: "#ffffff" }
typography:
  fontFamily: "'Source Sans 3', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif"
  source: "public/assets/vendor/source-sans-3/SourceSans3-latin.woff2 (local, no CDN), declared in assets/css/fonts.css"
  body: { fontSize: 16px, fontWeight: 400 }
  label: { fontSize: 14px, fontWeight: 600 }
  h1: { fontSize: 22px, fontWeight: 700 }
  barTitle: { fontSize: 20px, fontWeight: 700 }   # white text on primary must stay >= 18.66px bold
  sectionTitle: { fontSize: 14px, fontWeight: 700, textTransform: uppercase }
  tileNumber: { fontSize: 32px, fontWeight: 700 }
rounded:
  sm: 4px
  md: 8px
  pill: 999px
spacing:
  sm: 8px
  md: 16px
components:
  button-primary:
    backgroundColor: "{colors.primaryStrong}"
    textColor: "{colors.primaryText}"
    rounded: "{rounded.sm}"
  button-secondary:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.neutral}"
    border: "{colors.border}"
    rounded: "{rounded.sm}"
  fab-ai:
    backgroundColor: "{colors.primaryStrong}"
    textColor: "{colors.primaryText}"
    icon: auto_awesome
    label: "Nuevo con IA"
    height: 56px
    rounded: "{rounded.pill}"
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

MaintOps AI is a practical tool for staff on the move. It should be fast to read on a phone and impossible to mis-tap. The look follows the Stitch designs; W3.CSS classes do the work, and custom CSS only applies the tokens above.

## Existing Design Assets

Stitch exports (PNG) live in `docs/design/stitch/`: `mobile/` (360px-class) and `desktop/`, one file per screen, plus `ui-styles.png` (palette, type, buttons). They are the **layout reference**. This document wins whenever a screen conflicts with it, and product rules from `docs/build-brief.md`, `docs/domain-model.md` and `CONTEXT.md` always win over a screen. Sample data in the images (names, IDs like `#PT-2023-0891`, dates, room names) is illustrative only.

| Image (`mobile/` and `desktop/`) | Screen | Use it for | Ignore in it |
| --- | --- | --- | --- |
| `01-login.png` | 1. Login | Card layout, header, fields, button | "¿Olvidaste tu contraseña?", version / PWA footer |
| `02-dashboard.png` | 2. Dashboard | Tile style, FAB | The four tiles' content, "Histórico de actividad reciente", "Crítica" / "Material" tags |
| `03-work-order-list.png` | 3. Work order list | Card (mobile) and table (desktop), row buttons, "Nuevo parte", count badge, FAB | Search, filters, sorting, pagination, "Filas por página" |
| `04-work-order-detail.png` | 4. Status history dialog (part of the detail) | Timeline (mobile) and table (desktop) of status history entries | Per-entry notes, user roles, priority changes as entries, "registros auditados" footer |
| `05-new-work-order-with-ai.png` | 5a. AI description dialog | Whole dialog | "Asistente activo" microphone label |
| `05-new-work-order.png` | 5b. Work order form (create; reused for edit) | Whole form, field order | "Adjuntos" block, "Tomar foto", create-time "Estado" select |
| `99-sidebar-menu.png` (mobile only) | Side menu | Brand header, user block, items, logout | User role, "Online / MCP Activo", version footer |
| `ui-styles.png` | Style sheet | Palette families, Source Sans 3, button styles | Exact light shades of each ramp |

Labels in the images that differ from `CONTEXT.md` are replaced by the canonical terms: "En curso" → **En resolución**; "Crítica" and "Material" do not exist; "Técnico" in a label is fine, but the field is the **assignee**.

## Product Feel

Calm and operational, not decorative. Colour is used for meaning (brand, status, priority, AI), not for ornament.

## Colors

- **Primary (teal):** `primary` `#009688` for the top bar on mobile, the sidebar brand header, the login card header and dialog headers, always with white bold text of at least 18.66px (`barTitle`). `primaryStrong` `#00796B` for primary buttons, the FAB, links, the active menu item and any small text, because white on `#009688` is only 3.67:1.
- **Neutral** `#282A35`: body text, the desktop top bar, inverted buttons.
- **Tertiary** (blue grey): compact icon buttons and secondary text.
- **AI:** the AI action is recognisable by the `auto_awesome` icon on primary buttons ("Nuevo con IA", dialog 5a). The `ai` colour (`#1976D2`) is reserved for marking AI-prefilled fields for review.
- Status and priority are shown as small solid tags with text (`w3-tag`, colours from `status` and `priority`), never as colour alone. Baja is an outlined tag. The pastel tags in the mobile dashboard export are not used: tags look the same on every screen.

## Typography

Source Sans 3, hosted locally (`assets/css/fonts.css`), with the system font stack as fallback. 16px base (stops iOS zooming into inputs). Field labels 14px semibold above the input. Section titles in small uppercase bold, as in the Stitch cards ("PARTES", "HISTÓRICO DE CAMBIOS").

## Layout

- **Mobile (< 993px):** a primary-coloured top bar with the menu toggle, the brand (`logo-mark.png` at 32px, then "MaintOps AI" with the hotel name "Demo Hotel" below in small caps) and the user's initials in a round avatar. The side menu opens over the content (`99-sidebar-menu.png`).
- **Desktop (≥ 993px):** a persistent sidebar on the left (brand header in primary, menu items, "Cerrar sesión" at the bottom) and a neutral top bar showing the user's initials avatar and full name.
- **Side menu:** brand header (`logo-mark.png`, "MaintOps AI" and "Demo Hotel" below in small caps, with a close button on mobile), user block (initials avatar and full name), then "Dashboard" (`space_dashboard`) and "Partes de trabajo" (`assignment`). The active item has a left bar in `primaryStrong` and a `primaryTint` background. "Cerrar sesión" (`logout`) sits at the bottom in the danger colour. Creating work orders is done from the list's "Nuevo parte" button and the AI button, not from the menu.
- **Content width:** dashboard and list use the full content width. Forms, dialogs and the detail screen are at most about 720px wide.
- **Dialogs** (`w3-modal`): forms 5a, 5b and 6 and the status history open as dialogs over the current screen, with a primary header (icon, title in uppercase, close `close` button) and the actions in a light footer ("Cancelar" secondary, main action primary). On mobile they take almost the whole width.
- **Floating AI button:** bottom-right on the **dashboard and the work order list**, as a pill with `auto_awesome` and "Nuevo con IA". Not on the detail screen or inside dialogs.

## Shapes

4px corners (`w3-round`) for inputs, buttons, tags, cards and dialogs. Pill shape for the FAB and count badges. Light shadows only on cards and dialogs (`w3-card`).

## Components

- **Dashboard tile:** white card with a 3px top border in the tile's status or priority colour, the label in small uppercase, an icon at the top right, and the count as a large number (`tileNumber`).
- **Work order card (mobile list):** `#<id>` and created date on the first line with priority and status tags on the right; then "Ubicación", "Tipo" (L1 / L2 / L3) and "Técnico" (assignee name, or "Sin asignar" in the danger colour); compact icon buttons at the bottom right.
- **Work order table (desktop list):** columns Parte, Fecha, Prioridad, Ubicación, Tipo, Técnico, Estado, Acciones, in that order. No sort controls.
- **Row actions** (compact 44px icon buttons with `aria-label`): "Ver" (`visibility`) opens the detail; "Editar" (`edit`) opens form 6 and is hidden for closed orders; "Histórico" (`history`) opens the status history dialog.
- **Status history:** mobile shows one card per entry, newest first, with a left border in the status colour, the status tag, the date and time, and the user's name. Desktop shows a table: Fecha, Estado, Usuario. Only the fields a status history entry has (timestamp, status, user) are shown.
- **Work order form:** one form for create (manual and AI) and edit, shown as a dialog. Fields in the Stitch order: L1 → L2 → L3 cascaded selects, location, priority (set from the L3 default when L3 changes, editable), assignee, status (edit only), and the description (textarea) at the bottom. Required fields show a red asterisk. One column at 360px, two columns on wide screens (as in the Stitch desktop export). Footer: "Cancelar" and "Guardar" (`save`).
- **AI-prefilled fields:** a left border in the `ai` colour and an "IA" label. A **prefill note** appears below a blank field in muted text.
- **Loading state** for AI Prefill: a disabled "Enviar" button with a spinner and the text "Analizando…". It must handle about 15 seconds without looking frozen.
- **Errors:** a `w3-panel` in the danger colour at the top of the form or dialog.

## Brand

The hotel brand (navy building with a star, "Demo Hotel") lives in `public/assets/img/brand/` as transparent PNGs, exported at about 2× their display size. Use them as files; don't recolour, crop or redraw them in CSS. In HTML, set `width` and `height` to the displayed size and give each logo `alt="Demo Hotel"` (the favicon needs none).

| File | Size | Where | Display size |
| --- | --- | --- | --- |
| `favicon.png` | 180×180 | Browser tab and iPhone home-screen icon, in every page's `<head>`: `<link rel="icon" type="image/png" href="assets/img/brand/favicon.png">` and `<link rel="apple-touch-icon" href="assets/img/brand/favicon.png">` (relative URLs; no root `/favicon.ico`) | — |
| `logo-mark.png` | 330×313 | Navy square tile. Mobile top bar and sidebar brand header, left of "MaintOps AI" | 32px tall |
| `logo-white.png` | 714×374 | White stacked logo for teal backgrounds. Login card header | about 96px tall |
| `logo.png` | 714×374 | Navy stacked logo for white backgrounds. Not placed on any screen yet; use it wherever the brand appears on a white surface | — |

## Icons

- Use [Material Symbols](https://fonts.google.com/icons) **Outlined** (unfilled, weight 400), hosted locally. Link `assets/css/icons.css` on any page that shows icons. Never load the font from Google Fonts.
- Markup: `<span class="material-symbols-outlined" aria-hidden="true">edit</span>`. The text is the icon name from fonts.google.com/icons.
- Icons support text and never replace it, except in compact icon buttons. An icon-only button needs an `aria-label` in Spanish (for example `aria-label="Editar"`) and a 44px touch target.
- Colour follows the surrounding text or button. Colour still carries meaning only through status and priority tags, never through the icon alone.
- Filled icons and other weights are not available in the vendored file. See its `SOURCE.txt` before changing that.

## Core Screens

1. **Login** (Stitch `01-login.png`): a centred card. Primary header with the white stacked hotel logo (`logo-white.png`, about 96px tall, `alt="Demo Hotel"`), then "MaintOps AI" and the subtitle "Portal de Operaciones y Mantenimiento Hotelero". Fields "Usuario" and "Contraseña" (with a show/hide button, `visibility`), and a full-width "Iniciar sesión" button (`login`). Bad credentials show an error panel above the fields. No password recovery link and no version footer.
2. **Dashboard (home)** (Stitch `02-dashboard.png` for the look): two groups of tiles, "Por estado" (one tile per status with its count, all five) and "Abiertos por prioridad" (Alta, Media, Baja, counting open work orders). Two-column tile grid at 360px; on desktop, status tiles share one row and priority tiles another. The floating AI button is shown. No recent activity list.
3. **Work order list** (Stitch `03-work-order-list.png`): a header with the title "Partes", a count badge and a "Nuevo parte" button (`add`, opens the empty 5b form). Cards on mobile, a table on desktop, newest first, with the row actions above. The floating AI button is shown. No search, filters, sorting or pagination in the PoC.
4. **Work order detail:** no full Stitch screen. Show every field in the order of the 5b form, read-only, with the full description, then the status history (Stitch `04-work-order-detail.png` style). An "Editar" button opens form 6 for open orders. The history dialog from the list's "Histórico" button reuses the same history component.
5. **New work order**, in two steps for AI and one step for manual:
   - **5a. AI description dialog** (Stitch `05-new-work-order-with-ai.png`): a small dialog titled "Nuevo parte con IA" with a single "Descripción del parte" textarea, "Cancelar" and "Enviar". "Enviar" requests AI Prefill and shows the loading state ("Analizando…") inside this dialog. On success it closes and opens 5b with the AI values. On error it stays open with the description kept and an error panel. Opened from the floating AI button. Ignore the "Asistente activo" microphone label in the export: voice input is a non-goal.
   - **5b. Work order form, create** (Stitch `05-new-work-order.png`): the Work order form titled "Nuevo parte". In the AI flow it opens prefilled: the description from 5a, AI-prefilled fields highlighted and prefill notes on blank fields. The manual flow ("Nuevo parte" on the list) opens it empty. Status is not a field here: it is set on save (Asignado with an assignee, otherwise Pendiente). Attachments are a non-goal, so there is no "Adjuntos" block.
6. **Edit work order:** no Stitch screen. Reuse the 5b form, titled "Editar parte", filled with the order's current values and no AI highlights. Status is shown as a select offering only the valid next statuses. Fields that are not editable (see `api-update-work-order`) are shown read-only. Closed orders (Resuelto, Cancelado) offer no edit; their detail screen is read-only.

## Responsive Baseline

Design for 360px wide first. Touch targets are at least 44px. The side menu becomes persistent at 993px and above (the W3.CSS `large` breakpoint), which is also where the list switches from cards to a table.

## Accessibility Baseline

Every input has a `<label>`. Status and priority are always shown as text. WCAG AA contrast: 4.5:1 for normal text (every `status`, `priority` and `primaryStrong` pairing meets it) and 3:1 only for large bold text, which is the one case where white on `primary` is allowed. Dialogs move focus inside when opened, close with Escape and return focus to the button that opened them. Every action works by keyboard.

## Do's and Don'ts

- Do use W3.CSS utility classes before writing custom CSS.
- Do keep JavaScript limited to the menu toggle, opening and closing dialogs, the password show/hide button, cascading selects, the AI request and form submission.
- Don't add other icon sets, fonts or libraries. Material Symbols Outlined and Source Sans 3, both local, are the only ones.
- Don't style AI-prefilled values as final. The user must see what to review.
- Don't copy sample data, extra statuses or features from the Stitch images that the domain does not have.

## Open Design Questions

- UI language is assumed to be Spanish only.
