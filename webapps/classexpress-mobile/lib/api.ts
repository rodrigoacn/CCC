import * as Storage from '@/lib/storage';

const API_BASE = process.env.EXPO_PUBLIC_API_URL ?? 'http://localhost/CCC';
export const API_URL = `${API_BASE}/api_mobile.php`;

async function getToken(): Promise<string | null> {
  return Storage.getItem('ce_token');
}

export async function request<T>(action: string, data?: Record<string, unknown>): Promise<T> {
  const token = await getToken();
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  };
  if (token) headers['Authorization'] = `Bearer ${token}`;
  let url = `${API_URL}?action=${action}`;
  const res = await fetch(url, {
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
  let url = `${API_URL}?action=${action}`;
  if (params) url += '&' + new URLSearchParams(params).toString();
  const res = await fetch(url, { headers });
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
  nombre: string; email: string; password: string; pais_id: number; rol: string; username?: string; idiomas?: number[];
}) => request<{ needs_verification: boolean; message: string; email: string }>('register', data);

export const apiResendVerification = (email: string) =>
  request<{ message: string }>('resend_verification', { email });

export const apiVerifyEmail = (token: string) =>
  request<{ message: string; verified?: boolean; already_verified?: boolean }>('verify_email', { token });

export const apiForgotPassword = (email: string) =>
  request<{ message: string }>('forgot_password', { email });

export const apiResetPassword = (token: string, password: string, confirm: string) =>
  request<{ message: string }>('reset_password', { token, password, confirm });

export const apiProfile = () => get<{ user: User }>('profile');

export const apiSubjects = () => get<{ subjects: Subject[] }>('subjects');

export const apiTeachers = (params?: Record<string, string>) =>
  get<{ teachers: User[] }>('teachers', params);

export const apiClasses = (params?: Record<string, string>) =>
  get<{ classes: Clase[]; total: number; page: number; pages: number }>('classes', params);

export const apiClassDetail = (id: string) =>
  get<{ clase: Clase }>('class_detail', { id });

export const apiCredits = () => get<{ balance: number; tokens: number; history: Pago[] }>('credits');
export const apiTopup = (amount: number) =>
  request<{ checkout_url: string; preference_id: string }>('topup', { amount });
export const apiBuyTokens = (amount: number) =>
  request<{ checkout_url: string; preference_id: string }>('buy_tokens', { amount });
export const apiCreateCheckout = (type: 'credits' | 'tokens', quantity: number) =>
  request<{ checkout_url: string; preference_id: string }>('create_checkout', { type, quantity });
export const apiCheckoutStatus = (external_reference: string) =>
  get<{ status: string; type: string; quantity: number; payment_id: string | null }>('checkout_status', { external_reference });

export const apiWithdrawTokens = (data: { cantidad: number; cuenta_bancaria?: string; nombre_banco?: string; tipo_cuenta?: string; paypal_email?: string; metodo_retiro?: string }) =>
  request<{ ok: boolean; message: string; tokens_deducted: number; comision: number; neto_pagar_usd: number; neto_pagar_clp: number; exchange_rate: number }>('withdraw_tokens', data);

export const apiWithdrawalHistory = () =>
  get<{ ok: boolean; withdrawals: Withdrawal[] }>('withdrawal_history');

export interface Withdrawal {
  retiroId: number;
  cantidad: number;
  monto_usd: number;
  monto_clp: number;
  comision: number;
  neto_pagar: number;
  cuenta_bancaria: string;
  nombre_banco: string;
  tipo_cuenta: string;
  paypal_email: string;
  estado: string;
  admin_note: string | null;
  created_at: string;
  procesado_at: string | null;
}

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
export const apiPayment = (sesion_id: number) =>
  request<{ ok: boolean; creditos_restantes: number; recibo: string }>('payment', { sesion_id });

export const apiSessionStatus = (sesion_id: string) =>
  get<{ sesion: SesionPendiente; balance: number }>('session_status', { sesion_id });

export const apiRateSession = (sala_id: number, rating: number, comentario?: string) =>
  request<{ ok: boolean }>('rate_session', { sala_id, rating, comentario });

export const apiFriends = () =>
  get<{ siguiendo: FriendUser[]; seguidores: FriendUser[] }>('friends');

export const apiFollow = (usuario_id: number) =>
  request<{ ok: boolean; siguiendo: boolean }>('follow', { usuario_id });

export const apiUnfriend = (usuario_id: number) =>
  request<{ ok: boolean }>('unfriend', { usuario_id });

export const apiSendDirectMessage = (destinatario_id: number, mensaje: string) =>
  request<{ ok: boolean; mensaje: DirectMessage }>('send_dm', { destinatario_id, mensaje });

export const apiGetDirectMessages = (con?: number, after?: number) => {
  const params: Record<string, string> = {};
  if (con)   params.con = String(con);
  if (after) params.after = String(after);
  return get<{ mensajes: DirectMessage[] }>('get_dms', params);
};

export const apiUpdateAvatar = (avatarBase64: string) =>
  request<{ ok: boolean; avatar: string }>('update_avatar', { avatar: avatarBase64 });

export const apiTeacherDashboard = () =>
  get<TeacherDashboard>('teacher_dashboard');
