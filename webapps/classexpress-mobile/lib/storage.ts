import { Platform } from 'react-native';

const isWeb = Platform.OS === 'web';

export async function getItem(key: string): Promise<string | null> {
  if (isWeb) {
    try { return localStorage.getItem(key); } catch { return null; }
  }
  try {
    const SecureStore = await import('expo-secure-store');
    return await SecureStore.getItemAsync(key);
  } catch { return null; }
}

export async function setItem(key: string, value: string): Promise<void> {
  if (isWeb) {
    try { localStorage.setItem(key, value); } catch {}
    return;
  }
  try {
    const SecureStore = await import('expo-secure-store');
    await SecureStore.setItemAsync(key, value);
  } catch {}
}

export async function removeItem(key: string): Promise<void> {
  if (isWeb) {
    try { localStorage.removeItem(key); } catch {}
    return;
  }
  try {
    const SecureStore = await import('expo-secure-store');
    await SecureStore.deleteItemAsync(key);
  } catch {}
}
