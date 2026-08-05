package com.classexpress.app.model

import com.google.gson.JsonObject
import com.google.gson.annotations.SerializedName

// ────────────────────────────────────────────────
//  Usuario / Auth
// ────────────────────────────────────────────────

data class User(
    @SerializedName("id") val id: Int,
    @SerializedName("nombre") val nombre: String,
    @SerializedName("email") val email: String = "",
    @SerializedName("username") val username: String? = null,
    @SerializedName("rol") val rol: String = "estudiante",
    @SerializedName("creditos") val creditos: Int = 0,
    @SerializedName("verificado") val verificado: Boolean? = null,
    @SerializedName("avatar") val avatar: String? = null,
    @SerializedName("biografia") val biografia: String? = null,
    @SerializedName("pais_id") val paisId: Int? = null,
    @SerializedName("idiomas") val idiomas: List<String>? = null,
    @SerializedName("calificacion") val calificacion: Double? = null,
    @SerializedName("num_resenas") val numResenas: Int? = null,
    @SerializedName("idioma_preferido") val idiomaPreferido: String? = null,
    @SerializedName("ultima_materia") val ultimaMateria: Int? = null,
    @SerializedName("last_role_switch") val lastRoleSwitch: String? = null,
    @SerializedName("pendingPaymentSessionId") val pendingPaymentSessionId: Int? = null,
) {
    val firstName: String get() = nombre.trim().split(Regex("\\s+")).firstOrNull() ?: "Usuario"

    fun isTeacher(): Boolean = rol == "instructor" || rol == "both"

    fun roleLabel(): String =
        if (isTeacher()) "Profesor" else "Estudiante"

    /** El server devuelve `avatar` como URL absoluta. */
    val avatarUrl: String? get() = avatar?.takeIf { it.isNotBlank() }
}

data class LoginResponse(
    @SerializedName("token") val token: String,
    @SerializedName("user") val user: User,
)

data class RegisterResponse(
    @SerializedName("needs_verification") val needsVerification: Boolean? = null,
    @SerializedName("message") val message: String = "",
    @SerializedName("email") val email: String = "",
)

data class MessageResponse(
    @SerializedName("message") val message: String = "",
    @SerializedName("verified") val verified: Boolean? = null,
    @SerializedName("already_verified") val alreadyVerified: Boolean? = null,
)

data class UserResponse(
    @SerializedName("user") val user: User,
)

// ────────────────────────────────────────────────
//  Materias / Clases
// ────────────────────────────────────────────────

data class Subject(
    @SerializedName("id") val id: Int,
    @SerializedName("nombre") val nombre: String,
    @SerializedName("color") val color: String? = null,
    @SerializedName("icono") val icono: String? = null,
    @SerializedName("clases_activas") val clasesActivas: Int? = null,
)

data class ClassItem(
    @SerializedName("id") val id: Int,
    @SerializedName("titulo") val titulo: String,
    @SerializedName("descripcion") val descripcion: String? = null,
    @SerializedName("precio") val precio: Double = 0.0,
    @SerializedName("duracion_minutos") val duracionMinutos: Int? = null,
    @SerializedName("rating") val rating: Double? = null,
    @SerializedName("alumnos_max") val alumnosMax: Int? = null,
    @SerializedName("alumnos_activos") val alumnosActivos: Int? = null,
    @SerializedName("activa") val activa: Boolean? = null,
    @SerializedName("created_at") val createdAt: String? = null,
    @SerializedName("materia_id") val materiaId: Int? = null,
    @SerializedName("materia") val materia: String = "",
    @SerializedName("profesor_id") val profesorId: Int? = null,
    @SerializedName("profesor") val profesor: String = "",
    @SerializedName("sala_activa") val salaActiva: Int? = null,
    @SerializedName("total_visto") val totalVisto: Long? = null,
    @SerializedName("es_amigo") val esAmigo: Int? = null,
) {
    val isLive: Boolean get() = salaActiva == 1
    val isFriend: Boolean get() = esAmigo == 1
    val displayRating: Double get() = rating ?: 4.0
}

data class ClassListResponse(
    @SerializedName("classes") val classes: List<ClassItem> = emptyList(),
    @SerializedName("total") val total: Int = 0,
    @SerializedName("page") val page: Int = 1,
    @SerializedName("pages") val pages: Int = 1,
)

