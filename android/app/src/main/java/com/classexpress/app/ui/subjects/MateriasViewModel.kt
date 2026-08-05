package com.classexpress.app.ui.subjects

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.classexpress.app.Container
import com.classexpress.app.data.ApiException
import com.classexpress.app.data.ClassesRepository
import com.classexpress.app.data.ProfileRepository
import com.classexpress.app.model.Subject
import com.classexpress.app.model.User
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class MateriasUiState(
    val loading: Boolean = true,
    val error: String? = null,
    val subjects: List<Subject> = emptyList(),
    val user: User? = null,
    val continueMateria: Int = 0,
)

class MateriasViewModel : ViewModel() {

    private val _state = MutableStateFlow(MateriasUiState())
    val state: StateFlow<MateriasUiState> = _state.asStateFlow()

    fun load() {
        _state.update { it.copy(loading = true, error = null) }
        viewModelScope.launch {
            try {
                val subjects = ClassesRepository.subjects()
                val user = ProfileRepository.profile()
                Container.session.updateUser(user)
                val lastLocal = Container.session.ultimaMateria
                val cont = if (lastLocal in 1..11) lastLocal else (user.ultimaMateria ?: 0)
                _state.update {
                    it.copy(loading = false, subjects = subjects, user = user, continueMateria = cont)
                }
            } catch (e: ApiException) {
                _state.update { it.copy(loading = false, error = e.message) }
            }
        }
    }
}
