package com.classexpress.app.ui.payment

import androidx.compose.foundation.background
import androidx.compose.foundation.border
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
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.School
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
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.classexpress.app.ui.components.Avatar
import com.classexpress.app.ui.components.ErrorView
import com.classexpress.app.ui.components.LoadingView
import com.classexpress.app.ui.components.PrimaryButton
import com.classexpress.app.ui.theme.Hairline
import com.classexpress.app.ui.theme.Ink
import com.classexpress.app.ui.theme.InkSecondary
import com.classexpress.app.ui.theme.Mint
import com.classexpress.app.ui.theme.PageBackground
import com.classexpress.app.ui.theme.Success

@Composable
fun PaymentScreen(
    sesionId: Int,
    onDone: () -> Unit,
) {
    val vm: PaymentViewModel = androidx.lifecycle.viewmodel.compose.viewModel()
    val state by vm.state.collectAsStateWithLifecycle()

    LaunchedEffect(sesionId) { vm.load(sesionId) }

    Column(Modifier.fillMaxSize().background(PageBackground)) {
        Row(
            Modifier.fillMaxWidth().padding(horizontal = 8.dp, vertical = 8.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            IconButton(onClick = onDone) {
                Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Volver", tint = Ink)
            }
            Text("Pagar", fontSize = 20.sp, fontWeight = FontWeight.Bold, color = Ink)
        }

        when {
            state.loading -> LoadingView(Modifier.fillMaxSize())
            state.error != null -> ErrorView(state.error!!, Modifier.fillMaxSize()) { vm.load(sesionId) }
            state.result != null -> SuccessView(
                result = state.result!!,
                onDone = onDone,
            )
            else -> PayView(
                state = state,
                onPay = { vm.pay(sesionId) },
            )
        }
    }
}

@Composable
private fun PayView(state: PaymentUiState, onPay: () -> Unit) {
    val info = state.info

    Column(
        Modifier.fillMaxSize().padding(20.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp),
    ) {
        if (info != null) {
            Column(
                Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(18.dp))
                    .background(Color.White)
                    .border(1.dp, Hairline, RoundedCornerShape(18.dp))
                    .padding(18.dp),
                verticalArrangement = Arrangement.spacedBy(14.dp),
            ) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(Icons.Filled.School, null, tint = Mint, modifier = Modifier.size(20.dp))
                    Spacer(Modifier.width(8.dp))
                    Text(
                        info.titulo.ifBlank { "Clase" },
                        fontSize = 17.sp,
                        fontWeight = FontWeight.Bold,
                        color = Ink,
                    )
                }
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Avatar(info.instructorAvatar, info.instructorNombre, size = 34)
                    Spacer(Modifier.width(10.dp))
                    Column {
                        Text("Profesor", fontSize = 11.sp, color = InkSecondary)
                        Text(info.instructorNombre, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = Ink)
                    }
                }
                Row(
                    Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                ) {
                    Text("Costo de la clase", fontSize = 13.sp, color = InkSecondary)
                    Text(
                        "${info.precio.toInt()} créditos",
                        fontSize = 14.sp,
                        fontWeight = FontWeight.Bold,
                        color = Mint,
                    )
                }
            }

            if (!info.pagado) {
                Column(
                    Modifier
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(14.dp))
                        .background(Color(0x2216A34A))
                        .padding(12.dp),
                ) {
                    Text(
                        "Tu saldo: $state.balance créditos",
                        fontSize = 13.sp,
                        color = Success,
                        fontWeight = FontWeight.SemiBold,
                    )
                }
                Spacer(Modifier.height(4.dp))
                PrimaryButton(
                    text = "Pagar con mis créditos",
                    onClick = onPay,
                    loading = state.paying,
                )
            }
        }
    }
}

@Composable
private fun SuccessView(result: com.classexpress.app.model.PaymentResponse, onDone: () -> Unit) {
    Column(
        Modifier.fillMaxSize().padding(32.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center,
    ) {
        Icon(Icons.Filled.CheckCircle, null, tint = Success, modifier = Modifier.size(72.dp))
        Spacer(Modifier.height(16.dp))
        Text(
            "Pago completado",
            fontSize = 22.sp,
            fontWeight = FontWeight.Bold,
            color = Ink,
            textAlign = TextAlign.Center,
        )
        Spacer(Modifier.height(8.dp))
        Text(
            "Créditos restantes: ${result.creditosRestantes}",
            fontSize = 14.sp,
            color = InkSecondary,
            textAlign = TextAlign.Center,
        )
        Spacer(Modifier.height(24.dp))
        PrimaryButton(text = "Volver", onClick = onDone)
    }
}
