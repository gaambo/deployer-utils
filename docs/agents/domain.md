# Domain Docs

How engineering skills should consume this repo's domain documentation.

## Before exploring

- Read root `CONTEXT.md`.
- Read relevant decisions in `docs/adr/`.

If these files do not exist, proceed silently. The domain-modeling skill creates them when needed.

## Layout

This is a single-context repository:

```text
/
├── CONTEXT.md
├── docs/adr/
└── src/
```

Use the vocabulary in `CONTEXT.md`. Surface conflicts with relevant ADRs rather than silently overriding them.
