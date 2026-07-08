import { useTheme } from '@/context/ThemeContext';
import palette, { ColorScheme } from '@/constants/colors';

export function useColors(): ColorScheme {
  const { theme } = useTheme();
  return theme === 'dark' ? palette.dark : palette.light;
}
