package com.classexpress.app.data

import com.classexpress.app.Container
import com.classexpress.app.model.ActiveRoomsResponse
import com.classexpress.app.model.AdminWithdrawalsResponse
import com.classexpress.app.model.ApiError
import com.classexpress.app.model.CheckoutResponse
import com.classexpress.app.model.ClassActionResponse
import com.classexpress.app.model.ClassDetailResponse
import com.classexpress.app.model.ClassListResponse
import com.classexpress.app.model.ClassItem
import com.classexpress.app.model.CountriesResponse
import com.classexpress.app.model.CreateClassResponse
import com.classexpress.app.model.CreditsResponse
import com.classexpress.app.model.JoinRoomResponse
import com.classexpress.app.model.LanguagesResponse
import com.classexpress.app.model.LoginResponse
import com.classexpress.app.model.MessageResponse
import com.classexpress.app.model.MessagesResponse
import com.classexpress.app.model.OkResponse
import com.classexpress.app.model.PaymentResponse
import com.classexpress.app.model.RateResponse
import com.classexpress.app.model.RegisterResponse
import com.classexpress.app.model.RoomStatusResponse
import com.classexpress.app.model.RoomStudentsResponse
import com.classexpress.app.model.SessionStatusResponse
import com.classexpress.app.model.SignalsResponse
import com.classexpress.app.model.StartRoomResponse
import com.classexpress.app.model.Subject
import com.classexpress.app.model.SwitchRoleResponse
import com.classexpress.app.model.TeacherDashboardResponse
import com.classexpress.app.model.User
import com.classexpress.app.model.UserResponse
import com.classexpress.app.model.WithdrawResponse
import com.classexpress.app.model.WithdrawalHistoryResponse
import com.google.gson.reflect.TypeToken
import retrofit2.HttpException
import java.io.IOException

class ApiException(message: String) : Exception(message)

suspend fun <T> safeApi(call: suspend () -> T): T {
    return try {
        call()
    } catch (e: HttpException) {
        val msg = try {
            e.response()?.errorBody()?.string()?.let { body ->
                Container.gson.fromJson(body, ApiError::class.java)?.error
            }
        } catch (_: Exception) {
            null
        }
        throw ApiException(msg ?: "Error del servidor (${e.code()})")
    } catch (e: IOException) {
        throw ApiException("Error de conexión. Verifica tu internet e inténtalo de nuevo.")
    }
}

private val api get() = Container.api
private val gson get() = Container.gson

internal inline fun <reified T> fromJson(json: com.google.gson.JsonObject): T =
    gson.fromJson(json, object : TypeToken<T>() {}.type)

// ────────────────────────────────────────────────
//  Auth
// ────────────────────────────────────────────────

object AuthRepository {

    suspend fun login(email: String, password: String): LoginResponse = safeApi {
        fromJson(api.post("login", mapOf("email" to email, "password" to password)))
    }

    suspend fun register(
        nombre: String,
        username: String,
        email: String,
        password: String,
        rol: String,
        paisId: Int?,
        idiomas: List<Int>,
    ): RegisterResponse = safeApi {
        fromJson(
            api.post(
                "register",
                mapOf(
                    "nombre" to nombre,
                    "username" to username,
                    "email" to email,
                    "password" to password,
                    "rol" to rol,
                    "pais_id" to (paisId ?: 0),
                    "idiomas" to idiomas,
                )
            )
        )
    }

    suspend fun resendVerification(email: String): MessageResponse = safeApi {
        fromJson(api.post("resend_verification", mapOf("email" to email)))
    }

    suspend fun verifyEmail(token: String): MessageResponse = safeApi {
        fromJson(api.post("verify_email", mapOf("token" to token)))
    }

    suspend fun forgotPassword(email: String): MessageResponse = safeApi {
        fromJson(api.post("forgot_password", mapOf("email" to email)))
    }

    suspend fun countries(): List<com.classexpress.app.model.Country> = safeApi {
        fromJson<CountriesResponse>(api.get("countries")).countries
    }

    suspend fun languages(): List<com.classexpress.app.model.Language> = safeApi {
        fromJson<LanguagesResponse>(api.get("languages")).languages
    }
}

// ────────────────────────────────────────────────
//  Clases / Materias / Créditos
// ────────────────────────────────────────────────

object ClassesRepository {

