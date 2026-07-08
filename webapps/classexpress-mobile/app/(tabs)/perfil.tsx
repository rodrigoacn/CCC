import {
  View, Text, TouchableOpacity, StyleSheet, Platform, Alert, ScrollView, Image, TextInput, Modal,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useRouter } from 'expo-router';
import { Feather } from '@expo/vector-icons';
import { useColors } from '@/hooks/useColors';
import { useAuth } from '@/context/AuthContext';
import { useTheme } from '@/context/ThemeContext';
import { useState } from 'react';
import { request } from '@/lib/api';

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
  const { toggleTheme, isDark } = useTheme();
  const [showDeleteModal, setShowDeleteModal] = useState(false);
  const [deletePassword, setDeletePassword] = useState('');
  const [deleteLoading, setDeleteLoading] = useState(false);

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

  const handleDeleteAccount = async () => {
    if (!deletePassword.trim()) {
      Alert.alert('Error', 'Ingresa tu contraseña');
      return;
    }
    
    setDeleteLoading(true);
    try {
      await request<{ ok: boolean; message: string }>('delete_account', { password: deletePassword });
      Alert.alert('Cuenta eliminada', 'Tu cuenta ha sido eliminada correctamente.', [
        { text: 'Ok', onPress: async () => {
          await logout();
          router.replace('/(auth)/login');
        }}
      ]);
    } catch (e: any) {
      Alert.alert('Error', e.message || 'No se pudo eliminar la cuenta');
      setDeletePassword('');
    } finally {
      setDeleteLoading(false);
    }
  };

  const initial = user?.nombre?.[0]?.toUpperCase() ?? '?';

  return (
    <ScrollView
      style={{ flex: 1, backgroundColor: colors.background }}
      contentContainerStyle={{ paddingBottom: insets.bottom + 32 }}
    >
      <View style={[styles.headerWrap, { paddingTop: topPad + 12, backgroundColor: colors.surface }]}>
        {user?.avatar ? (
          <Image source={{ uri: user.avatar }} style={[styles.avatar, { borderWidth: 2, borderColor: colors.primary }]} />
        ) : (
          <View style={[styles.avatar, { backgroundColor: colors.primary }]}> 
            <Text style={styles.avatarLetter}>{initial}</Text> 
          </View>
        )}

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
        <MenuItem 
          icon={isDark ? "sun" : "moon"} 
          label={isDark ? "Modo claro" : "Modo oscuro"} 
          onPress={toggleTheme} 
        />
      </View>

      <View style={[styles.section, { backgroundColor: colors.surface, marginTop: 12 }]}>
        <Text style={[styles.sectionTitle, { color: colors.subtext }]}>SESIÓN</Text>
        <MenuItem icon="log-out" label="Cerrar sesión" onPress={handleLogout} danger />
        <MenuItem icon="trash-2" label="Eliminar Cuenta" onPress={() => setShowDeleteModal(true)} danger />
      </View>

      <Modal visible={showDeleteModal} transparent animationType="fade">
        <View style={[styles.modalOverlay, { backgroundColor: 'rgba(0, 0, 0, 0.7)' }]}>
          <View style={[styles.modalContent, { backgroundColor: colors.surface }]}>
            <Text style={[styles.modalTitle, { color: colors.danger }]}>Eliminar Cuenta</Text>
            <Text style={[styles.modalMessage, { color: colors.subtext }]}>
              ⚠️ Esta acción es permanente. Se eliminarán todos tus datos de forma irreversible.
            </Text>
            <Text style={[styles.modalLabel, { color: colors.foreground }]}>Ingresa tu contraseña:</Text>
            <TextInput
              style={[styles.modalInput, { backgroundColor: colors.muted, color: colors.foreground, borderColor: colors.border }]}
              placeholder="Tu contraseña"
              placeholderTextColor={colors.mutedForeground}
              secureTextEntry
              value={deletePassword}
              onChangeText={setDeletePassword}
            />
            <View style={styles.modalButtons}>
              <TouchableOpacity
                style={[styles.modalBtn, { backgroundColor: colors.muted }]}
                onPress={() => { setShowDeleteModal(false); setDeletePassword(''); }}
              >
                <Text style={{ color: colors.foreground, fontFamily: 'Poppins_600SemiBold' }}>Cancelar</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={[styles.modalBtn, { backgroundColor: colors.danger }]}
                onPress={handleDeleteAccount}
                disabled={deleteLoading}
              >
                <Text style={{ color: '#fff', fontFamily: 'Poppins_600SemiBold' }}>
                  {deleteLoading ? 'Eliminando...' : 'Eliminar'}
                </Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

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
  modalOverlay: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  modalContent: { width: '85%', borderRadius: 16, padding: 20 },
  modalTitle:  { fontFamily: 'Poppins_700Bold', fontSize: 18, marginBottom: 12 },
  modalMessage: { fontFamily: 'Poppins_400Regular', fontSize: 13, marginBottom: 16, lineHeight: 18 },
  modalLabel:  { fontFamily: 'Poppins_500Medium', fontSize: 13, marginBottom: 8 },
  modalInput:  { borderWidth: 1, borderRadius: 10, paddingHorizontal: 12, paddingVertical: 10, marginBottom: 16, fontFamily: 'Poppins_400Regular', fontSize: 14 },
  modalButtons: { flexDirection: 'row', gap: 10 },
  modalBtn:    { flex: 1, paddingVertical: 12, borderRadius: 10, alignItems: 'center' },
});
