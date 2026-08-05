package com.classexpress.app.ui.admin

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
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.AdminPanelSettings
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.OutlinedTextField
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
import com.classexpress.app.model.Withdrawal
import com.classexpress.app.ui.components.Chip
import com.classexpress.app.ui.components.ErrorView
import com.classexpress.app.ui.components.LoadingView
import com.classexpress.app.ui.components.PrimaryButton
import com.classexpress.app.ui.theme.Danger
import com.classexpress.app.ui.theme.Hairline
import com.classexpress.app.ui.theme.Ink
import com.classexpress.app.ui.theme.InkSecondary
import com.classexpress.app.ui.theme.Mint
import com.classexpress.app.ui.theme.PageBackground
import com.classexpress.app.ui.theme.Star

@Composable
fun AdminScreen(
    onBack: () -> Unit,
) {
    val vm: AdminViewModel = androidx.lifecycle.viewmodel.compose.viewModel()
    val state by vm.state.collectAsStateWithLifecycle()

    LaunchedEffect(Unit) { vm.load() }

    var rejectNote by remember { mutableStateOf("") }
    var rejectTarget by remember { mutableStateOf<Withdrawal?>(null) }

    Column(Modifier.fillMaxSize().background(PageBackground)) {
        Row(
            Modifier
                .fillMaxWidth()
                .background(Color.White)
                .padding(horizontal = 8.dp, vertical = 8.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            IconButton(onClick = onBack) {
                Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Volver", tint = Ink)
            }
            Icon(Icons.Filled.AdminPanelSettings, null, tint = Mint, modifier = Modifier.size(20.dp))
            Text(
                "Administración",
                fontSize = 17.sp,
                fontWeight = FontWeight.Bold,
                color = Ink,
                modifier = Modifier.padding(start = 6.dp),
            )
        }

        Row(
            Modifier.fillMaxWidth().padding(horizontal = 16.dp),
            horizontalArrangement = Arrangement.spacedBy(8.dp),
        ) {
            Chip(text = "Pendientes", selected = state.filter == "pendiente", onClick = { vm.setFilter("pendiente") })
            Chip(text = "Completados", selected = state.filter == "completado", onClick = { vm.setFilter("completado") })
            Chip(text = "Rechazados", selected = state.filter == "rechazado", onClick = { vm.setFilter("rechazado") })
        }
        Spacer(Modifier.height(8.dp))

        when {
            state.loading -> LoadingView(Modifier.fillMaxSize())
            state.error != null -> ErrorView(state.error!!, Modifier.fillMaxSize(), onRetry = { vm.load() })
            state.withdrawals.isEmpty() -> Box(
                Modifier.fillMaxSize(),
                contentAlignment = Alignment.Center,
            ) {
                Text("Sin solicitudes en esta categoría.", color = InkSecondary, fontSize = 14.sp)
            }
            else -> LazyColumn(
                Modifier.fillMaxSize().padding(horizontal = 16.dp),
                verticalArrangement = Arrangement.spacedBy(10.dp),
            ) {
                items(state.withdrawals, key = { it.retiroId }) { w ->
                    AdminWithdrawalCard(
                        w = w,
                        processing = state.processingId == w.retiroId,
                        onApprove = { vm.process(w.retiroId, "approve") },
                        onReject = { rejectTarget = w },
                    )
                }
                item { Spacer(Modifier.height(24.dp)) }
            }
        }
    }

    rejectTarget?.let { w ->
        AlertDialog(
            onDismissRequest = { rejectTarget = null },
            title = { Text("Rechazar retiro") },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Text("Retiro #${w.retiroId} de ${w.nombre ?: w.email ?: ""} por ${w.cantidad} tokens.")
                    OutlinedTextField(
                        value = rejectNote,
                        onValueChange = { rejectNote = it },
                        label = { Text("Motivo (opcional)") },
                        modifier = Modifier.fillMaxWidth(),
                    )
                }
            },
            confirmButton = {
                TextButton(
                    onClick = {
                        vm.process(w.retiroId, "reject", rejectNote.ifBlank { null })
                        rejectTarget = null
                        rejectNote = ""
                    },
                ) { Text("Rechazar", color = Danger) }
            },
            dismissButton = {
                TextButton(onClick = { rejectTarget = null }) { Text("Cancelar", color = InkSecondary) }
            },
        )
    }
}

@Composable
private fun AdminWithdrawalCard(
    w: Withdrawal,
    processing: Boolean,
    onApprove: () -> Unit,
    onReject: () -> Unit,
) {
    Column(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(14.dp))
            .background(Color.White)
            .border(1.dp, Hairline, RoundedCornerShape(14.dp))
            .padding(14.dp),
        verticalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Column(Modifier.weight(1f)) {
                Text(
                    w.nombre ?: "Usuario #${w.usuarioId}",
                    fontSize = 15.sp,
                    fontWeight = FontWeight.Bold,
                    color = Ink,
                )
                Text(w.email ?: "", fontSize = 12.sp, color = InkSecondary)
            }
            Text("$${"%.2f".format(w.netoPagar)}", fontSize = 16.sp, fontWeight = FontWeight.Bold, color = Mint)
        }
        Text(
            "${w.cantidad} tokens · monto $${w.montoClp} CLP · comisión $${
                "%.2f".format(w.comision)
            } USD · ${w.createdAt?.take(10) ?: ""}",
            fontSize = 12.sp,
            color = InkSecondary,
        )
        Text(
            "Método: ${w.nombreBanco?.takeIf { it.isNotBlank() } ?: "PayPal"} · ${w.paypalEmail ?: w.tipoCuenta ?: ""}",
            fontSize = 12.sp,
            color = InkSecondary,
        )
        w.adminNote?.let {
            Text("Motivo: $it", fontSize = 12.sp, color = Star)
        }
        if (w.estado == "pendiente") {
            Row(
                Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(10.dp),
            ) {
                PrimaryButton(
                    text = "Aprobar",
                    onClick = onApprove,
                    loading = processing,
                    modifier = Modifier.weight(1f),
                )
                Button(
                    onClick = onReject,
                    enabled = !processing,
                    modifier = Modifier.weight(1f).height(52.dp),
                    colors = ButtonDefaults.buttonColors(
                        containerColor = Color.White,
                        contentColor = Danger,
                        disabledContainerColor = Color(0xFFF4F4F5),
                        disabledContentColor = Danger.copy(alpha = 0.5f),
                    ),
                    shape = RoundedCornerShape(14.dp),
                    border = androidx.compose.foundation.BorderStroke(1.dp, Danger),
                ) {
                    Text("Rechazar", fontSize = 16.sp, fontWeight = FontWeight.Bold)
                }
            }
        }
    }
}
