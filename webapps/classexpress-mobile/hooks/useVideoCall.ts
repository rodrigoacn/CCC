import { useEffect, useRef, useState, useCallback } from 'react';
import { Platform, PermissionsAndroid } from 'react-native';

const WS_URL = 'wss://classexpress.online/ws/';

const RTC_CONFIG = {
  iceServers: [
    { urls: 'stun:stun.l.google.com:19302' },
    { urls: 'stun:stun1.l.google.com:19302' },
  ],
};

type MediaStreamLike = any;
type PeerConnectionLike = any;

function loadWebRTC() {
  if (Platform.OS === 'web') {
    return {
      RTCPeerConnection: globalThis.RTCPeerConnection,
      RTCSessionDescription: globalThis.RTCSessionDescription,
      RTCIceCandidate: globalThis.RTCIceCandidate,
      mediaDevices: globalThis.navigator?.mediaDevices,
      RTCView: null,
    };
  }
  // eslint-disable-next-line @typescript-eslint/no-require-imports
  const webrtc = require('react-native-webrtc');
  return {
    RTCPeerConnection: webrtc.RTCPeerConnection,
    RTCSessionDescription: webrtc.RTCSessionDescription,
    RTCIceCandidate: webrtc.RTCIceCandidate,
    mediaDevices: webrtc.mediaDevices,
    RTCView: webrtc.RTCView,
  };
}

export type VideoCallStatus = 'idle' | 'starting' | 'waiting' | 'connecting' | 'connected' | 'error';

