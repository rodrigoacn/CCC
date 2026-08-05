package com.classexpress.app.ui.sala

import android.Manifest
import android.content.pm.PackageManager
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.automirrored.filled.Send
import androidx.compose.material.icons.filled.Person
import androidx.compose.material.icons.filled.Star
import androidx.compose.material.icons.filled.Videocam
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
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.core.content.ContextCompat
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.classexpress.app.Config
import com.classexpress.app.ui.components.ErrorView
import com.classexpress.app.ui.components.LiveBadge
import com.classexpress.app.ui.components.LoadingView
import com.classexpress.app.ui.components.SectionHeader
import com.classexpress.app.ui.theme.Danger
import com.classexpress.app.ui.theme.Hairline
import com.classexpress.app.ui.theme.Ink
import com.classexpress.app.ui.theme.InkSecondary
import com.classexpress.app.ui.theme.Mint
import com.classexpress.app.ui.theme.PageBackground
import com.classexpress.app.ui.theme.Star
import com.classexpress.app.ui.webrtc.VideoSurface
import java.util.Locale

@Composable
fun SalaScreen(
    claseId: Int,
    salaId: Int,
    onExit: () -> Unit,
) {
    val vm: SalaViewModel = androidx.lifecycle.viewmodel.compose.viewModel()
    val state by vm.state.collectAsStateWithLifecycle()
    val engine by vm.engine.collectAsStateWithLifecycle()
    val context = LocalContext.current

    var showRateDialog by remember { mutableStateOf(false) }
    var timeUpShown by remember { mutableStateOf(false) }

    val permissionsLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.RequestMultiplePermissions(),
    ) { grants ->
        if (grants[Manifest.permission.CAMERA] == true && grants[Manifest.permission.RECORD_AUDIO] == true) {
            vm.startVideo(context.applicationContext)
        } else {
            vm.permissionDenied()
        }
    }

    LaunchedEffect(salaId) {
        if (salaId > 0 && !state.joined) vm.join(salaId)
    }

    // Dialog de tiempo agotado (los minutos gratis terminaron).
    val freeLeft = (Config.FREE_MINUTES * 60L) - state.timerSeconds
    LaunchedEffect(state.joined, state.timerSeconds) {
        if (state.joined && freeLeft <= 0 && !timeUpShown) {
            timeUpShown = true
        }
    }

    fun doLeave() {
        vm.leave { shouldRate ->
            if (shouldRate) showRateDialog = true else onExit()
        }
    }

    Box(Modifier.fillMaxSize().background(PageBackground)) {
        Column(Modifier.fillMaxSize().imePadding()) {
            Header(
                title = state.roomTitle,
                onBack = { doLeave() },
            )

            when {
                state.loading -> LoadingView(Modifier.fillMaxSize())
                !state.joined && state.error != null -> {
                    ErrorView(
                        state.error!!,
                        Modifier.fillMaxWidth().weight(1f),
                        onRetry = { vm.retryJoin(salaId) },
                    )
                    // Botón para salir si no se pudo entrar.
                    TextButton(
                        onClick = onExit,
                        modifier = Modifier.fillMaxWidth(),
                    ) { Text("Salir", color = InkSecondary) }
                }
                !state.joined -> {
                    LoadingView(Modifier.fillMaxSize())
                }
                else -> RoomContent(
                    state = state,
                    freeLeft = freeLeft,
                    timeUp = timeUpShown,
                    localRenderer = engine?.localRenderer,
                    remoteRenderer = engine?.remoteRenderer,
                    onStartVideo = {
                        val cam = ContextCompat.checkSelfPermission(context, Manifest.permission.CAMERA)
                        val mic = ContextCompat.checkSelfPermission(context, Manifest.permission.RECORD_AUDIO)
                        if (cam == PackageManager.PERMISSION_GRANTED && mic == PackageManager.PERMISSION_GRANTED) {
                            vm.startVideo(context.applicationContext)
                        } else {
                            permissionsLauncher.launch(
                                arrayOf(Manifest.permission.CAMERA, Manifest.permission.RECORD_AUDIO)
                            )
                        }
                    },
                    onStopVideo = vm::stopVideo,
                    onInput = vm::setChatInput,
                    onSend = vm::sendMessage,
                    onLeave = { doLeave() },
                    onContinue = { timeUpShown = false },
                )
            }
        }

        if (showRateDialog) {
            RateDialog(
                onDismiss = {
                    showRateDialog = false
                    onExit()
                },
                onSubmit = { rating, comentario ->
                    vm.submitRating(rating, comentario)
                    showRateDialog = false
                    onExit()
                },
            )
        }
    }
}

