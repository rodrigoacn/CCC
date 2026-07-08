import { useState, useEffect, useRef } from 'react';
import {
  View, Text, FlatList, TextInput, TouchableOpacity,
  StyleSheet, KeyboardAvoidingView, Platform, Alert,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useQuery, useMutation } from '@tanstack/react-query';
import { Feather } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { useColors } from '@/hooks/useColors';
import { useAuth } from '@/context/AuthContext';
import { apiRoomStatus, apiSendMessage, apiLeaveRoom, Mensaje } from '@/lib/api';
import VideoCall from '@/components/VideoCall';

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
  const { id } = useLocalSearchParams<{ id: string }>();
  const { user } = useAuth();

  const [msg, setMsg] = useState('');
  const [messages, setMessages] = useState<Mensaje[]>([]);
  const [lastId, setLastId] = useState(0);
  const flatRef = useRef<FlatList>(null);
  const [joinedAt, setJoinedAt] = useState<Date | null>(null);
  const [countdown, setCountdown] = useState(180); // 3 minutes in seconds
  const [isSpectator, setIsSpectator] = useState(true);
  const [billingStatus, setBillingStatus] = useState('');
  const [spectators, setSpectators] = useState<any[]>([]);

  const { data: statusData } = useQuery({
    queryKey: ['room_status', id],
    queryFn: () => apiRoomStatus(id!),
    refetchInterval: 3000,
  });

  const sala = statusData?.sala;
  const participantes = statusData?.participantes ?? [];
  const isTeacher = user?.rol === 'instructor';

  useEffect(() => {
    if (statusData?.messages?.length) {
      setMessages(statusData.messages);
      const last = statusData.messages[statusData.messages.length - 1];
      setLastId(last.id);
    }
  }, [statusData]);

  // Countdown timer for 3-minute free period
  useEffect(() => {
    if (!isTeacher && countdown > 0) {
      const timer = setInterval(() => {
        setCountdown(prev => {
          if (prev <= 1) {
            setIsSpectator(false);
            setBillingStatus('Cobrando por tiempo...');
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

  const { mutate: send } = useMutation({
    mutationFn: () => apiSendMessage(Number(id), msg.trim()),
    onSuccess: ({ mensaje }) => {
      setMessages(prev => [...prev, mensaje]);
      setMsg('');
      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
      flatRef.current?.scrollToEnd({ animated: true });
    },
  });

  const { mutate: leave } = useMutation({
    mutationFn: () => apiLeaveRoom(Number(id)),
    onSuccess: () => {
      if (user?.rol === 'instructor') {
        router.replace('/profesor/dashboard');
      } else {
        router.replace(`/calificar/${id}`);
      }
    },
  });

  const handleLeave = () => {
    const elapsedMinutes = joinedAt ? (Date.now() - joinedAt.getTime()) / 60000 : 0;
    let message = user?.rol === 'instructor' ? '¿Cerrar la sala?' : 'Se procesará el pago de la clase.';
    
    if (!isTeacher) {
      if (elapsedMinutes < 3) {
        message = 'Salir de la sala? Estás en el periodo gratis, no se aplicará cobro.';
      } else {
        message = 'Salir de la sala? Se cobrará por el tiempo utilizado.';
      }
    }
    
    Alert.alert(
      'Salir de la sala',
      message,
      [
        { text: 'Cancelar', style: 'cancel' },
        { text: 'Salir', style: 'destructive', onPress: () => leave() },
      ]
    );
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
        <View style={{ flex: 1 }}>
          <Text style={[styles.roomTitle, { color: colors.foreground }]} numberOfLines={1}>
            {sala?.clase ?? 'Sala de clase'}
          </Text>
          <View style={styles.headerMeta}>
            <View style={[styles.liveDot, { backgroundColor: sala?.activa ? colors.danger : colors.mutedForeground }]} />
            <Text style={[styles.headerSub, { color: colors.subtext }]}>
              {sala?.activa ? 'En vivo' : 'Finalizada'} · {participantes.length} participante{participantes.length !== 1 ? 's' : ''}
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
          <Feather name="hourglass" size={14} color={colors.warning} />
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
          placeholder="Escribe un mensaje..."
          placeholderTextColor={colors.mutedForeground}
          value={msg}
          onChangeText={setMsg}
          onSubmitEditing={() => { if (msg.trim()) send(); }}
          returnKeyType="send"
        />
        <TouchableOpacity
          style={[styles.sendBtn, { backgroundColor: msg.trim() ? colors.primary : colors.muted }]}
          onPress={() => { if (msg.trim()) send(); }}
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
