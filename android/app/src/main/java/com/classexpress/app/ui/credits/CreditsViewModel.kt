package com.classexpress.app.ui.credits

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.classexpress.app.Container
import com.classexpress.app.data.ApiException
import com.classexpress.app.data.ClassesRepository
import com.classexpress.app.data.ProfileRepository
import com.classexpress.app.model.HistoryItem
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class CreditsUiState(
    val loading: Boolean = true,
    val error: String? = null,
    val balance: Int = 0,
    val tokens: Double = 0.0,
    val history: List<HistoryItem> = emptyList(),
    val buying: Boolean = false,
    val pendingSessionId: Int? = null,
)

class CreditsViewModel : ViewModel() {

    private val _state = MutableStateFlow(CreditsUiState())
    val state: StateFlow<CreditsUiState> = _state.asStateFlow()

    fun load() {
        _state.update { it.copy(loading = true, error = null) }
        viewModelScope.launch {
            try {
                val credits = ClassesRepository.credits()
                val user = ProfileRepository.profile()
                Container.session.updateUser(user)
                _state.update {
                    it.copy(
                        loading = false,
                        balance = credits.balance,
                        tokens = credits.tokens,
                        history = credits.history,
                        pendingSessionId = user.pendingPaymentSessionId,
                    )
                }
            } catch (e: ApiException) {
                _state.update { it.copy(loading = false, error = e.message) }
            }
        }
    }

    /**
     * Crea un checkout para comprar créditos y devuelve la URL de pago.
     */
    fun buyCredits(quantity: Int, onCheckout: (String) -> Unit) {
        if (_state.value.buying) return
        _state.update { it.copy(buying = true) }
        viewModelScope.launch {
            try {
                val res = ClassesRepository.createCheckout("credits", quantity)
                _state.update { it.copy(buying = false) }
                res.checkoutUrl.takeIf { it.isNotBlank() }?.let { onCheckout(it) }
            } catch (e: ApiException) {
                _state.update { it.copy(buying = false, error = e.message) }
            }
        }
    }

    fun clearError() {
        _state.update { it.copy(error = null) }
    }
}
