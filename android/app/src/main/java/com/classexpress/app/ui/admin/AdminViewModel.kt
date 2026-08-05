package com.classexpress.app.ui.admin

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.classexpress.app.data.AdminRepository
import com.classexpress.app.data.ApiException
import com.classexpress.app.model.Withdrawal
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class AdminUiState(
    val loading: Boolean = true,
    val error: String? = null,
    val filter: String = "pendiente",
    val withdrawals: List<Withdrawal> = emptyList(),
    val processingId: Long = 0,
    val processError: String? = null,
)

class AdminViewModel : ViewModel() {

    private val _state = MutableStateFlow(AdminUiState())
    val state: StateFlow<AdminUiState> = _state.asStateFlow()

    fun setFilter(f: String) {
        if (_state.value.filter == f) return
        _state.update { it.copy(filter = f) }
        load()
    }

    fun load() {
        val f = _state.value.filter
        _state.update { it.copy(loading = true, error = null) }
        viewModelScope.launch {
            try {
                val res = AdminRepository.withdrawals(f)
                _state.update { it.copy(loading = false, withdrawals = res.withdrawals) }
            } catch (e: ApiException) {
                _state.update { it.copy(loading = false, error = e.message) }
            }
        }
    }

    fun process(id: Long, action: String, note: String? = null) {
        if (_state.value.processingId != 0L) return
        _state.update { it.copy(processingId = id, processError = null) }
        viewModelScope.launch {
            try {
                AdminRepository.process(id, action, note ?: "")
                _state.update { it.copy(processingId = 0) }
                load()
            } catch (e: ApiException) {
                _state.update { it.copy(processingId = 0, processError = e.message) }
            }
        }
    }
}
