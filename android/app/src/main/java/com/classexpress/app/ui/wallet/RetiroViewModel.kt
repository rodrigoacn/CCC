package com.classexpress.app.ui.wallet

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.classexpress.app.data.ApiException
import com.classexpress.app.data.ClassesRepository
import com.classexpress.app.data.WalletRepository
import com.classexpress.app.model.Withdrawal
import com.classexpress.app.model.WithdrawResponse
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class RetiroUiState(
    val loading: Boolean = true,
    val error: String? = null,
    val balance: Int = 0,
    val tokens: Double = 0.0,
    val cantidad: String = "",
    val metodo: String = "banco",
    val banco: String = "",
    val tipoCuenta: String = "corriente",
    val cuenta: String = "",
    val paypalEmail: String = "",
    val submitting: Boolean = false,
    val submitError: String? = null,
    val success: WithdrawResponse? = null,
    val history: List<Withdrawal> = emptyList(),
)

class RetiroViewModel : ViewModel() {

    private val _state = MutableStateFlow(RetiroUiState())
    val state: StateFlow<RetiroUiState> = _state.asStateFlow()

    fun load() {
        _state.update { it.copy(loading = true, error = null) }
        viewModelScope.launch {
            try {
                val credits = ClassesRepository.credits()
                val hist = WalletRepository.history()
                _state.update {
                    it.copy(
                        loading = false,
                        balance = credits.balance,
                        tokens = credits.tokens,
                        history = hist.withdrawals,
                    )
                }
            } catch (e: ApiException) {
                _state.update { it.copy(loading = false, error = e.message) }
            }
        }
    }

    fun setCantidad(v: String) = _state.update { it.copy(cantidad = v.filter { c -> c.isDigit() }) }
    fun setMetodo(m: String) = _state.update { it.copy(metodo = m) }
    fun setBanco(v: String) = _state.update { it.copy(banco = v) }
    fun setTipoCuenta(v: String) = _state.update { it.copy(tipoCuenta = v) }
    fun setCuenta(v: String) = _state.update { it.copy(cuenta = v) }
    fun setPaypal(v: String) = _state.update { it.copy(paypalEmail = v) }

    fun submit() {
        if (_state.value.submitting) return
        val s = _state.value
        val cantidad = s.cantidad.toIntOrNull() ?: 0
        if (cantidad < 10) {
            _state.update { it.copy(submitError = "El mínimo para retirar es 10 tokens.") }
            return
        }
        if (cantidad > s.tokens && cantidad > s.balance) {
            _state.update { it.copy(submitError = "No tienes saldo suficiente.") }
            return
        }
        if (s.metodo == "banco" && (s.banco.isBlank() || s.cuenta.isBlank())) {
            _state.update { it.copy(submitError = "Completa el nombre del banco y la cuenta.") }
            return
        }
        if (s.metodo == "paypal" && s.paypalEmail.isBlank()) {
            _state.update { it.copy(submitError = "Ingresa tu email de PayPal.") }
            return
        }
        _state.update { it.copy(submitting = true, submitError = null) }
        viewModelScope.launch {
            try {
                val res = WalletRepository.withdraw(
                    cantidad = cantidad,
                    metodoRetiro = s.metodo,
                    cuenta = s.cuenta,
                    banco = s.banco,
                    tipoCuenta = s.tipoCuenta,
                    paypalEmail = s.paypalEmail,
                )
                _state.update {
                    it.copy(
                        submitting = false,
                        success = res,
                        cantidad = "",
                        cuenta = "",
                        banco = "",
                        paypalEmail = "",
                    )
                }
                load()
            } catch (e: ApiException) {
                _state.update { it.copy(submitting = false, submitError = e.message) }
            }
        }
    }

    fun dismissSuccess() {
        _state.update { it.copy(success = null) }
    }
}