    suspend fun subjects(): List<Subject> = safeApi {
        val obj = api.get("subjects")
        val arr = obj.getAsJsonArray("subjects")
        gson.fromJson(arr, object : TypeToken<List<Subject>>() {}.type)
    }

    suspend fun classes(
        subjectId: Int? = null,
        search: String? = null,
        activeOnly: Boolean = false,
        sort: String = "relevance",
        page: Int = 1,
        limit: Int = 20,
    ): ClassListResponse = safeApi {
        val params = buildMap {
            if (subjectId != null && subjectId > 0) put("subject_id", subjectId.toString())
            if (!search.isNullOrBlank()) put("search", search.trim())
            if (activeOnly) put("active_only", "true")
            put("sort", sort)
            put("page", page.toString())
            put("limit", limit.toString())
        }
        fromJson(api.get("classes", params))
    }

    suspend fun classDetail(id: Int): ClassDetailResponse = safeApi {
        fromJson(api.get("class_detail", mapOf("id" to id.toString())))
    }

    suspend fun credits(): CreditsResponse = safeApi {
        fromJson(api.get("credits"))
    }

    suspend fun createCheckout(type: String, quantity: Int): CheckoutResponse = safeApi {
        fromJson(api.post("create_checkout", mapOf("type" to type, "quantity" to quantity)))
    }

    suspend fun topupCredits(amount: Int): CheckoutResponse = safeApi {
        fromJson(api.post("topup", mapOf("amount" to amount)))
    }

    suspend fun buyTokens(pack: Int): CheckoutResponse = safeApi {
        fromJson(api.post("buy_tokens", mapOf("amount" to pack)))
    }
}

// ────────────────────────────────────────────────
//  Sala en vivo / Chat
// ────────────────────────────────────────────────

object RoomRepository {

    suspend fun joinRoom(salaId: Int): JoinRoomResponse = safeApi {
        fromJson(api.post("join_room", mapOf("sala_id" to salaId)))
    }

    suspend fun leaveRoom(salaId: Int): OkResponse = safeApi {
        fromJson(api.post("leave_room", mapOf("sala_id" to salaId)))
    }

    suspend fun roomStatus(salaId: Int): RoomStatusResponse = safeApi {
        fromJson(api.get("room_status", mapOf("sala_id" to salaId.toString())))
    }

    suspend fun sendMessage(salaId: Int, mensaje: String): com.classexpress.app.model.Message = safeApi {
        val obj = api.post("send_message", mapOf("sala_id" to salaId, "mensaje" to mensaje))
        gson.fromJson(obj.getAsJsonObject("mensaje"), com.classexpress.app.model.Message::class.java)
    }

    suspend fun messages(salaId: Int, after: Long): List<com.classexpress.app.model.Message> = safeApi {
        val params = mutableMapOf("sala_id" to salaId.toString())
        if (after > 0) params["after"] = after.toString()
        fromJson<MessagesResponse>(api.get("messages", params)).messages
    }

    suspend fun sessionStatus(sesionId: Int): SessionStatusResponse = safeApi {
        fromJson(api.get("session_status", mapOf("sesion_id" to sesionId.toString())))
    }

    suspend fun payment(sesionId: Int): PaymentResponse = safeApi {
        fromJson(api.post("payment", mapOf("sesion_id" to sesionId)))
    }

    suspend fun rateSession(salaId: Int, rating: Int, comentario: String): RateResponse = safeApi {
        fromJson(
            api.post(
                "rate_session",
                mapOf("sala_id" to salaId, "rating" to rating, "comentario" to comentario)
            )
        )
    }

    // ── WebRTC (preparado; requiere la SDK de WebRTC para video real) ──
    suspend fun sendSignal(salaId: Int, tipo: String, payload: String, toUid: Int? = null) = safeApi {
        val body = buildMap<String, Any?> {
            put("sala_id", salaId)
            put("tipo", tipo)
            put("payload", payload)
            if (toUid != null) put("to_uid", toUid)
        }
        fromJson<OkResponse>(api.post("signal", body))
    }

    suspend fun pollSignals(salaId: Int, afterId: Long): SignalsResponse = safeApi {
        val params = mutableMapOf("sala_id" to salaId.toString())
        if (afterId > 0) params["after_id"] = afterId.toString()
        fromJson(api.get("poll_signals", params))
    }
}

