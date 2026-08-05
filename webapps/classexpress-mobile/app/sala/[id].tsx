import { useState, useEffect, useRef, useCallback } from 'react';
import {
  View, Text, FlatList, TextInput, TouchableOpacity,
  StyleSheet, KeyboardAvoidingView, Platform, Alert, Modal,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useQuery, useMutation } from '@tanstack/react-query';
import { Feather } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { useColors } from '@/hooks/useColors';
import { useAuth } from '@/context/AuthContext';
import { useI18n } from '@/context/I18nContext';
import { apiRoomStatus, apiSendMessage, apiLeaveRoom, apiRoomStudents, apiKickStudent, Mensaje, RoomStudent, esInstructor } from '@/lib/api';
import VideoCall from '@/components/VideoCall';

const WS_URL = 'wss://classexpress.online/ws/';

interface ChatMsg {
  mensajeId: number;
  alias: string;
  mensaje: string;
  enviado_at: string;
}

function MsgBubble({ item, myId }: { item: Mensaje; myId: number }) {
  const colors = useColors();
  const mine = item.usuario_id === myId;
  return (
    <View style={[styles.bubble, mine ? styles.bubbleMine : styles.bubbleOther]}>
      {!mine && <Text style={[styles.bubbleUser, { color: colors.primary }]}>{item.usuario}</Text>}
      <Text style={[styles.bubbleTxt, { color: mine ? '#fff' : colors.foreground }]}>{item.mensaje}</Text>
    </View>
  );
}

