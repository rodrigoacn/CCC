package com.classexpress.app.ui.sala

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.classexpress.app.Container
import com.classexpress.app.data.RoomRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class MiSalaUiState(
    val loading: Boolean = true,
    val hasSala: Boolean = false,
    val salaId: Int = 0,
    val claseId: Int = 0,
    val titulo: String = "",
    val participantes: Int = 0,
)

class MiSalaViewModel : ViewModel() {

    private val _state = MutableStateFlow(MiSalaUiState())
    val state: StateFlow<MiSalaUiState> = _state.asStateFlow()

    fun load() {
        val last = Container.session.lastSalaId
        if (last <= 0) {
            _state.update { it.copy(loading = false, hasSala = false) }
            return
        }
        _state.update { it.copy(loading = true) }
        viewModelScope.launch {
            try {
                val status = RoomRepository.roomStatus(last)
                val sala = status.sala
                if (sala?.activa == true) {
                    _state.update {
                        it.copy(
                            loading = false,
                            hasSala = true,
                            salaId = last,
                            claseId = sala.claseId ?: 0,
                            titulo = sala.clase ?: "Clase en vivo",
                            participantes = status.participantes.size,
                        )
                    }
                } else {
                    Container.session.saveLastSala(0)
                    _state.update { it.copy(loading = false, hasSala = false) }
                }
            } catch (_: Exception) {
                _state.update { it.copy(loading = false, hasSala = false) }
            }
        }
    }
}
