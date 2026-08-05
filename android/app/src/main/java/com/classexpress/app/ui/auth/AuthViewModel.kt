package com.classexpress.app.ui.auth

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.classexpress.app.Container
import com.classexpress.app.data.ApiException
import com.classexpress.app.data.AuthRepository
import com.classexpress.app.data.safeApi
import com.classexpress.app.model.Country
import com.classexpress.app.model.Language
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class AuthUiState(
    val loading: Boolean = false,
    val error: String? = null,
    val success: String? = null,
    val needsVerification: Boolean = false,
    val registerEmail: String = "",
    val countries: List<Country> = emptyList(),
    val languages: List<Language> = emptyList(),
    val loadingOptions: Boolean = true,
    val optionsError: String? = null,
)

class AuthViewModel : ViewModel() {

    private val _state = MutableStateFlow(AuthUiState())
    val state: StateFlow<AuthUiState> = _state.asStateFlow()

    init {
        loadOptions()
    }

    fun clearMessages() {
        _state.update { it.copy(error = null, success = null) }
    }

    fun loadOptions() {
        viewModelScope.launch {
            try {
                val countries = AuthRepository.countries()
                val languages = AuthRepository.languages()
                _state.update { it.copy(countries = countries, languages = languages, loadingOptions = false) }
            } catch (e: ApiException) {
                _state.update { it.copy(optionsError = e.message, loadingOptions = false) }
            }
        }
    }

    fun login(email: String, password: String) {
        _state.update { it.copy(loading = true, error = null, success = null) }
        viewModelScope.launch {
            try {
                val res = AuthRepository.login(email.trim(), password)
                Container.session.saveLogin(res.token, res.user)
                _state.update { it.copy(loading = false, success = null) }
            } catch (e: ApiException) {
                _state.update { it.copy(loading = false, error = e.message) }
            }
        }
    }

    fun register(
        nombre: String,
        username: String,
        email: String,
        password: String,
        confirm: String,
        rol: String,
        paisId: Int?,
        idiomas: List<Int>,
    ) {
        _state.update { it.copy(loading = true, error = null, success = null) }

        // Validaciones locales (espejo de la web)
        if (nombre.isBlank() || email.isBlank() || password.isBlank() || confirm.isBlank() || username.isBlank()) {
            _state.update { it.copy(loading = false, error = "Completa todos los campos obligatorios.") }
            return
        }
        if (!android.util.Patterns.EMAIL_ADDRESS.matcher(email.trim()).matches()) {
            _state.update { it.copy(loading = false, error = "Correo electrónico no válido.") }
            return
        }
        if (username.length < 3 || !username.matches(Regex("^[a-zA-Z0-9_]+$"))) {
            _state.update {
                it.copy(
                    loading = false,
                    error = "El nombre de usuario debe tener al menos 3 caracteres (solo letras, números y _).",
                )
            }
            return
        }
        if (password.length < 6) {
            _state.update { it.copy(loading = false, error = "Mín. 6 caracteres.") }
            return
        }
        if (password != confirm) {
            _state.update { it.copy(loading = false, error = "Las contraseñas no coinciden o son muy cortas (mín. 6 caracteres).") }
            return
        }

        viewModelScope.launch {
            try {
                val res = AuthRepository.register(
                    nombre = nombre.trim(),
                    username = username.trim(),
                    email = email.trim(),
                    password = password,
                    rol = rol,
                    paisId = paisId,
                    idiomas = idiomas,
                )
                _state.update {
                    it.copy(
                        loading = false,
                        needsVerification = res.needsVerification ?: true,
                        success = res.message,
                        registerEmail = res.email.ifBlank { email.trim() },
                    )
                }
            } catch (e: ApiException) {
                _state.update { it.copy(loading = false, error = e.message) }
            }
        }
    }

    fun resendVerification(email: String) {
        _state.update { it.copy(loading = true, error = null, success = null) }
        viewModelScope.launch {
            try {
                val res = AuthRepository.resendVerification(email.trim())
                _state.update { it.copy(loading = false, success = res.message.ifBlank { "Si ese correo está pendiente de verificación, se envió un nuevo enlace. Revisa tu bandeja de entrada y spam." }) }
            } catch (e: ApiException) {
                _state.update { it.copy(loading = false, error = e.message) }
            }
        }
    }

    fun verifyEmail(token: String) {
        _state.update { it.copy(loading = true, error = null, success = null) }
        viewModelScope.launch {
            try {
                val res = AuthRepository.verifyEmail(token.trim())
                _state.update {
                    it.copy(loading = false, needsVerification = false, success = res.message)
                }
            } catch (e: ApiException) {
                _state.update { it.copy(loading = false, error = e.message) }
            }
        }
    }

    fun forgotPassword(email: String) {
        _state.update { it.copy(loading = true, error = null, success = null) }
        viewModelScope.launch {
            try {
                val res = AuthRepository.forgotPassword(email.trim())
                _state.update {
                    it.copy(
                        loading = false,
                        success = res.message.ifBlank { "Si ese correo está registrado y verificado, se ha enviado un enlace para restablecer la contraseña. Revisa tu bandeja de entrada y spam." },
                    )
                }
            } catch (e: ApiException) {
                _state.update { it.copy(loading = false, error = e.message) }
            }
        }
    }
}
