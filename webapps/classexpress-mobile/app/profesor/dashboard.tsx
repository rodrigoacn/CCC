import {
  View, Text, FlatList, TouchableOpacity, StyleSheet,
  Platform, ActivityIndicator, Alert,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useRouter } from 'expo-router';
import { useQuery, useMutation } from '@tanstack/react-query';
import { Feather } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { useColors } from '@/hooks/useColors';
import { useAuth } from '@/context/AuthContext';
import { apiTeacherDashboard, apiStartRoom, Clase, Sesion } from '@/lib/api';

function ClaseItem({ item, onStart }: { item: Clase; onStart: () => void }) {
  const colors = useColors();
  return (
    <View style={[styles.claseItem, { backgroundColor: colors.card }]}>
      <View style={{ flex: 1 }}>
        <Text style={[styles.claseTitle, { color: colors.foreground }]} numberOfLines={1}>{item.titulo}</Text>
        <Text style={[styles.claseSub, { color: colors.subtext }]}>{item.materia} · {item.precio} cr.</Text>
        {item.sala_activa && (
          <View style={[styles.livePill, { backgroundColor: colors.danger }]}>
            <Text style={styles.liveLabel}>EN VIVO</Text>
          </View>
        )}
      </View>
      <TouchableOpacity
        style={[styles.startBtn, { backgroundColor: item.sala_activa ? colors.success : colors.primary }]}
        onPress={onStart}
      >
        <Feather name={item.sala_activa ? 'arrow-right' : 'video'} size={18} color="#fff" />
      </TouchableOpacity>
    </View>
  );
}

export default function DashboardScreen() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const { user } = useAuth();

  const { data, isLoading } = useQuery({
    queryKey: ['teacher_dashboard'],
    queryFn: apiTeacherDashboard,
    refetchInterval: 10_000,
  });

  const { mutate: startRoom } = useMutation({
    mutationFn: (clase_id: number) => apiStartRoom(clase_id),
    onSuccess: ({ sala }) => {
      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
      router.push(`/sala/${sala.id}`);
    },
    onError: (e: any) => Alert.alert('Error', e.message),
  });

  const botPad = Platform.OS === 'web' ? 34 : insets.bottom;

  if (isLoading) {
    return <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: colors.background }}>
      <ActivityIndicator color={colors.primary} size="large" />
    </View>;
  }

  const clases = data?.clases ?? [];
  const sesiones = data?.sesiones ?? [];
  const ganancias = data?.ganancias ?? 0;

  const header = (
    <>
      <View style={styles.statsRow}>
        <View style={[styles.statCard, { backgroundColor: colors.primary }]}>
          <Text style={styles.statNum}>{ganancias.toFixed(0)}</Text>
          <Text style={styles.statLbl}>créditos ganados</Text>
        </View>
        <View style={[styles.statCard, { backgroundColor: colors.success }]}>
          <Text style={styles.statNum}>{clases.filter(c => c.sala_activa).length}</Text>
          <Text style={styles.statLbl}>salas activas</Text>
        </View>
        <View style={[styles.statCard, { backgroundColor: colors.accent }]}>
          <Text style={styles.statNum}>{clases.length}</Text>
          <Text style={styles.statLbl}>clases</Text>
        </View>
      </View>

      <TouchableOpacity style={[styles.createBtn, { backgroundColor: colors.primaryLight }]}
        onPress={() => router.push('/profesor/crear')}>
        <Feather name="plus" size={20} color={colors.primary} />
        <Text style={[styles.createTxt, { color: colors.primary }]}>Crear nueva clase</Text>
      </TouchableOpacity>

      <Text style={[styles.section, { color: colors.foreground }]}>Mis clases</Text>
    </>
  );

  const footer = sesiones.length > 0 ? (
    <View style={{ marginTop: 16 }}>
      <Text style={[styles.section, { color: colors.foreground }]}>Sesiones recientes</Text>
      {sesiones.map((s: Sesion) => (
        <View key={s.id} style={[styles.sesionRow, { backgroundColor: colors.card, borderColor: colors.border }]}>
          <View style={{ flex: 1 }}>
            <Text style={[styles.sesionClase, { color: colors.foreground }]} numberOfLines={1}>{s.clase}</Text>
            <Text style={[styles.sesionDate, { color: colors.subtext }]}>
              {new Date(s.created_at).toLocaleDateString('es-ES')}
            </Text>
          </View>
        </View>
      ))}
    </View>
  ) : null;

  return (
    <FlatList
      data={clases}
      keyExtractor={i => String(i.id)}
      contentContainerStyle={{ padding: 16, paddingBottom: botPad + 24 }}
      style={{ backgroundColor: colors.background }}
      ListHeaderComponent={header}
      ListFooterComponent={footer}
      ItemSeparatorComponent={() => <View style={{ height: 10 }} />}
      ListEmptyComponent={
        <View style={{ alignItems: 'center', paddingTop: 32 }}>
          <Feather name="book" size={36} color={colors.mutedForeground} />
          <Text style={{ color: colors.subtext, marginTop: 10, fontFamily: 'Poppins_400Regular' }}>
            Aún no tienes clases. ¡Crea una!
          </Text>
        </View>
      }
      renderItem={({ item }) => (
        <ClaseItem
          item={item}
          onStart={() => {
            if (item.sala_activa && item.sala_id) router.push(`/sala/${item.sala_id}`);
            else startRoom(item.id);
          }}
        />
      )}
    />
  );
}

const styles = StyleSheet.create({
  statsRow:   { flexDirection: 'row', gap: 10, marginBottom: 16 },
  statCard:   { flex: 1, borderRadius: 16, padding: 14, alignItems: 'center' },
  statNum:    { color: '#fff', fontFamily: 'Poppins_700Bold', fontSize: 24 },
  statLbl:    { color: 'rgba(255,255,255,0.8)', fontFamily: 'Poppins_400Regular', fontSize: 11, textAlign: 'center' },
  createBtn:  { flexDirection: 'row', alignItems: 'center', gap: 8, borderRadius: 14, padding: 14, marginBottom: 20 },
  createTxt:  { fontFamily: 'Poppins_600SemiBold', fontSize: 15 },
  section:    { fontFamily: 'Poppins_700Bold', fontSize: 18, marginBottom: 12 },
  claseItem:  { borderRadius: 16, padding: 16, flexDirection: 'row', alignItems: 'center', gap: 12, boxShadow: '0px 2px 6px rgba(0,0,0,0.05)' },
  claseTitle: { fontFamily: 'Poppins_600SemiBold', fontSize: 15 },
  claseSub:   { fontFamily: 'Poppins_400Regular', fontSize: 13, marginTop: 2 },
  livePill:   { alignSelf: 'flex-start', marginTop: 6, paddingHorizontal: 8, paddingVertical: 2, borderRadius: 20 },
  liveLabel:  { color: '#fff', fontFamily: 'Poppins_700Bold', fontSize: 10 },
  startBtn:   { width: 44, height: 44, borderRadius: 14, justifyContent: 'center', alignItems: 'center' },
  sesionRow:  { borderRadius: 12, padding: 14, marginBottom: 8, borderWidth: 1 },
  sesionClase: { fontFamily: 'Poppins_500Medium', fontSize: 14 },
  sesionDate: { fontFamily: 'Poppins_400Regular', fontSize: 12 },
});
