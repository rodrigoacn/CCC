package com.classexpress.app.ui.presala

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.automirrored.filled.ExitToApp
import androidx.compose.material.icons.filled.Book
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.Schedule
import androidx.compose.material.icons.filled.Person
import androidx.compose.material.icons.filled.Star
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.classexpress.app.ui.components.ErrorView
import com.classexpress.app.ui.components.LoadingView
import com.classexpress.app.ui.components.PrimaryButton
import com.classexpress.app.ui.components.formatRating
import com.classexpress.app.ui.theme.Danger
import com.classexpress.app.ui.theme.Hairline
import com.classexpress.app.ui.theme.Ink
import com.classexpress.app.ui.theme.InkSecondary
import com.classexpress.app.ui.theme.Mint
import com.classexpress.app.ui.theme.PageBackground
import com.classexpress.app.ui.theme.Success

@Composable
fun PreSalaScreen(
    claseId: Int,
    onBack: () -> Unit,
    onEnter: (claseId: Int, salaId: Int) -> Unit,
) {
    val vm: PreSalaViewModel = androidx.lifecycle.viewmodel.compose.viewModel()
    val state by vm.state.collectAsStateWithLifecycle()

    LaunchedEffect(claseId) { vm.load(claseId) }

    Column(Modifier.fillMaxSize().background(PageBackground)) {
        Row(
            Modifier.fillMaxWidth().padding(horizontal = 8.dp, vertical = 8.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            IconButton(onClick = onBack) {
                Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Volver", tint = Ink)
            }
        }

        when {
            state.loading -> LoadingView(Modifier.fillMaxSize())
            state.error != null -> ErrorView(state.error!!, Modifier.fillMaxSize()) { vm.load(claseId) }
            state.clase != null -> Content(
                state = state,
                onEnter = { onEnter(state.clase!!.id, state.clase!!.salaId ?: 0) },
                onBack = onBack,
            )
        }
    }
}

@Composable
private fun Content(state: PreSalaUiState, onEnter: () -> Unit, onBack: () -> Unit) {
    val clase = state.clase!!
    val hasCredits = state.creditos >= clase.precio.toInt()
    val isLive = clase.isLive && clase.salaId != null

    Column(
        Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(horizontal = 20.dp),
        verticalArrangement = Arrangement.spacedBy(14.dp),
    ) {
        Text(clase.titulo, fontSize = 28.sp, fontWeight = FontWeight.Bold, color = Ink)

        // Chips meta
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            MetaPill(Icons.Filled.Book, clase.materia)
            MetaPill(Icons.Filled.Person, clase.profesor)
            if ((clase.duracionMinutos ?: 0) > 0) {
                MetaPill(Icons.Filled.Schedule, "${clase.duracionMinutos} min")
            }
            MetaPill(Icons.Filled.Star, formatRating(clase.displayRating))
        }

        clase.descripcion?.takeIf { it.isNotBlank() }?.let { desc ->
            Column(
                Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(16.dp))
                    .background(Color(0xFF1E293B))
                    .padding(16.dp),
            ) {
                Text(desc, color = Color.White, fontSize = 14.sp, lineHeight = 20.sp)
            }
        }

        // Precio
        Column(
            Modifier
                .fillMaxWidth()
                .clip(RoundedCornerShape(16.dp))
                .background(Color.White)
                .border(1.dp, Hairline, RoundedCornerShape(16.dp))
                .padding(18.dp),
            verticalArrangement = Arrangement.spacedBy(6.dp),
        ) {
            Text("Precio de la clase", fontSize = 12.sp, color = InkSecondary)
            Text(
                "${clase.precio.toInt()} créditos",
                fontSize = 26.sp,
                fontWeight = FontWeight.Bold,
                color = Mint,
            )
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text("Tu saldo: ${state.creditos} cr.", fontSize = 13.sp, color = InkSecondary)
                Spacer(Modifier.width(10.dp))
                Row(
                    Modifier
                        .clip(RoundedCornerShape(20.dp))
                        .background(if (hasCredits) Color(0x2216A34A) else Color(0x22DC2626))
                        .padding(horizontal = 10.dp, vertical = 4.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Icon(
                        if (hasCredits) Icons.Filled.CheckCircle else Icons.Filled.CheckCircle,
                        null,
                        tint = if (hasCredits) Success else Danger,
                        modifier = Modifier.size(13.dp),
                    )
                    Spacer(Modifier.width(4.dp))
                    Text(
                        if (hasCredits) "Tienes suficiente" else "Saldo insuficiente",
                        fontSize = 11.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = if (hasCredits) Success else Danger,
                    )
                }
            }
        }

        if (!isLive) {
            Row(
                Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(12.dp))
                    .background(Color(0x22F59E0B))
                    .padding(12.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Text(
                    "El profesor aún no ha iniciado la clase. Vuelve cuando esté EN VIVO.",
                    color = Color(0xFFB45309),
                    fontSize = 13.sp,
                )
            }
        }

        Spacer(Modifier.height(8.dp))

        PrimaryButton(
            text = if (isLive) "Entrar a la clase" else "Clase no disponible",
            onClick = onEnter,
            enabled = isLive && hasCredits,
        )

        Row(
            Modifier
                .fillMaxWidth()
                .clip(RoundedCornerShape(14.dp))
                .background(Color.White)
                .border(1.dp, Hairline, RoundedCornerShape(14.dp))
                .clickable { onBack() }
                .padding(vertical = 14.dp),
            horizontalArrangement = Arrangement.Center,
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Icon(Icons.AutoMirrored.Filled.ExitToApp, null, tint = InkSecondary, modifier = Modifier.size(18.dp))
            Spacer(Modifier.width(8.dp))
            Text("Salir", color = InkSecondary, fontWeight = FontWeight.SemiBold, fontSize = 15.sp)
        }
    }
}

@Composable
private fun MetaPill(icon: androidx.compose.ui.graphics.vector.ImageVector, text: String) {
    Row(
        Modifier
            .clip(RoundedCornerShape(20.dp))
            .background(Color.White)
            .border(1.dp, Hairline, RoundedCornerShape(20.dp))
            .padding(horizontal = 12.dp, vertical = 6.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(icon, null, tint = InkSecondary, modifier = Modifier.size(13.dp))
        Spacer(Modifier.width(4.dp))
        Text(text, fontSize = 12.sp, color = Ink, fontWeight = FontWeight.Medium)
    }
}
