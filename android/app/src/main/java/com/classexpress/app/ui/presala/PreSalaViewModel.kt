package com.classexpress.app.ui.presala

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.classexpress.app.Container
import com.classexpress.app.data.ApiException
import com.classexpress.app.data.ClassesRepository
import com.classexpress.app.data.ProfileRepository
import com.classexpress.app.model.ClassDetail
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class PreSalaUiState(
    val loading: Boolean = true,
    val error: String? = null,
    val clase: ClassDetail? = null,
    val creditos: Int = 0,
)

class PreSalaViewModel : ViewModel() {

    private val _state = MutableStateFlow(PreSalaUiState())
    val state: StateFlow<PreSalaUiState> = _state.asStateFlow()

    fun load(claseId: Int) {
        _state.update { it.copy(loading = true, error = null) }
        viewModelScope.launch {
            try {
                val res = ClassesRepository.classDetail(claseId)
                val user = ProfileRepository.profile()
                Container.session.updateUser(user)
                _state.update {
                    it.copy(loading = false, clase = res.clase, creditos = user.creditos)
                }
            } catch (e: ApiException) {
                _state.update { it.copy(loading = false, error = e.message) }
            }
        }
    }
}
