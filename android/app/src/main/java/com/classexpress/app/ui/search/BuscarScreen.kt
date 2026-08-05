package com.classexpress.app.ui.search

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Favorite
import androidx.compose.material.icons.filled.RadioButtonChecked
import androidx.compose.material.icons.filled.Search
import androidx.compose.material.icons.filled.TrendingUp
import androidx.compose.material.icons.filled.Close
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.Text
import androidx.compose.material3.TextField
import androidx.compose.material3.TextFieldDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.classexpress.app.Config
import com.classexpress.app.ui.components.ClassCard
import com.classexpress.app.ui.components.EmptyView
import com.classexpress.app.ui.components.ErrorView
import com.classexpress.app.ui.components.LoadingView
import com.classexpress.app.ui.components.parseColorHex
import com.classexpress.app.ui.theme.Hairline
import com.classexpress.app.ui.theme.Ink
import com.classexpress.app.ui.theme.InkSecondary
import com.classexpress.app.ui.theme.Mint
import com.classexpress.app.ui.theme.PageBackground
import com.classexpress.app.ui.theme.Success

private val SORTS = listOf(
    "relevance" to "Relevancia",
    "popular" to "Más populares",
    "rating" to "Mejor valorados",
    "price_asc" to "Más baratos",
    "price_desc" to "Más caros",
    "newest" to "Recientes",
)

