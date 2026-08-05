package com.classexpress.app.ui.profile

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.classexpress.app.Container
import com.classexpress.app.data.ApiException
import com.classexpress.app.data.AuthRepository
import com.classexpress.app.data.ProfileRepository
import com.classexpress.app.model.Language
import com.classexpress.app.model.User
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class PerfilUiState(
    val loading: Boolean = true,
    val error: String? = null,
    val user: User? = null,
    val languages: List<Language> = emptyList(),
    val selectedLanguages: Set<Int> = emptySet(),
    val savingLanguages: Boolean = false,
    val deleting: Boolean = false,
    val deleteError: String? = null,
    val modo: String = "student",
    val switching: Boolean = false,
    val switchError: String? = null,
)

class PerfilViewModel : ViewModel() {

    private val _state = MutableStateFlow(PerfilUiState())
    val state: StateFlow<PerfilUiState> = _state.asStateFlow()

    fun load() {
        _state.update { it.copy(loading = true, error = null) }
        viewModelScope.launch {
            try {
                val user = ProfileRepository.profile()
                val languages = AuthRepository.languages()
                Container.session.updateUser(user)
                val selected = user.idiomas.orEmpty()
                    .mapNotNull { name ->
                        languages.firstOrNull { it.nombre.equals(name, ignoreCase = true) }?.id
                    }
                    .toSet()
                _state.update {
                    it.copy(
                        loading = false,
                        user = user,
                        languages = languages,
                        selectedLanguages = selected,
                        modo = Container.session.modo.value,
                    )
                }
            } catch (e: ApiException) {
                _state.update { it.copy(loading = false, error = e.message) }
            }
        }
    }

    fun toggleLanguage(id: Int) {
        val current = _state.value
        val next = if (id in current.selectedLanguages) current.selectedLanguages - id else current.selectedLanguages + id
        _state.update { it.copy(selectedLanguages = next, savingLanguages = true) }
        viewModelScope.launch {
            try {
                ProfileRepository.updateLanguages(next.toList())
            } catch (_: Exception) {
            } finally {
                _state.update { it.copy(savingLanguages = false) }
            }
        }
    }

    fun setAppLanguage(id: Int) {
        viewModelScope.launch {
            try {
                ProfileRepository.setUiLanguage(id.toString())
            } catch (_: Exception) {
            }
        }
    }

    fun changeAvatar(base64: String) {
        viewModelScope.launch {
            try {
                ProfileRepository.updateAvatar(base64)
                load()
            } catch (_: Exception) {
            }
        }
    }

    fun deleteAccount(password: String) {
        if (_state.value.deleting) return
        _state.update { it.copy(deleting = true, deleteError = null) }
        viewModelScope.launch {
            try {
                ProfileRepository.deleteAccount(password)
                _state.update { it.copy(deleting = false) }
                Container.session.logout()
            } catch (e: ApiException) {
                _state.update { it.copy(deleting = false, deleteError = e.message) }
            }
        }
    }

    fun logout() {
        viewModelScope.launch { Container.session.logout() }
    }

    fun switchRole(password: String) {
        if (_state.value.switching) return
        val target = if (_state.value.modo == "teacher") "student" else "teacher"
        _state.update { it.copy(switching = true, switchError = null) }
        viewModelScope.launch {
            try {
                ProfileRepository.switchRole(password, target)
                Container.session.setModo(target)
                _state.update { it.copy(switching = false, modo = target) }
            } catch (e: ApiException) {
                val msg = if (e.message == "locked") {
                    "Solo puedes cambiar de rol una vez cada 24 horas."
                } else {
                    e.message ?: "No se pudo cambiar el rol."
                }
                _state.update { it.copy(switching = false, switchError = msg) }
            }
        }
    }
}
