import { useState } from 'react';
import { View, Text, TextInput, TouchableOpacity, StyleSheet, Alert, ActivityIndicator, Platform } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useRouter } from 'expo-router';
import { Feather } from '@expo/vector-icons';
import { useColors } from '@/hooks/useColors';
import { useI18n } from '@/context/I18nContext';
import { apiForgotPassword } from '@/lib/api';

export default function ForgotPasswordScreen() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const [email, setEmail] = useState('');
  const [loading, setLoading] = useState(false);
  const [sent, setSent] = useState(false);
  const { t } = useI18n();

  const topPad = Platform.OS === 'web' ? 67 : insets.top;

  const handleSubmit = async () => {
    if (!email || !email.includes('@')) {
      Alert.alert(t('general.error'), t('login.error_email'));
      return;
    }
    setLoading(true);
    try {
      const { message } = await apiForgotPassword(email.trim());
      setSent(true);
    } catch (e: any) {
      Alert.alert(t('general.error'), e.message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <View style={{ paddingTop: topPad + 16, paddingHorizontal: 24 }}>
        <TouchableOpacity onPress={() => router.back()} style={{ marginBottom: 20, width: 40 }}>
          <Feather name="arrow-left" size={24} color={colors.foreground} />
        </TouchableOpacity>
      </View>

      <View style={{ flex: 1, paddingHorizontal: 24, justifyContent: 'center', paddingBottom: insets.bottom + 32 }}>
        <Text style={[s.title, { color: colors.foreground }]}>{t('forgot.title')}</Text>

        {sent ? (
          <>
            <View style={[s.iconWrap, { backgroundColor: colors.primary + '22', alignSelf: 'center', marginBottom: 24 }]}>
              <Feather name="mail" size={40} color={colors.primary} />
            </View>
            <Text style={[s.body, { color: colors.subtext }]}>
              {t('forgot.sent')}
            </Text>
            <TouchableOpacity style={[s.btn, { backgroundColor: colors.primary, marginTop: 24 }]} onPress={() => router.push('/(auth)/login')}>
              <Text style={s.btnText}>{t('forgot.back')}</Text>
            </TouchableOpacity>
          </>
        ) : (
          <>
            <Text style={[s.body, { color: colors.subtext }]}>
              {t('forgot.body')}
            </Text>

            <Text style={[s.label, { color: colors.subtext }]}>{t('forgot.email')}</Text>
            <TextInput
              style={[s.input, { backgroundColor: colors.muted, color: colors.foreground }]}
              placeholder="correo@ejemplo.com"
              placeholderTextColor={colors.mutedForeground}
              value={email}
              onChangeText={setEmail}
              keyboardType="email-address"
              autoCapitalize="none"
              autoFocus
            />

            <TouchableOpacity
              style={[s.btn, { backgroundColor: colors.primary }, loading && { opacity: 0.6 }]}
              onPress={handleSubmit}
              disabled={loading}
            >
              {loading ? <ActivityIndicator color="#fff" /> : <Text style={s.btnText}>{t('forgot.send')}</Text>}
            </TouchableOpacity>

            <TouchableOpacity style={{ marginTop: 20, alignItems: 'center' }} onPress={() => router.push('/(auth)/login')}>
              <Text style={[s.link, { color: colors.primary }]}>{t('forgot.back')}</Text>
            </TouchableOpacity>
          </>
        )}
      </View>
    </View>
  );
}

const s = StyleSheet.create({
  title:   { fontFamily: 'Poppins_700Bold', fontSize: 26, marginBottom: 12 },
  body:    { fontFamily: 'Poppins_400Regular', fontSize: 14, lineHeight: 22, marginBottom: 24 },
  label:   { fontFamily: 'Poppins_500Medium', fontSize: 13, marginBottom: 8 },
  input:   { borderRadius: 14, paddingHorizontal: 16, paddingVertical: 13, fontSize: 15, fontFamily: 'Poppins_400Regular', marginBottom: 20 },
  btn:     { borderRadius: 14, paddingVertical: 16, alignItems: 'center' },
  btnText: { color: '#fff', fontFamily: 'Poppins_700Bold', fontSize: 16 },
  link:    { fontFamily: 'Poppins_600SemiBold', fontSize: 14 },
  iconWrap: { width: 88, height: 88, borderRadius: 44, alignItems: 'center', justifyContent: 'center' },
});