@Composable
private fun Header(title: String, onBack: () -> Unit) {
    Row(
        Modifier
            .fillMaxWidth()
            .background(Color.White)
            .padding(horizontal = 8.dp, vertical = 8.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        IconButton(onClick = onBack) {
            Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Salir de la sala", tint = Ink)
        }
        Column(Modifier.weight(1f)) {
            Text(
                title.ifBlank { "Sala en vivo" },
                fontSize = 17.sp,
                fontWeight = FontWeight.Bold,
                color = Ink,
                maxLines = 1,
            )
            Text("Sala activa", fontSize = 11.sp, color = InkSecondary)
        }
        Spacer(Modifier.width(8.dp))
        LiveBadge()
        Spacer(Modifier.width(12.dp))
    }
}

@Composable
private fun RoomContent(
    state: SalaUiState,
    freeLeft: Long,
    timeUp: Boolean,
    localRenderer: org.webrtc.SurfaceViewRenderer?,
    remoteRenderer: org.webrtc.SurfaceViewRenderer?,
    onStartVideo: () -> Unit,
    onStopVideo: () -> Unit,
    onInput: (String) -> Unit,
    onSend: () -> Unit,
    onLeave: () -> Unit,
    onContinue: () -> Unit,
) {
    Column(
        Modifier
            .fillMaxSize()
            .padding(horizontal = 16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Spacer(Modifier.height(4.dp))

        TimerCard(
            timerSeconds = state.timerSeconds,
            freeLeft = freeLeft,
            billing = state.billing,
            timeUp = timeUp,
            onContinue = onContinue,
        )

        StudentVideoPanel(
            started = state.videoStarted,
            error = state.videoError,
            localRenderer = localRenderer,
            remoteRenderer = remoteRenderer,
            onStart = onStartVideo,
            onStop = onStopVideo,
        )

        Row(
            Modifier.fillMaxWidth(),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Icon(Icons.Filled.Person, null, tint = InkSecondary, modifier = Modifier.size(16.dp))
            Spacer(Modifier.width(6.dp))
            Text(
                "${state.participantes.size} en la sala",
                fontSize = 13.sp,
                fontWeight = FontWeight.SemiBold,
                color = Ink,
            )
            Spacer(Modifier.weight(1f))
            TextButton(onClick = onLeave) {
                Text("Salir de la clase", color = Danger, fontSize = 13.sp)
            }
        }

        ChatList(messages = state.messages, modifier = Modifier.weight(1f))
        ChatInput(value = state.chatInput, onChange = onInput, onSend = onSend)
        Spacer(Modifier.height(8.dp))
    }
}

@Composable
private fun TimerCard(
    timerSeconds: Long,
    freeLeft: Long,
    billing: BillingStatus,
    timeUp: Boolean,
    onContinue: () -> Unit,
) {
    val mm = (timerSeconds / 60).toString().padStart(2, '0')
    val ss = (timerSeconds % 60).toString().padStart(2, '0')
    val count = if (freeLeft > 0) {
        val c = "%02d:%02d".format(Locale.US, freeLeft / 60, freeLeft % 60)
        "Tiempo gratis: $c"
    } else {
        "Créditos en curso"
    }

    Column(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(16.dp))
            .background(Color.White)
            .border(1.dp, Hairline, RoundedCornerShape(16.dp))
            .padding(16.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Icon(Icons.Filled.Videocam, null, tint = Mint, modifier = Modifier.size(18.dp))
            Spacer(Modifier.width(8.dp))
            Text("En clase", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = Ink)
        }
        Spacer(Modifier.height(8.dp))
        Text("$mm:$ss", fontSize = 34.sp, fontWeight = FontWeight.Bold, color = if (billing == BillingStatus.Free) Mint else Star)
        Text(
            count,
            fontSize = 12.sp,
            color = if (billing == BillingStatus.Free) InkSecondary else Star,
        )
    }

    if (timeUp) {
        AlertDialog(
            onDismissRequest = onContinue,
            title = { Text("Tiempo de clase finalizado") },
            text = {
                Text("Tus minutos gratis terminaron. A partir de ahora se cobrará el costo de la clase desde tus créditos por cada minuto de permanencia.")
            },
            confirmButton = {
                TextButton(onClick = onContinue) { Text("Entendido", color = Mint) }
            },
        )
    }
}

@Composable
private fun StudentVideoPanel(
    started: Boolean,
    error: String?,
    localRenderer: org.webrtc.SurfaceViewRenderer?,
    remoteRenderer: org.webrtc.SurfaceViewRenderer?,
    onStart: () -> Unit,
    onStop: () -> Unit,
) {
    Column(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(16.dp))
            .background(Color.White)
            .border(1.dp, Hairline, RoundedCornerShape(16.dp))
            .padding(12.dp),
        verticalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        if (!started || remoteRenderer == null) {
            Row(
                Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(14.dp))
                    .background(Mint.copy(alpha = 0.10f))
                    .clickable(onClick = onStart)
                    .padding(vertical = 20.dp),
                horizontalArrangement = Arrangement.Center,
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Icon(Icons.Filled.Videocam, null, tint = Mint, modifier = Modifier.size(22.dp))
                Spacer(Modifier.width(8.dp))
                Text("Unirse al video", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = Mint)
            }
            error?.let {
                Text(it, color = Danger, fontSize = 12.sp)
            }
        } else {
            Box(
                Modifier
                    .fillMaxWidth()
                    .aspectRatio(16f / 9f)
                    .clip(RoundedCornerShape(14.dp)),
            ) {
                VideoSurface(remoteRenderer, Modifier.fillMaxSize())
                localRenderer?.let {
                    Box(
                        Modifier
                            .align(Alignment.TopEnd)
                            .padding(8.dp)
                            .size(width = 96.dp, height = 72.dp)
                            .clip(RoundedCornerShape(10.dp)),
                    ) {
                        VideoSurface(it, Modifier.fillMaxSize())
                    }
                }
            }
            TextButton(onClick = onStop) { Text("Salir del video", color = Danger) }
        }
    }
}

