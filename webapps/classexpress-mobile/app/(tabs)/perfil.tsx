import {
  View, Text, TouchableOpacity, StyleSheet, Platform, Alert, ScrollView,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useRouter } from 'expo-router';
import { Feather } from '@expo/vector-icons';
import { useColors } from '@/hooks/useColors';
import { useAuth } from '@/context/AuthContext';

function MenuItem({ icon, label, onPress, danger }: { icon: any; label: string; onPress: () => void; danger?: boolean }) {
  const colors = useColors();
  return (
    <TouchableOpacity style={[styles.menuItem, { borderBottomColor: colors.border }]} onPress={onPress} activeOpacity={0.7}>
      <View style={[styles.menuIcon, { backgroundColor: danger ? colors.danger + '22' : colors.primaryLight }]}>
        <Feather name={icon} size={18} color={danger ? colors.danger : colors.primary} />
      </View>
      <Text style={[styles.menuLabel, { color: danger ? colors.danger : colors.foreground }]}>{label}</Text>
      {!danger && <Feather name="chevron-right" size={18} color={colors.mutedForeground} />}
    </TouchableOpacity>
  );
}

export default function PerfilScreen() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const { user, logout } = useAuth();

  const topPad = Platform.OS === 'web' ? 67 : insets.top;

  const handleLogout = () => {
    Alert.alert('Cerrar sesión', '¿Seguro que quieres salir?', [
      { text: 'Cancelar', style: 'cancel' },
      { text: 'Salir', style: 'destructive', onPress: async () => {
        await logout();
        router.replace('/(auth)/login');
      }},
    ]);
  };

  const initial = user?.nombre?.[0]?.toUpperCase() ?? '?';

  return (
    <ScrollView
      style={{ flex: 1, backgroundColor: colors.background }}
      contentContainerStyle={{ paddingBottom: insets.bottom + 32 }}
    >
      <View style={[styles.headerWrap, { paddingTop: topPad + 12, backgroundColor: colors.surface }]}>
        <View style={[styles.avatar, { backgroundColor: colors.primary }]}>
          <Text style={styles.avatarLetter}>{initial}</Text>
        </View>
        <Text style={[styles.name, { color: colors.foreground }]}>{user?.nombre}</Text>
        <Text style={[styles.email, { color: colors.subtext }]}>{user?.email}</Text>
        <View style={[styles.rolBadge, { backgroundColor: colors.primaryLight }]}>
          <Text style={[styles.rolText, { color: colors.primary }]}>
            {user?.rol === 'instructor' ? 'Instructor' : user?.rol === 'director' ? 'Director' : 'Estudiante'}
          </Text>
        </View>
      </View>

      <View style={[styles.statsRow, { backgroundColor: colors.surface, borderColor: colors.border }]}>
        <View style={styles.stat}>
          <Text style={[styles.statNum, { color: colors.primary }]}>{user?.creditos ?? 0}</Text>
          <Text style={[styles.statLabel, { color: colors.subtext }]}>Créditos</Text>
        </View>
        <View style={[styles.statDivider, { backgroundColor: colors.border }]} />
        <View style={styles.stat}>
          <Text style={[styles.statNum, { color: colors.primary }]}>1 USD</Text>
          <Text style={[styles.statLabel, { color: colors.subtext }]}>por crédito</Text>
        </View>
      </View>

      <View style={[styles.section, { backgroundColor: colors.surface }]}>
        <Text style={[styles.sectionTitle, { color: colors.subtext }]}>CUENTA</Text>

        {user?.rol === 'instructor' && (
          <MenuItem icon="bar-chart-2" label="Panel del Profesor" onPress={() => router.push('/profesor/dashboard')} />
        )}
        {user?.rol === 'instructor' && (
          <MenuItem icon="plus-circle" label="Crear nueva clase" onPress={() => router.push('/profesor/crear')} />
        )}
        <MenuItem icon="credit-card" label="Mis créditos" onPress={() => router.push('/(tabs)/creditos')} />
        <MenuItem icon="search" label="Buscar clases" onPress={() => router.push('/(tabs)/buscar')} />
      </View>

      <View style={[styles.section, { backgroundColor: colors.surface, marginTop: 12 }]}>
        <Text style={[styles.sectionTitle, { color: colors.subtext }]}>SESIÓN</Text>
        <MenuItem icon="log-out" label="Cerrar sesión" onPress={handleLogout} danger />
      </View>

      <Text style={[styles.version, { color: colors.mutedForeground }]}>ClassExpress v1.0 · Hecho con ❤️ en LATAM</Text>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  headerWrap:  { alignItems: 'center', paddingBottom: 24, paddingHorizontal: 24 },
  avatar:      { width: 80, height: 80, borderRadius: 40, justifyContent: 'center', alignItems: 'center', marginBottom: 12 },
  avatarLetter: { color: '#fff', fontFamily: 'Poppins_700Bold', fontSize: 32 },
  name:        { fontFamily: 'Poppins_700Bold', fontSize: 22, marginBottom: 2 },
  email:       { fontFamily: 'Poppins_400Regular', fontSize: 14, marginBottom: 10 },
  rolBadge:    { paddingHorizontal: 16, paddingVertical: 5, borderRadius: 20 },
  rolText:     { fontFamily: 'Poppins_600SemiBold', fontSize: 13 },
  statsRow:    { flexDirection: 'row', marginHorizontal: 20, marginTop: 16, borderRadius: 16, borderWidth: 1, overflow: 'hidden' },
  stat:        { flex: 1, paddingVertical: 16, alignItems: 'center' },
  statNum:     { fontFamily: 'Poppins_700Bold', fontSize: 22 },
  statLabel:   { fontFamily: 'Poppins_400Regular', fontSize: 12 },
  statDivider: { width: 1 },
  section:     { marginTop: 16, marginHorizontal: 20, borderRadius: 16, overflow: 'hidden' },
  sectionTitle: { fontFamily: 'Poppins_700Bold', fontSize: 11, paddingHorizontal: 16, paddingTop: 12, paddingBottom: 4, letterSpacing: 1 },
  menuItem:    { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 14, paddingHorizontal: 16, borderBottomWidth: 1 },
  menuIcon:    { width: 36, height: 36, borderRadius: 10, justifyContent: 'center', alignItems: 'center' },
  menuLabel:   { flex: 1, fontFamily: 'Poppins_500Medium', fontSize: 15 },
  version:     { textAlign: 'center', fontFamily: 'Poppins_400Regular', fontSize: 12, marginTop: 32 },
});
