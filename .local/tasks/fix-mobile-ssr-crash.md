# Fix Mobile App SSR Crash

## What & Why
The Expo web preview crashes with `Error: Premature close` because `app.json` sets `"output": "static"` (SSR/SSG mode). The Metro SSR renderer tries to server-render every route, encounters deprecated `shadow*` style props that React Native Web 0.20+ no longer supports, and crashes the Node.js response stream before it finishes — leaving the artifact in a broken state.

## Done looks like
- The Expo web preview loads without a crash screen
- No `Error: Premature close` in the Mobile workflow logs
- No `"shadow*" style props are deprecated` warnings in the console

## Out of scope
- Native iOS/Android builds
- Adding real payment or video features
- Changing any visual design

## Steps

1. **Switch web output to SPA mode** — In `app.json`, change `"output": "static"` to `"output": "single"`. This disables SSR and makes the app a single-page app; there is no server-side render stream to crash.

2. **Replace deprecated shadow* style props** — In every screen/component file that has `shadowColor`, `shadowOpacity`, `shadowRadius`, or `shadowOffset` inside a `StyleSheet.create` block, replace those four props with a single `boxShadow` string equivalent (e.g. `boxShadow: '0px 2px 8px rgba(0,0,0,0.05)'`). Affected files include `(tabs)/index.tsx`, `(tabs)/buscar.tsx`, `materia/[id].tsx`, `materia/clase.tsx`, `pago/[id].tsx`, `profesor/dashboard.tsx`, and `(auth)/login.tsx`.

3. **Restart the ClassExpress Mobile workflow** — After both edits, restart the workflow so Metro picks up the `app.json` change and rebuilds cleanly.

## Relevant files
- `webapps/classexpress-mobile/app.json`
- `webapps/classexpress-mobile/app/(tabs)/index.tsx`
- `webapps/classexpress-mobile/app/(tabs)/buscar.tsx`
- `webapps/classexpress-mobile/app/materia/[id].tsx`
- `webapps/classexpress-mobile/app/materia/clase.tsx`
- `webapps/classexpress-mobile/app/pago/[id].tsx`
- `webapps/classexpress-mobile/app/profesor/dashboard.tsx`
- `webapps/classexpress-mobile/app/(auth)/login.tsx`
