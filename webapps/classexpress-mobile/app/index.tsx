import { useState, useEffect } from 'react';
import { View, Text, TouchableOpacity, StyleSheet, ScrollView, Platform } from 'react-native';
import { Redirect, useRouter } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useAuth } from '@/context/AuthContext';
import { useColors } from '@/hooks/useColors';
import { Feather } from '@expo/vector-icons';
import * as Storage from '@/lib/storage';

const SKIP_KEY = 'ce_skip_landing';

export default function Index() {
  const { user, loading } = useAuth();
  const colors = useColors();
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const [showLanding, setShowLanding] = useState(false);
  const [checking, setChecking] = useState(true);

  useEffect(() => {
    (async () => {
      try {
        const val = await Storage.getItem(SKIP_KEY);
        setShowLanding(val !== '1');
      } catch {
        setShowLanding(true);
      }
      setChecking(false);
    })();
  }, []);

  const handleDismiss = async () => {
    try {
      await Storage.setItem(SKIP_KEY, '1');
    } catch {}
    router.replace('/(auth)/login');
  };

  if (loading || checking) {
    return (
      <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: colors.background }}>
        <Text style={{ fontSize: 34, fontFamily: 'Poppins_700Bold', color: colors.primary }}>ClassExpress</Text>
      </View>
    );
  }

  if (user) {
    if (user.pendingPaymentSessionId) {
      return <Redirect href={`/pago/${user.pendingPaymentSessionId}`} />;
    }
    return <Redirect href="/(tabs)" />;
  }

  if (!showLanding) {
    return <Redirect href="/(auth)/login" />;
  }

  const sty = styles(colors);
  const topPad = Platform.OS === 'web' ? 67 : insets.top;

  return (
    <ScrollView style={{ flex: 1, backgroundColor: colors.background }} bounces={false}>
      <View style={[sty.hero, { paddingTop: topPad + 40 }]}>
        <Text style={sty.brand}>ClassExpress</Text>
        <Text style={sty.tagline}>Conéctate con personas que hablan tu idioma, en cualquier parte del mundo</Text>

        <View style={sty.statsRow}>
          {[{ num: '+500', label: 'Estudiantes' }, { num: '+50', label: 'Profesores' }, { num: '+1200', label: 'Clases' }]
            .map((st, i) => (
              <View key={i} style={sty.statCard}>
                <Text style={[sty.statNum, { color: colors.primary }]}>{st.num}</Text>
                <Text style={[sty.statLabel, { color: colors.subtext }]}>{st.label}</Text>
              </View>
            ))}
        </View>

        <TouchableOpacity style={sty.cta} onPress={() => router.replace('/(auth)/login')}>
          <Text style={sty.ctaText}>Comenzar ahora — es gratis</Text>
        </TouchableOpacity>

        <View style={sty.featuresGrid}>
          {[
            { icon: 'monitor', label: 'Clases en vivo' },
            { icon: 'search', label: 'Buscador inteligente' },
            { icon: 'credit-card', label: 'Pago por clase' },
            { icon: 'bar-chart-2', label: 'Dashboard profesor' },
          ].map((f, i) => (
            <View key={i} style={[sty.featureCard, { backgroundColor: colors.card }]}>
              <Feather name={f.icon as any} size={20} color={colors.primary} />
              <Text style={[sty.featureLabel, { color: colors.foreground }]}>{f.label}</Text>
            </View>
          ))}
        </View>
      </View>

      <View style={sty.stepsSection}>
        <Text style={sty.sectionTitle}>¿Cómo funciona?</Text>
        {[
          { n: '1', title: 'Regístrate', desc: 'Crea tu cuenta gratis como estudiante o profesor.' },
          { n: '2', title: 'Busca o crea una clase', desc: 'Explora clases disponibles o crea la tuya.' },
          { n: '3', title: 'Aprende en vivo', desc: 'Únete a la sala interactiva y aprende en tiempo real.' },
        ].map((st, i) => (
          <View key={i} style={sty.stepRow}>
            <View style={[sty.stepCircle, { backgroundColor: colors.primary }]}>
              <Text style={sty.stepNum}>{st.n}</Text>
            </View>
            <View style={{ flex: 1 }}>
              <Text style={[sty.stepTitle, { color: colors.foreground }]}>{st.title}</Text>
              <Text style={[sty.stepDesc, { color: colors.subtext }]}>{st.desc}</Text>
            </View>
          </View>
        ))}
      </View>

      <View style={sty.ctaSection}>
        <Text style={sty.ctaTitle}>Empieza hoy</Text>
        <Text style={sty.ctaSub}>Únete a la comunidad que ya está transformando la educación en línea.</Text>
        <TouchableOpacity style={sty.cta} onPress={() => router.replace('/(auth)/login')}>
          <Text style={sty.ctaText}>Crear cuenta gratis</Text>
        </TouchableOpacity>
        <TouchableOpacity style={sty.skipBtn} onPress={handleDismiss}>
          <Text style={[sty.skipText, { color: colors.subtext }]}>No volver a mostrar</Text>
        </TouchableOpacity>
      </View>
    </ScrollView>
  );
}

