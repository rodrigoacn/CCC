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

  const { data: statusData } = useQuery({
    queryKey: ['room_status', id],
    queryFn: () => apiRoomStatus(id!),
    refetchInterval: 3000,
  });

  const sala = statusData?.sala;
  const participantes = statusData?.participantes ?? [];

  useEffect(() => {
    if (statusData?.messages?.length) {
      setMessages(statusData.messages);
      const last = statusData.messages[statusData.messages.length - 1];
      setLastId(last.id);
    }
  }, [statusData]);

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
      if (user?.rol === 'estudiante') {
        router.push(`/pago/${id}?sala_id=${id}&precio=${sala?.precio ?? 0}`);
      } else {
        router.back();
      }
    },
  });

  const handleLeave = () => {
    Alert.alert(
      'Salir de la sala',
      user?.rol === 'estudiante' ? 'Se procesará el pago de la clase.' : '¿Cerrar la sala?',
      [
        { text: 'Cancelar', style: 'cancel' },
        { text: 'Salir', style: 'destructive', onPress: () => leave() },
      ]
    );
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

      <View style={[styles.videoBanner, { backgroundColor: colors.muted }]}>
        <Feather name="video-off" size={28} color={colors.subtext} />
        <Text style={[styles.videoTxt, { color: colors.subtext }]}>
          Video disponible en la app instalada
        </Text>
        <Text style={[styles.videoSub, { color: colors.mutedForeground }]}>
          Escanea el QR en Expo Go para ver video completo
        </Text>
      </View>

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
  header:     { flexDirection: 'row', alignItems: 'flex-start', paddingHorizontal: 20, paddingBottom: 12, gap: 12 },
  roomTitle:  { fontFamily: 'Poppins_700Bold', fontSize: 18 },
  headerMeta: { flexDirection: 'row', alignItems: 'center', gap: 6, marginTop: 2 },
  liveDot:    { width: 8, height: 8, borderRadius: 4 },
  headerSub:  { fontFamily: 'Poppins_400Regular', fontSize: 13 },
  leaveBtn:   { width: 44, height: 44, borderRadius: 14, justifyContent: 'center', alignItems: 'center' },
  videoBanner:{ marginHorizontal: 16, borderRadius: 14, padding: 16, alignItems: 'center', gap: 6, marginBottom: 8 },
  videoTxt:   { fontFamily: 'Poppins_600SemiBold', fontSize: 14 },
  videoSub:   { fontFamily: 'Poppins_400Regular', fontSize: 12 },
  emptyChat:  { fontFamily: 'Poppins_400Regular', fontSize: 14 },
  bubble:     { maxWidth: '80%', borderRadius: 16, paddingHorizontal: 14, paddingVertical: 8, marginBottom: 8 },
  bubbleMine: { alignSelf: 'flex-end', backgroundColor: '#5B6EF5' },
  bubbleOther: { alignSelf: 'flex-start', backgroundColor: '#88888822' },
  bubbleUser: { fontFamily: 'Poppins_600SemiBold', fontSize: 12, marginBottom: 2 },
  bubbleTxt:  { fontFamily: 'Poppins_400Regular', fontSize: 14 },
  inputRow:   { flexDirection: 'row', alignItems: 'center', gap: 10, paddingHorizontal: 16, paddingTop: 10, borderTopWidth: 1 },
  input:      { flex: 1, borderRadius: 24, paddingHorizontal: 16, paddingVertical: 10, fontFamily: 'Poppins_400Regular', fontSize: 14 },
  sendBtn:    { width: 44, height: 44, borderRadius: 22, justifyContent: 'center', alignItems: 'center' },
});
