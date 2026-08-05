package com.classexpress.app.ui.teacher

import android.content.Context
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.classexpress.app.Container
import com.classexpress.app.data.ApiException
import com.classexpress.app.data.RoomRepository
import com.classexpress.app.data.TeacherRepository
import com.classexpress.app.model.RoomStudent
import com.classexpress.app.model.SignalsResponse
import com.classexpress.app.ui.webrtc.WebRtcEngine
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch

data class TeacherRoomUiState(
    val loading: Boolean = true,
    val error: String? = null,
    val students: List<RoomStudent> = emptyList(),
    val videoStarted: Boolean = false,
    val videoError: String? = null,
    val kicking: Boolean = false,
)

class TeacherRoomViewModel : ViewModel() {

    private val _state = MutableStateFlow(TeacherRoomUiState())
    val state: StateFlow<TeacherRoomUiState> = _state.asStateFlow()

    private val _engine = MutableStateFlow<WebRtcEngine?>(null)
    val engine: StateFlow<WebRtcEngine?> = _engine.asStateFlow()

    private var pollJob: Job? = null
    private var salaId = 0

    fun load(salaId: Int) {
        this.salaId = salaId
        _state.update { it.copy(loading = true, error = null) }
        viewModelScope.launch {
            try {
                RoomRepository.joinRoom(salaId)
                Container.session.saveLastSala(salaId)
                _state.update { it.copy(loading = false) }
                startPolling(salaId)
            } catch (e: ApiException) {
                _state.update { it.copy(loading = false, error = e.message) }
            }
        }
    }

    private fun startPolling(salaId: Int) {
        pollJob?.cancel()
        pollJob = viewModelScope.launch {
            while (isActive) {
                try {
                    val res = TeacherRepository.roomStudents(salaId)
                    _state.update { it.copy(students = res.students) }
                } catch (_: Exception) {
                }
                delay(4000)
            }
        }
    }

    fun startVideo(context: Context) {
        if (_engine.value != null) return
        _state.update { it.copy(videoError = null) }
        val e = WebRtcEngine(
            context = context,
            role = WebRtcEngine.Role.HOST,
            scope = viewModelScope,
            channel = object : WebRtcEngine.SignalChannel {
                override suspend fun send(tipo: String, payload: String) {
                    RoomRepository.sendSignal(salaId, tipo, payload)
                }

                override suspend fun poll(afterId: Long): SignalsResponse =
                    RoomRepository.pollSignals(salaId, afterId)
            },
        )
        try {
            e.start()
            e.startSignaling()
            _engine.value = e
            _state.update { it.copy(videoStarted = true) }
        } catch (ex: Exception) {
            e.dispose()
            _state.update { it.copy(videoError = "No se pudo iniciar el video: ${ex.message}") }
        }
    }

    fun stopVideo() {
        _engine.value?.dispose()
        _engine.value = null
        _state.update { it.copy(videoStarted = false) }
    }

    fun permissionDenied() {
        _state.update { it.copy(videoError = "Se necesitan permisos de cámara y micrófono para el video en vivo.") }
    }

    fun kick(estudianteId: Int, comentario: String) {
        if (_state.value.kicking) return
        _state.update { it.copy(kicking = true) }
        viewModelScope.launch {
            try {
                TeacherRepository.kickStudent(salaId, estudianteId, comentario)
            } catch (e: ApiException) {
                _state.update { it.copy(kicking = false, error = e.message) }
            }
            _state.update { it.copy(kicking = false) }
        }
    }

    fun leave(onDone: () -> Unit) {
        stopVideo()
        pollJob?.cancel()
        viewModelScope.launch {
            try {
                if (salaId > 0) RoomRepository.leaveRoom(salaId)
            } catch (_: Exception) {
            }
            onDone()
        }
    }

    override fun onCleared() {
        stopVideo()
        pollJob?.cancel()
        super.onCleared()
    }
}
