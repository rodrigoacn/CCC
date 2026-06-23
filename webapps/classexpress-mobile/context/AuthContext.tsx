import React, { createContext, useContext, useState, useEffect, ReactNode } from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { apiProfile, User } from '@/lib/api';

interface AuthState {
  user: User | null;
  token: string | null;
  loading: boolean;
  login: (token: string, user: User) => Promise<void>;
  logout: () => Promise<void>;
  refreshUser: () => Promise<void>;
}

const AuthContext = createContext<AuthState>({} as AuthState);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [token, setToken] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    (async () => {
      const t = await AsyncStorage.getItem('ce_token');
      if (t) {
        setToken(t);
        try {
          const { user: u } = await apiProfile();
          setUser(u);
        } catch {
          await AsyncStorage.removeItem('ce_token');
        }
      }
      setLoading(false);
    })();
  }, []);

  const login = async (t: string, u: User) => {
    await AsyncStorage.setItem('ce_token', t);
    setToken(t);
    setUser(u);
  };

  const logout = async () => {
    await AsyncStorage.removeItem('ce_token');
    setToken(null);
    setUser(null);
  };

  const refreshUser = async () => {
    try {
      const { user: u } = await apiProfile();
      setUser(u);
    } catch {}
  };

  return (
    <AuthContext.Provider value={{ user, token, loading, login, logout, refreshUser }}>
      {children}
    </AuthContext.Provider>
  );
}

export const useAuth = () => useContext(AuthContext);
