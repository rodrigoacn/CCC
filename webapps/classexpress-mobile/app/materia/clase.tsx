import { View, Text, TouchableOpacity, StyleSheet, ScrollView, Alert, ActivityIndicator, Platform } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useQuery, useMutation } from '@tanstack/react-query';
import { Feather } from '@expo/vector-icons';
import { CameraView, useCameraPermissions, useMicrophonePermissions } from 'expo-camera';
import * as Haptics from 'expo-haptics';
import { useColors } from '@/hooks/useColors';
import { useAuth } from '@/context/AuthContext';
import { apiClassDetail, apiJoinRoom, apiStartRoom, esInstructor } from '@/lib/api';
import { useState } from 'react';

export default function ClaseDetailScreen() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const { id } = useLocalSearchParams<{ id: string }>();
  const { user } = useAuth();
  const [camEnabled, setCamEnabled] = useState(true);
  const [micEnabled, setMicEnabled] = useState(true);
  const [camPermission, requestCamPermission] = useCameraPermissions();
  const [micPermission, requestMicPermission] = useMicrophonePermissions();

  const { data, isLoading } = useQuery({
    queryKey: ['clase', id],
    queryFn: () => apiClassDetail(id!),
  });
  const clase = data?.clase;

  const { mutate: joinRoom, isPending: joining } = useMutation({
    mutationFn: () => apiJoinRoom(Number(clase?.sala_id)),
    onSuccess: ({ sala }) => {
      try { Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success); } catch {}
      if (Platform.OS === 'web') {
        window.alert('Entrando a la clase...');
        router.push(`/sala/${sala.id}?from=clase`);
      } else {
        Alert.alert('Entrando a la clase...');
        router.push(`/sala/${sala.id}?from=clase`);
      }
    },
    onError: (e: any) => {
      if (Platform.OS === 'web') {
        window.alert('Error: ' + (e?.message ?? 'Error desconocido'));
      } else {
        Alert.alert('Error', e?.message ?? 'Error desconocido');
      }
    },
  });

  const { mutate: startRoom, isPending: starting } = useMutation({
    mutationFn: () => apiStartRoom(Number(clase?.id)),
    onSuccess: ({ sala }) => {
      try { Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success); } catch {}
      router.push(`/sala/${sala.id}?from=clase`);
    },
    onError: (e: any) => {
      if (Platform.OS === 'web') {
        window.alert('Error: ' + (e?.message ?? 'Error desconocido'));
      } else {
        Alert.alert('Error', e?.message ?? 'Error desconocido');
      }
    },
  });

  const isTeacher = esInstructor(user?.rol) && Number(clase?.profesor_id) === Number(user?.id);

  const handleEmpezar = () => {
    if (isTeacher) {
      if (!camPermission?.granted) requestCamPermission();
      if (!micPermission?.granted) requestMicPermission();
      startRoom();
    } else {
      if (!camPermission?.granted) requestCamPermission();
      if (!micPermission?.granted) requestMicPermission();
      joinRoom();
    }
  };

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
      <TouchableOpacity
        onPress={() => router.back()}
        style={{ position: 'absolute', top: Platform.OS === 'web' ? 16 : insets.top + 8, left: 16, zIndex: 10, width: 40, height: 40, borderRadius: 20, backgroundColor: colors.muted, justifyContent: 'center', alignItems: 'center' }}
      >
        <Feather name="arrow-left" size={22} color={colors.foreground} />
      </TouchableOpacity>
      <ScrollView contentContainerStyle={{ padding: 20, paddingBottom: botPad + 240 }}>
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
            Tu saldo: {user?.creditos ?? 0} cr. — {(user?.creditos ?? 0) >= clase.precio ? 'Tienes suficiente \u2713' : 'Saldo insuficiente \u2717'}
          </Text>
        </View>

        {/* ── Camera preview inline ── */}
        <Text style={[styles.sectionTitle, { color: colors.foreground, marginTop: 24 }]}>
          Vista previa
        </Text>
        <View style={{ height: 260, borderRadius: 14, overflow: 'hidden', backgroundColor: '#000' }}>
          {camPermission?.granted && camEnabled ? (
            <View style={{ flex: 1 }}>
              <CameraView style={{ flex: 1 }} facing="front" mute={!micEnabled} active={camEnabled} />
              <View style={{ position: 'absolute', bottom: 8, left: 8, flexDirection: 'row', alignItems: 'center', gap: 6, backgroundColor: 'rgba(0,0,0,0.6)', paddingHorizontal: 10, paddingVertical: 5, borderRadius: 12 }}>
                <Feather name={micEnabled ? 'mic' : 'mic-off'} size={14} color={micEnabled ? '#4ade80' : '#f87171'} />
                <Text style={{ color: '#fff', fontFamily: 'Poppins_500Medium', fontSize: 11 }}>{micEnabled ? 'Micrófono activo' : 'Micrófono mute'}</Text>
              </View>
            </View>
          ) : (
            <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center' }}>
              <Feather name={camPermission?.granted ? 'camera-off' : 'camera-off'} size={36} color={colors.subtext} />
              <Text style={[styles.metaTxt, { color: colors.subtext, marginTop: 8 }]}>
                {camPermission?.granted ? 'Cámara desactivada' : 'Cámara no disponible'}
              </Text>
              {!camPermission?.granted && (
                <TouchableOpacity
                  style={{ marginTop: 12 }}
                  onPress={() => requestCamPermission()}
                >
                  <Text style={{ color: colors.primary, fontFamily: 'Poppins_600SemiBold' }}>Permitir cámara</Text>
                </TouchableOpacity>
              )}
            </View>
          )}
        </View>

        {/* ── Camera / Mic toggles ── */}
        <View style={{ flexDirection: 'row', gap: 12, marginTop: 16, justifyContent: 'center' }}>
          <TouchableOpacity
            style={[styles.toggleBtn, { backgroundColor: camEnabled ? colors.primary : colors.muted }]}
            onPress={() => {
              setCamEnabled(!camEnabled);
              if (!camPermission?.granted) requestCamPermission();
            }}
          >
            <Feather name={camEnabled ? 'camera' : 'camera-off'} size={18} color="#fff" />
            <Text style={{ color: '#fff', fontFamily: 'Poppins_600SemiBold', fontSize: 12 }}>Cámara</Text>
          </TouchableOpacity>
          <TouchableOpacity
            style={[styles.toggleBtn, { backgroundColor: micEnabled ? colors.primary : colors.muted }]}
            onPress={() => {
              setMicEnabled(!micEnabled);
              if (!micPermission?.granted) requestMicPermission();
            }}
          >
            <Feather name={micEnabled ? 'mic' : 'mic-off'} size={18} color="#fff" />
            <Text style={{ color: '#fff', fontFamily: 'Poppins_600SemiBold', fontSize: 12 }}>Micrófono</Text>
          </TouchableOpacity>
        </View>
      </ScrollView>

      <View style={[styles.footer, { paddingBottom: botPad + 16, backgroundColor: colors.surface, borderTopColor: colors.border }]}>
        {isTeacher ? (
          <TouchableOpacity
            style={[styles.btn, { backgroundColor: (joining || starting) ? colors.muted : colors.primary }]}
            onPress={handleEmpezar}
            disabled={joining || starting}
          >
            {(joining || starting) ? <ActivityIndicator color="#fff" /> : (
              <>
                <Feather name="video" size={20} color="#fff" />
                <Text style={styles.btnTxt}>Empezar</Text>
              </>
            )}
          </TouchableOpacity>
        ) : clase?.sala_activa ? (
          <TouchableOpacity
            style={[styles.btn, { backgroundColor: joining ? colors.muted : colors.primary }]}
            onPress={handleEmpezar}
            disabled={joining}
          >
            {joining ? <ActivityIndicator color="#fff" /> : (
              <>
                <Feather name="log-in" size={20} color="#fff" />
                <Text style={styles.btnTxt}>Entrar a la clase</Text>
              </>
            )}
          </TouchableOpacity>
        ) : (
          <View style={[styles.btn, { backgroundColor: colors.muted }]}>
            <Feather name="clock" size={20} color={colors.subtext} />
            <Text style={[styles.btnTxt, { color: colors.subtext }]}>Clase no iniciada aún</Text>
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
  sectionTitle: { fontFamily: 'Poppins_700Bold', fontSize: 18, marginBottom: 12 },
  toggleBtn:  { flexDirection: 'row', alignItems: 'center', gap: 6, paddingVertical: 10, paddingHorizontal: 16, borderRadius: 24 },
  footer:     { position: 'absolute', bottom: 0, left: 0, right: 0, padding: 20, borderTopWidth: 1 },
  btn:        { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 10, paddingVertical: 16, borderRadius: 16 },
  btnTxt:     { color: '#fff', fontFamily: 'Poppins_700Bold', fontSize: 16 },
});
