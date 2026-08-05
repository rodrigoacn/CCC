import React, { createContext, useContext, useState, useEffect, useCallback, useRef, ReactNode } from 'react';
import * as Storage from '@/lib/storage';
import { request } from '@/lib/api';
import { useAuth } from '@/context/AuthContext';
import { translate, LangCode } from '@/i18n';

const LANG_KEY = 'ce_lang';
const VALID_CODES: string[] = ['es', 'en', 'fr', 'de', 'pt', 'it', 'zh', 'ja', 'ru', 'ar', 'hi', 'ko'];

interface I18nContextType {
  lang: LangCode;
  setLang: (code: LangCode) => Promise<void>;
  t: (key: string, params?: Record<string, string | number>) => string;
}

const I18nContext = createContext<I18nContextType>({} as I18nContextType);

export function I18nProvider({ children }: { children: ReactNode }) {
  const [lang, setLangState] = useState<LangCode>('en');
  const hasLocalLang = useRef(false);
  const { user } = useAuth();

  useEffect(() => {
    (async () => {
      try {
        const saved = await Storage.getItem(LANG_KEY);
        if (saved && VALID_CODES.includes(saved)) {
          setLangState(saved as LangCode);
          hasLocalLang.current = true;
        }
      } catch {}
    })();
  }, []);

  useEffect(() => {
    if (user?.idioma_preferido && VALID_CODES.includes(user.idioma_preferido) && !hasLocalLang.current) {
      setLangState(user.idioma_preferido as LangCode);
      Storage.setItem(LANG_KEY, user.idioma_preferido).catch(() => {});
    }
  }, [user?.idioma_preferido]);

  const setLang = useCallback(async (code: LangCode) => {
    if (!VALID_CODES.includes(code)) return;
    hasLocalLang.current = true;
    setLangState(code);
    try {
      await Storage.setItem(LANG_KEY, code);
    } catch {}
    try {
      await request('set_ui_language', { lang: code });
    } catch {}
  }, []);

  const t = useCallback(
    (key: string, params?: Record<string, string | number>) => translate(lang, key, params),
    [lang],
  );

  return (
    <I18nContext.Provider value={{ lang, setLang, t }}>
      {children}
    </I18nContext.Provider>
  );
}

export function useI18n() {
  const ctx = useContext(I18nContext);
  if (!ctx) throw new Error('useI18n must be used within I18nProvider');
  return ctx;
}
