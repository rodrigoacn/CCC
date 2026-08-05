package com.classexpress.app.ui.classes

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.classexpress.app.data.ApiException
import com.classexpress.app.data.ClassesRepository
import com.classexpress.app.model.ClassItem
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class ClasesUiState(
    val loading: Boolean = true,
    val error: String? = null,
    val classes: List<ClassItem> = emptyList(),
)

class ClasesViewModel : ViewModel() {

    private val _state = MutableStateFlow(ClasesUiState())
    val state: StateFlow<ClasesUiState> = _state.asStateFlow()

    fun load(materiaId: Int) {
        _state.update { it.copy(loading = true, error = null) }
        viewModelScope.launch {
            try {
                val res = ClassesRepository.classes(subjectId = materiaId, limit = 50)
                _state.update { it.copy(loading = false, classes = res.classes) }
            } catch (e: ApiException) {
                _state.update { it.copy(loading = false, error = e.message) }
            }
        }
    }
}
