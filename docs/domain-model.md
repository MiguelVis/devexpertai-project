# Domain Model

## Core Concepts

| Concept | Key attributes |
| --- | --- |
| User | username, password hash, first name, last names, is service account |
| Work Order | created at, creator, assignee (optional), status, location, typology L1/L2/L3, priority, description |
| Status History Entry | work order, timestamp, status, user who caused it |
| Typology L1 / L2 / L3 | name, parent (L2 → L1, L3 → L2); L3 also has a default priority |
| Location | name, location type |
| Location Type | name: Habitación, Zona común, Establecimiento |

Priority values: Baja, Media, Alta. Status values: Pendiente, Asignado, En resolución, Resuelto, Cancelado.

## Relationships

- A work order has exactly one creator, zero or one assignee, one location and one L3 typology (L1 and L2 follow from it).
- A work order has one or more status history entries, ordered by time; the latest matches its current status.
- Typologies form a strict tree: each L2 has one L1 parent and each L3 has one L2 parent.
- Each location has one location type. There is one hotel, so locations have no parent.
- Service accounts can authenticate but are never creators or assignees.

## States and Lifecycles

```
            assign                start
Pendiente ─────────▶ Asignado ─────────▶ En resolución ─────▶ Resuelto (closed)
    ▲                   │ unassign                │
    └───────────────────┘                         │
 any non-closed status ──────────────────────────────────────▶ Cancelado (closed)
```

- **On creation:** Asignado if there is an assignee, otherwise Pendiente.
- **Assign / unassign:** setting an assignee on a Pendiente order makes it Asignado; removing the assignee from an Asignado order makes it Pendiente.
- **En resolución:** requires an assignee.
- **Resuelto:** reached from En resolución.
- **Cancelado:** reachable from any non-closed status.
- **Closed (Resuelto, Cancelado):** final; the work order can no longer be edited.
- Every status change, including the initial one, writes a status history entry.
- Priority is prefilled from the L3 typology's default priority whenever the L3 is chosen or changed. The user can override it when creating or editing.

## Important Scenarios

1. "El aire de la 214 no enfría, asígnamelo" → location Habitación 214, Avería > Climatización > AC no enfría, assignee is the creator, status Asignado, priority from that L3.
2. "Gotea el grifo de la piscina, que lo mire Juan" → location Piscina, typology inferred, assignee Juan, status Asignado.
3. "Hay una bombilla fundida en el pasillo" with no matching location → location blank with a prefill note; the user picks it manually.

## Edge Cases

- **Named user is ambiguous** (two users called Juan) or doesn't exist → assignee blank with a prefill note.
- **Name is a service account** → never suggested.
- **The AI returns IDs** that don't exist, or an L3 that isn't under the returned L2/L1 → the backend discards them and shows a note.
- **Unassigning an En resolución order** → not allowed. The user must first move the order back to a valid status, or cancel it.
- **Editing a closed order** → rejected by the API.
