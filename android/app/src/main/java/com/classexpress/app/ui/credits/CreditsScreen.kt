package com.classexpress.app.ui.credits

import android.content.Intent
import android.net.Uri
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
import androidx.compose.material.icons.filled.Payments
import androidx.compose.material.icons.filled.Receipt
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.classexpress.app.model.HistoryItem
import com.classexpress.app.ui.components.ErrorView
import com.classexpress.app.ui.components.LoadingView
import com.classexpress.app.ui.components.SectionHeader
import com.classexpress.app.ui.theme.Danger
import com.classexpress.app.ui.theme.Hairline
import com.classexpress.app.ui.theme.Ink
import com.classexpress.app.ui.theme.InkSecondary
import com.classexpress.app.ui.theme.Mint
import com.classexpress.app.ui.theme.PageBackground
import com.classexpress.app.ui.theme.Success

private val PACKS = listOf(10, 25, 50, 100)

@Composable
fun CreditsScreen(
    onOpenPending: (Int) -> Unit,
) {
    val context = LocalContext.current
    val vm: CreditsViewModel = androidx.lifecycle.viewmodel.compose.viewModel()
    val state by vm.state.collectAsStateWithLifecycle()

    LaunchedEffect(Unit) { vm.load() }

    Column(
        Modifier.fillMaxSize().background(PageBackground),
    ) {
        Column(Modifier.fillMaxWidth().padding(20.dp)) {
            Text("Créditos", fontSize = 26.sp, fontWeight = FontWeight.Bold, color = Ink)
        }

        when {
            state.loading -> LoadingView(Modifier.fillMaxSize())
            state.error != null -> ErrorView(state.error!!, Modifier.fillMaxSize(), onRetry = { vm.load() })
            else -> LazyColumn(
                Modifier.fillMaxSize().padding(horizontal = 20.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp),
            ) {
                item {
                    BalanceCard(balance = state.balance, tokens = state.tokens)
                }

                if (state.pendingSessionId != null) {
                    item {
                        PendingPaymentBanner(
                            onClick = { onOpenPending(state.pendingSessionId!!) },
                        )
                    }
                }

                item {
                    SectionHeader("Comprar créditos")
                }
                item {
                    Row(
                        Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(10.dp),
                    ) {
                        PACKS.forEach { pack ->
                            PackCard(
                                pack = pack,
                                buying = state.buying,
                                modifier = Modifier.weight(1f),
                                onClick = {
                                    vm.buyCredits(pack) { url ->
                                        context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(url)))
                                    }
                                },
                            )
                        }
                    }
                }

                item {
                    SectionHeader("Historial")
                }
                if (state.history.isEmpty()) {
                    item {
                        Text(
                            "Sin movimientos todavía.",
                            modifier = Modifier.fillMaxWidth().padding(vertical = 16.dp),
                            color = InkSecondary,
                            textAlign = TextAlign.Center,
                        )
                    }
                } else {
                    items(state.history, key = { it.id }) { item ->
                        HistoryRow(item)
                    }
                }
                item { Spacer(Modifier.height(24.dp)) }
            }
        }
    }
}

@Composable
private fun BalanceCard(balance: Int, tokens: Double) {
    Column(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(18.dp))
            .background(Color.White)
            .border(1.dp, Hairline, RoundedCornerShape(18.dp))
            .padding(20.dp),
        verticalArrangement = Arrangement.spacedBy(4.dp),
    ) {
        Text("Saldo disponible", fontSize = 13.sp, color = InkSecondary)
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(
                "$balance",
                fontSize = 40.sp,
                fontWeight = FontWeight.Bold,
                color = Mint,
            )
            Spacer(Modifier.width(6.dp))
            Text("créditos", fontSize = 15.sp, color = InkSecondary)
        }
        Text(
            if (tokens > 0) "Bonus: ${String.format("%.1f", tokens)} tokens" else "Compra créditos para entrar a clases pagadas",
            fontSize = 12.sp,
            color = InkSecondary,
        )
    }
}

@Composable
private fun PendingPaymentBanner(onClick: () -> Unit) {
    Row(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(14.dp))
            .background(Color(0x22F59E0B))
            .clickable(onClick = onClick)
            .padding(14.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(Icons.Filled.Receipt, null, tint = Color(0xFFB45309), modifier = Modifier.size(22.dp))
        Spacer(Modifier.width(10.dp))
        Column(Modifier.weight(1f)) {
            Text("Pago pendiente", fontSize = 14.sp, fontWeight = FontWeight.Bold, color = Color(0xFFB45309))
            Text("Completa el pago de tu última clase", fontSize = 12.sp, color = Color(0xFFB45309))
        }
    }
}

@Composable
private fun PackCard(
    pack: Int,
    buying: Boolean,
    modifier: Modifier = Modifier,
    onClick: () -> Unit,
) {
    Column(
        modifier = modifier
            .clip(RoundedCornerShape(14.dp))
            .background(Color.White)
            .border(1.dp, Hairline, RoundedCornerShape(14.dp))
            .clickable(enabled = !buying, onClick = onClick)
            .padding(vertical = 14.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(2.dp),
    ) {
        Icon(Icons.Filled.Payments, null, tint = Mint, modifier = Modifier.size(22.dp))
        Text("+$pack", fontSize = 17.sp, fontWeight = FontWeight.Bold, color = Ink)
        Text("créditos", fontSize = 11.sp, color = InkSecondary)
    }
}

@Composable
private fun HistoryRow(item: HistoryItem) {
    Row(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(12.dp))
            .background(Color.White)
            .border(1.dp, Hairline, RoundedCornerShape(12.dp))
            .padding(14.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(item.descripcion.ifBlank { item.tipo }, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = Ink)
            Text(item.createdAt, fontSize = 11.sp, color = InkSecondary)
        }
        val positive = item.monto >= 0
        Text(
            "${if (positive) "+" else "−"}${kotlin.math.abs(item.monto).toInt()}",
            fontSize = 15.sp,
            fontWeight = FontWeight.Bold,
            color = if (positive) Success else Danger,
        )
    }
}