export function useVideoCall(salaId: number, userId: number, isTeacher: boolean, onChatMessage?: (msg: any) => void) {
  const [status, setStatus] = useState<VideoCallStatus>('idle');
  const [localStream, setLocalStream] = useState<MediaStreamLike>(null);
  const [remoteStream, setRemoteStream] = useState<MediaStreamLike>(null);
  const [micOn, setMicOn] = useState(true);
  const [camOn, setCamOn] = useState(true);
  const [errorMsg, setErrorMsg] = useState('');

  const pcRef = useRef<PeerConnectionLike>(null);
  const localRef = useRef<MediaStreamLike>(null);
  const activeRef = useRef(false);
  const webrtcRef = useRef(loadWebRTC());

  const wsRef = useRef<WebSocket | null>(null);
  const wsReadyRef = useRef(false);

  const sendSignal = useCallback((tipo: string, payload: string) => {
    if (wsReadyRef.current && wsRef.current) {
      wsRef.current.send(JSON.stringify({ type: 'signal', tipo, payload }));
    }
  }, []);

  const buildPC = useCallback(() => {
    const { RTCPeerConnection } = webrtcRef.current;
    if (pcRef.current) {
      pcRef.current.close();
      pcRef.current = null;
    }
    const pc = new RTCPeerConnection(RTC_CONFIG);
    pcRef.current = pc;

    if (localRef.current) {
      localRef.current.getTracks().forEach((track: any) => {
        pc.addTrack(track, localRef.current);
      });
    }

    pc.ontrack = (event: any) => {
      const stream = event.streams?.[0]
        ?? (event.track && Platform.OS === 'web'
          ? new globalThis.MediaStream([event.track])
          : null);
      if (stream) setRemoteStream(stream);
      setStatus('connected');
    };

    pc.onicecandidate = (event: any) => {
      if (event.candidate) {
        sendSignal('candidate', JSON.stringify(event.candidate));
      }
    };

    pc.onconnectionstatechange = () => {
      const s = pc.connectionState;
      if (s === 'connected') setStatus('connected');
      if (s === 'failed' || s === 'disconnected') setStatus('error');
    };

    return pc;
  }, [sendSignal]);

  const handleSignal = useCallback(async (sig: { tipo: string; payload: string }) => {
    const { RTCSessionDescription, RTCIceCandidate } = webrtcRef.current;
    const payload = JSON.parse(sig.payload);

    if (sig.tipo === 'offer' && isTeacher) {
      const pc = buildPC();
      await pc.setRemoteDescription(new RTCSessionDescription(payload));
      const answer = await pc.createAnswer();
      await pc.setLocalDescription(answer);
      await sendSignal('answer', JSON.stringify(answer));
      setStatus('connecting');
    } else if (sig.tipo === 'answer' && !isTeacher && pcRef.current) {
      if (pcRef.current.signalingState !== 'stable') {
        await pcRef.current.setRemoteDescription(new RTCSessionDescription(payload));
      }
    } else if (sig.tipo === 'candidate' && pcRef.current?.remoteDescription) {
      try {
        await pcRef.current.addIceCandidate(new RTCIceCandidate(payload));
      } catch {
        // ignore stale candidates
      }
    } else if (sig.tipo === 'bye') {
      setRemoteStream(null);
      setStatus('waiting');
    }
  }, [buildPC, isTeacher, sendSignal]);

  const startCall = useCallback(async () => {
    if (activeRef.current) return;
    activeRef.current = true;
    setStatus('starting');
    setErrorMsg('');

    try {
      if (Platform.OS === 'android') {
        const granted = await PermissionsAndroid.requestMultiple([
          PermissionsAndroid.PERMISSIONS.CAMERA,
          PermissionsAndroid.PERMISSIONS.RECORD_AUDIO,
        ]);
        if (granted[PermissionsAndroid.PERMISSIONS.CAMERA] !== 'granted' ||
            granted[PermissionsAndroid.PERMISSIONS.RECORD_AUDIO] !== 'granted') {
          throw new Error('Permisos de cámara y micrófono denegados');
        }
      }

      const { mediaDevices } = webrtcRef.current;
      if (!mediaDevices?.getUserMedia) {
        throw new Error('Cámara/micrófono no disponibles en este dispositivo');
      }

      const stream = await mediaDevices.getUserMedia({ video: true, audio: true });
      localRef.current = stream;
      setLocalStream(stream);
      setStatus(isTeacher ? 'waiting' : 'connecting');

      // WebSocket connection for signaling
      const ws = new WebSocket(WS_URL);
      wsRef.current = ws;

      ws.onopen = () => {
        wsReadyRef.current = true;
        ws.send(JSON.stringify({ type: 'join', salaId: String(salaId), userId }));
      };

      ws.onmessage = (event) => {
        try {
          const msg = JSON.parse(event.data);
          if (msg.type === 'signal') {
            handleSignal(msg.data);
          } else if (msg.type === 'chat' && onChatMessage) {
            onChatMessage(msg.data);
          }
        } catch { /* ignore */ }
      };

      ws.onclose = () => {
        wsReadyRef.current = false;
      };

      ws.onerror = () => {
        wsReadyRef.current = false;
      };

      if (!isTeacher) {
        const pc = buildPC();
        const offer = await pc.createOffer();
        await pc.setLocalDescription(offer);
        sendSignal('offer', JSON.stringify(offer));
      }
    } catch (e: any) {
      activeRef.current = false;
      setStatus('error');
      setErrorMsg(e.message || 'No se pudo acceder a cámara/micrófono');
    }
  }, [buildPC, isTeacher, sendSignal, salaId, userId, handleSignal]);

  const stopCall = useCallback(async () => {
    activeRef.current = false;
    if (wsReadyRef.current && wsRef.current) {
      wsRef.current.send(JSON.stringify({ type: 'leave' }));
      wsRef.current.close();
    }
    wsRef.current = null;
    wsReadyRef.current = false;
    if (pcRef.current) {
      pcRef.current.close();
      pcRef.current = null;
    }
    if (localRef.current) {
      localRef.current.getTracks().forEach((t: any) => t.stop());
      localRef.current = null;
    }
    setLocalStream(null);
    setRemoteStream(null);
    setStatus('idle');
  }, []);

  const toggleMic = useCallback(() => {
    setMicOn(v => {
      const next = !v;
      localRef.current?.getAudioTracks().forEach((t: any) => { t.enabled = next; });
      return next;
    });
  }, []);

  const toggleCam = useCallback(() => {
    setCamOn(v => {
      const next = !v;
      localRef.current?.getVideoTracks().forEach((t: any) => { t.enabled = next; });
      return next;
    });
  }, []);

  const sendChat = useCallback((mensaje: string) => {
    if (wsReadyRef.current && wsRef.current) {
      wsRef.current.send(JSON.stringify({ type: 'chat_send', data: { mensaje } }));
    }
  }, []);

  useEffect(() => () => {
    activeRef.current = false;
    if (wsRef.current) {
      try { wsRef.current.close(); } catch {}
    }
    if (pcRef.current) pcRef.current.close();
    if (localRef.current) localRef.current.getTracks().forEach((t: any) => t.stop());
  }, []);

  return {
    status,
    localStream,
    remoteStream,
    micOn,
    camOn,
    errorMsg,
    RTCView: webrtcRef.current.RTCView,
    startCall,
    stopCall,
    toggleMic,
    toggleCam,
    sendChat,
  };
}

export function streamToURL(stream: MediaStreamLike): string | null {
  if (!stream) return null;
  if (Platform.OS === 'web') return stream;
  try {
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    const { MediaStream } = require('react-native-webrtc');
    return stream.toURL?.() ?? (stream instanceof MediaStream ? stream.toURL() : null);
  } catch {
    return stream.toURL?.() ?? null;
  }
}