// ────────────────────────────────────────────────
//  Perfil
// ────────────────────────────────────────────────

object ProfileRepository {

    suspend fun profile(): User = safeApi {
        fromJson<UserResponse>(api.get("profile")).user
    }

    suspend fun updateLanguages(idiomas: List<Int>): OkResponse = safeApi {
        fromJson(api.post("update_languages", mapOf("idiomas" to idiomas)))
    }

    suspend fun setUiLanguage(lang: String): OkResponse = safeApi {
        fromJson(api.post("set_ui_language", mapOf("lang" to lang)))
    }

    suspend fun updateAvatar(base64: String): OkResponse = safeApi {
        fromJson(api.post("update_avatar", mapOf("avatar" to base64)))
    }

    suspend fun deleteAccount(password: String): OkResponse = safeApi {
        fromJson(api.post("delete_account", mapOf("password" to password)))
    }

    suspend fun switchRole(password: String, targetRole: String): SwitchRoleResponse = safeApi {
        fromJson(
            api.post(
                "switch_role",
                mapOf("password" to password, "target_role" to targetRole),
            )
        )
    }
}

// ────────────────────────────────────────────────
//  Profesor
// ────────────────────────────────────────────────

object TeacherRepository {

    suspend fun dashboard(): TeacherDashboardResponse = safeApi {
        fromJson(api.get("teacher_dashboard"))
    }

    suspend fun createClass(
        titulo: String,
        materiaId: Int,
        precio: Double,
        descripcion: String,
        duracion: Int,
    ): CreateClassResponse = safeApi {
        fromJson(
            api.post(
                "create_class",
                mapOf(
                    "titulo" to titulo,
                    "materia_id" to materiaId,
                    "precio" to precio,
                    "descripcion" to descripcion,
                    "duracion" to duracion,
                )
            )
        )
    }

    suspend fun classAction(claseId: Int, action: String): ClassActionResponse = safeApi {
        fromJson(api.post("class_action", mapOf("clase_id" to claseId, "action" to action)))
    }

    suspend fun startRoom(claseId: Int): StartRoomResponse = safeApi {
        fromJson(api.post("start_room", mapOf("clase_id" to claseId)))
    }

    suspend fun activeRooms(): ActiveRoomsResponse = safeApi {
        fromJson(api.get("active_rooms"))
    }

    suspend fun roomStudents(salaId: Int): RoomStudentsResponse = safeApi {
        fromJson(api.get("room_students", mapOf("salaId" to salaId.toString())))
    }

    suspend fun kickStudent(salaId: Int, estudianteId: Int, comentario: String): OkResponse = safeApi {
        fromJson(
            api.post(
                "kick_student",
                mapOf("salaId" to salaId, "estudianteId" to estudianteId, "comentario" to comentario),
            )
        )
    }
}

// ────────────────────────────────────────────────
//  Retiros / Wallet
// ────────────────────────────────────────────────

object WalletRepository {

    suspend fun withdraw(
        cantidad: Int,
        metodoRetiro: String,
        cuenta: String,
        banco: String,
        tipoCuenta: String,
        paypalEmail: String,
    ): WithdrawResponse = safeApi {
        fromJson(
            api.post(
                "withdraw_tokens",
                mapOf(
                    "cantidad" to cantidad,
                    "metodo_retiro" to metodoRetiro,
                    "cuenta_bancaria" to cuenta,
                    "nombre_banco" to banco,
                    "tipo_cuenta" to tipoCuenta,
                    "paypal_email" to paypalEmail,
                )
            )
        )
    }

    suspend fun history(): WithdrawalHistoryResponse = safeApi {
        fromJson(api.get("withdrawal_history"))
    }
}

// ────────────────────────────────────────────────
//  Admin
// ────────────────────────────────────────────────

object AdminRepository {

    suspend fun withdrawals(estado: String = ""): AdminWithdrawalsResponse = safeApi {
        val params = if (estado.isNotBlank()) mapOf("estado" to estado) else emptyMap()
        fromJson(api.get("admin_withdrawals", params))
    }

    suspend fun process(retiroId: Long, action: String, note: String): OkResponse = safeApi {
        fromJson(
            api.post(
                "admin_process_withdrawal",
                mapOf("retiro_id" to retiroId, "action" to action, "note" to note),
            )
        )
    }
}
