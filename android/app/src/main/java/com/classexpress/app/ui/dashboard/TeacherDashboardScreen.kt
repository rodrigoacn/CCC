package com.classexpress.app.ui.dashboard

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.Delete
import androidx.compose.material.icons.filled.Payments
import androidx.compose.material.icons.filled.PlayArrow
import androidx.compose.material.icons.filled.Star
import androidx.compose.material.icons.filled.Videocam
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Switch
import androidx.compose.material3.SwitchDefaults
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.classexpress.app.model.TeacherClass
import com.classexpress.app.model.TeacherDashboardResponse
import com.classexpress.app.ui.components.Avatar
import com.classexpress.app.ui.components.ErrorView
import com.classexpress.app.ui.components.LiveBadge
import com.classexpress.app.ui.components.LoadingView
import com.classexpress.app.ui.components.PrimaryButton
import com.classexpress.app.ui.components.RatingStars
import com.classexpress.app.ui.components.SectionHeader
import com.classexpress.app.ui.theme.Danger
import com.classexpress.app.ui.theme.Hairline
import com.classexpress.app.ui.theme.Ink
import com.classexpress.app.ui.theme.InkSecondary
import com.classexpress.app.ui.theme.Mint
import com.classexpress.app.ui.theme.PageBackground
import com.classexpress.app.ui.theme.Star
import java.util.Locale

@Composable
fun TeacherDashboardScreen(
    onCreateClass: () -> Unit,
    onWithdraw: () -> Unit,
    onOpenRoom: (claseId: Int, salaId: Int) -> Unit,
    onExit: () -> Unit,
) {
    val vm: TeacherDashboardViewModel = androidx.lifecycle.viewmodel.compose.viewModel()
    val state by vm.state.collectAsStateWithLifecycle()

    var deletingId by remember { mutableStateOf<Int?>(null) }

    LaunchedEffect(Unit) { vm.load() }

    Column(Modifier.fillMaxSize().background(PageBackground)) {
        when {
            state.loading -> LoadingView(Modifier.fillMaxSize())
            state.error != null -> ErrorView(state.error!!, Modifier.fillMaxSize(), onRetry = { vm.load() })
            state.data != null -> {
                val data = state.data!!
                val liveClase = data.clases.firstOrNull { it.salaActiva == 1 }
                LazyColumn(
                    Modifier.fillMaxSize().padding(horizontal = 16.dp),
                    verticalArrangement = Arrangement.spacedBy(12.dp),
                ) {
                    item {
                        Spacer(Modifier.height(12.dp))
                        DashboardHeader(data = data)
                    }

                    item {
                        StatsGrid(data = data)
                    }

                    if (data.earningsByCurrency.isNotEmpty()) {
                        item {
                            Row(
                                Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.spacedBy(8.dp),
                            ) {
                                data.earningsByCurrency.forEach { e ->
                                    Text(
                                        "${e.simboloLocal ?: ""}${"%.0f".format(e.total)} ${e.monedaLocal ?: ""}".trim(),
                                        modifier = Modifier
                                            .clip(RoundedCornerShape(20.dp))
                                            .background(Mint.copy(alpha = 0.12f))
                                            .padding(horizontal = 12.dp, vertical = 6.dp),
                                        color = Mint,
                                        fontSize = 12.sp,
                                        fontWeight = FontWeight.Bold,
                                    )
                                }
                            }
                        }
                    }

                    item {
                        Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                            PrimaryButton(text = "Crear clase", onClick = onCreateClass)
                            Row(
                                Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.spacedBy(10.dp),
                            ) {
                                SecondaryAction(
                                    text = "Retirar tokens",
                                    onClick = onWithdraw,
                                    modifier = Modifier.weight(1f),
                                )
                                if (liveClase != null) {
                                    SecondaryAction(
                                        text = "Ir a sala",
                                        onClick = { onOpenRoom(liveClase.id, liveClase.salaId ?: 0) },
                                        modifier = Modifier.weight(1f),
                                        highlight = true,
                                    )
                                }
                            }
                        }
                    }

                    item { SectionHeader("Mis clases") }
                    if (data.clases.isEmpty()) {
                        item {
                            Text(
                                "Aún no tienes clases. Crea una para empezar a enseñar.",
                                color = InkSecondary,
                                fontSize = 14.sp,
                                modifier = Modifier.padding(vertical = 8.dp),
                            )
                        }
                    }
                    items(data.clases, key = { it.id }) { clase ->
                        TeacherClassCard(
                            clase = clase,
                            acting = state.actingClassId == clase.id,
                            starting = state.startingClassId == clase.id,
                            onToggle = { vm.toggleClass(clase) },
                            onDelete = { deletingId = clase.id },
                            onStart = { vm.startRoom(clase.id) { salaId -> onOpenRoom(clase.id, salaId) } },
                        )
                    }

                    item { SectionHeader("Sesiones recientes") }
                    if (data.sesiones.isEmpty()) {
                        item {
                            Text(
                                "Aún no hay sesiones.",
                                color = InkSecondary,
                                fontSize = 14.sp,
                                modifier = Modifier.padding(vertical = 8.dp),
                            )
                        }
                    }
                    items(data.sesiones, key = { it.id }) { s ->
                        SessionRow(
                            estudiante = s.estudiante,
                            clase = s.clase,
                            fecha = s.inicio ?: "",
                            pagado = s.pagado == 1,
                            monto = if (s.montoLocal != null) "${s.simboloLocal ?: ""}${"%.0f".format(s.montoLocal)}" else "",
                        )
                    }

                    item { Spacer(Modifier.height(24.dp)) }
                }
            }
        }
    }

    deletingId?.let { id ->
        AlertDialog(
            onDismissRequest = { deletingId = null },
            title = { Text("Eliminar clase") },
            text = { Text("¿Seguro que quieres eliminar esta clase? Esta acción no se puede deshacer.") },
            confirmButton = {
                TextButton(onClick = {
                    vm.deleteClass(id)
                    deletingId = null
                }) { Text("Eliminar", color = Danger) }
            },
            dismissButton = {
                TextButton(onClick = { deletingId = null }) { Text("Cancelar", color = InkSecondary) }
            },
        )
    }
}