export const apiClassAction = (claseId: number, action: 'activate' | 'deactivate' | 'delete') =>
  request<{ ok: boolean; activa?: boolean }>('class_action', { clase_id: claseId, action });
export const apiCreateClass = (data: {
  titulo: string; materia_id: number; precio: number; descripcion: string; duracion: number;
}) => request<{ clase: Clase }>('create_class', data);
export const apiStartRoom = (clase_id: number) =>
  request<{ sala: Sala }>('start_room', { clase_id });
export const apiActiveRooms = () => get<{ rooms: Sala[] }>('active_rooms');
export const apiMyActiveRoom = async () => {
  const { rooms } = await apiActiveRooms();
  return { room: rooms?.[0] ?? null };
};
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
  calificacion?: number;
  num_resenas?: number;
  clases_count?: number;
  biografia?: string;
  pais?: string;
  idiomas?: string[];
  idioma_preferido?: string;
  ultima_materia?: number;
  last_role_switch?: string | null;
  username?: string;
}

export function esInstructor(rol: string | undefined | null): boolean {
  return rol === 'instructor' || rol === 'both';
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
  alumnos_max?: number;
  alumnos_min?: number;
  precio_min?: number;
  precio_max?: number;
  precio_base?: number;
  codigo_moneda?: string;
  num_sesiones?: number;
  num_pagados?: number;
  alumnos_activos?: number;
  total_visto?: number;
  es_amigo?: number;
  created_at?: string;
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

export interface TeacherDashboard {
  me: {
    nombre: string;
    rol: string;
    calificacion?: number;
    num_resenas?: number;
    avatar?: string;
    pais?: string;
    simbolo?: string;
    codigo_moneda?: string;
  };
  stats: {
    total_clases: number;
    clases_activas: number;
    total_sesiones: number;
    sesiones_pagadas: number;
    ganancias_usd: number;
  };
  live: number;
  earningsByCurrency: {
    moneda_local: string;
    simbolo_local: string;
    total: number;
    num_pagos: number;
  }[];
  ganancias: number;
  clases: Clase[];
  sesiones: SesionDashboard[];
}

export interface SesionDashboard {
  id: number;
  inicio: string;
  fin: string | null;
  duracion_min: number | null;
  monto_local: number | null;
  moneda_local: string | null;
  simbolo_local: string | null;
  pagado: number;
  estudiante: string;
  clase: string | null;
  materia: string | null;
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

export interface FriendUser {
  usuarioid: number;
  nombre: string;
  username: string;
  avatar?: string;
  rol?: string;
  seguido_desde?: string;
  sigue_desde?: string;
  creditos?: number;
  calificacion?: number | string;
  num_resenas?: number;
}

export interface DirectMessage {
  id: number;
  remitente_id: number;
  destinatario_id: number;
  mensaje: string;
  leido: number;
  created_at: string;
  remitente_nombre: string;
}

export interface PersonaResult {
  id: number;
  nombre: string;
  username: string;
  avatar?: string;
  rol: string;
  rating?: number;
  reviews?: number;
  pais?: string;
  biografia?: string;
}

export interface UserProfile {
  id: number;
  nombre: string;
  username: string;
  email: string;
  rol: string;
  avatar?: string;
  biografia: string;
  pais: string;
  idiomas: string[];
  calificacion: number;
  num_resenas: number;
  privacidad: string;
  created_at: string;
  resenas: Resena[];
  siguiendo: boolean;
  clases?: ProfileClase[];
}

export interface ProfileClase {
  id: number;
  titulo: string;
  precio_base: number;
  duracion: number;
  activa: number;
  materia: string;
  icono: string;
  color: string;
  alumnos_activos: number;
}

export interface Resena {
  resenaId: number;
  sesionId: number;
  estudianteId: number;
  profesorId: number;
  rating: number;
  comentario: string;
  created_at: string;
  estudiante_nombre: string;
  estudiante_avatar?: string;
}

export interface RoomStudent {
  sesionId: number;
  estudianteId: number;
  espectador: number;
  pagado: number;
  nombre: string;
  username: string;
  avatar?: string;
  avatar_url?: string;
  pais?: string;
  idiomas?: string;
  rol: string;
  es_gratis: boolean;
  segundos_acumulados: number;
}

export const apiSearchPeople = (q: string) =>
  get<{ people: PersonaResult[] }>('search_people', { q });

export const apiUserProfile = (usuario_id: number) =>
  request<{ profile: UserProfile }>('user_profile', { usuario_id });

export const apiRoomStudents = (salaId: string) =>
  get<{ students: RoomStudent[] }>('room_students', { salaId });

export const apiKickStudent = (salaId: number, estudianteId: number, comentario: string) =>
  request<{ ok: boolean }>('kick_student', { salaId, estudianteId, comentario });

export interface Language {
  id: number;
  nombre: string;
}

export const apiLanguages = () =>
  get<{ languages: Language[] }>('languages');

export interface SesionPendiente {
  sesionId: number;
  claseId: number;
  pagado: boolean;
  fin: string | null;
  precio: number;
  titulo: string;
  instructorId: number;
  instructor_nombre: string;
  instructor_avatar?: string;
  materiaId: number;
}

// apiReferrals was removed — it's now part of apiFriends