@Composable
fun BuscarScreen(onOpenClass: (Int) -> Unit) {
    val vm: BuscarViewModel = androidx.lifecycle.viewmodel.compose.viewModel()
    val state by vm.state.collectAsStateWithLifecycle()

    Column(Modifier.fillMaxSize().background(PageBackground)) {
        Text(
            "Buscar Clases",
            fontSize = 30.sp,
            fontWeight = FontWeight.Bold,
            color = Ink,
            modifier = Modifier.padding(start = 20.dp, top = 18.dp, end = 20.dp),
        )

        // Caja de búsqueda
        Row(
            Modifier
                .fillMaxWidth()
                .padding(horizontal = 20.dp, vertical = 12.dp)
                .clip(RoundedCornerShape(16.dp))
                .background(Color.White)
                .border(1.dp, Hairline, RoundedCornerShape(16.dp))
                .padding(horizontal = 8.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Icon(Icons.Filled.Search, null, tint = InkSecondary, modifier = Modifier.size(20.dp).padding(start = 4.dp))
            TextField(
                value = state.query,
                onValueChange = { vm.setQuery(it) },
                placeholder = { Text("Buscar por materia, tema o profesor...", color = InkSecondary) },
                singleLine = true,
                modifier = Modifier.weight(1f),
                colors = TextFieldDefaults.colors(
                    focusedContainerColor = Color.Transparent,
                    unfocusedContainerColor = Color.Transparent,
                    focusedIndicatorColor = Color.Transparent,
                    unfocusedIndicatorColor = Color.Transparent,
                ),
                keyboardOptions = KeyboardOptions(imeAction = ImeAction.Search),
                keyboardActions = KeyboardActions(onSearch = { vm.search() }),
            )
            if (state.query.isNotEmpty()) {
                IconButton(onClick = { vm.setQuery("") }) {
                    Icon(Icons.Filled.Close, null, tint = InkSecondary)
                }
            }
        }

        // Chips de filtros (En vivo + materias)
        LazyRow(
            contentPadding = PaddingValues(horizontal = 20.dp),
            horizontalArrangement = Arrangement.spacedBy(8.dp),
        ) {
            item {
                LiveChip(active = state.liveOnly, onClick = { vm.toggleLive() })
            }
            items(state.subjects, key = { it.id }) { s ->
                val color = parseColorHex(s.color ?: Config.SUBJECT_COLORS[s.id])
                val selected = state.subjectId == s.id
                Row(
                    Modifier
                        .clip(RoundedCornerShape(20.dp))
                        .background(if (selected) color else color.copy(alpha = 0.13f))
                        .border(1.dp, color, RoundedCornerShape(20.dp))
                        .clickable { vm.toggleSubject(s.id) }
                        .padding(horizontal = 14.dp, vertical = 8.dp),
                ) {
                    Text(
                        s.nombre,
                        color = if (selected) Color.White else color,
                        fontSize = 12.sp,
                        fontWeight = FontWeight.Medium,
                    )
                }
            }
        }

        // Barra de orden
        Column(Modifier.padding(horizontal = 20.dp)) {
            Row(
                Modifier.padding(top = 10.dp),
                horizontalArrangement = Arrangement.spacedBy(8.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Text("Ordenar:", fontSize = 11.sp, fontWeight = FontWeight.SemiBold, color = InkSecondary)
                LazyRow(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    items(SORTS) { (key, label) ->
                        val selected = state.sort == key
                        Text(
                            label,
                            fontSize = 12.sp,
                            color = if (selected) Color.White else InkSecondary,
                            modifier = Modifier
                                .clip(RoundedCornerShape(20.dp))
                                .background(if (selected) Mint else Color.White)
                                .border(1.dp, if (selected) Mint else Hairline, RoundedCornerShape(20.dp))
                                .clickable { vm.setSort(key) }
                                .padding(horizontal = 12.dp, vertical = 6.dp),
                        )
                    }
                }
            }
        }

        if (state.total > 0) {
            Text(
                "${state.total} resultados",
                fontSize = 11.sp,
                color = InkSecondary,
                modifier = Modifier.padding(start = 20.dp, top = 10.dp, bottom = 4.dp),
            )
        }

        when {
            state.loading -> LoadingView(Modifier.fillMaxSize())
            state.error != null -> ErrorView(state.error!!, Modifier.fillMaxSize()) { vm.search() }
            state.classes.isEmpty() -> EmptyView(Icons.Filled.Search, "No se encontraron clases.")
            else -> LazyColumn(
                Modifier.fillMaxSize(),
                contentPadding = PaddingValues(horizontal = 20.dp, vertical = 8.dp),
                verticalArrangement = Arrangement.spacedBy(10.dp),
            ) {
                val friends = state.classes.filter { it.isFriend }
                val others = state.classes.filter { !it.isFriend }

                if (friends.isNotEmpty()) {
                    item(key = "h-friends") {
                        SectionLabel(Icons.Filled.Favorite, "Clases de amigos", tint = Mint)
                    }
                    items(friends, key = { "f-${it.id}" }) { c ->
                        ClassCard(item = c, showMeta = true, onClick = { onOpenClass(c.id) })
                    }
                }
                if (others.isNotEmpty()) {
                    item(key = "h-more") {
                        SectionLabel(Icons.Filled.TrendingUp, "Más clases", tint = InkSecondary)
                    }
                    items(others, key = { "o-${it.id}" }) { c ->
                        ClassCard(item = c, showMeta = true, onClick = { onOpenClass(c.id) })
                    }
                }
            }
        }
    }
}

@Composable
private fun LiveChip(active: Boolean, onClick: () -> Unit) {
    Row(
        Modifier
            .clip(RoundedCornerShape(20.dp))
            .background(if (active) Success else Color.White)
            .border(1.dp, if (active) Success else Hairline, RoundedCornerShape(20.dp))
            .clickable { onClick() }
            .padding(horizontal = 14.dp, vertical = 8.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(
            Icons.Filled.RadioButtonChecked,
            null,
            tint = if (active) Color.White else Success,
            modifier = Modifier.size(13.dp),
        )
        Spacer(Modifier.width(5.dp))
        Text(
            "En vivo",
            fontSize = 12.sp,
            fontWeight = FontWeight.Medium,
            color = if (active) Color.White else Success,
        )
    }
}

@Composable
private fun SectionLabel(icon: androidx.compose.ui.graphics.vector.ImageVector, text: String, tint: Color) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        Icon(icon, null, tint = tint, modifier = Modifier.size(14.dp))
        Spacer(Modifier.width(4.dp))
        Text(text, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = tint)
    }
}
