import { useState } from 'react';
import {
  View, Text, TextInput, TouchableOpacity, StyleSheet,
  ScrollView, Platform, Alert, ActivityIndicator,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useRouter } from 'expo-router';
import { useColors } from '@/hooks/useColors';
import { useAuth } from '@/context/AuthContext';
import { apiLogin, apiRegister, apiCountries, Pais } from '@/lib/api';
import { useQuery } from '@tanstack/react-query';
import { Feather } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';

type Tab = 'login' | 'register';

export default function LoginScreen() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const { login } = useAuth();

  const [tab, setTab] = useState<Tab>('login');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [nombre, setNombre] = useState('');
  const [rol, setRol] = useState<'estudiante' | 'instructor'>('estudiante');
  const [paisId, setPaisId] = useState(0);
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);

  const { data: countriesData } = useQuery({
    queryKey: ['countries'],
    queryFn: () => apiCountries(),
  });
  const countries = countriesData?.countries ?? [];

  const s = styles(colors);

  const handleLogin = async () => {
    if (!email || !password) { Alert.alert('Completa todos los campos'); return; }
    setLoading(true);
    try {
      const { token, user } = await apiLogin(email.trim(), password);
      await login(token, user);
      Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
      router.replace('/(tabs)');
    } catch (e: any) {
      Alert.alert('Error', e.message);
    } finally {
      setLoading(false);
    }
  };

  const handleRegister = async () => {
    if (!nombre || !email || !password) { Alert.alert('Completa todos los campos'); return; }
    setLoading(true);
    try {
      await apiRegister({ nombre: nombre.trim(), email: email.trim(), password, pais_id: paisId, rol });
      Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
      Alert.alert('¡Listo!', 'Cuenta creada. Verifica tu email para ingresar.', [
        { text: 'OK', onPress: () => setTab('login') },
      ]);
    } catch (e: any) {
      Alert.alert('Error', e.message);
    } finally {
      setLoading(false);
    }
  };

  const topPad = Platform.OS === 'web' ? 67 : insets.top;

  return (
    <ScrollView
      style={{ flex: 1, backgroundColor: colors.background }}
      contentContainerStyle={{ paddingTop: topPad + 24, paddingBottom: insets.bottom + 32, paddingHorizontal: 24 }}
      keyboardShouldPersistTaps="handled"
    >
      <Text style={s.brand}>ClassExpress</Text>
      <Text style={s.tagline}>Aprende con los mejores profesores</Text>

      <View style={s.tabRow}>
        {(['login', 'register'] as Tab[]).map(t => (
          <TouchableOpacity
            key={t}
            style={[s.tabBtn, tab === t && { backgroundColor: colors.primary }]}
            onPress={() => setTab(t)}
          >
            <Text style={[s.tabText, { color: tab === t ? '#fff' : colors.subtext }]}>
              {t === 'login' ? 'Iniciar sesión' : 'Registrarse'}
            </Text>
          </TouchableOpacity>
        ))}
      </View>

      <View style={s.card}>
        {tab === 'register' && (
          <>
            <Text style={s.label}>Nombre completo</Text>
            <TextInput
              style={s.input}
              placeholder="Tu nombre"
              placeholderTextColor={colors.mutedForeground}
              value={nombre}
              onChangeText={setNombre}
            />

            <Text style={s.label}>Soy</Text>
            <View style={s.roleRow}>
              {(['estudiante', 'instructor'] as const).map(r => (
                <TouchableOpacity
                  key={r}
                  style={[s.roleBtn, rol === r && { backgroundColor: colors.primary }]}
                  onPress={() => setRol(r)}
                >
                  <Feather name={r === 'estudiante' ? 'user' : 'briefcase'} size={16}
                    color={rol === r ? '#fff' : colors.subtext} />
                  <Text style={[s.roleText, { color: rol === r ? '#fff' : colors.subtext }]}>
                    {r === 'estudiante' ? 'Estudiante' : 'Instructor'}
                  </Text>
                </TouchableOpacity>
              ))}
            </View>

            {countries.length > 0 && (
              <>
                <Text style={s.label}>País</Text>
                <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ marginBottom: 16 }}>
                  {countries.map((p: Pais) => (
                    <TouchableOpacity
                      key={p.id}
                      style={[s.countryChip, paisId === p.id && { backgroundColor: colors.primary }]}
                      onPress={() => setPaisId(p.id)}
                    >
                      <Text style={{ color: paisId === p.id ? '#fff' : colors.foreground, fontSize: 13 }}>
                        {p.nombre}
                      </Text>
                    </TouchableOpacity>
                  ))}
                </ScrollView>
              </>
            )}
          </>
        )}

        <Text style={s.label}>Email</Text>
        <TextInput
          style={s.input}
          placeholder="correo@ejemplo.com"
          placeholderTextColor={colors.mutedForeground}
          value={email}
          onChangeText={setEmail}
          keyboardType="email-address"
          autoCapitalize="none"
        />

        <Text style={s.label}>Contraseña</Text>
        <View style={s.pwRow}>
          <TextInput
            style={[s.input, { flex: 1, marginBottom: 0 }]}
            placeholder="••••••••"
            placeholderTextColor={colors.mutedForeground}
            value={password}
            onChangeText={setPassword}
            secureTextEntry={!showPassword}
          />
          <TouchableOpacity style={s.eyeBtn} onPress={() => setShowPassword(v => !v)}>
            <Feather name={showPassword ? 'eye-off' : 'eye'} size={20} color={colors.subtext} />
          </TouchableOpacity>
        </View>

        <TouchableOpacity
          style={[s.btn, loading && { opacity: 0.6 }]}
          onPress={tab === 'login' ? handleLogin : handleRegister}
          disabled={loading}
        >
          {loading
            ? <ActivityIndicator color="#fff" />
            : <Text style={s.btnText}>{tab === 'login' ? 'Entrar' : 'Crear cuenta'}</Text>}
        </TouchableOpacity>
      </View>

      <Text style={[s.tagline, { marginTop: 24, fontSize: 12 }]}>
        Al registrarte recibes 100 créditos gratis 🎁
      </Text>
    </ScrollView>
  );
}