@Composable
private fun ChatList(messages: List<com.classexpress.app.model.Message>, modifier: Modifier = Modifier) {
    val listState = rememberLazyListState()
    LazyColumn(
        state = listState,
        modifier = modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(16.dp))
            .background(Color.White)
            .border(1.dp, Hairline, RoundedCornerShape(16.dp)),
    ) {
        item { SectionHeader("Chat", Modifier.padding(horizontal = 16.dp)) }
        if (messages.isEmpty()) {
            item {
                Text(
                    "Aún no hay mensajes. ¡Saluda al profesor!",
                    modifier = Modifier.padding(16.dp),
                    color = InkSecondary,
                    fontSize = 13.sp,
                    textAlign = TextAlign.Center,
                )
            }
        }
        items(messages, key = { it.id }) { msg ->
            ChatBubble(msg)
        }
    }

    LaunchedEffect(messages.size) {
        if (messages.isNotEmpty()) listState.scrollToItem(messages.size)
    }
}

@Composable
private fun ChatBubble(msg: com.classexpress.app.model.Message) {
    Column(Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 6.dp)) {
        Text(
            msg.usuario,
            fontSize = 11.sp,
            fontWeight = FontWeight.Bold,
            color = Mint,
        )
        Text(msg.mensaje, fontSize = 14.sp, color = Ink)
    }
}

@Composable
private fun ChatInput(value: String, onChange: (String) -> Unit, onSend: () -> Unit) {
    Row(
        Modifier.fillMaxWidth(),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        OutlinedTextField(
            value = value,
            onValueChange = onChange,
            placeholder = { Text("Escribe un mensaje…", color = InkSecondary) },
            modifier = Modifier.weight(1f),
            shape = RoundedCornerShape(24.dp),
            keyboardOptions = KeyboardOptions(imeAction = ImeAction.Send),
            keyboardActions = KeyboardActions(onSend = { onSend() }),
            singleLine = true,
        )
        Spacer(Modifier.width(8.dp))
        IconButton(
            onClick = onSend,
            modifier = Modifier
                .size(52.dp)
                .clip(CircleShape)
                .background(Mint),
        ) {
            Icon(
                Icons.AutoMirrored.Filled.Send,
                contentDescription = "Enviar",
                tint = Color.White,
            )
        }
    }
}

@Composable
private fun RateDialog(
    onDismiss: () -> Unit,
    onSubmit: (rating: Int, comentario: String) -> Unit,
) {
    var rating by remember { mutableStateOf(5) }
    var comentario by remember { mutableStateOf("") }

    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("Califica tu clase") },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                Text("¿Cómo estuvo la clase?", color = InkSecondary, fontSize = 14.sp)
                Row(horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                    for (i in 1..5) {
                        IconButton(onClick = { rating = i }) {
                            Icon(
                                Icons.Filled.Star,
                                contentDescription = "$i estrellas",
                                tint = if (i <= rating) Star else Color(0xFFCBD5E1),
                            )
                        }
                    }
                }
                OutlinedTextField(
                    value = comentario,
                    onValueChange = { comentario = it },
                    placeholder = { Text("Comentario (opcional)") },
                    modifier = Modifier.fillMaxWidth(),
                    maxLines = 3,
                )
            }
        },
        confirmButton = {
            TextButton(onClick = { onSubmit(rating, comentario) }) {
                Text("Enviar", color = Mint)
            }
        },
        dismissButton = {
            TextButton(onClick = onDismiss) { Text("Omitir", color = InkSecondary) }
        },
    )
}
