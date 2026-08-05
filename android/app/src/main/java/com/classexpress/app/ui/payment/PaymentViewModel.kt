package com.classexpress.app.ui.payment

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.classexpress.app.data.ApiException
import com.classexpress.app.data.RoomRepository
import com.classexpress.app.model.PaymentResponse
import com.classexpress.app.model.SessionInfo
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class PaymentUiState(
    val loading: Boolean = true,
    val error: String? = null,
    val info: SessionInfo? = null,
    val balance: Int = 0,
    val paying: Boolean = false,
    val result: PaymentResponse? = null,
)

class PaymentViewModel : ViewModel() {

    private val _state = MutableStateFlow(PaymentUiState())
    val state: StateFlow<PaymentUiState> = _state.asStateFlow()

    fun load(sesionId: Int) {
        _state.update { it.copy(loading = true, error = null) }
        viewModelScope.launch {
            try {
                val res = RoomRepository.sessionStatus(sesionId)
                _state.update {
                    it.copy(loading = false, info = res.sesion, balance = res.balance)
                }
            } catch (e: ApiException) {
                _state.update { it.copy(loading = false, error = e.message) }
            }
        }
    }

    fun pay(sesionId: Int) {
        if (_state.value.paying) return
        _state.update { it.copy(paying = true, error = null) }
        viewModelScope.launch {
            try {
                val res = RoomRepository.payment(sesionId)
                _state.update { it.copy(paying = false, result = res) }
            } catch (e: ApiException) {
                _state.update { it.copy(paying = false, error = e.message) }
            }
        }
    }
}
