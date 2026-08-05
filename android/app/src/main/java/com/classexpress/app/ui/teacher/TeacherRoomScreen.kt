package com.classexpress.app.ui.teacher

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
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.Block
import androidx.compose.material.icons.filled.Person
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
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.core.content.ContextCompat
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.classexpress.app.model.RoomStudent
import com.classexpress.app.ui.components.Avatar
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
import com.classexpress.app.ui.webrtc.VideoSurface

@Composable
fun TeacherRoomScreen(
    claseId: Int,
    salaId: Int,
    titulo: String,
    onExit: () -> Unit,
) {
    val vm: TeacherRoomViewModel = androidx.lifecycle.viewmodel.compose.viewModel()
    val state by vm.state.collectAsStateWithLifecycle()
    val engine by vm.engine.collectAsStateWithLifecycle()
    val context = LocalContext.current

    var kickStudent by remember { mutableStateOf<RoomStudent?>(null) }

    val permissionsLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.RequestMultiplePermissions(),
    ) { grants ->
        if (grants[Manifest.permission.CAMERA] == true && grants[Manifest.permission.RECORD_AUDIO] == true) {
            vm.startVideo(context.applicationContext)
        } else {
            vm.permissionDenied()
        }
    }

    LaunchedEffect(salaId) { vm.load(salaId) }

    Column(Modifier.fillMaxSize().background(PageBackground)) {
        Row(
            Modifier
                .fillMaxWidth()
                .background(Color.White)
                .padding(horizontal = 8.dp, vertical = 8.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            IconButton(onClick = { vm.leave(onExit) }) {
                Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Terminar clase", tint = Ink)
            }
            Column(Modifier.weight(1f)) {
                Text(titulo.ifBlank { "Tu sala" }, fontSize = 17.sp, fontWeight = FontWeight.Bold, color = Ink, maxLines = 1)
                Text("Sala del profesor", fontSize = 11.sp, color = InkSecondary)
            }
            LiveBadge()
            Spacer(Modifier.width(12.dp))
        }

        when {
            state.loading -> LoadingView(Modifier.fillMaxSize())
            state.error != null -> ErrorView(state.error!!, Modifier.fillMaxSize(), onRetry = { vm.load(salaId) })
            else -> LazyColumn(
                Modifier.fillMaxSize().padding(horizontal = 16.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp),
            ) {
                item {
                    Spacer(Modifier.height(8.dp))
                    VideoPanel(
                        started = state.videoStarted,
                        error = state.videoError,
                        localRenderer = engine?.localRenderer,
                        remoteRenderer = engine?.remoteRenderer,
                        onStart = {
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
                        onStop = vm::stopVideo,
                    )
                }

                item {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Icon(Icons.Filled.Person, null, tint = InkSecondary, modifier = Modifier.size(16.dp))
                        Spacer(Modifier.width(6.dp))
                        Text(
                            "${state.students.size} en la sala",
                            fontSize = 13.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = Ink,
                        )
                    }
                }

                item { SectionHeader("Estudiantes") }
                if (state.students.isEmpty()) {
                    item {
                        Text(
                            "Esperando estudiantes…",
                            color = InkSecondary,
                            fontSize = 14.sp,
                            modifier = Modifier.padding(vertical = 8.dp),
                        )
                    }
                }
                items(state.students, key = { it.sesionId }) { student ->
                    StudentCard(
                        student = student,
                        onKick = { kickStudent = student },
                    )
                }

                item { Spacer(Modifier.height(24.dp)) }
            }
        }
    }

    kickStudent?.let { student ->
        KickDialog(
            nombre = student.nombre,
            onDismiss = { kickStudent = null },
            onConfirm = { comentario ->
                vm.kick(student.estudianteId, comentario)
                kickStudent = null
            },
        )
    }
}

@Composable
private fun VideoPanel(
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
            .clip(RoundedCornerShape(18.dp))
            .background(Color.White)
            .border(1.dp, Hairline, RoundedCornerShape(18.dp))
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
                    .padding(vertical = 22.dp),
                horizontalArrangement = Arrangement.Center,
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Icon(Icons.Filled.Videocam, null, tint = Mint, modifier = Modifier.size(22.dp))
                Spacer(Modifier.width(8.dp))
                Text("Iniciar video", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = Mint)
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
            TextButton(onClick = onStop) { Text("Detener video", color = Danger) }
        }
    }
}

@Composable
private fun StudentCard(student: RoomStudent, onKick: () -> Unit) {
    Row(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(14.dp))
            .background(Color.White)
            .border(1.dp, Hairline, RoundedCornerShape(14.dp))
            .padding(12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Avatar(student.avatarUrl, student.nombre, size = 44)
        Spacer(Modifier.width(12.dp))
        Column(Modifier.weight(1f)) {
            Text(student.nombre, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = Ink, maxLines = 1)
            Text(
                buildString {
                    if (student.esGratis) append("Tiempo gratis · ")
                    append(if (student.pagado == 1) "Pagada" else "Sin pago")
                    student.pais?.let { append(" · $it") }
                },
                fontSize = 11.sp,
                color = InkSecondary,
            )
        }
        IconButton(onClick = onKick) {
            Icon(Icons.Filled.Block, contentDescription = "Expulsar", tint = Danger, modifier = Modifier.size(22.dp))
        }
    }
}

@Composable
private fun KickDialog(
    nombre: String,
    onDismiss: () -> Unit,
    onConfirm: (String) -> Unit,
) {
    var comentario by remember { mutableStateOf("") }
    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("Expulsar a $nombre") },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Text(
                    "Esta acción terminará su sesión en la clase. Escribe el motivo (obligatorio).",
                    fontSize = 14.sp,
                    color = InkSecondary,
                )
                OutlinedTextField(
                    value = comentario,
                    onValueChange = { comentario = it },
                    placeholder = { Text("Motivo de la expulsión") },
                    modifier = Modifier.fillMaxWidth(),
                    maxLines = 3,
                )
            }
        },
        confirmButton = {
            TextButton(
                enabled = comentario.isNotBlank(),
                onClick = { onConfirm(comentario.trim()) },
            ) {
                Text("Expulsar", color = Danger)
            }
        },
        dismissButton = {
            TextButton(onClick = onDismiss) { Text("Cancelar", color = InkSecondary) }
        },
    )
}
