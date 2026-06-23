import { View, Text, TouchableOpacity, StyleSheet, ScrollView, Alert, ActivityIndicator, Platform } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useQuery, useMutation } from '@tanstack/react-query';
import { Feather } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { useColors } from '@/hooks/useColors';
import { useAuth } from '@/context/AuthContext';
import { apiClassDetail, apiJoinRoom, apiStartRoom } from '@/lib/api';

export default function ClaseDetailScreen() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const { id } = useLocalSearchParams<{ id: string }>();
  const { user } = useAuth();

  const { data, isLoading } = useQuery({
    queryKey: ['clase', id],
    queryFn: () => apiClassDetail(id!),
  });
  const clase = data?.clase;

  const { mutate: joinRoom, isPending: joining } = useMutation({
    mutationFn: () => apiJoinRoom(Number(clase?.sala_id)),
    onSuccess: ({ sala }) => {
      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
      router.push(`/sala/${sala.id}`);
    },
    onError: (e: any) => Alert.alert('Error', e.message),
  });

  const { mutate: startRoom, isPending: starting } = useMutation({
    mutationFn: () => apiStartRoom(Number(clase?.id)),
    onSuccess: ({ sala }) => {
      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
      router.push(`/sala/${sala.id}`);
    },
    onError: (e: any) => Alert.alert('Error', e.message),
  });

  const isTeacher = user?.rol === 'instructor' && clase?.profesor_id === user?.id;
  const canJoin = !isTeacher && clase?.sala_activa && clase?.sala_id;
  const canStart = isTeacher;

  if (isLoading) {
    return (
      <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: colors.background }}>
        <ActivityIndicator color={colors.primary} size="large" />
      </View>
    );
  }

  if (!clase) return null;

  const botPad = Platform.OS === 'web' ? 34 : insets.bottom;

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <ScrollView contentContainerStyle={{ padding: 20, paddingBottom: botPad + 100 }}>
        {clase.sala_activa && (
          <View style={[styles.liveBanner, { backgroundColor: colors.danger + '22' }]}>
            <View style={[styles.liveDot, { backgroundColor: colors.danger }]} />
            <Text style={[styles.liveTxt, { color: colors.danger }]}>CLASE EN VIVO AHORA</Text>
          </View>
        )}

        <Text style={[styles.title, { color: colors.foreground }]}>{clase.titulo}</Text>
        <Text style={[styles.materia, { color: colors.primary }]}>{clase.materia}</Text>

        <View style={styles.metaRow}>
          <View style={styles.metaItem}>
            <Feather name="user" size={14} color={colors.subtext} />
            <Text style={[styles.metaTxt, { color: colors.subtext }]}>{clase.profesor}</Text>
          </View>
          {clase.duracion_minutos ? (
            <View style={styles.metaItem}>
              <Feather name="clock" size={14} color={colors.subtext} />
              <Text style={[styles.metaTxt, { color: colors.subtext }]}>{clase.duracion_minutos} min</Text>
            </View>
          ) : null}
          <View style={styles.metaItem}>
            <Feather name="star" size={14} color={colors.warning} />
            <Text style={[styles.metaTxt, { color: colors.subtext }]}>{Number(clase.rating ?? 4).toFixed(1)}</Text>
          </View>
        </View>

        {clase.descripcion ? (
          <View style={[styles.descBox, { backgroundColor: colors.muted }]}>
            <Text style={[styles.descTxt, { color: colors.foreground }]}>{clase.descripcion}</Text>
          </View>
        ) : null}

        <View style={[styles.priceBox, { backgroundColor: colors.primaryLight }]}>
          <Text style={[styles.priceLabel, { color: colors.subtext }]}>Precio de la clase</Text>
          <Text style={[styles.priceNum, { color: colors.primary }]}>{clase.precio} créditos</Text>
          <Text style={[styles.priceSub, { color: colors.subtext }]}>
            Tu saldo: {user?.creditos ?? 0} cr. — {(user?.creditos ?? 0) >= clase.precio ? 'Tienes suficiente ✓' : 'Saldo insuficiente ✗'}
          </Text>
        </View>
      </ScrollView>

      <View style={[styles.footer, { paddingBottom: botPad + 16, backgroundColor: colors.surface, borderTopColor: colors.border }]}>
        {canStart ? (
          <>
            <TouchableOpacity
              style={[styles.btn, { backgroundColor: clase.sala_activa ? colors.danger : colors.primary }]}
              onPress={() => startRoom()}
              disabled={starting}
            >
              {starting ? <ActivityIndicator color="#fff" /> : (
                <>
                  <Feather name={clase.sala_activa ? 'refresh-cw' : 'video'} size={20} color="#fff" />
                  <Text style={styles.btnTxt}>{clase.sala_activa ? 'Reiniciar sala' : 'Abrir sala'}</Text>
                </>
              )}
            </TouchableOpacity>
          </>
        ) : canJoin ? (
          <TouchableOpacity
            style={[styles.btn, { backgroundColor: colors.primary }]}
            onPress={() => joinRoom()}
            disabled={joining}
          >
            {joining ? <ActivityIndicator color="#fff" /> : (
              <>
                <Feather name="video" size={20} color="#fff" />
                <Text style={styles.btnTxt}>Unirse a la clase</Text>
              </>
            )}
          </TouchableOpacity>
        ) : (
          <View style={[styles.btn, { backgroundColor: colors.muted }]}>
            <Feather name="clock" size={20} color={colors.subtext} />
            <Text style={[styles.btnTxt, { color: colors.subtext }]}>
              {isTeacher ? 'Esperando estudiantes' : 'Clase no iniciada aún'}
            </Text>
          </View>
        )}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  liveBanner: { flexDirection: 'row', alignItems: 'center', gap: 8, padding: 10, borderRadius: 12, marginBottom: 16 },
  liveDot:    { width: 8, height: 8, borderRadius: 4 },
  liveTxt:    { fontFamily: 'Poppins_700Bold', fontSize: 12, letterSpacing: 1 },
  title:      { fontFamily: 'Poppins_700Bold', fontSize: 24, marginBottom: 6 },
  materia:    { fontFamily: 'Poppins_600SemiBold', fontSize: 14, marginBottom: 16 },
  metaRow:    { flexDirection: 'row', gap: 16, marginBottom: 20, flexWrap: 'wrap' },
  metaItem:   { flexDirection: 'row', alignItems: 'center', gap: 6 },
  metaTxt:    { fontFamily: 'Poppins_400Regular', fontSize: 13 },
  descBox:    { borderRadius: 14, padding: 16, marginBottom: 20 },
  descTxt:    { fontFamily: 'Poppins_400Regular', fontSize: 14, lineHeight: 22 },
  priceBox:   { borderRadius: 16, padding: 20 },
  priceLabel: { fontFamily: 'Poppins_400Regular', fontSize: 13, marginBottom: 4 },
  priceNum:   { fontFamily: 'Poppins_700Bold', fontSize: 32, marginBottom: 4 },
  priceSub:   { fontFamily: 'Poppins_400Regular', fontSize: 13 },
  footer:     { position: 'absolute', bottom: 0, left: 0, right: 0, padding: 20, borderTopWidth: 1 },
  btn:        { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 10, paddingVertical: 16, borderRadius: 16 },
  btnTxt:     { color: '#fff', fontFamily: 'Poppins_700Bold', fontSize: 16 },
});