data class ClassDetail(
    @SerializedName("id") val id: Int,
    @SerializedName("titulo") val titulo: String = "",
    @SerializedName("descripcion") val descripcion: String? = null,
    @SerializedName("precio") val precio: Double = 0.0,
    @SerializedName("duracion_minutos") val duracionMinutos: Int? = null,
    @SerializedName("rating") val rating: Double? = null,
    @SerializedName("alumnos_max") val alumnosMax: Int? = null,
    @SerializedName("activa") val activa: Boolean? = null,
    @SerializedName("created_at") val createdAt: String? = null,
    @SerializedName("materia_id") val materiaId: Int? = null,
    @SerializedName("materia") val materia: String = "",
    @SerializedName("profesor_id") val profesorId: Int? = null,
    @SerializedName("profesor") val profesor: String = "",
    @SerializedName("sala_id") val salaId: Int? = null,
    @SerializedName("sala_activa") val salaActiva: Int? = null,
) {
    val isLive: Boolean get() = salaActiva == 1
    val displayRating: Double get() = rating ?: 4.0
}

data class ClassDetailResponse(
    @SerializedName("clase") val clase: ClassDetail,
)

// ────────────────────────────────────────────────
//  Créditos
// ────────────────────────────────────────────────

data class CreditsResponse(
    @SerializedName("balance") val balance: Int = 0,
    @SerializedName("tokens") val tokens: Double = 0.0,
    @SerializedName("history") val history: List<HistoryItem> = emptyList(),
)

data class HistoryItem(
    @SerializedName("id") val id: Long = 0,
    @SerializedName("monto") val monto: Double = 0.0,
    @SerializedName("tipo") val tipo: String = "",
    @SerializedName("descripcion") val descripcion: String = "",
    @SerializedName("created_at") val createdAt: String = "",
)

data class CheckoutResponse(
    @SerializedName("checkout_url") val checkoutUrl: String = "",
    @SerializedName("preference_id") val preferenceId: String = "",
)

// ────────────────────────────────────────────────
//  Sala / Chat
// ────────────────────────────────────────────────

data class JoinRoomResponse(
    @SerializedName("sala") val sala: RoomInfo,
)

data class RoomInfo(
    @SerializedName("id") val id: Int,
    @SerializedName("claseId") val claseId: Int? = null,
    @SerializedName("clase_id") val claseId2: Int? = null,
    @SerializedName("activa") val activa: Boolean? = null,
    @SerializedName("created_at") val createdAt: String? = null,
    @SerializedName("clase") val clase: String? = null,
    @SerializedName("precio") val precio: Double? = null,
    @SerializedName("instructorId") val instructorId: Int? = null,
    @SerializedName("alumnos_max") val alumnosMax: Int? = null,
)

data class RoomStatusResponse(
    @SerializedName("sala") val sala: RoomStatusSala? = null,
    @SerializedName("participantes") val participantes: List<Participant> = emptyList(),
    @SerializedName("messages") val messages: List<Message> = emptyList(),
)

data class RoomStatusSala(
    @SerializedName("id") val id: Int = 0,
    @SerializedName("claseId") val claseId: Int? = null,
    @SerializedName("activa") val activa: Boolean? = null,
    @SerializedName("created_at") val createdAt: String? = null,
    @SerializedName("clase") val clase: String? = null,
    @SerializedName("precio") val precio: Double? = null,
)

data class Participant(
    @SerializedName("id") val id: Int = 0,
    @SerializedName("nombre") val nombre: String = "",
    @SerializedName("rol") val rol: String = "",
)

data class Message(
    @SerializedName("id") val id: Long = 0,
    @SerializedName("usuario_id") val usuarioId: Int = 0,
    @SerializedName("salaId") val salaId: Int = 0,
    @SerializedName("mensaje") val mensaje: String = "",
    @SerializedName("created_at") val createdAt: String? = null,
    @SerializedName("usuario") val usuario: String = "",
)

data class MessagesResponse(
    @SerializedName("messages") val messages: List<Message> = emptyList(),
)

data class SendMessageResponse(
    @SerializedName("mensaje") val mensaje: Message,
)

// ────────────────────────────────────────────────
//  Sesión / Pago / Rating
// ────────────────────────────────────────────────

data class SessionStatusResponse(
    @SerializedName("sesion") val sesion: SessionInfo? = null,
    @SerializedName("balance") val balance: Int = 0,
)

data class SessionInfo(
    @SerializedName("sesionId") val sesionId: Int = 0,
    @SerializedName("claseId") val claseId: Int = 0,
    @SerializedName("pagado") val pagado: Boolean = false,
    @SerializedName("fin") val fin: String? = null,
    @SerializedName("precio") val precio: Double = 0.0,
    @SerializedName("titulo") val titulo: String = "",
    @SerializedName("instructorId") val instructorId: Int = 0,
    @SerializedName("instructor_nombre") val instructorNombre: String = "",
    @SerializedName("instructor_avatar") val instructorAvatar: String? = null,
    @SerializedName("materiaId") val materiaId: Int = 0,
)

data class PaymentResponse(
    @SerializedName("ok") val ok: Boolean = false,
    @SerializedName("creditos_restantes") val creditosRestantes: Int = 0,
    @SerializedName("recibo") val recibo: String = "",
)