const styles = (c: any) => StyleSheet.create({
  brand:       { fontSize: 34, fontFamily: 'Poppins_700Bold', color: c.primary, textAlign: 'center', marginBottom: 4 },
  tagline:     { fontSize: 14, fontFamily: 'Poppins_400Regular', color: c.subtext, textAlign: 'center', marginBottom: 32 },
  tabRow:      { flexDirection: 'row', backgroundColor: c.muted, borderRadius: 12, padding: 4, marginBottom: 24 },
  tabBtn:      { flex: 1, paddingVertical: 10, borderRadius: 10, alignItems: 'center' },
  tabText:     { fontFamily: 'Poppins_600SemiBold', fontSize: 13 },
  card:        { backgroundColor: c.card, borderRadius: 20, padding: 20, shadowColor: '#000', shadowOpacity: 0.06, shadowRadius: 12, shadowOffset: { width: 0, height: 4 } },
  label:       { fontFamily: 'Poppins_500Medium', fontSize: 13, color: c.subtext, marginBottom: 6 },
  input:       { backgroundColor: c.muted, borderRadius: 12, paddingHorizontal: 16, paddingVertical: 12, fontSize: 15, color: c.foreground, marginBottom: 16, fontFamily: 'Poppins_400Regular' },
  pwRow:       { flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 16 },
  eyeBtn:      { paddingHorizontal: 8 },
  btn:         { backgroundColor: c.primary, borderRadius: 14, paddingVertical: 16, alignItems: 'center', marginTop: 8 },
  btnText:     { color: '#fff', fontFamily: 'Poppins_700Bold', fontSize: 16 },
  roleRow:     { flexDirection: 'row', gap: 10, marginBottom: 16 },
  roleBtn:     { flex: 1, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 6, paddingVertical: 10, borderRadius: 12, backgroundColor: c.muted },
  roleText:    { fontFamily: 'Poppins_500Medium', fontSize: 13 },
  countryChip: { paddingHorizontal: 14, paddingVertical: 8, borderRadius: 20, backgroundColor: c.muted, marginRight: 8 },
});
