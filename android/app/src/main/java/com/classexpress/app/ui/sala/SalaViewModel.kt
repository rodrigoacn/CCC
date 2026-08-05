package com.classexpress.app.ui.sala

import android.content.Context
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.classexpress.app.Config
import com.classexpress.app.Container
import com.classexpress.app.data.ApiException
import com.classexpress.app.data.RoomRepository
import com.classexpress.app.model.Message
import com.classexpress.app.model.Participant
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
import java.util.concurrent.atomic.AtomicLong

enum class BillingStatus { Free, Charging }

data class SalaUiState(
    val loading: Boolean = true,
    val error: String? = null,
    val joining: Boolean = false,
    val joined: Boolean = false,
    val leaving: Boolean = false,
    val roomTitle: String = "",
    val salaId: Int = 0,
    val participantes: List<Participant> = emptyList(),
    val messages: List<Message> = emptyList(),
    val timerSeconds: Long = 0L,
    val billing: BillingStatus = BillingStatus.Free,
    val chatInput: String = "",
    val videoStarted: Boolean = false,
    val videoError: String? = null,
)

class SalaViewModel : ViewModel() {

    private val _state = MutableStateFlow(SalaUiState())
    val state: StateFlow<SalaUiState> = _state.asStateFlow()

    private val _engine = MutableStateFlow<WebRtcEngine?>(null)
    val engine: StateFlow<WebRtcEngine?> = _engine.asStateFlow()

    private var pollJob: Job? = null
    private var timerJob: Job? = null
    private val joinedAt = AtomicLong(0L)
    private var lastMsgId = 0L
    private var active = false

    private val FREE_SECONDS = Config.FREE_MINUTES * 60L

    fun join(salaId: Int) {
        if (active) return
        _state.update { it.copy(loading = false, joining = true, error = null) }
        viewModelScope.launch {
            try {
                val res = RoomRepository.joinRoom(salaId)
                active = true
                Container.session.saveLastSala(salaId)
                joinedAt.set(System.currentTimeMillis())
                _state.update {
                    it.copy(
                        loading = false,
                        joining = false,
                        joined = true,
                        salaId = salaId,
                        roomTitle = res.sala.clase ?: "",
                    )
                }
                startTimer()
                startPolling(salaId)
            } catch (e: ApiException) {
                _state.update { it.copy(loading = false, joining = false, error = e.message) }
            }
        }
    }

    private fun startTimer() {
        timerJob?.cancel()
        timerJob = viewModelScope.launch {
            while (isActive) {
                val elapsed = (System.currentTimeMillis() - joinedAt.get()) / 1000
                val billing = if (elapsed < FREE_SECONDS) BillingStatus.Free else BillingStatus.Charging
                _state.update { it.copy(timerSeconds = elapsed, billing = billing) }
                delay(1000)
            }
        }
    }

    private fun startPolling(salaId: Int) {
        pollJob?.cancel()
        pollJob = viewModelScope.launch {
            // Estado de la sala (participantes + mensajes iniciales) cada 5s
            var roomTick = 0
            while (isActive) {
                try {
                    roomTick++
                    if (roomTick % 2 == 0 || lastMsgId == 0L) {
                        val status = RoomRepository.roomStatus(salaId)
                        _state.update {
                            it.copy(participantes = status.participantes)
                        }
                        if (status.messages.isNotEmpty() && lastMsgId == 0L) {
                            val msgs = status.messages
                            lastMsgId = msgs.maxOfOrNull { m -> m.id } ?: 0L
                            _state.update { it.copy(messages = msgs) }
                        }
                    }
                    val nuevos = RoomRepository.messages(salaId, lastMsgId)
                    if (nuevos.isNotEmpty()) {
                        lastMsgId = nuevos.maxOfOrNull { m -> m.id } ?: lastMsgId
                        _state.update {
                            it.copy(messages = it.messages + nuevos.filter { n -> it.messages.none { m -> m.id == n.id } })
                        }
                    }
                } catch (_: Exception) {
                    // Se reintenta en el siguiente tick.
                }
                delay(3000)
            }
        }
    }

    fun setChatInput(value: String) {
        _state.update { it.copy(chatInput = value) }
    }

    fun sendMessage() {
        val text = _state.value.chatInput.trim()
        val salaId = _state.value.salaId
        if (text.isEmpty() || salaId == 0) return
        viewModelScope.launch {
            try {
                val sent = RoomRepository.sendMessage(salaId, text)
                lastMsgId = sent.id
                _state.update {
                    it.copy(
                        messages = it.messages + sent,
                        chatInput = "",
                    )
                }
            } catch (_: Exception) {
            }
        }
    }

    /**
     * Sale de la sala. El callback recibe `true` si la sesión superó los
     * minutos gratis y corresponde mostrar la calificación (rating).
     */
    fun leave(onDone: (Boolean) -> Unit) {
        val salaId = _state.value.salaId
        val elapsed = (System.currentTimeMillis() - joinedAt.get()) / 1000
        val shouldRate = elapsed >= FREE_SECONDS
        stopVideo()
        stopJobs()
        viewModelScope.launch {
            _state.update { it.copy(leaving = true) }
            try {
                if (active && salaId > 0) RoomRepository.leaveRoom(salaId)
            } catch (_: Exception) {
            }
            active = false
            _state.update { it.copy(leaving = false, joined = false) }
            onDone(shouldRate)
        }
    }

    fun submitRating(rating: Int, comentario: String) {
        val salaId = _state.value.salaId
        viewModelScope.launch {
            try {
                if (salaId > 0) RoomRepository.rateSession(salaId, rating, comentario)
            } catch (_: Exception) {
            }
        }
    }

    fun retryJoin(salaId: Int) {
        _state.update { it.copy(loading = false, error = null) }
        join(salaId)
    }

    // ── WebRTC (estudiante) ────────────────────────────────

    fun startVideo(context: Context) {
        if (_engine.value != null) return
        val salaId = _state.value.salaId
        if (salaId == 0) return
        _state.update { it.copy(videoError = null) }
        val e = WebRtcEngine(
            context = context,
            role = WebRtcEngine.Role.GUEST,
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

    private fun stopJobs() {
        timerJob?.cancel()
        pollJob?.cancel()
        timerJob = null
        pollJob = null
    }

    override fun onCleared() {
        stopVideo()
        stopJobs()
        super.onCleared()
    }
}
