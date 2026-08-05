package com.classexpress.app.data

import android.content.Context
import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.intPreferencesKey
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import com.classexpress.app.Container
import com.classexpress.app.model.User
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.launch

sealed interface AuthState {
    data object Loading : AuthState
    data object LoggedOut : AuthState
    data class LoggedIn(val token: String) : AuthState
}

private val Context.dataStore: DataStore<Preferences> by preferencesDataStore(name = "ce_prefs")

class SessionManager(private val context: Context) {

    private val _auth = MutableStateFlow<AuthState>(AuthState.Loading)
    val auth: StateFlow<AuthState> = _auth.asStateFlow()

    private val _user = MutableStateFlow<User?>(null)
    val user: StateFlow<User?> = _user.asStateFlow()

    @Volatile
    var tokenValue: String = ""
        private set

    @Volatile
    var lastSalaId: Int = 0
        private set

    @Volatile
    var ultimaMateria: Int = 0
        private set

    private val _modo = MutableStateFlow("student")
    val modo: StateFlow<String> = _modo.asStateFlow()

    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)

    init {
        scope.launch {
            val prefs = context.dataStore.data.first()
            tokenValue = prefs[KEY_TOKEN] ?: ""
            lastSalaId = prefs[KEY_LAST_SALA] ?: 0
            ultimaMateria = prefs[KEY_ULTIMA_MATERIA] ?: 0
            _modo.value = prefs[KEY_MODO] ?: "student"
            prefs[KEY_USER]?.let { json ->
                try {
                    _user.value = Container.gson.fromJson(json, User::class.java)
                } catch (_: Exception) {
                    _user.value = null
                }
            }
            _auth.value = if (tokenValue.isNotEmpty()) AuthState.LoggedIn(tokenValue) else AuthState.LoggedOut
        }
    }

    suspend fun saveLogin(token: String, user: User) {
        tokenValue = token
        _user.value = user
        _auth.value = AuthState.LoggedIn(token)
        context.dataStore.edit {
            it[KEY_TOKEN] = token
            it[KEY_USER] = Container.gson.toJson(user)
        }
    }

    suspend fun updateUser(user: User) {
        _user.value = user
        context.dataStore.edit { it[KEY_USER] = Container.gson.toJson(user) }
    }

    suspend fun logout() {
        tokenValue = ""
        _user.value = null
        _auth.value = AuthState.LoggedOut
        _modo.value = "student"
        context.dataStore.edit {
            it.remove(KEY_TOKEN)
            it.remove(KEY_USER)
            it.remove(KEY_LAST_SALA)
            it.remove(KEY_MODO)
        }
    }

    suspend fun saveLastSala(id: Int) {
        lastSalaId = id
        context.dataStore.edit { it[KEY_LAST_SALA] = id }
    }

    suspend fun saveUltimaMateria(id: Int) {
        ultimaMateria = id
        context.dataStore.edit { it[KEY_ULTIMA_MATERIA] = id }
    }

    /** Modo activo: "student" o "teacher" (espejo de la cookie ce_app_modo de la web). */
    suspend fun setModo(modo: String) {
        _modo.value = modo
        context.dataStore.edit { it[KEY_MODO] = modo }
    }

    fun isTeacherModo(): Boolean = _modo.value == "teacher"

    private companion object {
        val KEY_TOKEN = stringPreferencesKey("token")
        val KEY_USER = stringPreferencesKey("user")
        val KEY_LAST_SALA = intPreferencesKey("last_sala")
        val KEY_ULTIMA_MATERIA = intPreferencesKey("ultima_materia")
        val KEY_MODO = stringPreferencesKey("modo")
    }
}
