package com.classexpress.app.ui.teacher

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.classexpress.app.data.ApiException
import com.classexpress.app.data.ClassesRepository
import com.classexpress.app.data.TeacherRepository
import com.classexpress.app.model.Subject
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class CreateClassUiState(
    val loading: Boolean = true,
    val error: String? = null,
    val subjects: List<Subject> = emptyList(),
    val titulo: String = "",
    val descripcion: String = "",
    val materiaId: Int = 0,
    val precio: String = "",
    val duracion: String = "60",
    val submitting: Boolean = false,
    val submitError: String? = null,
)

class CreateClassViewModel : ViewModel() {

    private val _state = MutableStateFlow(CreateClassUiState())
    val state: StateFlow<CreateClassUiState> = _state.asStateFlow()

    fun load() {
        viewModelScope.launch {
            try {
                val subjects = ClassesRepository.subjects()
                _state.update {
                    it.copy(
                        loading = false,
                        subjects = subjects,
                        materiaId = if (it.materiaId == 0) subjects.firstOrNull()?.id ?: 0 else it.materiaId,
                    )
                }
            } catch (e: ApiException) {
                _state.update { it.copy(loading = false, error = e.message) }
            }
        }
    }

    fun setTitulo(v: String) = _state.update { it.copy(titulo = v) }
    fun setDescripcion(v: String) = _state.update { it.copy(descripcion = v) }
    fun setMateria(id: Int) = _state.update { it.copy(materiaId = id) }
    fun setPrecio(v: String) = _state.update { it.copy(precio = v.filter { c -> c.isDigit() || c == '.' }) }
    fun setDuracion(v: String) = _state.update { it.copy(duracion = v.filter { c -> c.isDigit() }) }

    fun submit(onCreated: () -> Unit) {
        if (_state.value.submitting) return
        val s = _state.value
        val precio = s.precio.toDoubleOrNull() ?: 0.0
        if (s.titulo.isBlank() || s.materiaId == 0 || precio <= 0) {
            _state.update { it.copy(submitError = "Completa el título, elige una materia y escribe un precio válido.") }
            return
        }
        _state.update { it.copy(submitting = true, submitError = null) }
        viewModelScope.launch {
            try {
                TeacherRepository.createClass(
                    titulo = s.titulo.trim(),
                    materiaId = s.materiaId,
                    precio = precio,
                    descripcion = s.descripcion.trim(),
                    duracion = s.duracion.toIntOrNull() ?: 60,
                )
                _state.update { it.copy(submitting = false) }
                onCreated()
            } catch (e: ApiException) {
                _state.update { it.copy(submitting = false, submitError = e.message) }
            }
        }
    }
}
