package com.classexpress.app.ui.dashboard

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.classexpress.app.data.ApiException
import com.classexpress.app.data.TeacherRepository
import com.classexpress.app.model.TeacherClass
import com.classexpress.app.model.TeacherDashboardResponse
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class TeacherDashboardUiState(
    val loading: Boolean = true,
    val error: String? = null,
    val data: TeacherDashboardResponse? = null,
    val actingClassId: Int = 0,
    val startingClassId: Int = 0,
)

class TeacherDashboardViewModel : ViewModel() {

    private val _state = MutableStateFlow(TeacherDashboardUiState())
    val state: StateFlow<TeacherDashboardUiState> = _state.asStateFlow()

    fun load() {
        _state.update { it.copy(loading = true, error = null) }
        viewModelScope.launch {
            try {
                val data = TeacherRepository.dashboard()
                _state.update { it.copy(loading = false, data = data) }
            } catch (e: ApiException) {
                _state.update { it.copy(loading = false, error = e.message) }
            }
        }
    }

    fun toggleClass(clase: TeacherClass) {
        if (_state.value.actingClassId != 0) return
        _state.update { it.copy(actingClassId = clase.id) }
        viewModelScope.launch {
            try {
                TeacherRepository.classAction(clase.id, if (clase.activa == true) "deactivate" else "activate")
                load()
            } catch (e: ApiException) {
                _state.update { it.copy(actingClassId = 0, error = e.message) }
            }
        }
    }

    fun deleteClass(claseId: Int) {
        if (_state.value.actingClassId != 0) return
        _state.update { it.copy(actingClassId = claseId) }
        viewModelScope.launch {
            try {
                TeacherRepository.classAction(claseId, "delete")
                load()
            } catch (e: ApiException) {
                _state.update { it.copy(actingClassId = 0, error = e.message) }
            }
        }
    }

    fun startRoom(claseId: Int, onStarted: (salaId: Int) -> Unit) {
        if (_state.value.startingClassId != 0) return
        _state.update { it.copy(startingClassId = claseId) }
        viewModelScope.launch {
            try {
                val res = TeacherRepository.startRoom(claseId)
                _state.update { it.copy(startingClassId = 0) }
                onStarted(res.sala.id)
            } catch (e: ApiException) {
                _state.update { it.copy(startingClassId = 0, error = e.message) }
            }
        }
    }
}
