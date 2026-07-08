import AsyncStorage from '@react-native-async-storage/async-storage';

const API_BASE = process.env.EXPO_PUBLIC_API_URL ?? 'http://10.0.2.2/CCC';
export const API_URL = `${API_BASE}/api_mobile.php`;

async function getToken(): Promise<string | null> {
  return AsyncStorage.getItem('ce_token');
}

export async function request<T>(action: string, data?: Record<string, unknown>): Promise<T> {
  const token = await getToken();
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  };
  if (token) headers['Authorization'] = `Bearer ${token}`;
  const res = await fetch(`${API_URL}?action=${action}`, {
    method: 'POST',
    headers,
    body: JSON.stringify(data ?? {}),
  });
  const json = await res.json();
  if (json.error) {
    const err = new Error(json.error) as Error & { code?: string };
    if (json.code) err.code = json.code;
    throw err;
  }
  return json as T;
}

export async function get<T>(action: string, params?: Record<string, string>): Promise<T> {
  const token = await getToken();
  const headers: Record<string, string> = { Accept: 'application/json' };
  if (token) headers['Authorization'] = `Bearer ${token}`;
  const qs = params ? '&' + new URLSearchParams(params).toString() : '';
  const res = await fetch(`${API_URL}?action=${action}${qs}`, { headers });
  const json = await res.json();
  if (json.error) {
    const err = new Error(json.error) as Error & { code?: string };
    if (json.code) err.code = json.code;
    throw err;
  }
  return json as T;
}

export const apiLogin = (email: string, password: string) =>
  request<{ token: string; user: User }>('login', { email, password });

export const apiRegister = (data: {
  nombre: string; email: string; password: string; pais_id: number; rol: string;
}) => request<{ needs_verification: boolean; message: string; email: string }>('register', data);

export const apiResendVerification = (email: string) =>
  request<{ message: string }>('resend_verification', { email });

export const apiVerifyEmail = (token: string) =>
  request<{ message: string; verified?: boolean; already_verified?: boolean }>('verify_email', { token });

export const apiProfile = () => get<{ user: User }>('profile');

export const apiSubjects = () => get<{ subjects: Subject[] }>('subjects');

export const apiTeachers = (params?: Record<string, string>) =>
  get<{ teachers: User[] }>('teachers', params);

export const apiClasses = (params?: Record<string, string>) =>
  get<{ classes: Clase[] }>('classes', params);

export const apiClassDetail = (id: string) =>
  get<{ clase: Clase }>('class_detail', { id });

export const apiCredits = () => get<{ balance: number; history: Pago[] }>('credits');
export const apiTopup = (amount: number) =>
  request<{ balance: number }>('topup', { amount });

export const apiJoinRoom = (sala_id: number) =>
  request<{ sala: Sala }>('join_room', { sala_id });
export const apiLeaveRoom = (sala_id: number) =>
  request<{ ok: boolean }>('leave_room', { sala_id });
export const apiRoomStatus = (sala_id: string) =>
  get<{ sala: Sala; participantes: User[]; messages: Mensaje[] }>('room_status', { sala_id });
export const apiSendMessage = (sala_id: number, mensaje: string) =>
  request<{ mensaje: Mensaje }>('send_message', { sala_id, mensaje });
export const apiMessages = (sala_id: string, after?: number) =>
  get<{ messages: Mensaje[] }>('messages', {
    sala_id,
    ...(after ? { after: String(after) } : {}),
  });
export const apiSendSignal = (sala_id: number, tipo: string, payload: string, to_uid?: number) =>
  request<{ ok: boolean }>('signal', { sala_id, tipo, payload, ...(to_uid ? { to_uid } : {}) });
export const apiPollSignals = (sala_id: string, after_id: number) =>
  get<{ signals: WebRTCSignal[] }>('poll_signals', { sala_id, after_id: String(after_id) });
export const apiPayment = (sala_id: number) =>
  request<{ ok: boolean; creditos_restantes: number; recibo: string }>('payment', { sala_id });

export const apiRateSession = (sala_id: number, rating: number) =>
  request<{ ok: boolean }>('rate_session', { sala_id, rating });

export const apiTeacherDashboard = () =>
  get<{ ganancias: number; clases: Clase[]; sesiones: Sesion[] }>('teacher_dashboard');
export const apiCreateClass = (data: {
  titulo: string; materia_id: number; precio: number; descripcion: string; duracion: number;
}) => request<{ clase: Clase }>('create_class', data);
export const apiStartRoom = (clase_id: number) =>
  request<{ sala: Sala }>('start_room', { clase_id });
export const apiActiveRooms = () => get<{ rooms: Sala[] }>('active_rooms');
export const apiCountries = () => get<{ countries: Pais[] }>('countries');

export interface User {
  id: number;
  nombre: string;
  email: string;
  rol: string;
  creditos: number;
  verificado?: boolean;
  avatar?: string;
  pendingPaymentSessionId?: number | null;
  rating?: number;
  clases_count?: number;
}

export interface Subject {
  id: number;
  nombre: string;
  icono: string;
  color: string;
  clases_activas?: number;
}

export interface Clase {
  id: number;
  titulo: string;
  precio: number;
  descripcion?: string;
  duracion_minutos?: number;
  materia_id?: number;
  materia?: string;
  profesor_id?: number;
  profesor?: string;
  rating?: number;
  activa?: boolean;
  sala_id?: number;
  sala_activa?: boolean;
}

export interface Sala {
  id: number;
  clase_id: number;
  activa: boolean;
  clase?: string;
  precio?: number;
}

export interface Mensaje {
  id: number;
  usuario_id: number;
  usuario?: string;
  mensaje: string;
  created_at: string;
}

export interface Pago {
  id: number;
  monto: number;
  descripcion: string;
  created_at: string;
}

export interface Sesion {
  id: number;
  duracion?: number;
  ganancia?: number;
  clase?: string;
  created_at: string;
}

export interface Pais {
  id: number;
  nombre: string;
  codigo: string;
  moneda: string;
}

export interface WebRTCSignal {
  signalId: number;
  from_uid: number;
  tipo: 'offer' | 'answer' | 'candidate' | 'bye';
  payload: string;
}
