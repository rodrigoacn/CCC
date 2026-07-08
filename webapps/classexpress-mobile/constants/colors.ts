const palette = {
  light: {
    // Verde agua para botones principales (igual que web)
    primary: '#20c997',
    primaryLight: '#d1f2ea',
    accent: '#FF6B35',
    background: '#f8f9fa',
    surface: '#ffffff',
    card: '#ffffff',
    foreground: '#212529',
    subtext: '#6c757d',
    border: '#dee2e6',
    success: '#28a745',
    danger: '#dc3545',
    warning: '#ffc107',
    muted: '#e9ecef',
    mutedForeground: '#adb5bd',
    tabBar: '#ffffff',
    tabBarInactive: '#adb5bd',
    radius: 14,
  },
  dark: {
    // Verde agua para botones principales (igual que web)
    primary: '#20c997',
    primaryLight: '#1a7a5c',
    accent: '#FF7F4F',
    background: '#1a1a1a',
    surface: '#2d2d2d',
    card: '#2d2d2d',
    foreground: '#dee2e6',
    subtext: '#adb5bd',
    border: '#495057',
    success: '#28a745',
    danger: '#dc3545',
    warning: '#ffc107',
    muted: '#2d2d2d',
    mutedForeground: '#777777',
    tabBar: '#1a1a1a',
    tabBarInactive: '#666666',
    radius: 14,
  },
};

export type ColorScheme = typeof palette.light;
export default palette;
