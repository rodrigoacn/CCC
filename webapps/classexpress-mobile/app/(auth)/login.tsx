import { useState, useMemo } from 'react';
import {
  View, Text, TextInput, TouchableOpacity, StyleSheet,
  ScrollView, Platform, Alert, ActivityIndicator, Modal, FlatList,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useRouter } from 'expo-router';
import { useColors } from '@/hooks/useColors';
import { useAuth } from '@/context/AuthContext';
import { apiLogin, apiRegister, apiCountries, apiResendVerification, Pais } from '@/lib/api';
import { useQuery } from '@tanstack/react-query';
import { Feather } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { apiLanguages, Language } from '@/lib/api';

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
  const [username, setUsername] = useState('');
  const [paisId, setPaisId] = useState(0);
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [showCountryPicker, setShowCountryPicker] = useState(false);
  const [countrySearch, setCountrySearch] = useState('');
  const [loginAttempts, setLoginAttempts] = useState(0);
  const [selectedIdiomas, setSelectedIdiomas] = useState<number[]>([]);
  const [loginRol, setLoginRol] = useState<'student' | 'teacher'>('student');

  const { data: langData } = useQuery({ queryKey: ['languages'], queryFn: () => apiLanguages() });
  const allLanguages = langData?.languages ?? [];

  const { data: countriesData } = useQuery({
    queryKey: ['countries'],
    queryFn: () => apiCountries(),
  });
  const countries = countriesData?.countries ?? [];

  const normalizedSearch = useMemo(() => {
    return countrySearch.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  }, [countrySearch]);

  const filteredCountries = useMemo(() => {
    if (!normalizedSearch) return countries;
    return countries.filter((p: Pais) =>
      p.nombre.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').includes(normalizedSearch)
    );
  }, [countries, normalizedSearch]);

  const selectedCountry = countries.find((p: Pais) => p.id === paisId);

  const s = styles(colors);

  const handleLogin = async () => {
    if (!email || !password) { Alert.alert('Completa todos los campos'); return; }
    if (loginAttempts >= 5) {
      Alert.alert('Demasiados intentos', 'Espera unos segundos antes de intentar de nuevo.');
      return;
    }
    setLoading(true);
    // Exponential backoff: 1s, 2s, 4s...
    if (loginAttempts > 0) await new Promise(r => setTimeout(r, Math.min(1000 * Math.pow(2, loginAttempts - 1), 10000)));
    try {
      const { token, user } = await apiLogin(email.trim(), password);
      setLoginAttempts(0);
      await AsyncStorage.setItem('ce_login_rol', loginRol);
      await login(token, user);
      Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
      if (user.pendingPaymentSessionId) {
        router.replace(`/pago/${user.pendingPaymentSessionId}`);
      } else {
        router.replace('/(tabs)');
      }
    } catch (e: any) {
      if (__DEV__) console.log('login error', e);
      if (e.code === 'NOT_VERIFIED') {
        Alert.alert(
          'Cuenta no verificada',
          'Tu cuenta aún no está verificada. Revisa tu correo o solicita un nuevo enlace.',
          [
            { text: 'Cancelar', style: 'cancel' },
            {
              text: 'Reenviar correo',
              onPress: async () => {
                try {
                  await apiResendVerification(email.trim());
                  router.push({ pathname: '/(auth)/verify-email', params: { email: email.trim() } });
                } catch {
                  Alert.alert('Error', 'No se pudo reenviar el correo. Intenta de nuevo.');
                }
              },
            },
          ]
        );
      } else {
        Alert.alert('Error', 'Hubo un problema. Verifica tus credenciales e intenta de nuevo.');
      }
      setLoginAttempts(a => a + 1);
    } finally {
      setLoading(false);
    }
  };

  const handleRegister = async () => {
    if (!nombre || !email || !password || !username) { Alert.alert('Completa todos los campos'); return; }
    if (password.length < 6) { Alert.alert('Error', 'La contraseña debe tener al menos 6 caracteres'); return; }
    setLoading(true);
    try {
      const result = await apiRegister({ 
        nombre: nombre.trim(), 
        email: email.trim(), 
        password, 
        username: username.trim(),
        pais_id: paisId, 
        rol: 'estudiante',
        idiomas: selectedIdiomas,
      });
      Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
      router.replace({ pathname: '/(auth)/verify-email', params: { email: result.email || email.trim() } });
    } catch (e: any) {
      if (__DEV__) console.log('register error', e);
      if (e.code === 'NOT_VERIFIED') {
        router.replace({ pathname: '/(auth)/verify-email', params: { email: email.trim() } });
      } else {
        Alert.alert('Error', 'No se pudo completar el registro. Intenta de nuevo.');
      }
    } finally {
      setLoading(false);
    }
  };

  const topPad = Platform.OS === 'web' ? 67 : insets.top;

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <ScrollView
        style={{ flex: 1 }}
        contentContainerStyle={{ paddingTop: topPad + 24, paddingBottom: insets.bottom + 32, paddingHorizontal: 24 }}
        keyboardShouldPersistTaps="handled"
      >
      <Text style={s.brand}>ClassExpress</Text>
      <Text style={s.tagline}>Conéctate con personas que hablan tu idioma, en cualquier parte del mundo</Text>

      <View style={s.statsRow}>
        <View style={s.statCard}><Text style={s.statNumber}>+500</Text><Text style={s.statLabel}>Estudiantes</Text></View>
        <View style={s.statCard}><Text style={s.statNumber}>+50</Text><Text style={s.statLabel}>Profesores</Text></View>
        <View style={s.statCard}><Text style={s.statNumber}>+1200</Text><Text style={s.statLabel}>Clases</Text></View>
      </View>

      <View style={s.featuresGrid}>
        {[
          { icon: 'video', label: 'Clases en vivo' },
          { icon: 'search', label: 'Buscador inteligente' },
          { icon: 'shield', label: 'Pagos seguros' },
          { icon: 'bar-chart-2', label: 'Dashboard' },
        ].map((f, i) => (
          <View key={i} style={s.featureCard}>
            <Feather name={f.icon as any} size={20} color={colors.primary} />
            <Text style={s.featureLabel}>{f.label}</Text>
          </View>
        ))}
      </View>

      <View style={s.stepsRow}>
        {[{ n: '1', t: 'Explora' }, { n: '2', t: 'Inscríbete' }, { n: '3', t: 'Aprende' }].map((step, i) => (
          <View key={i} style={s.stepItem}>
            <View style={s.stepCircle}><Text style={s.stepNumber}>{step.n}</Text></View>
            <Text style={s.stepText}>{step.t}</Text>
          </View>
        ))}
      </View>

      <View style={s.separator} />

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

            <Text style={s.label}>Nombre de usuario</Text>
            <TextInput
              style={s.input}
              placeholder="@usuario"
              placeholderTextColor={colors.mutedForeground}
              value={username}
              onChangeText={setUsername}
              autoCapitalize="none"
            />

            <Text style={s.label}>País</Text>
            <TouchableOpacity
              style={s.countrySelector}
              onPress={() => { setCountrySearch(''); setShowCountryPicker(true); }}
            >
              <Text style={[s.countrySelectorText, !paisId && { color: colors.mutedForeground }]}>
                {selectedCountry ? selectedCountry.nombre : 'Selecciona tu país'}
              </Text>
              <Feather name="chevron-down" size={16} color={colors.subtext} />
            </TouchableOpacity>

            <Text style={s.label}>Idiomas que hablas</Text>
            <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 6, marginBottom: 12 }}>
              {allLanguages.map((lang: Language) => {
                const selected = selectedIdiomas.includes(lang.id);
                return (
                  <TouchableOpacity
                    key={lang.id}
                    style={{
                      paddingHorizontal: 14, paddingVertical: 8, borderRadius: 20,
                      borderWidth: 1, borderColor: selected ? colors.primary : colors.border,
                      backgroundColor: selected ? colors.primary + '22' : 'transparent',
                    }}
                    onPress={() => setSelectedIdiomas(prev =>
                      prev.includes(lang.id) ? prev.filter(id => id !== lang.id) : [...prev, lang.id]
                    )}
                  >
                    <Text style={{ fontFamily: 'Poppins_500Medium', fontSize: 13, color: selected ? colors.primary : colors.foreground }}>{lang.nombre}</Text>
                  </TouchableOpacity>
                );
              })}
            </View>
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

        {tab === 'login' && (
          <View style={{ marginBottom: 16 }}>
            <Text style={s.label}>Entrar como</Text>
            <View style={{ flexDirection: 'row', gap: 8 }}>
              {(['student', 'teacher'] as const).map(r => (
                <TouchableOpacity
                  key={r}
                  style={{
                    flex: 1, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 6,
                    paddingVertical: 10, borderRadius: 24, borderWidth: 1,
                    borderColor: loginRol === r ? colors.primary : colors.border,
                    backgroundColor: loginRol === r ? colors.primary + '22' : 'transparent',
                  }}
                  onPress={() => setLoginRol(r)}
                >
                  <Feather name={r === 'student' ? 'user' : 'briefcase'} size={16} color={loginRol === r ? colors.primary : colors.subtext} />
                  <Text style={{ fontFamily: 'Poppins_600SemiBold', fontSize: 13, color: loginRol === r ? colors.primary : colors.subtext }}>
                    {r === 'student' ? 'Estudiante' : 'Profesor'}
                  </Text>
                </TouchableOpacity>
              ))}
            </View>
          </View>
        )}

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
        Regístrate gratis como estudiante o profesor
      </Text>
    </ScrollView>

      <Modal visible={showCountryPicker} animationType="slide" transparent>
        <View style={s.modalOverlay}>
          <View style={s.modalContent}>
            <View style={s.modalHeader}>
              <Text style={s.modalTitle}>Seleccionar país</Text>
              <TouchableOpacity onPress={() => setShowCountryPicker(false)}>
                <Feather name="x" size={22} color={colors.foreground} />
              </TouchableOpacity>
            </View>
            <TextInput
              style={s.modalSearch}
              placeholder="Buscar país…"
              placeholderTextColor={colors.mutedForeground}
              value={countrySearch}
              onChangeText={setCountrySearch}
              autoFocus
            />
            <FlatList
              data={filteredCountries}
              keyExtractor={(p: Pais) => String(p.id)}
              renderItem={({ item: p }: { item: Pais }) => (
                <TouchableOpacity
                  style={[s.modalItem, paisId === p.id && { backgroundColor: colors.primary + '20' }]}
                  onPress={() => { setPaisId(p.id); setShowCountryPicker(false); }}
                >
                  <Text style={[s.modalItemText, paisId === p.id && { color: colors.primary, fontWeight: '700' }]}>
                    {p.nombre}
                  </Text>
                  {paisId === p.id && <Feather name="check" size={18} color={colors.primary} />}
                </TouchableOpacity>
              )}
              style={{ flex: 1 }}
            />
          </View>
        </View>
      </Modal>
    </View>
  );
}