@Composable
private fun DashboardHeader(data: TeacherDashboardResponse) {
    Column(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(18.dp))
            .background(Color.White)
            .border(1.dp, Hairline, RoundedCornerShape(18.dp))
            .padding(18.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(6.dp),
    ) {
        Avatar(data.me.avatar, data.me.nombre, size = 72)
        Text(data.me.nombre, fontSize = 19.sp, fontWeight = FontWeight.Bold, color = Ink)
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
            RatingStars(data.me.calificacion)
            Text(
                "%.1f".format(Locale.US, data.me.calificacion) + " · ${data.me.numResenas} reseñas",
                fontSize = 12.sp,
                color = InkSecondary,
            )
        }
        Row(
            Modifier
                .clip(RoundedCornerShape(20.dp))
                .background(Mint.copy(alpha = 0.12f))
                .padding(horizontal = 14.dp, vertical = 6.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Icon(Icons.Filled.Star, null, tint = Star, modifier = Modifier.size(16.dp))
            Spacer(Modifier.width(6.dp))
            Text(
                "Ganancias: $${"%.2f".format(data.ganancias)}",
                fontSize = 13.sp,
                fontWeight = FontWeight.Bold,
                color = Mint,
            )
        }
    }
}

@Composable
private fun StatsGrid(data: TeacherDashboardResponse) {
    val stats = data.stats
    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
        StatCard("Clases", stats.totalClases.toString(), Modifier.weight(1f))
        StatCard("Activas", stats.clasesActivas.toString(), Modifier.weight(1f))
    }
    Spacer(Modifier.height(10.dp))
    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
        StatCard("Sesiones pagadas", stats.sesionesPagadas.toString(), Modifier.weight(1f))
        StatCard("En vivo", data.live.toString(), Modifier.weight(1f))
    }
}

@Composable
private fun StatCard(label: String, value: String, modifier: Modifier = Modifier) {
    Column(
        modifier
            .clip(RoundedCornerShape(16.dp))
            .background(Color.White)
            .border(1.dp, Hairline, RoundedCornerShape(16.dp))
            .padding(14.dp),
        verticalArrangement = Arrangement.spacedBy(4.dp),
    ) {
        Text(value, fontSize = 22.sp, fontWeight = FontWeight.Bold, color = Ink)
        Text(label, fontSize = 12.sp, color = InkSecondary)
    }
}