export default function SalaScreen() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const { id, from } = useLocalSearchParams<{ id: string; from?: string }>();
  const { user } = useAuth();
  const { t } = useI18n();

  const [msg, setMsg] = useState('');
  const [messages, setMessages] = useState<Mensaje[]>([]);
  const flatRef = useRef<FlatList>(null);
  const [joinedAt, setJoinedAt] = useState<Date | null>(null);
  const [countdown, setCountdown] = useState(180); // 3 minutes in seconds
  const isFreeLeaveRef = useRef(false);
  const [isSpectator, setIsSpectator] = useState(true);
  const [showTimeUpModal, setShowTimeUpModal] = useState(false);
  const [billingStatus, setBillingStatus] = useState('');
  const [spectators, setSpectators] = useState<any[]>([]);
  const [students, setStudents] = useState<RoomStudent[]>([]);
  const [showStudents, setShowStudents] = useState(false);
  const [kickTarget, setKickTarget] = useState<RoomStudent | null>(null);
  const [kickReason, setKickReason] = useState('');
  const wsRef = useRef<WebSocket | null>(null);
  const wsReadyRef = useRef(false);

  const isTeacher = esInstructor(user?.rol);

  // Poll students (teacher only)
  useEffect(() => {
    if (!isTeacher || !id) return;
    const poll = setInterval(async () => {
      try {
        const res = await apiRoomStudents(id);
        setStudents(res.students ?? []);
      } catch {}
    }, 5000);
    apiRoomStudents(id).then(r => setStudents(r.students ?? [])).catch(() => {});
    return () => clearInterval(poll);
  }, [isTeacher, id]);

  // WebSocket for chat
  const onChatMessage = useCallback((data: ChatMsg) => {
    setMessages(prev => {
      if (prev.some(m => m.id === data.mensajeId)) return prev;
      return [...prev, { id: data.mensajeId, usuario_id: 0, usuario: data.alias, mensaje: data.mensaje, created_at: data.enviado_at }];
    });
  }, []);

  useEffect(() => {
    if (!id || !user) return;
    const ws = new WebSocket(WS_URL);
    wsRef.current = ws;
    ws.onopen = () => {
      wsReadyRef.current = true;
      ws.send(JSON.stringify({ type: 'join', salaId: String(id), userId: user.id }));
    };
    ws.onmessage = (event) => {
      try {
        const msg = JSON.parse(event.data);
        if (msg.type === 'chat') {
          onChatMessage(msg.data);
        }
      } catch {}
    };
    ws.onclose = () => { wsReadyRef.current = false; };
    ws.onerror = () => { wsReadyRef.current = false; };
    return () => {
      wsReadyRef.current = false;
      ws.close();
    };
  }, [id, user, onChatMessage]);

  // Room status polling (only for sala info, not chat)
  const { data: statusData } = useQuery({
    queryKey: ['room_status', id],
    queryFn: () => apiRoomStatus(id!),
    refetchInterval: 30000,
  });

  const sala = statusData?.sala;
  const participantes = statusData?.participantes ?? [];

  // Countdown timer for 3-minute free period
  useEffect(() => {
    if (!isTeacher && countdown > 0) {
      const timer = setInterval(() => {
        setCountdown(prev => {
          if (prev <= 1) {
            setIsSpectator(false);
            setBillingStatus('Cobrando por tiempo...');
            setShowTimeUpModal(true);
            return 0;
          }
          return prev - 1;
        });
      }, 1000);
      return () => clearInterval(timer);
    }
  }, [isTeacher, countdown]);

  // Update billing status periodically
  useEffect(() => {
    if (!isTeacher && joinedAt) {
      const interval = setInterval(() => {
        const elapsed = Math.floor((Date.now() - joinedAt.getTime()) / 1000);
        const minutes = elapsed / 60;
        if (minutes < 3) {
          setBillingStatus('Gratis - Primeros 3 minutos');
        } else {
          setBillingStatus('Cobrando por tiempo...');
        }
      }, 30000);
      return () => clearInterval(interval);
    }
  }, [isTeacher, joinedAt]);

  // Set joined time when component mounts
  useEffect(() => {
    if (!isTeacher && !joinedAt) {
      setJoinedAt(new Date());
      setBillingStatus('Gratis - Primeros 3 minutos');
    }
  }, [isTeacher, joinedAt]);

  const send = useCallback((mensaje: string) => {
    if (wsReadyRef.current && wsRef.current) {
      wsRef.current.send(JSON.stringify({ type: 'chat_send', data: { mensaje } }));
      setMsg('');
      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
      flatRef.current?.scrollToEnd({ animated: true });
    } else {
      // Fallback to HTTP
      apiSendMessage(Number(id), mensaje).then(({ mensaje: m }) => {
        setMessages(prev => {
          if (prev.some(x => x.id === m.id)) return prev;
          return [...prev, m];
        });
        setMsg('');
        flatRef.current?.scrollToEnd({ animated: true });
      }).catch(() => {});
    }
  }, [id]);

  const { mutate: leave } = useMutation({
    mutationFn: () => apiLeaveRoom(Number(id)),
    onSuccess: () => {
      if (esInstructor(user?.rol)) {
        router.replace('/profesor/crear');
      } else if (isFreeLeaveRef.current) {
        router.replace(from === 'explorar' ? '/(tabs)/sala' : '/(tabs)');
      } else if (from === 'explorar') {
        router.replace('/(tabs)/sala');
      } else {
        router.replace(`/calificar/${id}`);
      }
    },
  });

  const handleLeave = () => {
    const elapsedMinutes = joinedAt ? (Date.now() - joinedAt.getTime()) / 60000 : 0;
    isFreeLeaveRef.current = !isTeacher && elapsedMinutes < 3;
    let message = esInstructor(user?.rol) ? t('sala.close_confirm') : t('sala.will_pay');
    
    if (!isTeacher) {
      if (elapsedMinutes < 3) {
        message = t('sala.leave_free');
      } else {
        message = t('sala.leave_pay');
      }
    }

    const doLeave = () => leave();

    if (Platform.OS === 'web') {
      if (window.confirm(message)) doLeave();
    } else {
      Alert.alert(
        t('sala.leave_title'),
        message,
        [
          { text: t('common.cancel'), style: 'cancel' },
          { text: t('sala.leave'), style: 'destructive', onPress: doLeave },
        ]
      );
    }
  };

  const handleApproveSpectator = async (espectadorId: number) => {
    // TODO: Implement API call to approve spectator
    setSpectators(prev => prev.filter(s => s.espectadorid !== espectadorId));
    Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
  };

  const handleRejectSpectator = async (espectadorId: number) => {
    // TODO: Implement API call to reject spectator
    setSpectators(prev => prev.filter(s => s.espectadorid !== espectadorId));
    Haptics.notificationAsync(Haptics.NotificationFeedbackType.Warning);
  };

  const botPad = Platform.OS === 'web' ? 34 : insets.bottom;

  return (
    <KeyboardAvoidingView
      style={{ flex: 1, backgroundColor: colors.background }}
      behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
    >
      <View style={[styles.header, { paddingTop: Platform.OS === 'web' ? 67 : insets.top + 12 }]}>
        <TouchableOpacity style={[styles.backBtn, { backgroundColor: colors.muted }]} onPress={() => router.back()}>
          <Feather name="arrow-left" size={20} color={colors.foreground} />
        </TouchableOpacity>
        <View style={{ flex: 1 }}>
          <Text style={[styles.roomTitle, { color: colors.foreground }]} numberOfLines={1}>
            {sala?.clase ?? t('sala.classroom')}
          </Text>
          <View style={styles.headerMeta}>
            <View style={[styles.liveDot, { backgroundColor: sala?.activa ? colors.danger : colors.mutedForeground }]} />
            <Text style={[styles.headerSub, { color: colors.subtext }]}>
              {sala?.activa ? t('sala.live') : t('sala.ended')} · {participantes.length} {t('sala.participants', { count: String(participantes.length) })}
            </Text>
          </View>
        </View>
        <TouchableOpacity style={[styles.leaveBtn, { backgroundColor: colors.danger + '22' }]} onPress={handleLeave}>
          <Feather name="phone-off" size={20} color={colors.danger} />
        </TouchableOpacity>
      </View>

      {/* Countdown badge (for students) */}
      {!isTeacher && countdown > 0 && (
        <View style={[styles.countdownBadge, { backgroundColor: colors.warning + '22' }]}>
          <Feather name="clock" size={14} color={colors.warning} />
          <Text style={[styles.countdownText, { color: colors.warning }]}>
            {Math.floor(countdown / 60)}:{(countdown % 60).toString().padStart(2, '0')} gratis
          </Text>
        </View>
      )}

      {/* Billing status badge (for students) */}
      {!isTeacher && billingStatus && (
        <View style={[styles.billingBadge, { backgroundColor: colors.muted }]}>
          <Feather name="credit-card" size={14} color={colors.subtext} />
          <Text style={[styles.billingText, { color: colors.subtext }]}>{billingStatus}</Text>
        </View>
      )}

      {/* Time-up modal (when free minutes expire) */}
      <Modal visible={showTimeUpModal} transparent animationType="fade" onRequestClose={() => setShowTimeUpModal(false)}>
        <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: 'rgba(0,0,0,0.7)' }}>
          <View style={{ width: '85%', maxWidth: 360, backgroundColor: colors.surface, borderRadius: 20, padding: 24, borderWidth: 1, borderColor: colors.border, alignItems: 'center' }}>
            <Text style={{ fontSize: 48, marginBottom: 12 }}>⏰</Text>
            <Text style={[styles.roomTitle, { textAlign: 'center', marginBottom: 8 }]}>{t('sala.time_up')}</Text>
            <Text style={[styles.billingText, { textAlign: 'center', marginBottom: 20, color: colors.subtext, lineHeight: 20 }]}>
              {t('sala.time_up_continue')}
            </Text>
            <View style={{ flexDirection: 'row', gap: 12, width: '100%' }}>
              <TouchableOpacity
                style={{ flex: 1, backgroundColor: colors.success, borderRadius: 14, paddingVertical: 14, alignItems: 'center' }}
                onPress={() => setShowTimeUpModal(false)}
              >
                <Text style={{ color: '#fff', fontFamily: 'Poppins_700Bold', fontSize: 14 }}>{t('sala.time_up_accept')}</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={{ flex: 1, backgroundColor: colors.danger + '22', borderRadius: 14, paddingVertical: 14, alignItems: 'center' }}
                onPress={() => {
                  setShowTimeUpModal(false);
                  leave();
                }}
              >
                <Text style={{ color: colors.danger, fontFamily: 'Poppins_700Bold', fontSize: 14 }}>{t('sala.time_up_exit')}</Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      {/* Students list (teacher only) */}
      {isTeacher && (
        <View style={[styles.spectatorsCard, { backgroundColor: colors.card, marginHorizontal: 16, marginTop: 8, padding: 12, borderRadius: 12 }]}>
          <View style={styles.spectatorsHeader}>
            <TouchableOpacity onPress={() => setShowStudents(!showStudents)} style={{ flexDirection: 'row', alignItems: 'center', gap: 8, flex: 1 }}>
              <Feather name="users" size={16} color={colors.primary} />
              <Text style={[styles.spectatorsTitle, { color: colors.foreground }]}>Estudiantes ({students.length})</Text>
              <Feather name={showStudents ? 'chevron-up' : 'chevron-down'} size={16} color={colors.subtext} />
            </TouchableOpacity>
          </View>
          {showStudents && students.map((st) => (
            <View key={st.sesionId} style={[styles.spectatorItem, { borderBottomColor: colors.border }]}>
              <View style={{ flex: 1 }}>
                <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
                  <Text style={[styles.spectatorName, { color: colors.foreground }]}>{st.nombre}</Text>
                  <View style={{ backgroundColor: st.es_gratis ? colors.success : colors.primary, paddingHorizontal: 6, paddingVertical: 2, borderRadius: 8 }}>
                    <Text style={{ color: '#fff', fontSize: 9, fontFamily: 'Poppins_600SemiBold' }}>{st.es_gratis ? 'Gratis' : 'Pagando'}</Text>
                  </View>
                </View>
                <Text style={[styles.spectatorUsername, { color: colors.subtext }]}>
                  @{st.username}{st.pais ? ` · ${st.pais}` : ''}{st.idiomas ? ` · ${st.idiomas}` : ''}
                </Text>
              </View>
              <TouchableOpacity style={[styles.rejectBtn, { backgroundColor: colors.danger }]} onPress={() => setKickTarget(st)}>
                <Feather name="x-circle" size={16} color="#fff" />
              </TouchableOpacity>
            </View>
          ))}
          {showStudents && students.length === 0 && (
            <Text style={{ color: colors.subtext, fontFamily: 'Poppins_400Regular', fontSize: 12, textAlign: 'center', paddingVertical: 8 }}>
              No students yet
            </Text>
          )}
        </View>
      )}

      {/* Spectators list (for teachers) */}
      {isTeacher && spectators.length > 0 && (
        <View style={[styles.spectatorsCard, { backgroundColor: colors.card, marginHorizontal: 16, marginTop: 8, padding: 12, borderRadius: 12 }]}>
          <View style={styles.spectatorsHeader}>
            <Feather name="users" size={16} color={colors.primary} />
            <Text style={[styles.spectatorsTitle, { color: colors.foreground }]}>Espectadores ({spectators.length})</Text>
          </View>
          {spectators.map((s: any) => (
            <View key={s.espectadorid} style={[styles.spectatorItem, { borderBottomColor: colors.border }]}>
              <View style={{ flex: 1 }}>
                <Text style={[styles.spectatorName, { color: colors.foreground }]}>{s.nombre}</Text>
                <Text style={[styles.spectatorUsername, { color: colors.subtext }]}>@{s.username}</Text>
              </View>
              <View style={styles.spectatorActions}>
                <TouchableOpacity style={[styles.approveBtn, { backgroundColor: colors.success }]} onPress={() => handleApproveSpectator(s.espectadorid)}>
                  <Feather name="check" size={16} color="#fff" />
                </TouchableOpacity>
                <TouchableOpacity style={[styles.rejectBtn, { backgroundColor: colors.danger }]} onPress={() => handleRejectSpectator(s.espectadorid)}>
                  <Feather name="x" size={16} color="#fff" />
                </TouchableOpacity>
              </View>
            </View>
          ))}
        </View>
      )}

      {/* Kick Modal */}
      <Modal visible={!!kickTarget} transparent animationType="fade" onRequestClose={() => setKickTarget(null)}>
        <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: 'rgba(0,0,0,0.7)' }}>
          <View style={{ width: '85%', maxWidth: 360, backgroundColor: colors.surface, borderRadius: 20, padding: 24, borderWidth: 1, borderColor: colors.border }}>
            <View style={{ alignItems: 'center', marginBottom: 12 }}>
              <Feather name="shield-off" size={36} color={colors.danger} />
            </View>
            <Text style={[styles.roomTitle, { textAlign: 'center', marginBottom: 4 }]}>{t('sala.kick_student')}</Text>
            <Text style={{ textAlign: 'center', color: colors.subtext, fontFamily: 'Poppins_400Regular', fontSize: 14, marginBottom: 16 }}>
              {kickTarget?.nombre}
            </Text>
            <TextInput
              style={{ borderWidth: 1, borderColor: colors.border, borderRadius: 12, padding: 12, color: colors.foreground, backgroundColor: colors.muted, fontFamily: 'Poppins_400Regular', fontSize: 14, minHeight: 80, textAlignVertical: 'top' }}
              placeholder={t('sala.kick_reason')}
              placeholderTextColor={colors.mutedForeground}
              value={kickReason}
              onChangeText={setKickReason}
              multiline
              maxLength={500}
            />
            <View style={{ flexDirection: 'row', gap: 12, marginTop: 16 }}>
              <TouchableOpacity
                style={{ flex: 1, backgroundColor: colors.danger, borderRadius: 14, paddingVertical: 14, alignItems: 'center' }}
                onPress={async () => {
                  if (!kickReason.trim() || !kickTarget) return;
                  try {
                    await apiKickStudent(Number(id), kickTarget.estudianteId, kickReason.trim());
                    setStudents(prev => prev.filter(s => s.estudianteId !== kickTarget.estudianteId));
                    setKickTarget(null);
                    setKickReason('');
                  } catch {}
                }}
              >
                <Text style={{ color: '#fff', fontFamily: 'Poppins_700Bold', fontSize: 14 }}>Expulsar</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={{ flex: 1, backgroundColor: colors.muted, borderRadius: 14, paddingVertical: 14, alignItems: 'center' }}
                onPress={() => { setKickTarget(null); setKickReason(''); }}
              >
                <Text style={{ color: colors.foreground, fontFamily: 'Poppins_700Bold', fontSize: 14 }}>Cancelar</Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      {user && id ? (
        <VideoCall salaId={Number(id)} userId={user.id} isTeacher={isTeacher} />
      ) : null}

      <FlatList
        ref={flatRef}
        data={messages}
        keyExtractor={i => String(i.id)}
        contentContainerStyle={{ padding: 16, paddingBottom: 8 }}
        ListEmptyComponent={
          <View style={{ alignItems: 'center', paddingTop: 24 }}>
            <Text style={[styles.emptyChat, { color: colors.mutedForeground }]}>El chat está vacío. ¡Di hola! 👋</Text>
          </View>
        }
        renderItem={({ item }) => <MsgBubble item={item} myId={user?.id ?? 0} />}
        onContentSizeChange={() => flatRef.current?.scrollToEnd({ animated: true })}
      />

      <View style={[styles.inputRow, { paddingBottom: botPad + 8, backgroundColor: colors.surface, borderTopColor: colors.border }]}>
        <TextInput
          style={[styles.input, { backgroundColor: colors.muted, color: colors.foreground }]}
          placeholder={t('sala.type_message')}
          placeholderTextColor={colors.mutedForeground}
          value={msg}
          onChangeText={setMsg}
          onSubmitEditing={() => { if (msg.trim()) send(msg.trim()); }}
          returnKeyType="send"
        />
        <TouchableOpacity
          style={[styles.sendBtn, { backgroundColor: msg.trim() ? colors.primary : colors.muted }]}
          onPress={() => { if (msg.trim()) send(msg.trim()); }}
          disabled={!msg.trim()}
        >
          <Feather name="send" size={18} color={msg.trim() ? '#fff' : colors.mutedForeground} />
        </TouchableOpacity>
      </View>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  header:          { flexDirection: 'row', alignItems: 'flex-start', paddingHorizontal: 20, paddingBottom: 12, gap: 12 },
  roomTitle:       { fontFamily: 'Poppins_700Bold', fontSize: 18 },
  headerMeta:      { flexDirection: 'row', alignItems: 'center', gap: 6, marginTop: 2 },
  liveDot:         { width: 8, height: 8, borderRadius: 4 },
  headerSub:       { fontFamily: 'Poppins_400Regular', fontSize: 13 },
  leaveBtn:        { width: 44, height: 44, borderRadius: 14, justifyContent: 'center', alignItems: 'center' },
  backBtn:         { width: 40, height: 40, borderRadius: 20, justifyContent: 'center', alignItems: 'center', marginRight: 8 },
  countdownBadge:  { flexDirection: 'row', alignItems: 'center', gap: 6, alignSelf: 'center', paddingHorizontal: 12, paddingVertical: 6, borderRadius: 20, marginTop: 8 },
  countdownText:   { fontFamily: 'Poppins_600SemiBold', fontSize: 12 },
  billingBadge:    { flexDirection: 'row', alignItems: 'center', gap: 6, alignSelf: 'center', paddingHorizontal: 12, paddingVertical: 6, borderRadius: 20, marginTop: 4 },
  billingText:     { fontFamily: 'Poppins_400Regular', fontSize: 11 },
  spectatorsCard:  { marginTop: 8 },
  spectatorsHeader: { flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 8 },
  spectatorsTitle: { fontFamily: 'Poppins_600SemiBold', fontSize: 14 },
  spectatorItem:   { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingVertical: 8, borderBottomWidth: 1 },
  spectatorName:   { fontFamily: 'Poppins_500Medium', fontSize: 14 },
  spectatorUsername: { fontFamily: 'Poppins_400Regular', fontSize: 12 },
  spectatorActions: { flexDirection: 'row', gap: 8 },
  approveBtn:      { width: 32, height: 32, borderRadius: 16, justifyContent: 'center', alignItems: 'center' },
  rejectBtn:       { width: 32, height: 32, borderRadius: 16, justifyContent: 'center', alignItems: 'center' },
  emptyChat:       { fontFamily: 'Poppins_400Regular', fontSize: 14 },
  bubble:          { maxWidth: '80%', borderRadius: 16, paddingHorizontal: 14, paddingVertical: 8, marginBottom: 8 },
  bubbleMine:      { alignSelf: 'flex-end', backgroundColor: '#5B6EF5' },
  bubbleOther:     { alignSelf: 'flex-start', backgroundColor: '#88888822' },
  bubbleUser:      { fontFamily: 'Poppins_600SemiBold', fontSize: 12, marginBottom: 2 },
  bubbleTxt:       { fontFamily: 'Poppins_400Regular', fontSize: 14 },
  inputRow:        { flexDirection: 'row', alignItems: 'center', gap: 10, paddingHorizontal: 16, paddingTop: 10, borderTopWidth: 1 },
  input:           { flex: 1, borderRadius: 24, paddingHorizontal: 16, paddingVertical: 10, fontFamily: 'Poppins_400Regular', fontSize: 14 },
  sendBtn:         { width: 44, height: 44, borderRadius: 22, justifyContent: 'center', alignItems: 'center' },
});
