import { useState } from 'react';
import { View, Text, TextInput, TouchableOpacity, StyleSheet, Alert, ActivityIndicator, Platform } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { Feather } from '@expo/vector-icons';
import { useColors } from '@/hooks/useColors';
import { useI18n } from '@/context/I18nContext';
import { apiResetPassword } from '@/lib/api';

export default function ResetPasswordScreen() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const { token } = useLocalSearchParams<{ token: string }>();
  const [password, setPassword] = useState('');
  const [confirm, setConfirm] = useState('');
  const [loading, setLoading] = useState(false);
  const [success, setSuccess] = useState(false);
  const [showPassword, setShowPassword] = useState(false);
  const { t } = useI18n();

  const topPad = Platform.OS === 'web' ? 67 : insets.top;

  const handleSubmit = async () => {
    if (!password || !confirm) {
      Alert.alert(t('general.error'), t('login.error_fields'));
      return;
    }
    if (password.length < 6) {
      Alert.alert(t('general.error'), t('login.error_pass'));
      return;
    }
    if (password !== confirm) {
      Alert.alert(t('general.error'), t('login.error_pass'));
      return;
    }
    setLoading(true);
    try {
      const { message } = await apiResetPassword(token!, password, confirm);
      setSuccess(true);
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
        <Text style={[s.title, { color: colors.foreground }]}>{t('reset.title')}</Text>

        {success ? (
          <>
            <View style={[s.iconWrap, { backgroundColor: colors.success + '22', alignSelf: 'center', marginBottom: 24 }]}>
              <Feather name="check-circle" size={40} color={colors.success} />
            </View>
            <Text style={[s.body, { color: colors.subtext, textAlign: 'center' }]}>
              {t('reset.success')}
            </Text>
            <TouchableOpacity style={[s.btn, { backgroundColor: colors.primary, marginTop: 24 }]} onPress={() => router.push('/(auth)/login')}>
              <Text style={s.btnText}>{t('reset.login')}</Text>
            </TouchableOpacity>
          </>
        ) : (
          <>
            <Text style={[s.body, { color: colors.subtext }]}>
              {t('reset.body')}
            </Text>

            <Text style={[s.label, { color: colors.subtext }]}>{t('reset.password')}</Text>
            <View style={s.pwRow}>
              <TextInput
                style={[s.input, { flex: 1, marginBottom: 0, backgroundColor: colors.muted, color: colors.foreground }]}
                placeholder={t('reset.placeholder')}
                placeholderTextColor={colors.mutedForeground}
                value={password}
                onChangeText={setPassword}
                secureTextEntry={!showPassword}
                autoFocus
              />
              <TouchableOpacity style={s.eyeBtn} onPress={() => setShowPassword(v => !v)}>
                <Feather name={showPassword ? 'eye-off' : 'eye'} size={20} color={colors.subtext} />
              </TouchableOpacity>
            </View>

            <Text style={[s.label, { color: colors.subtext }]}>{t('reset.confirm')}</Text>
            <TextInput
              style={[s.input, { backgroundColor: colors.muted, color: colors.foreground }]}
              placeholder="••••••••"
              placeholderTextColor={colors.mutedForeground}
              value={confirm}
              onChangeText={setConfirm}
              secureTextEntry={!showPassword}
            />

            <TouchableOpacity
              style={[s.btn, { backgroundColor: colors.primary }, loading && { opacity: 0.6 }]}
              onPress={handleSubmit}
              disabled={loading}
            >
              {loading ? <ActivityIndicator color="#fff" /> : <Text style={s.btnText}>{t('reset.update')}</Text>}
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
  input:   { borderRadius: 14, paddingHorizontal: 16, paddingVertical: 13, fontSize: 15, fontFamily: 'Poppins_400Regular', marginBottom: 16 },
  pwRow:   { flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 16 },
  eyeBtn:  { paddingHorizontal: 8 },
  btn:     { borderRadius: 14, paddingVertical: 16, alignItems: 'center' },
  btnText: { color: '#fff', fontFamily: 'Poppins_700Bold', fontSize: 16 },
  iconWrap: { width: 88, height: 88, borderRadius: 44, alignItems: 'center', justifyContent: 'center' },
});