@Composable
private fun SecondaryAction(
    text: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    highlight: Boolean = false,
) {
    Row(
        modifier
            .clip(RoundedCornerShape(14.dp))
            .background(if (highlight) Mint.copy(alpha = 0.12f) else Color.White)
            .border(1.dp, if (highlight) Mint else Hairline, RoundedCornerShape(14.dp))
            .clickable(onClick = onClick)
            .padding(vertical = 14.dp),
        horizontalArrangement = Arrangement.Center,
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(
            if (highlight) Icons.Filled.Videocam else Icons.Filled.Payments,
            null,
            tint = if (highlight) Mint else InkSecondary,
            modifier = Modifier.size(18.dp),
        )
        Spacer(Modifier.width(6.dp))
        Text(
            text,
            fontSize = 13.sp,
            fontWeight = FontWeight.SemiBold,
            color = if (highlight) Mint else Ink,
        )
    }
}

@Composable
private fun TeacherClassCard(
    clase: TeacherClass,
    acting: Boolean,
    starting: Boolean,
    onToggle: () -> Unit,
    onDelete: () -> Unit,
    onStart: () -> Unit,
) {
    val isLive = clase.salaActiva == 1
    Column(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(16.dp))
            .background(Color.White)
            .border(1.dp, Hairline, RoundedCornerShape(16.dp))
            .padding(14.dp),
        verticalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Column(Modifier.weight(1f)) {
                Text(clase.titulo, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = Ink, maxLines = 1)
                Text(
                    "${clase.materia} · $${"%.2f".format(clase.precio)}",
                    fontSize = 12.sp,
                    color = InkSecondary,
                )
            }
            if (isLive) LiveBadge()
        }

        Text(
            "${clase.numSesiones} sesiones · ${clase.numPagados} pagadas",
            fontSize = 12.sp,
            color = InkSecondary,
        )

        Row(verticalAlignment = Alignment.CenterVertically) {
            Row(
                Modifier.weight(1f).clip(RoundedCornerShape(10.dp)).clickable(onClick = onStart).padding(vertical = 8.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Icon(Icons.Filled.PlayArrow, null, tint = Mint, modifier = Modifier.size(18.dp))
                Spacer(Modifier.width(4.dp))
                Text(
                    if (starting) "Iniciando…" else "Iniciar sala",
                    fontSize = 13.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = Mint,
                )
            }
            Icon(
                Icons.Filled.Delete,
                contentDescription = "Eliminar",
                tint = Danger,
                modifier = Modifier.size(20.dp).clip(RoundedCornerShape(8.dp)).clickable(onClick = onDelete),
            )
            Spacer(Modifier.width(12.dp))
            Column(horizontalAlignment = Alignment.CenterHorizontally) {
                Text("Activa", fontSize = 10.sp, color = InkSecondary)
                Switch(
                    checked = clase.activa == true,
                    onCheckedChange = { onToggle() },
                    enabled = !acting,
                    colors = SwitchDefaults.colors(checkedTrackColor = Mint),
                )
            }
        }
    }
}

@Composable
private fun SessionRow(estudiante: String, clase: String, fecha: String, pagado: Boolean, monto: String) {
    Row(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(14.dp))
            .background(Color.White)
            .border(1.dp, Hairline, RoundedCornerShape(14.dp))
            .padding(14.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Column(Modifier.weight(1f)) {
            Text(estudiante, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = Ink, maxLines = 1)
            Text("$clase · ${fecha.take(10)}", fontSize = 12.sp, color = InkSecondary)
        }
        Column(horizontalAlignment = Alignment.End) {
            if (monto.isNotBlank()) {
                Text(monto, fontSize = 13.sp, fontWeight = FontWeight.Bold, color = if (pagado) Mint else InkSecondary)
            }
            Text(
                if (pagado) "Pagada" else "Pendiente",
                fontSize = 11.sp,
                color = if (pagado) Mint else Star,
            )
        }
    }
}
