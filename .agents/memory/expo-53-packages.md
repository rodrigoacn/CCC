---
name: Expo 53 correct package set
description: The exact package versions required for expo 53 to work correctly, especially expo-router 5.x
---

## Rule
Expo 53 requires expo-router ~5.1.11 (NOT 4.x). Using 4.x causes bundling failures on web.

## Full compatible set
```json
"expo": "^53.0.0",
"expo-asset": "~11.1.7",
"expo-constants": "~17.1.8",
"expo-font": "~13.3.2",
"expo-haptics": "~14.1.4",
"expo-linking": "~7.1.7",
"expo-router": "~5.1.11",
"expo-splash-screen": "~0.30.10",
"expo-status-bar": "~2.2.0",
"react": "19.0.0",
"react-dom": "19.0.0",
"react-native": "0.79.6",
"react-native-gesture-handler": "~2.24.0",
"react-native-reanimated": "~3.17.4",
"react-native-safe-area-context": "5.4.0",
"react-native-screens": "~4.11.1",
"react-native-web": "^0.20.0"
```

**Why:** Expo 53 ships with React 19 and React Native 0.79. expo-router jumped to v5 for expo 53. Using v4 router with expo 53 causes "Premature close" bundling errors.

**How to apply:** Always run `npx expo install` inside the project or check `npx expo doctor` to get the correct version matrix for the installed expo version.