const styles = (c: any) => StyleSheet.create({
  hero:        { alignItems: 'center', paddingHorizontal: 24, paddingBottom: 40 },
  brand:       { fontSize: 42, fontFamily: 'Poppins_700Bold', color: c.primary, marginBottom: 8 },
  tagline:     { fontSize: 16, fontFamily: 'Poppins_400Regular', color: c.subtext, textAlign: 'center', marginBottom: 20, lineHeight: 22 },
  statsRow:    { flexDirection: 'row', justifyContent: 'space-around', width: '100%', marginBottom: 32 },
  statCard:    { alignItems: 'center' },
  statNum:     { fontSize: 26, fontFamily: 'Poppins_700Bold' },
  statLabel:   { fontSize: 12, fontFamily: 'Poppins_400Regular', marginTop: 2 },
  cta:         { backgroundColor: c.primary, paddingVertical: 16, paddingHorizontal: 36, borderRadius: 14, width: '100%', alignItems: 'center', marginBottom: 32 },
  ctaText:     { color: '#fff', fontFamily: 'Poppins_700Bold', fontSize: 17 },
  featuresGrid:{ flexDirection: 'row', flexWrap: 'wrap', gap: 10 },
  featureCard: { width: '48%', borderRadius: 14, padding: 16, gap: 10 },
  featureLabel:{ fontFamily: 'Poppins_500Medium', fontSize: 13 },
  stepsSection:{ paddingHorizontal: 24, paddingVertical: 40 },
  sectionTitle:{ fontSize: 22, fontFamily: 'Poppins_700Bold', color: c.foreground, textAlign: 'center', marginBottom: 24 },
  stepRow:     { flexDirection: 'row', gap: 16, marginBottom: 20, alignItems: 'center' },
  stepCircle:  { width: 40, height: 40, borderRadius: 20, alignItems: 'center', justifyContent: 'center' },
  stepNum:     { fontSize: 16, fontFamily: 'Poppins_700Bold', color: '#fff' },
  stepTitle:   { fontFamily: 'Poppins_600SemiBold', fontSize: 15, marginBottom: 2 },
  stepDesc:    { fontFamily: 'Poppins_400Regular', fontSize: 13, lineHeight: 18 },
  ctaSection:  { paddingHorizontal: 24, paddingVertical: 40, alignItems: 'center' },
  ctaTitle:    { fontSize: 24, fontFamily: 'Poppins_700Bold', color: c.foreground, textAlign: 'center', marginBottom: 8 },
  ctaSub:      { fontSize: 14, fontFamily: 'Poppins_400Regular', color: c.subtext, textAlign: 'center', marginBottom: 24, lineHeight: 20 },
  skipBtn:     { marginTop: 16, paddingVertical: 8 },
  skipText:    { fontSize: 13, fontFamily: 'Poppins_400Regular', textDecorationLine: 'underline' },
});