data class RateResponse(
    @SerializedName("ok") val ok: Boolean = false,
)

// ────────────────────────────────────────────────
//  Catálogos
// ────────────────────────────────────────────────

data class Country(
    @SerializedName("id") val id: Int,
    @SerializedName("nombre") val nombre: String,
    @SerializedName("codigo") val codigo: String? = null,
    @SerializedName("codigo_moneda") val codigoMoneda: String? = null,
    @SerializedName("simbolo") val simbolo: String? = null,
)

data class CountriesResponse(
    @SerializedName("countries") val countries: List<Country> = emptyList(),
)

data class Language(
    @SerializedName("id") val id: Int,
    @SerializedName("nombre") val nombre: String,
)

data class LanguagesResponse(
    @SerializedName("languages") val languages: List<Language> = emptyList(),
)

// ────────────────────────────────────────────────
//  Señales WebRTC (preparado para videollamada)
// ────────────────────────────────────────────────

data class SignalItem(
    @SerializedName("signalId") val signalId: Long = 0,
    @SerializedName("from_uid") val fromUid: Int = 0,
    @SerializedName("tipo") val tipo: String = "",
    @SerializedName("payload") val payload: String = "",
)

data class SignalsResponse(
    @SerializedName("signals") val signals: List<SignalItem> = emptyList(),
)

// ────────────────────────────────────────────────
//  Errores genéricos
// ────────────────────────────────────────────────

data class ApiError(
    @SerializedName("error") val error: String? = null,
)

/** Respuesta cruda para acciones sin DTO (ok/mensaje). */
data class OkResponse(
    @SerializedName("ok") val ok: Boolean = false,
    @SerializedName("message") val message: String = "",
)

/** Helper para leer JsonObject cuando el DTO no existe. */
fun JsonObject.fieldOrNull(name: String): String? =
    if (has(name) && !get(name).isJsonNull) get(name).asString else null

// ────────────────────────────────────────────────
//  Profesor (dashboard / clases / sala)
// ────────────────────────────────────────────────

data class TeacherDashboardResponse(
    @SerializedName("me") val me: TeacherMe = TeacherMe(),
    @SerializedName("stats") val stats: TeacherStats = TeacherStats(),
    @SerializedName("live") val live: Int = 0,
    @SerializedName("earningsByCurrency") val earningsByCurrency: List<EarningByCurrency> = emptyList(),
    @SerializedName("ganancias") val ganancias: Double = 0.0,
    @SerializedName("clases") val clases: List<TeacherClass> = emptyList(),
    @SerializedName("sesiones") val sesiones: List<TeacherSession> = emptyList(),
)

data class TeacherMe(
    @SerializedName("nombre") val nombre: String = "",
    @SerializedName("rol") val rol: String = "",
    @SerializedName("calificacion") val calificacion: Double = 0.0,
    @SerializedName("num_resenas") val numResenas: Int = 0,
    @SerializedName("avatar") val avatar: String? = null,
    @SerializedName("pais") val pais: String? = null,
    @SerializedName("simbolo") val simbolo: String? = null,
    @SerializedName("codigo_moneda") val codigoMoneda: String? = null,
)

data class TeacherStats(
    @SerializedName("total_clases") val totalClases: Int = 0,
    @SerializedName("clases_activas") val clasesActivas: Int = 0,
    @SerializedName("total_sesiones") val totalSesiones: Int = 0,
    @SerializedName("sesiones_pagadas") val sesionesPagadas: Int = 0,
    @SerializedName("ganancias_usd") val gananciasUsd: Double = 0.0,
)

data class EarningByCurrency(
    @SerializedName("moneda_local") val monedaLocal: String? = null,
    @SerializedName("simbolo_local") val simboloLocal: String? = null,
    @SerializedName("total") val total: Double = 0.0,
    @SerializedName("num_pagos") val numPagos: Int = 0,
)

data class TeacherClass(
    @SerializedName("id") val id: Int = 0,
    @SerializedName("titulo") val titulo: String = "",
    @SerializedName("descripcion") val descripcion: String? = null,
    @SerializedName("precio") val precio: Double = 0.0,
    @SerializedName("materia") val materia: String = "",
    @SerializedName("materia_id") val materiaId: Int? = null,
    @SerializedName("activa") val activa: Boolean? = null,
    @SerializedName("sala_id") val salaId: Int? = null,
    @SerializedName("sala_activa") val salaActiva: Int? = null,
    @SerializedName("duracion_minutos") val duracionMinutos: Int? = null,
    @SerializedName("rating") val rating: Double? = null,
    @SerializedName("created_at") val createdAt: String? = null,
    @SerializedName("num_sesiones") val numSesiones: Int = 0,
    @SerializedName("num_pagados") val numPagados: Int = 0,
)

