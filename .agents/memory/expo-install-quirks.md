---
name: Expo install in PHP subfolder on Replit
description: How to successfully install Expo packages in a subdirectory of a PHP Replit project
---

## Rule
Use `npm install --legacy-peer-deps` with semver range versions (^/~), never pinned exact versions like `18.3.2`. The Replit package proxy may not carry every exact patch version.

**Why:** The Replit npm proxy blocks or doesn't cache every exact patch version. Pinned `react@18.3.2` fails with "No matching version found" while `react@^18.2.0` resolves correctly.

## Must-install packages not pulled in automatically
These are NOT in expo-router's direct dependency list but are required at runtime for web bundling:
- `expo-linking` (required by expo-router's routing module)
- `expo-asset` (required by expo-router's render pipeline)  
- `expo-constants` (required by expo at runtime)
- `react-dom` (required for web mode)
- `react-native-web` (required for web mode)

Missing any of these produces: `Error: Premature close` / `Web Bundling failed`.

## Install command
```bash
cd webapps/classexpress-mobile && npm install --legacy-peer-deps
```

## Workflow command
```
cd /home/runner/workspace/webapps/classexpress-mobile && npx expo start --web --port 8080
```
Uses port 8080 (outputType: "console" in Replit workflow config).
