import { useState } from 'react';
import { View, Text, TouchableOpacity, StyleSheet, ActivityIndicator, Alert } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { Feather } from '@expo/vector-icons';
import { useColors } from '@/hooks/useColors';
import { apiResendVerification } from '@/lib/api';

export default function VerifyEmailScreen() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const { email } = useLocalSearchParams<{ email: string }>();
  const [sending, setSending] = useState(false);

  const handleResend = async () => {
    if (!email) return;
    setSending(true);
    try {
      const { message } = await apiResendVerification(String(email));
      Alert.alert('Correo enviado', message);
    } catch (e: any) {
      Alert.alert('Error', e.message);
    } finally {
      setSending(false);
    }
  };

  return (
    <View style={[styles.container, { backgroundColor: colors.background, paddingTop: insets.top + 40, paddingBottom: insets.bottom + 24 }]}>
      <View style={[styles.iconWrap, { backgroundColor: colors.primary + '22' }]}>
        <Feather name="mail" size={40} color={colors.primary} />
      </View>
      <Text style={[styles.title, { color: colors.foreground }]}>Verifica tu correo</Text>
      <Text style={[styles.body, { color: colors.subtext }]}>
        Enviamos un enlace de verificación a{'\n'}
        <Text style={{ color: colors.foreground, fontFamily: 'Poppins_600SemiBold' }}>{email}</Text>
        {'\n\n'}Abre el correo y haz clic en el enlace. Después podrás iniciar sesión.
      </Text>

      <TouchableOpacity
        style={[styles.btn, { backgroundColor: colors.primary }, sending && { opacity: 0.6 }]}
        onPress={handleResend}
        disabled={sending}
      >
        {sending ? <ActivityIndicator color="#fff" /> : <Text style={styles.btnText}>Reenviar correo</Text>}
      </TouchableOpacity>

      <TouchableOpacity style={styles.linkBtn} onPress={() => router.replace('/(auth)/login')}>
        <Text style={[styles.linkText, { color: colors.primary }]}>Volver a iniciar sesión</Text>
      </TouchableOpacity>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, paddingHorizontal: 28, alignItems: 'center', justifyContent: 'center' },
  iconWrap: { width: 88, height: 88, borderRadius: 44, alignItems: 'center', justifyContent: 'center', marginBottom: 24 },
  title: { fontFamily: 'Poppins_700Bold', fontSize: 24, marginBottom: 12, textAlign: 'center' },
  body: { fontFamily: 'Poppins_400Regular', fontSize: 15, lineHeight: 24, textAlign: 'center', marginBottom: 32 },
  btn: { width: '100%', borderRadius: 14, paddingVertical: 16, alignItems: 'center' },
  btnText: { color: '#fff', fontFamily: 'Poppins_700Bold', fontSize: 16 },
  linkBtn: { marginTop: 20, padding: 8 },
  linkText: { fontFamily: 'Poppins_600SemiBold', fontSize: 14 },
});