data class TeacherSession(
    @SerializedName("id") val id: Int = 0,
    @SerializedName("inicio") val inicio: String? = null,
    @SerializedName("fin") val fin: String? = null,
    @SerializedName("duracion_min") val duracionMin: Int? = null,
    @SerializedName("monto_local") val montoLocal: Double? = null,
    @SerializedName("moneda_local") val monedaLocal: String? = null,
    @SerializedName("simbolo_local") val simboloLocal: String? = null,
    @SerializedName("pagado") val pagado: Int? = null,
    @SerializedName("estudiante") val estudiante: String = "",
    @SerializedName("clase") val clase: String = "",
    @SerializedName("materia") val materia: String? = null,
)

data class CreateClassResponse(
    @SerializedName("clase") val clase: TeacherClass? = null,
)

data class StartRoomResponse(
    @SerializedName("sala") val sala: RoomInfo = RoomInfo(0),
)

data class ActiveRoomsResponse(
    @SerializedName("rooms") val rooms: List<RoomInfo> = emptyList(),
)

data class ClassActionResponse(
    @SerializedName("ok") val ok: Boolean = false,
    @SerializedName("activa") val activa: Boolean? = null,
)

data class RoomStudent(
    @SerializedName("sesionId") val sesionId: Int = 0,
    @SerializedName("estudianteId") val estudianteId: Int = 0,
    @SerializedName("espectador") val espectador: Int? = null,
    @SerializedName("pagado") val pagado: Int? = null,
    @SerializedName("inicio") val inicio: String? = null,
    @SerializedName("segundos_acumulados") val segundosAcumulados: Int? = null,
    @SerializedName("ultima_salida") val ultimaSalida: String? = null,
    @SerializedName("nombre") val nombre: String = "",
    @SerializedName("username") val username: String? = null,
    @SerializedName("avatar_url") val avatarUrl: String? = null,
    @SerializedName("rol") val rol: String? = null,
    @SerializedName("pais") val pais: String? = null,
    @SerializedName("idiomas") val idiomas: String? = null,
    @SerializedName("es_gratis") val esGratis: Boolean = false,
)

data class RoomStudentsResponse(
    @SerializedName("students") val students: List<RoomStudent> = emptyList(),
)

// ────────────────────────────────────────────────
//  Retiros / Wallet / Admin
// ────────────────────────────────────────────────

data class Withdrawal(
    @SerializedName("retiroId") val retiroId: Long = 0,
    @SerializedName("cantidad") val cantidad: Int = 0,
    @SerializedName("monto_usd") val montoUsd: Double = 0.0,
    @SerializedName("monto_clp") val montoClp: Int = 0,
    @SerializedName("comision") val comision: Double = 0.0,
    @SerializedName("neto_pagar") val netoPagar: Double = 0.0,
    @SerializedName("nombre_banco") val nombreBanco: String? = null,
    @SerializedName("tipo_cuenta") val tipoCuenta: String? = null,
    @SerializedName("paypal_email") val paypalEmail: String? = null,
    @SerializedName("estado") val estado: String = "",
    @SerializedName("admin_note") val adminNote: String? = null,
    @SerializedName("created_at") val createdAt: String? = null,
    @SerializedName("procesado_at") val procesadoAt: String? = null,
    @SerializedName("usuario_id") val usuarioId: Int? = null,
    @SerializedName("nombre") val nombre: String? = null,
    @SerializedName("email") val email: String? = null,
) {
    val estadoLabel: String
        get() = when (estado) {
            "pendiente" -> "Pendiente"
            "completado" -> "Completado"
            "rechazado" -> "Rechazado"
            else -> estado
        }
}

data class WithdrawResponse(
    @SerializedName("ok") val ok: Boolean = false,
    @SerializedName("message") val message: String = "",
    @SerializedName("tokens_deducted") val tokensDeducted: Int = 0,
    @SerializedName("comision") val comision: Double = 0.0,
    @SerializedName("neto_pagar_usd") val netoPagarUsd: Double = 0.0,
    @SerializedName("neto_pagar_clp") val netoPagarClp: Int = 0,
    @SerializedName("exchange_rate") val exchangeRate: Int = 0,
)

data class WithdrawalHistoryResponse(
    @SerializedName("withdrawals") val withdrawals: List<Withdrawal> = emptyList(),
)

data class AdminWithdrawalsResponse(
    @SerializedName("withdrawals") val withdrawals: List<Withdrawal> = emptyList(),
)

data class SwitchRoleResponse(
    @SerializedName("ok") val ok: Boolean = false,
    @SerializedName("message") val message: String = "",
    @SerializedName("hours") val hours: Int = 0,
)
