import { useEffect } from 'react';
import { View, Text, TouchableOpacity, StyleSheet, Platform } from 'react-native';
import { Feather } from '@expo/vector-icons';
import { useColors } from '@/hooks/useColors';
import { useVideoCall, streamToURL, VideoCallStatus } from '@/hooks/useVideoCall';

interface VideoCallProps {
  salaId: number;
  userId: number;
  isTeacher: boolean;
}

function statusLabel(status: VideoCallStatus): string {
  switch (status) {
    case 'starting': return 'Iniciando cámara…';
    case 'waiting': return 'Esperando participante…';
    case 'connecting': return 'Conectando…';
    case 'connected': return 'Conectado';
    case 'error': return 'Error de conexión';
    default: return 'Videollamada';
  }
}

function WebVideo({ stream, mirror, videoId }: { stream: any; mirror?: boolean; videoId: string }) {
  useEffect(() => {
    if (Platform.OS !== 'web' || !stream) return;
    const el = document.getElementById(videoId) as HTMLVideoElement | null;
    if (el) {
      el.srcObject = stream;
      el.play?.().catch(() => {});
    }
    return () => {
      if (el) el.srcObject = null;
    };
  }, [stream, videoId]);

  if (Platform.OS !== 'web') return null;

  return (
    // @ts-expect-error web-only element
    <video
      id={videoId}
      autoPlay
      playsInline
      muted={mirror}
      style={{
        width: '100%',
        height: '100%',
        objectFit: 'cover',
        transform: mirror ? 'scaleX(-1)' : undefined,
      }}
    />
  );
}

export default function VideoCall({ salaId, userId, isTeacher }: VideoCallProps) {
  const colors = useColors();
  const {
    status, localStream, remoteStream, micOn, camOn, errorMsg,
    RTCView, startCall, toggleMic, toggleCam,
  } = useVideoCall(salaId, userId, isTeacher);

  useEffect(() => {
    startCall();
  }, [startCall]);

  const remoteURL = streamToURL(remoteStream);
  const localURL = streamToURL(localStream);
  const active = status !== 'idle' && status !== 'error';

  return (
    <View style={[styles.wrap, { backgroundColor: colors.muted }]}>
      <View style={styles.remoteBox}>
        {Platform.OS === 'web' && remoteStream ? (
          <WebVideo stream={remoteStream} videoId="ce-remote-video" />
        ) : RTCView && remoteURL ? (
          <RTCView streamURL={remoteURL} style={styles.fill} objectFit="cover" />
        ) : (
          <View style={styles.placeholder}>
            <Feather name="user" size={36} color={colors.subtext} />
            <Text style={[styles.placeholderTxt, { color: colors.subtext }]}>
              {status === 'connected' ? 'Esperando video…' : statusLabel(status)}
            </Text>
          </View>
        )}
      </View>

      {active && localStream && (
        <View style={[styles.localBox, { borderColor: colors.border }]}>
          {Platform.OS === 'web' ? (
            <WebVideo stream={localStream} mirror videoId="ce-local-video" />
          ) : RTCView && localURL ? (
            <RTCView streamURL={localURL} style={styles.fill} objectFit="cover" mirror />
          ) : null}
        </View>
      )}

      <View style={styles.controls}>
        <View style={[styles.badge, { backgroundColor: colors.background + 'CC' }]}>
          <View style={[styles.dot, {
            backgroundColor: status === 'connected' ? '#22C55E' : status === 'error' ? colors.danger : '#F59E0B',
          }]} />
          <Text style={[styles.badgeTxt, { color: colors.foreground }]}>{statusLabel(status)}</Text>
        </View>

        {active && (
          <View style={styles.btnRow}>
            <TouchableOpacity
              style={[styles.ctrlBtn, { backgroundColor: micOn ? colors.background + 'CC' : colors.danger + 'CC' }]}
              onPress={toggleMic}
            >
              <Feather name={micOn ? 'mic' : 'mic-off'} size={18} color={colors.foreground} />
            </TouchableOpacity>
            <TouchableOpacity
              style={[styles.ctrlBtn, { backgroundColor: camOn ? colors.background + 'CC' : colors.danger + 'CC' }]}
              onPress={toggleCam}
            >
              <Feather name={camOn ? 'video' : 'video-off'} size={18} color={colors.foreground} />
            </TouchableOpacity>
          </View>
        )}
      </View>

      {errorMsg ? (
        <Text style={[styles.error, { color: colors.danger }]}>{errorMsg}</Text>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { marginHorizontal: 16, borderRadius: 14, overflow: 'hidden', height: 220, marginBottom: 8, position: 'relative' },
  remoteBox: { flex: 1, backgroundColor: '#111' },
  fill: { flex: 1, width: '100%', height: '100%' },
  placeholder: { flex: 1, alignItems: 'center', justifyContent: 'center', gap: 8 },
  placeholderTxt: { fontFamily: 'Poppins_400Regular', fontSize: 13 },
  localBox: {
    position: 'absolute', right: 10, bottom: 44, width: 80, height: 110,
    borderRadius: 10, overflow: 'hidden', borderWidth: 2,
  },
  controls: {
    position: 'absolute', left: 10, right: 10, bottom: 8,
    flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between',
  },
  badge: { flexDirection: 'row', alignItems: 'center', gap: 6, paddingHorizontal: 10, paddingVertical: 5, borderRadius: 20 },
  dot: { width: 8, height: 8, borderRadius: 4 },
  badgeTxt: { fontFamily: 'Poppins_500Medium', fontSize: 11 },
  btnRow: { flexDirection: 'row', gap: 8 },
  ctrlBtn: { width: 36, height: 36, borderRadius: 18, alignItems: 'center', justifyContent: 'center' },
  error: { position: 'absolute', top: 8, left: 10, right: 10, fontFamily: 'Poppins_400Regular', fontSize: 11, textAlign: 'center' },
});
