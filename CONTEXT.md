# Context

Shared vocabulary for the hotel maintenance app. Documents are in English; UI labels and stored domain values are in Spanish (shown in parentheses).

## Glossary

### Work Order (Parte de trabajo)
A request to fix or check something in the hotel. Has a creator, an optional assignee, a status, a location, a three-level typology, a priority and the full free-text description written by the user. Code and API use `work-order`.

### Description
The complete free text the user wrote to report the problem. Stored verbatim; it is also the input for AI Prefill.

### Creator
The authenticated user who saved the work order. Set by the system, never inferred.

### Assignee
The user responsible for resolving a work order. Optional. Set manually or by AI Prefill when the description names a user or the creator self-assigns ("asígnamelo").

### Status
Lifecycle position of a work order: Pendiente, Asignado, En resolución, Resuelto, Cancelado. Resuelto and Cancelado are **closed** statuses.

### Status History Entry
An immutable record of a status change: timestamp, new status, and the user who caused it.

### Typology (Tipología)
Three-level classification of a work order. Level 1 is the kind of request (e.g. Avería), Level 2 the system or area (e.g. Climatización), Level 3 the specific problem (e.g. AC no enfría). Each level belongs to exactly one parent in the level above.

### Default Priority
The priority (Baja, Media, Alta) attached to a Level 3 typology. A work order's priority is prefilled from its Level 3 typology; the user can change it.

### Location (Localización)
A place in the hotel where work is needed, e.g. "Habitación 214", "Piscina". Locations form a flat list, each with one Location Type.

### Location Type
Category of a location: Habitación, Zona común, Establecimiento (the hotel as a whole).

### User
A person who logs in with username and password. All users have the same permissions in the proof of concept.

### Service Account
A non-human user the MCP server uses to log in to the API. It is not a person and must not be offered as an assignee.

### AI Prefill (Prerellenado por IA)
Turning a description into suggested values for location, typology L1/L2/L3 and assignee, shown in the creation form for the user to review before saving. Called "inference" in the API (`get-work-order-inference`).

### Prefill Note
A short message attached to a field the AI could not match confidently (e.g. "Habitación 999 no encontrada"). The field is left blank.

### MCP Server
The project's own stdio Model Context Protocol server. It gives the LLM read-only context (locations, typologies, users, work orders) by calling the API.

### LLM Provider
A configured external model endpoint (base URL, API key, model) that speaks the Anthropic Messages format, e.g. Anthropic API (Claude) or DevExpert inference.

## Rejected / Ambiguous Terms

### Task / Tarea / Orden de trabajo
Use `Work Order` (Parte de trabajo). All three were used for the same concept.

### Inference (as a user-facing term)
Use `AI Prefill` in product language. "Inference" stays only in the endpoint name.

### Claude Code (as a model)
Claude Code is an agent CLI, not a model. Use `Claude models via the Anthropic API`. Claude Code is not part of the runtime.

### Closed
Means Resuelto or Cancelado only, never "not visible" or "archived".
