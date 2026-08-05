package com.classexpress.app.ui.search

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.classexpress.app.data.ApiException
import com.classexpress.app.data.ClassesRepository
import com.classexpress.app.model.ClassItem
import com.classexpress.app.model.Subject
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class BuscarUiState(
    val loading: Boolean = false,
    val error: String? = null,
    val query: String = "",
    val subjectId: Int? = null,
    val liveOnly: Boolean = false,
    val sort: String = "relevance",
    val subjects: List<Subject> = emptyList(),
    val classes: List<ClassItem> = emptyList(),
    val total: Int = 0,
)

class BuscarViewModel : ViewModel() {

    private val _state = MutableStateFlow(BuscarUiState())
    val state: StateFlow<BuscarUiState> = _state.asStateFlow()

    private var debounceJob: Job? = null

    init {
        loadSubjects()
        search()
    }

    fun loadSubjects() {
        viewModelScope.launch {
            try {
                val subjects = ClassesRepository.subjects()
                _state.update { it.copy(subjects = subjects) }
            } catch (_: Exception) {
                // Los filtros son opcionales; no bloqueamos la búsqueda.
            }
        }
    }

    fun setQuery(query: String) {
        _state.update { it.copy(query = query) }
        debounceJob?.cancel()
        debounceJob = viewModelScope.launch {
            delay(300)
            search()
        }
    }

    fun toggleSubject(id: Int) {
        _state.update {
            it.copy(subjectId = if (it.subjectId == id) null else id)
        }
        search()
    }

    fun toggleLive() {
        _state.update { it.copy(liveOnly = !it.liveOnly) }
        search()
    }

    fun setSort(sort: String) {
        _state.update { it.copy(sort = sort) }
        search()
    }

    fun search() {
        val s = _state.value
        viewModelScope.launch {
            _state.update { it.copy(loading = true, error = null) }
            try {
                val res = ClassesRepository.classes(
                    subjectId = s.subjectId,
                    search = s.query,
                    activeOnly = s.liveOnly,
                    sort = s.sort,
                    limit = 50,
                )
                _state.update {
                    it.copy(loading = false, classes = res.classes, total = res.total)
                }
            } catch (e: ApiException) {
                _state.update { it.copy(loading = false, error = e.message) }
            }
        }
    }
}