const styles = (c: any) => StyleSheet.create({
  brand:       { fontSize: 34, fontFamily: 'Poppins_700Bold', color: c.primary, textAlign: 'center', marginBottom: 4 },
  tagline:     { fontSize: 14, fontFamily: 'Poppins_400Regular', color: c.subtext, textAlign: 'center', marginBottom: 16 },
  statsRow:    { flexDirection: 'row', justifyContent: 'space-around', marginBottom: 24 },
  statCard:    { alignItems: 'center' },
  statNumber:  { fontSize: 22, fontFamily: 'Poppins_700Bold', color: c.primary },
  statLabel:   { fontSize: 11, fontFamily: 'Poppins_400Regular', color: c.subtext, marginTop: 2 },
  featuresGrid:{ flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginBottom: 24 },
  featureCard: { width: '48%', backgroundColor: c.card, borderRadius: 12, padding: 12, flexDirection: 'row', alignItems: 'center', gap: 10 },
  featureLabel:{ fontSize: 13, fontFamily: 'Poppins_500Medium', color: c.foreground },
  stepsRow:    { flexDirection: 'row', justifyContent: 'space-around', marginBottom: 24 },
  stepItem:    { alignItems: 'center', gap: 6 },
  stepCircle:  { width: 36, height: 36, borderRadius: 18, backgroundColor: c.primary, alignItems: 'center', justifyContent: 'center' },
  stepNumber:  { fontSize: 16, fontFamily: 'Poppins_700Bold', color: '#fff' },
  stepText:    { fontSize: 12, fontFamily: 'Poppins_500Medium', color: c.subtext },
  separator:   { height: 1, backgroundColor: c.border, marginBottom: 24 },
  tabRow:      { flexDirection: 'row', backgroundColor: c.muted, borderRadius: 12, padding: 4, marginBottom: 24 },
  tabBtn:      { flex: 1, paddingVertical: 10, borderRadius: 10, alignItems: 'center' },
  tabText:     { fontFamily: 'Poppins_600SemiBold', fontSize: 13 },
  card:        { backgroundColor: c.card, borderRadius: 20, padding: 20, boxShadow: '0px 4px 12px rgba(0,0,0,0.06)' },
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
  countrySelector: {
    flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between',
    backgroundColor: c.muted, borderRadius: 12, paddingHorizontal: 16, paddingVertical: 14,
    marginBottom: 16,
  },
  countrySelectorText: { fontSize: 15, color: c.foreground, fontFamily: 'Poppins_400Regular' },
  modalOverlay: { flex: 1, backgroundColor: 'rgba(0,0,0,0.5)', justifyContent: 'flex-end' },
  modalContent: { backgroundColor: c.card, borderTopLeftRadius: 20, borderTopRightRadius: 20, maxHeight: '80%', paddingBottom: 32 },
  modalHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', padding: 16, borderBottomWidth: 1, borderBottomColor: c.border },
  modalTitle: { fontSize: 17, fontFamily: 'Poppins_600SemiBold', color: c.foreground },
  modalSearch: {
    backgroundColor: c.muted, borderRadius: 10, margin: 16, paddingHorizontal: 16, paddingVertical: 10,
    fontSize: 15, color: c.foreground, fontFamily: 'Poppins_400Regular',
  },
  modalItem: {
    flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between',
    paddingVertical: 12, paddingHorizontal: 16, borderBottomWidth: 0.5, borderBottomColor: c.border,
  },
  modalItemText: { fontSize: 15, color: c.foreground, fontFamily: 'Poppins_400Regular' },
});
