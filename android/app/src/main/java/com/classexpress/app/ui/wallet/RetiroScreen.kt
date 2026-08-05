package com.classexpress.app.ui.wallet

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
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.CreditCard
import androidx.compose.material.icons.filled.Payments
import androidx.compose.material3.AlertDialog
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
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.classexpress.app.model.Withdrawal
import com.classexpress.app.ui.components.Chip
import com.classexpress.app.ui.components.ErrorView
import com.classexpress.app.ui.components.LoadingView
import com.classexpress.app.ui.components.PrimaryButton
import com.classexpress.app.ui.components.SectionHeader
import com.classexpress.app.ui.theme.Danger
import com.classexpress.app.ui.theme.Hairline
import com.classexpress.app.ui.theme.Ink
import com.classexpress.app.ui.theme.InkSecondary
import com.classexpress.app.ui.theme.Mint
import com.classexpress.app.ui.theme.PageBackground
import com.classexpress.app.ui.theme.Star

@Composable
fun RetiroScreen(
    onBack: () -> Unit,
) {
    val vm: RetiroViewModel = androidx.lifecycle.viewmodel.compose.viewModel()
    val state by vm.state.collectAsStateWithLifecycle()

    LaunchedEffect(Unit) { vm.load() }

    Column(Modifier.fillMaxSize().background(PageBackground).imePadding()) {
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
            Text(
                "Retirar tokens",
                fontSize = 17.sp,
                fontWeight = FontWeight.Bold,
                color = Ink,
                modifier = Modifier.padding(start = 4.dp),
            )
        }

        when {
            state.loading -> LoadingView(Modifier.fillMaxSize())
            state.error != null -> ErrorView(state.error!!, Modifier.fillMaxSize(), onRetry = { vm.load() })
            else -> LazyColumn(
                Modifier.fillMaxSize().padding(horizontal = 16.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp),
            ) {
                item {
                    Spacer(Modifier.height(8.dp))
                    Row(
                        Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(16.dp))
                            .background(Color.White)
                            .border(1.dp, Hairline, RoundedCornerShape(16.dp))
                            .padding(16.dp),
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        Icon(Icons.Filled.Payments, null, tint = Mint, modifier = Modifier.size(30.dp))
                        Spacer(Modifier.width(12.dp))
                        Column {
                            Text("Saldo disponible", fontSize = 12.sp, color = InkSecondary)
                            Text(
                                "%.2f".format(state.tokens.coerceAtLeast(state.balance.toDouble())) + " tokens",
                                fontSize = 20.sp,
                                fontWeight = FontWeight.Bold,
                                color = Ink,
                            )
                        }
                    }
                }

                item { SectionHeader("Nuevo retiro") }
                item {
                    Column(
                        Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(16.dp))
                            .background(Color.White)
                            .border(1.dp, Hairline, RoundedCornerShape(16.dp))
                            .padding(16.dp),
                        verticalArrangement = Arrangement.spacedBy(12.dp),
                    ) {
                        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            Chip(text = "Banco", selected = state.metodo == "banco", onClick = { vm.setMetodo("banco") })
                            Chip(text = "PayPal", selected = state.metodo == "paypal", onClick = { vm.setMetodo("paypal") })
                        }
                        OutlinedTextField(
                            value = state.cantidad,
                            onValueChange = vm::setCantidad,
                            label = { Text("Cantidad (mínimo 10)") },
                            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                            modifier = Modifier.fillMaxWidth(),
                            singleLine = true,
                        )
                        if (state.metodo == "banco") {
                            OutlinedTextField(
                                value = state.banco,
                                onValueChange = vm::setBanco,
                                label = { Text("Banco") },
                                modifier = Modifier.fillMaxWidth(),
                                singleLine = true,
                            )
                            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                Chip(text = "Corriente", selected = state.tipoCuenta == "corriente", onClick = { vm.setTipoCuenta("corriente") })
                                Chip(text = "Ahorro", selected = state.tipoCuenta == "ahorro", onClick = { vm.setTipoCuenta("ahorro") })
                            }
                            OutlinedTextField(
                                value = state.cuenta,
                                onValueChange = vm::setCuenta,
                                label = { Text("Número de cuenta") },
                                modifier = Modifier.fillMaxWidth(),
                                singleLine = true,
                            )
                        } else {
                            OutlinedTextField(
                                value = state.paypalEmail,
                                onValueChange = vm::setPaypal,
                                label = { Text("Email de PayPal") },
                                modifier = Modifier.fillMaxWidth(),
                                singleLine = true,
                            )
                        }
                        Text(
                            "Comisión 15% · Cambio 950 CLP por token",
                            fontSize = 11.sp,
                            color = InkSecondary,
                        )
                        state.submitError?.let {
                            Text(it, color = Danger, fontSize = 13.sp)
                        }
                        PrimaryButton(
                            text = "Solicitar retiro",
                            onClick = vm::submit,
                            loading = state.submitting,
                        )
                    }
                }

                item { SectionHeader("Historial de retiros") }
                if (state.history.isEmpty()) {
                    item {
                        Text(
                            "Aún no tienes retiros.",
                            color = InkSecondary,
                            fontSize = 14.sp,
                            modifier = Modifier.padding(vertical = 8.dp),
                        )
                    }
                }
                items(state.history, key = { it.retiroId }) { w ->
                    WithdrawalRow(w)
                }

                item { Spacer(Modifier.height(24.dp)) }
            }
        }
    }

    state.success?.let { res ->
        AlertDialog(
            onDismissRequest = vm::dismissSuccess,
            title = { Text("Retiro solicitado") },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                    Text("Tu solicitud quedó en revisión.")
                    Text("Descontado: ${res.tokensDeducted} tokens")
                    Text("Comisión: ${res.comision} USD")
                    Text("A pagar: ${res.netoPagarUsd} USD · $${res.netoPagarClp} CLP")
                }
            },
            confirmButton = {
                TextButton(onClick = vm::dismissSuccess) { Text("Entendido", color = Mint) }
            },
        )
    }
}

@Composable
private fun WithdrawalRow(w: Withdrawal) {
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
            Text(
                "${w.cantidad} tokens · ${w.nombreBanco?.takeIf { it.isNotBlank() } ?: w.paypalEmail ?: ""}",
                fontSize = 14.sp,
                fontWeight = FontWeight.SemiBold,
                color = Ink,
                maxLines = 1,
            )
            Text(
                "${w.createdAt?.take(10) ?: ""} · Neto $${"%.2f".format(w.netoPagar)}",
                fontSize = 12.sp,
                color = InkSecondary,
            )
        }
        val color = when (w.estado) {
            "pendiente" -> Star
            "completado" -> Mint
            "rechazado" -> Danger
            else -> InkSecondary
        }
        Text(
            w.estadoLabel,
            fontSize = 12.sp,
            fontWeight = FontWeight.Bold,
            color = color,
        )
    }
}
