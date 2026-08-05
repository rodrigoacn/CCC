package com.classexpress.app.ui.sala

import androidx.compose.foundation.background
import androidx.compose.foundation.border
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
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Person
import androidx.compose.material.icons.filled.Search
import androidx.compose.material.icons.filled.Videocam
import androidx.compose.material.icons.filled.VideocamOff
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.classexpress.app.ui.components.LiveBadge
import com.classexpress.app.ui.components.LoadingView
import com.classexpress.app.ui.components.PrimaryButton
import com.classexpress.app.ui.theme.Hairline
import com.classexpress.app.ui.theme.Ink
import com.classexpress.app.ui.theme.InkSecondary
import com.classexpress.app.ui.theme.Mint
import com.classexpress.app.ui.theme.PageBackground

@Composable
fun MiSalaScreen(
    onOpenRoom: (salaId: Int, claseId: Int) -> Unit,
    onSearch: () -> Unit,
) {
    val vm: MiSalaViewModel = androidx.lifecycle.viewmodel.compose.viewModel()
    val state by vm.state.collectAsStateWithLifecycle()

    LaunchedEffect(Unit) { vm.load() }

    Column(
        Modifier.fillMaxSize().background(PageBackground).padding(20.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp),
    ) {
        Text("Mi Sala", fontSize = 26.sp, fontWeight = FontWeight.Bold, color = Ink)

        when {
            state.loading -> LoadingView(Modifier.fillMaxSize())
            state.hasSala -> ActiveRoomCard(
                titulo = state.titulo,
                participantes = state.participantes,
                onOpen = { onOpenRoom(state.salaId, state.claseId) },
            )
            else -> EmptyRoomCard(onSearch = onSearch)
        }
    }
}

@Composable
private fun ActiveRoomCard(
    titulo: String,
    participantes: Int,
    onOpen: () -> Unit,
) {
    Column(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(18.dp))
            .background(Color.White)
            .border(1.dp, Hairline, RoundedCornerShape(18.dp))
            .padding(18.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text("Sala activa", fontSize = 12.sp, color = InkSecondary)
            Spacer(Modifier.weight(1f))
            LiveBadge()
        }
        Row(verticalAlignment = Alignment.CenterVertically) {
            Icon(Icons.Filled.Videocam, null, tint = Mint, modifier = Modifier.size(20.dp))
            Spacer(Modifier.width(8.dp))
            Text(titulo, fontSize = 17.sp, fontWeight = FontWeight.Bold, color = Ink)
        }
        Row(verticalAlignment = Alignment.CenterVertically) {
            Icon(Icons.Filled.Person, null, tint = InkSecondary, modifier = Modifier.size(15.dp))
            Spacer(Modifier.width(5.dp))
            Text("$participantes en la sala", fontSize = 12.sp, color = InkSecondary)
        }
        PrimaryButton(text = "Reunirse", onClick = onOpen)
    }
}

@Composable
private fun EmptyRoomCard(onSearch: () -> Unit) {
    Column(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(18.dp))
            .background(Color.White)
            .border(1.dp, Hairline, RoundedCornerShape(18.dp))
            .padding(24.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Box(
            Modifier
                .size(72.dp)
                .clip(RoundedCornerShape(24.dp))
                .background(Mint.copy(alpha = 0.12f)),
            contentAlignment = Alignment.Center,
        ) {
            Icon(Icons.Filled.VideocamOff, null, tint = Mint, modifier = Modifier.size(32.dp))
        }
        Text(
            "No estás en ninguna sala",
            fontSize = 17.sp,
            fontWeight = FontWeight.Bold,
            color = Ink,
            textAlign = TextAlign.Center,
        )
        Text(
            "Busca una clase EN VIVO y entra para reunirte aquí.",
            fontSize = 13.sp,
            color = InkSecondary,
            textAlign = TextAlign.Center,
        )
        Spacer(Modifier.height(4.dp))
        PrimaryButton(
            text = "Buscar clases",
            onClick = onSearch,
        )
    }
}
