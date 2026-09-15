# POET Therapist Search Tool — Copilot Instructions

## Project Purpose

This project provides a public Hebrew RTL search tool for certified POET therapists.

The system currently contains approximately 380 therapist records.

The public-facing experience is a search/filter tool for parents looking for certified POET therapists.

Only the administrator manages therapist records.

## Architecture

The project includes:

- A WordPress plugin under `poet-directory/`
- A static public mirror under `public/`
- Therapist data under `data/`
- Supporting scripts under `scripts/`

WordPress uses the custom post type:

`poet_therapist`

Therapist records use a UUIDv5 identifier stored in:

`_poet_therapist_id`

## Therapist Data

Taxonomies include:

- Region
- Framework
- Language
- Modality

Metadata includes:

- Settlements
- Age range
- Phones
- Emails
- Notes
- MATI status
- Certification
- Visibility

Visibility is controlled using:

`_poet_visible`

## Admin Behavior

The administrator can:

- Add therapists
- Edit therapists
- Hide therapists
- Show therapists

Do not introduce additional workflow or moderation mechanisms unless explicitly requested.

Deletion of therapist records is intentionally disabled.

Required fields must continue to be enforced.

## Public Search

The public site is Hebrew and RTL.

Preserve:

- Existing search behavior
- Existing filters
- RTL layout
- Mobile usability
- Public read-only behavior

Do not expose administrative functionality through the public API.

## Static Mirror

The `public/` directory contains the static version of the therapist search tool.

It uses sanitized public therapist data.

Do not expose private/internal therapist fields in the static dataset.

## Development Rules

Before changing code:

1. Inspect the relevant existing implementation.
2. Understand the current behavior before proposing changes.
3. Prefer minimal changes over rewrites.
4. Preserve existing working functionality unless explicitly asked to change it.
5. Do not invent requirements.
6. Do not introduce new frameworks or dependencies unless necessary.
7. Keep WordPress and static implementations consistent when the requested change affects both.

When a requested change could affect existing behavior, explain the impact before implementing it.

## Working With The User

The user manages development through AI coding agents and does not currently write the implementation code manually.

For development requests:

- Inspect the repository first.
- State briefly what you found.
- Provide a concise implementation plan when the change is non-trivial.
- Then implement the requested change.
- After implementation, explain what changed and how to test it.

Do not make unrelated refactors or architectural changes.