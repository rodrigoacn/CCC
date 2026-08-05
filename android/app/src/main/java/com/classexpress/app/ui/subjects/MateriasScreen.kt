package com.classexpress.app.ui.subjects

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.Bolt
import androidx.compose.material.icons.filled.Book
import androidx.compose.material.icons.filled.Brush
import androidx.compose.material.icons.filled.Calculate
import androidx.compose.material.icons.filled.Computer
import androidx.compose.material.icons.filled.Edit
import androidx.compose.material.icons.filled.Favorite
import androidx.compose.material.icons.filled.HealthAndSafety
import androidx.compose.material.icons.filled.Language
import androidx.compose.material.icons.filled.Map
import androidx.compose.material.icons.filled.Memory
import androidx.compose.material.icons.filled.MusicNote
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.classexpress.app.Config
import com.classexpress.app.Container
import com.classexpress.app.model.Subject
import com.classexpress.app.ui.components.ErrorView
import com.classexpress.app.ui.components.LoadingView
import com.classexpress.app.ui.components.parseColorHex
import com.classexpress.app.ui.theme.Ink
import com.classexpress.app.ui.theme.InkSecondary
import com.classexpress.app.ui.theme.Mint
import kotlinx.coroutines.launch

@Composable
fun MateriasScreen(onOpenSubject: (Int, String) -> Unit) {
    val vm: MateriasViewModel = androidx.lifecycle.viewmodel.compose.viewModel()
    val state by vm.state.collectAsStateWithLifecycle()
    val scope = rememberCoroutineScope()

    LaunchedEffect(Unit) { vm.load() }

    fun open(s: Subject) {
        scope.launch { Container.session.saveUltimaMateria(s.id) }
        onOpenSubject(s.id, s.nombre)
    }

    Column(Modifier.fillMaxSize().background(Mint.copy(alpha = 0.03f))) {
        Column(Modifier.padding(horizontal = 20.dp, vertical = 18.dp)) {
            state.user?.let { u ->
                Text("¡Hola, ${u.firstName}!", fontSize = 13.sp, color = InkSecondary)
            }
            Text("¿Qué estudias hoy?", fontSize = 26.sp, fontWeight = FontWeight.Bold, color = Ink)
        }

        when {
            state.loading -> LoadingView(Modifier.fillMaxSize())
            state.error != null -> ErrorView(state.error!!, Modifier.fillMaxSize()) { vm.load() }
            else -> LazyVerticalGrid(
                columns = GridCells.Fixed(2),
                modifier = Modifier.fillMaxSize(),
                contentPadding = PaddingValues(start = 20.dp, end = 20.dp, bottom = 24.dp),
                horizontalArrangement = Arrangement.spacedBy(12.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp),
            ) {
                val continuar = state.subjects.firstOrNull { it.id == state.continueMateria }
                if (continuar != null) {
                    item(key = "continuar") {
                        SubjectCard(
                            subject = continuar,
                            isContinue = true,
                            onClick = { open(continuar) },
                        )
                    }
                }
                items(state.subjects, key = { it.id }) { s ->
                    SubjectCard(subject = s, isContinue = false, onClick = { open(s) })
                }
            }
        }
    }
}

@Composable
private fun SubjectCard(subject: Subject, isContinue: Boolean, onClick: () -> Unit) {
    val color = parseColorHex(subject.color ?: Config.SUBJECT_COLORS[subject.id])
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .aspectRatio(0.85f)
            .clip(RoundedCornerShape(18.dp))
            .background(color)
            .clickable { onClick() }
            .padding(14.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center,
    ) {
        if (isContinue) {
            Text(
                "CONTINUAR",
                color = Color.White,
                fontSize = 10.sp,
                fontWeight = FontWeight.Bold,
                letterSpacing = 0.5.sp,
                modifier = Modifier
                    .clip(RoundedCornerShape(20.dp))
                    .background(Color(0x40000000))
                    .padding(horizontal = 10.dp, vertical = 4.dp),
            )
            Spacer(Modifier.height(10.dp))
        }
        Box(
            Modifier
                .size(58.dp)
                .clip(RoundedCornerShape(18.dp))
                .background(Color(0x38FFFFFF)),
            contentAlignment = Alignment.Center,
        ) {
            Icon(
                iconFor(subject.icono),
                contentDescription = null,
                tint = Color.White,
                modifier = Modifier.size(30.dp),
            )
        }
        Spacer(Modifier.height(10.dp))
        Text(
            subject.nombre,
            color = Color.White,
            fontSize = 14.sp,
            fontWeight = FontWeight.SemiBold,
            lineHeight = 18.sp,
        )
        (subject.clasesActivas ?: 0).takeIf { it > 0 }?.let { active ->
            Spacer(Modifier.height(8.dp))
            Text(
                "$active en vivo",
                color = Color.White,
                fontSize = 11.sp,
                modifier = Modifier
                    .clip(RoundedCornerShape(20.dp))
                    .background(Color(0x40000000))
                    .padding(horizontal = 10.dp, vertical = 3.dp),
            )
        }
    }
}

private fun iconFor(feather: String?): ImageVector = when (feather) {
    "calculator" -> Icons.Filled.Calculate
    "activity" -> Icons.Filled.HealthAndSafety
    "zap" -> Icons.Filled.Bolt
    "cpu" -> Icons.Filled.Memory
    "book-open", "book" -> Icons.Filled.Book
    "map" -> Icons.Filled.Map
    "feather" -> Icons.Filled.Edit
    "globe" -> Icons.Filled.Language
    "pen-tool" -> Icons.Filled.Brush
    "monitor" -> Icons.Filled.Computer
    "heart" -> Icons.Filled.Favorite
    "music" -> Icons.Filled.MusicNote
    else -> Icons.Filled.Book
}
